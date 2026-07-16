<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailTransactionResource;
use App\Http\Resources\SimpleTransactionResource;
use App\Models\Document;
use App\Models\Member;
use App\Models\Site;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TubeBarcode;
use App\Models\TubeTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionManagementController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'paginate' => 'bail|nullable|integer|min:1'
        ]);

        try {
            $_transactions = Transaction::when($r->filled('search'), function ($query) use ($r) {
                $query->where(function ($q) use ($r) {
                    $q->whereHas('site', function ($q2) use ($r) {
                        $q2->where('name', 'like', '%' . $r->input('search') . '%');
                    })
                    ->orWhereHas('member', function ($q3) use ($r) {
                        $q3->where('name', 'like', '%' . $r->input('search') . '%');
                    });
                });
            })
            ->when($user->level != 0, function ($q) use ($sites) {
                $q->whereIn('site_id', $sites);
            })
            ->orderByDesc('date')
            ->orderByDesc('created_at');
            if ($r->filled('paginate')) {
                $transactions = $_transactions->paginate($r->integer('paginate'));
            } else {
                $transactions = $_transactions->get();
            }
            return SimpleTransactionResource::collection($transactions);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(Request $r, string $uid)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $transaction = Transaction::where('uid', $uid)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })
        ->firstOrFail();
        return new DetailTransactionResource($transaction);
    }

    public function create(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'site' => 'bail|required|exists:sites,uid',
            'member' => 'bail|nullable|exists:members,uid',
            'date' => 'bail|required|date_format:Y-m-d H:i',
            'transaction_type' => 'bail|required|in:in,out,return,sell',
            'tube_status' => 'bail|required|in:filled,empty,broken,expired,display',
            'note' => 'bail|nullable|string|max:500',
            'document' => 'bail|nullable|file|max:10240|mimes:pdf',
            'nominal' => 'bail|nullable|numeric|min:0',
            'barcodes' => 'bail|required|array|min:1',
            'barcodes.*' => 'bail|required|string',
        ], [
            'site.required' => 'Tentukan cabang',
            'date.required' => 'Masukkan tanggal transaksi',
            'date.date_format' => 'Format tanggal tidak valid',
            'transaction_type.required' => 'Tentukan jenis transaksi',
            'tube_status.required' => 'Tentukan status tabung',
            'note.max' => 'Catatan maksimal 500 karakter',
            'document.max' => 'Ukuran file maksimal 10MB',
            'document.mimes' => 'Format file harus PDF, JPG, JPEG, atau PNG',
            'nominal.min' => 'Nominal tidak valid',
            'barcodes.required' => 'Masukkan barcode tabung',
            'barcodes.min' => 'Input tidak sesuai',
            'barcodes.*.required' => 'Masukkan barcode tabung'
        ]);

        if ($r->input('transaction_type') == 'sell' && $r->isNotFilled('member')) {
            return Response::validation(['member' => ['Tentukan member']]);
        }
        $site = Site::where('uid', $r->input('site'))
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })->first();
        if(!$site) {
            return Response::validation(['site' => __('validation.in')]);
        }

        DB::beginTransaction();
        try {
            $transaction = new Transaction;
            $transaction->site()->associate($site);
            if ($r->filled('member')) {
                $member = Member::where('uid', $r->input('member'))->firstOrFail();
                $transaction->member()->associate($member);
            }
            $transaction->date = $r->input('date');
            $transaction->transaction_type = $r->input('transaction_type');
            $transaction->tube_status = $r->input('tube_status');
            $transaction->note = $r->input('note');
            $transaction->nominal = $r->input('nominal');
            $transaction->save();

            if ($r->hasFile('document')) {
                $file = $r->file('document');
                $path = Storage::disk('documents')->put('transactions', $file);
                $document = new Document;
                $document->documentable()->associate($transaction);
                $document->type = 'transaction';
                $document->path = $path;
                $document->save();
            }

            $errorBarcodes = [];
            foreach ($r->input('barcodes') as $barcode) {
                $tubeBarcode = TubeBarcode::where('barcode', $barcode)->first();
                // check barcode exists
                if (!$tubeBarcode) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak ditemukan'
                    ];
                    continue;
                }
                $tube = $tubeBarcode->tube;
                // check active tube
                if (!$tube->active) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak aktif'
                    ];
                    continue;
                }
                // check if barcode has change
                if ($tube->barcode != $tubeBarcode->barcode) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Kode barcode telah berubah'
                    ];
                    continue;
                }
                // check if tube is in the same site as transaction for out and sell transaction type
                if (($transaction->transaction_type == 'out' || $transaction->transaction_type == 'sell') && $tube->site?->id !== $transaction->site_id) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak ditemukan'
                    ];
                    continue;
                }
                // check tube already sold
                if ($transaction->transaction_type == 'sell' && !$tube->own) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung NON DM tidak dapat dijual'
                    ];
                    continue;
                }
                // check if tube is sold but trying to distribute to another member
                if ($r->filled('member')) {
                    if ($transaction->transaction_type == 'out' && $tube->second_owner != null && $tube->second_owner != $member) {
                        $errorBarcodes[] = [
                            'barcode' => $barcode,
                            'message' => "Tabung sudah terjual kepada {$tube->second_owner->code}-{$tube->second_owner->name}"
                        ];
                        continue;
                    }
                }
                // check if tube is not broken/expired
                if (($transaction->transaction_type == 'out' || $transaction->transaction_type == 'sell') && !$tube->is_usable) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung rusak/afkir tidak dapat diproses'
                    ];
                    continue;
                }
                // check if tube already out of site
                if (($transaction->transaction_type == 'out' || $transaction->transaction_type == 'sell') && $tube->position != 'site') {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak dapat diproses. CODE: OUT-SITE'
                    ];
                    continue;
                }
                // check if tube already in site
                if (($transaction->transaction_type == 'in' || $transaction->transaction_type == 'return') && $tube->position == 'site') {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak dapat diproses. CODE: IN-SITE'
                    ];
                    continue;
                }

                $tubeTransaction = new TubeTransaction;
                $tubeTransaction->tube()->associate($tube);
                $tubeTransaction->site()->associate($site);
                if ($r->filled('member')) {
                    $member = Member::where('uid', $r->input('member'))->firstOrFail();
                    $tubeTransaction->locationable()->associate($member);
                }
                $tubeTransaction->date = $r->input('date');
                $tubeTransaction->transaction_type = $r->input('transaction_type');
                $tubeTransaction->tube_status = $r->input('tube_status');
                $tubeTransaction->save();

                $transactionItem = new TransactionItem;
                $transactionItem->transaction()->associate($transaction);
                $transactionItem->tube()->associate($tube);
                $transactionItem->tubeTransaction()->associate($tubeTransaction);
                $transactionItem->save();
            }
            if (!empty($errorBarcodes)) {
                $errorCount = count($errorBarcodes);
                return Response::errorData($errorBarcodes, "{$errorCount} tabung tidak dapat diproses", 422);
            }
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    // public function createItems(Request $r, string $uid)
    // {
    //     $r->validate([
    //         'barcodes' => 'bail|required|array|min:1',
    //         'barcodes.*' => 'bail|required|string',
    //     ], [
    //         'barcodes.required' => 'Input tidak sesuai',
    //         'barcodes.min' => 'Input tidak sesuai',
    //     ]);

    //     $transaction = Transaction::where('uid', $uid)->firstOrFail();
    //     DB::beginTransaction();
    //     try {
            
    //         return Response::created();
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         return Response::internalError($th->getMessage());
    //     }
    // }

    public function delete(Request $r, string $uid)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $transaction = Transaction::where('uid', $uid)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })
        ->firstOrFail();
        try {
            foreach ($transaction->transactionItems as $transactionItem) {
                if ($transactionItem->tubeTransaction->is_past) {
                    return Response::error('Transaksi tidak dapat dihapus');
                }
                $transactionItem->tubeTransaction->delete();
            }
            $transaction->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    // public function deleteItem(string $uid)
    // {
    //     $transactionItem = TransactionItem::whereRelation('tube', 'uid', $uid)->firstOrFail();
    //     try {
    //         if ($transactionItem->tubeTransaction->is_past) {
    //             return Response::error('Item tidak dapat dihapus');
    //         }
    //         $transactionItem->tubeTransaction->delete();
    //         $transactionItem->delete();
    //         DB::commit();
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         return Response::internalError($th->getMessage());
    //     }
    // }
}
