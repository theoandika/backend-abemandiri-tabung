<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\SimpleStockOpnameResource;
use App\Http\Resources\StockOpnameTubeListResource;
use App\Models\Member;
use App\Models\Site;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\Tube;
use App\Models\TubeTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->get();
            return SimpleStockOpnameResource::collection($stockOpnames);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
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
        })->first();

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
            'tubes.*.tube_status.required_if' => 'Tentukan status tabung',
            'tubes.*.position.required_if' => 'Tentukan posisi tabung',
        ]);

        $site = Site::where('uid', $r->input('site'))
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->first();

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
                            $tubeStatus = $input['tube_status'];
                        }
                        $adjust->site()->associate($site);
                        $adjust->locationable()->associate($locationable);
                        $adjust->transaction_type = $input['supplier_transaction_type'];
                        $adjust->tube_status = $tubeStatus;
                    } else if ($input['position'] == 'transit') {
                        $adjust->site()->associate($site);
                        $adjust->transaction_type = "out";
                        $adjust->tube_status = $input['tube_status'];
                    }

                    $adjust->save();

                    $stockOpnameItem = new StockOpnameItem;
                    $stockOpnameItem->stockOpname()->associate($stockOpname);
                    $stockOpnameItem->tube()->associate($tube);
                    $stockOpnameItem->tubeTransaction()->associate($adjust);
                    $stockOpnameItem->match = false;
                    $stockOpnameItem->adjust = true;
                    $stockOpnameItem->save();
                } else {
                    $stockOpnameItem = new StockOpnameItem;
                    $stockOpnameItem->stockOpname()->associate($stockOpname);
                    $stockOpnameItem->tube()->associate($tube);
                    $stockOpnameItem->tubeTransaction()->associate($tube->latestTubeTransaction);
                    $stockOpnameItem->match = true;
                    $stockOpnameItem->adjust = false;
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

    public function delete(Request $r, string $uid)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $stockOpname = StockOpname::where('uid', $uid)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })
        ->first();

        DB::beginTransaction();
        try {
            $stockOpname->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
