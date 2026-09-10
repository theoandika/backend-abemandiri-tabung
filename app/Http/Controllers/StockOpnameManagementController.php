<?php

namespace App\Http\Controllers;

use App\Helpers\ImageCompress;
use App\Helpers\Response;
use App\Http\Resources\DetailStockOpnameResource;
use App\Http\Resources\SimpleStockOpnameResource;
use App\Http\Resources\StockOpnameTubeListResource;
use App\Models\Image;
use App\Models\Member;
use App\Models\Site;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\Tube;
use App\Models\TubeContentType;
use App\Models\TubeTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StockOpnameManagementController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');

        try {
            $stockOpnames = StockOpname::when($user->level != 0, function ($q) use ($sites) {
                $q->whereIn('site_id', $sites);
            })
            ->orderByDesc('created_at')
            ->get();
            return SimpleStockOpnameResource::collection($stockOpnames);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(Request $r, string $uid)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $stockOpname = StockOpname::where('uid', $uid)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->firstOrFail();
        return new DetailStockOpnameResource($stockOpname);
    }

    public function tubeList(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'site' => 'bail|required|exists:sites,uid',
        ], [
            'site.required' => 'Tentukan cabang'
        ]);
        
        $site = Site::where('uid', $r->input('site'))
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->firstOrFail();

        try {
            $tubes = Tube::whereHas('latestTubeTransaction', function ($q) use ($site) {
                $q->where('site_id', $site->id)
                ->where(function ($q) {
                    $q->where(function ($q) {
                        $q->where('transaction_type', 'out')
                        ->whereNotNull('locationable_type');
                    })
                    ->orWhereIn('transaction_type', ['in', 'sell', 'return', 'refill', 'fixing']);
                });
            })
            ->where('active', true)
            ->orderBy('number')
            ->get();
            return StockOpnameTubeListResource::collection($tubes);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function create(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'site' => 'bail|required|exists:sites,uid',
            'tubes' => 'bail|required|array',
            'tubes.*.id' => 'bail|required|exists:tubes,uid',
            'tubes.*.is_match' => 'bail|required|boolean',
            'tubes.*.adjust' => 'bail|required|boolean',
            'tubes.*.tube_status' => 'bail|required_if:is_match,false|in:filled,empty,broken,expired,display',
            'tubes.*.position' => 'bail|required_if:is_match,false|in:site,member,supplier,transit',
            'tubes.*.position_id' => 'bail|nullable',
            'tubes.*.supplier_transaction_type' => 'bail|nullable|in:refill,filled,fixing,fixed',
        ], [
            'site.required' => 'Tentukan cabang',
            'tubes.*.is_match.required' => 'Tentukan hasil pemeriksaan',
            'tubes.*.adjust.required' => 'Tentukan status penyesuaian',
            'tubes.*.tube_status.required_if' => 'Tentukan status tabung',
            'tubes.*.position.required_if' => 'Tentukan posisi tabung',
        ]);

        $site = Site::where('uid', $r->input('site'))
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->firstOrFail();

        DB::beginTransaction();
        try {
            $tubes = Tube::whereHas('latestTubeTransaction', function ($q) use ($site) {
                $q->where('site_id', $site->id)
                ->where(function ($q) {
                    $q->where(function ($q) {
                        $q->where('transaction_type', 'out')
                        ->whereNotNull('locationable_type');
                    })
                    ->orWhereIn('transaction_type', ['in', 'sell', 'return', 'refill', 'fixing']);
                });
            })
            ->get();

            $stockOpname = new StockOpname;
            $stockOpname->site()->associate($site);
            $stockOpname->save();

            $tubesInput = collect($r->input('tubes'));
            foreach ($tubes as $key => $tube) {
                $input = $tubesInput->filter(function ($item) use ($tube) {
                    return $item['id'] == $tube->uid;
                })->first();
                if (!$input) {
                    return Response::validation(['tubes' => ["Tentukan hasil pemeriksaan untuk tabung dengan nomor {$tube->number}"]]);
                }
                if (!$input['is_match'] && $input['adjust']) {
                    if (($input['position'] == 'site' || $input['position'] == 'member' || $input['position'] == 'supplier') && !($input['position_id'] ?? null)) {
                        return Response::validation(["tubes.{$key}.position_id" => ["Tentukan posisi tabung"]]);
                    }

                    $adjust = new TubeTransaction;
                    $adjust->date = Carbon::now()->format('Y-m-d H:i');
                    $adjust->tube()->associate($tube);

                    if ($input['position'] == 'site') {
                        if (!isset($input['tube_status'])) {
                            return Response::validation(["tubes.{$key}.tube_status" => ["Tentukan kondisi tabung"]]);
                        }
                        if ($input['position_id'] == $site->uid) {
                            $adjust->site()->associate($site);
                        } else {
                            $siteUpdate = Site::where('uid', $input['position_id'])->first();
                            if (!$siteUpdate) {
                                return Response::validation(["tubes.{$key}.position_id" => [__('validation.exists')]]);
                            }
                            $adjust->site()->associate($siteUpdate);
                        }
                        $adjust->transaction_type = "in";
                        $adjust->tube_status = $input['tube_status'];
                    } else if ($input['position'] == 'member') {
                        if (!isset($input['tube_status'])) {
                            return Response::validation(["tubes.{$key}.tube_status" => ["Tentukan kondisi tabung"]]);
                        }
                        $locationable = Member::where('uid', $input['position_id'])->first();
                        if (!$locationable) {
                            return Response::validation(["tubes.{$key}.position_id" => [__('validation.exists')]]);
                        }
                        $adjust->site()->associate($site);
                        $adjust->locationable()->associate($locationable);
                        $adjust->transaction_type = "out";
                        $adjust->tube_status = $input['tube_status'];
                    } else if ($input['position'] == 'supplier') {
                        $locationable = Supplier::where('uid', $input['position_id'])->first();
                        if (!isset($input['supplier_transaction_type'])) {
                            return Response::validation(["tubes.{$key}.supplier_transaction_type" => ["Tentukan jenis transaksi"]]);
                        }
                        if (!$locationable) {
                            return Response::validation(["tubes.{$key}.position_id" => [__('validation.exists')]]);
                        }
                        if ($input['supplier_transaction_type'] == 'refill') {
                            $tubeStatus = "empty";
                        } else if ($input['supplier_transaction_type'] == 'filled') {
                            $tubeStatus = "filled";
                        } else if ($input['supplier_transaction_type'] == 'fixing') {
                            $tubeStatus = "broken";
                        } else if ($input['supplier_transaction_type'] == 'fixed') {
                            if (!isset($input['tube_status'])) {
                                return Response::validation(["tubes.{$key}.tube_status" => ["Tentukan kondisi tabung"]]);
                            }
                            $tubeStatus = $input['tube_status'];
                        }
                        $adjust->site()->associate($site);
                        $adjust->locationable()->associate($locationable);
                        $adjust->transaction_type = $input['supplier_transaction_type'];
                        $adjust->tube_status = $tubeStatus;
                    } else if ($input['position'] == 'transit') {
                        $adjust->site()->associate($site);
                        $adjust->transaction_type = "out";
                        if (!isset($input['tube_status'])) {
                            return Response::validation(["tubes.{$key}.tube_status" => ["Tentukan kondisi tabung"]]);
                        }
                        $adjust->tube_status = $input['tube_status'];
                    }

                    $adjust->save();

                    $stockOpnameItem = new StockOpnameItem;
                    $stockOpnameItem->stockOpname()->associate($stockOpname);
                    $stockOpnameItem->tube()->associate($tube);
                    $stockOpnameItem->tubeTransaction()->associate($adjust);
                    $stockOpnameItem->match = $input['is_match'];
                    $stockOpnameItem->adjust = $input['adjust'];
                    $stockOpnameItem->save();
                } else {
                    $stockOpnameItem = new StockOpnameItem;
                    $stockOpnameItem->stockOpname()->associate($stockOpname);
                    $stockOpnameItem->tube()->associate($tube);
                    $stockOpnameItem->tubeTransaction()->associate($tube->latestTubeTransaction);
                    $stockOpnameItem->match = $input['is_match'];
                    $stockOpnameItem->adjust = $input['adjust'];
                    $stockOpnameItem->save();
                }
            }
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function createV2(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'date' => 'bail|required|date_format:Y-m-d H:i',
            'site' => 'bail|required|exists:sites,uid',
            'content' => 'bail|required|exists:tube_content_types,uid',
            'pic' => 'bail|required|string|max:100',
            'tube_status' => 'bail|required|in:filled,empty,broken,expired,display',
        ], [
            'date.required' => 'Masukkan tanggal transaksi',
            'date.date_format' => 'Format tanggal tidak valid',
            'site.required' => 'Tentukan cabang',
            'content.required' => 'Tentukan isi tabung',
            'pic.required' => 'Masukkan nama PIC',
            'pic.max' => 'Nama PIC maksimal 100 karakter',
            'tube_status.required' => 'Tentukan status tabung',
        ]);

        $site = Site::where('uid', $r->input('site'))
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->firstOrFail();

        DB::beginTransaction();
        try {
            $tubeContent = TubeContentType::where('uid', $r->input('content'))->first();
            $stockOpname = new StockOpname;
            $stockOpname->site()->associate($site);
            $stockOpname->tubeContentType()->associate($tubeContent);
            $stockOpname->date = $r->input('date');
            $stockOpname->pic = $r->input('pic');
            $stockOpname->tube_status = $r->input('tube_status');
            $stockOpname->save();
            DB::commit();
            return Response::successData(['id' => $stockOpname->uid]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function addItemV2(Request $r, string $uid)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $stockOpname = StockOpname::where('uid', $uid)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->firstOrFail();
        $r->validate([
            'barcode' => 'bail|required|string|max:100',
            'position' => 'bail|required|in:site,supplier,member',
            'supplier' => 'bail|nullable|exists:suppliers,uid',
            'member' => 'bail|nullable|exists:members,uid',
            'note' => 'bail|nullable|string|max:200',
            'photo' => 'bail|required|image|mimes:png,jpg,jpeg|max:10240'
        ], [
            'barcode.required' => 'Masukkan barcode tabung',
            'barcode.max' => 'Barcode maksimal 100 karakter',
            'position.required' => 'Tentukan lokasi tabung',
            'note.max' => 'Catatan maksimal 200 karakter',
            'photo.required' => 'Masukkan foto tabung',
            'photo.mimes' => 'Format foto tidak valid. Gunakan format png atau jpg',
            'photo.max' => 'Ukuran foto maksimal 10MB'
        ]);
        DB::beginTransaction();
        try {
            $site = $stockOpname->site;
            $tubeStatus = $stockOpname->tube_status;
            $tube = Tube::whereRelation('latestTubeBarcode', 'barcode', $r->input('barcode'))
            ->whereRelation('latestTubeContent', 'tube_content_type_id', $stockOpname->tube_content_type_id)
            ->first();
            if (!$tube) {
                return Response::validation(['barcode' => ['Tabung tidak ditemukan']]);
            }
            $exist = StockOpnameItem::where('stock_opname_id', $stockOpname->id)
            ->where('tube_id', $tube->id)->exists();
            if ($exist) {
                return Response::validation(['barcode' => ['Tabung sudah ada']]);
            }

            $latestTubeTransaction = $tube->latestTubeTransaction;
            if ($latestTubeTransaction) {
                if ($latestTubeTransaction->site_id != $site->id || $latestTubeTransaction->tube_status != $tubeStatus || $latestTubeTransaction->position != $r->input('position')) {
                    $newTubeTransaction = new TubeTransaction;
                    $newTubeTransaction->tube()->associate($tube);
                    $newTubeTransaction->site()->associate($site);
                    $newTubeTransaction->date = $stockOpname->date;
                    if ($r->input('position') == 'site') {
                        $newTubeTransaction->transaction_type = 'in';
                    }
                    if ($r->input('position') == 'member') {
                        if (!$r->filled('member')) {
                            return Response::validation(['member' => ['Tentukan member']]);
                        }
                        $member = Member::where('uid', $r->input('member'))->first();
                        $newTubeTransaction->locationable()->associate($member);
                        $newTubeTransaction->transaction_type = 'out';
                    }
                    if ($r->input('position') == 'supplier') {
                        if (!$r->filled('supplier')) {
                            return Response::validation(['supplier' => ['Tentukan supplier']]);
                        }
                        $supplier = Supplier::where('uid', $r->input('supplier'))->first();
                        $member = Member::where('uid', $r->input('member'))->first();
                        $newTubeTransaction->locationable()->associate($supplier);
                        if ($stockOpname->tube_status == 'filled') {
                            return Response::validation(['position' => ['Tabung yang terisi seharusnya tidak berada di supplier']]);
                        } else if ($stockOpname->tube_status == 'empty') {
                            $newTubeTransaction->transaction_type = 'refill';
                        } else if ($stockOpname->tube_status == 'broken') {
                            $newTubeTransaction->transaction_type = 'fixing';
                        } else if ($stockOpname->tube_status == 'expired') {
                            return Response::validation(['position' => ['Tabung afkir seharusnya tidak berada di supplier']]);
                        } else if ($stockOpname->tube_status == 'display') {
                            return Response::validation(['position' => ['Tabung pajangan seharusnya tidak berada di supplier']]);
                        }
                    }
                    $newTubeTransaction->tube_status = $tubeStatus;
                    $newTubeTransaction->save();
                    
                    $stockOpnameItem = new StockOpnameItem;
                    $stockOpnameItem->stockOpname()->associate($stockOpname);
                    $stockOpnameItem->tube()->associate($tube);
                    $stockOpnameItem->tubeTransaction()->associate($newTubeTransaction);
                    $stockOpnameItem->match = false;
                    $stockOpnameItem->adjust = true;
                    $stockOpnameItem->note = $r->input('note');
                    $stockOpnameItem->save();

                    $optImage = ImageCompress::compress(
                        $r->file('photo'),
                        maxDimension: 2048,
                        quality: 85
                    );
                    $_photo = Storage::disk('images')->put('stock-opname', $optImage);
                    $photo = new Image;
                    $photo->imageable()->associate($stockOpnameItem);
                    $photo->path = $_photo;
                    $photo->type = 'tube';
                    $photo->save();
                } else {
                    $stockOpnameItem = new StockOpnameItem;
                    $stockOpnameItem->stockOpname()->associate($stockOpname);
                    $stockOpnameItem->tube()->associate($tube);
                    $stockOpnameItem->tubeTransaction()->associate($latestTubeTransaction);
                    $stockOpnameItem->match = true;
                    $stockOpnameItem->adjust = false;
                    $stockOpnameItem->note = $r->input('note');
                    $stockOpnameItem->save();

                    $optImage = ImageCompress::compress(
                        $r->file('photo'),
                        maxDimension: 2048,
                        quality: 85
                    );
                    $_photo = Storage::disk('images')->put('stock-opname', $optImage);
                    $photo = new Image;
                    $photo->imageable()->associate($stockOpnameItem);
                    $photo->path = $_photo;
                    $photo->type = 'tube';
                    $photo->save();
                }
            } else {
                $newTubeTransaction = new TubeTransaction;
                $newTubeTransaction->tube()->associate($tube);
                $newTubeTransaction->site()->associate($site);
                $newTubeTransaction->date = $stockOpname->date;
                if ($r->input('position') == 'site') {
                    $newTubeTransaction->transaction_type = 'in';
                }
                if ($r->input('position') == 'member') {
                    if (!$r->filled('member')) {
                        return Response::validation(['member' => ['Tentukan member']]);
                    }
                    $member = Member::where('uid', $r->input('member'))->first();
                    $newTubeTransaction->locationable()->associate($member);
                    $newTubeTransaction->transaction_type = 'out';
                }
                if ($r->input('position') == 'supplier') {
                    if (!$r->filled('supplier')) {
                        return Response::validation(['supplier' => ['Tentukan supplier']]);
                    }
                    $supplier = Supplier::where('uid', $r->input('supplier'))->first();
                    $member = Member::where('uid', $r->input('member'))->first();
                    $newTubeTransaction->locationable()->associate($supplier);
                    if ($stockOpname->tube_status == 'filled') {
                        return Response::validation(['position' => ['Tabung yang terisi seharusnya tidak berada di supplier']]);
                    } else if ($stockOpname->tube_status == 'empty') {
                        $newTubeTransaction->transaction_type = 'refill';
                    } else if ($stockOpname->tube_status == 'broken') {
                        $newTubeTransaction->transaction_type = 'fixing';
                    } else if ($stockOpname->tube_status == 'expired') {
                        return Response::validation(['position' => ['Tabung afkir seharusnya tidak berada di supplier']]);
                    } else if ($stockOpname->tube_status == 'display') {
                        return Response::validation(['position' => ['Tabung pajangan seharusnya tidak berada di supplier']]);
                    }
                }
                $newTubeTransaction->tube_status = $tubeStatus;
                $newTubeTransaction->save();

                $stockOpnameItem = new StockOpnameItem;
                $stockOpnameItem->stockOpname()->associate($stockOpname);
                $stockOpnameItem->tube()->associate($tube);
                $stockOpnameItem->tubeTransaction()->associate($newTubeTransaction);
                $stockOpnameItem->match = false;
                $stockOpnameItem->adjust = true;
                $stockOpnameItem->note = $r->input('note');
                $stockOpnameItem->save();

                $optImage = ImageCompress::compress(
                        $r->file('photo'),
                        maxDimension: 2048,
                        quality: 85
                    );
                    $_photo = Storage::disk('images')->put('stock-opname', $optImage);
                    $photo = new Image;
                    $photo->imageable()->associate($stockOpnameItem);
                    $photo->path = $_photo;
                    $photo->type = 'tube';
                    $photo->save();
            }
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function deleteItemV2(Request $r, string $stockOpnameId, string $uid)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $stockOpname = StockOpname::where('uid', $stockOpnameId)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->firstOrFail();
        $stockOpnameItem = StockOpnameItem::where('stock_opname_id', $stockOpname->id)
        ->where('uid', $uid)
        ->firstOrFail();

        DB::beginTransaction();
        try {
            if ($stockOpnameItem->tubeTransaction->is_past) {
                return Response::error('Data stock opname tidak dapat dihapus');
            }
            $stockOpnameItem->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function delete(Request $r, string $uid)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $stockOpname = StockOpname::where('uid', $uid)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })
        ->firstOrFail();

        DB::beginTransaction();
        try {
            foreach ($stockOpname->stockOpnameItems as $stockOpnameItem) {
                if ($stockOpnameItem->tubeTransaction->is_past) {
                    return Response::error('Data stock opname tidak dapat dihapus');
                }
                $stockOpnameItem->delete();
            }
            $stockOpname->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
