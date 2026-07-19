<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailSupplierTransactionResource;
// use App\Http\Resources\SimpleSupplierTransactionResource;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\SupplierTransactionItem;
use App\Models\TubeBarcode;
use App\Models\TubeTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierTransactionManagementController extends Controller
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
            $_transactions = SupplierTransaction::when($r->filled('search'), function ($query) use ($r) {
                $query->where(function ($q) use ($r) {
                    $q->whereHas('site', function ($q2) use ($r) {
                        $q2->where('name', 'like', '%' . $r->input('search') . '%');
                    })
                    ->orWhereHas('supplier', function ($q3) use ($r) {
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
            return DetailSupplierTransactionResource::collection($transactions);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(Request $r, string $uid)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $transaction = SupplierTransaction::where('uid', $uid)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })
        ->firstOrFail();
        return new DetailSupplierTransactionResource($transaction);
    }

    public function create(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'site' => 'bail|required|exists:sites,uid',
            'supplier' => 'bail|required|exists:suppliers,uid',
            'date' => 'bail|required|date_format:Y-m-d H:i|before:tomorrow',
            'transaction_type' => 'bail|required|in:refill,filled,fixing,fixed',
            'tube_status' => 'bail|nullable|in:filled,empty,broken',
            'note' => 'bail|nullable|string|max:500',
            'barcodes' => 'bail|required|array|min:1',
            'barcodes.*' => 'bail|required|string'
        ], [
            'site.required' => 'Tentukan cabang',
            'supplier.required' => 'Tentukan supplier',
            'date.required' => 'Masukkan tanggal transaksi',
            'date.date_format' => 'Format tanggal tidak valid',
            'date.before' => 'Tanggal tidak valid',
            'transaction_type.required' => 'Tentukan jenis transaksi',
            'note.max' => 'Catatan maksimal 500 karakter',
            'barcodes.required' => 'Masukkan barcode tabung',
            'barcodes.min' => 'Masukkan barcode tabung',
            'barcodes.*.required' => 'Masukkan barcode tabung'
        ]);

        if ($r->input('transaction_type') == 'fixed') {
            if($r->isNotFilled('tube_status')) {
                return Response::validation(['tube_status' => ['Tentukan status tabung']]);
            }
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
            $supplier = Supplier::where('uid', $r->input('supplier'))->first();
            $transaction = new SupplierTransaction;
            $transaction->site()->associate($site);
            $transaction->supplier()->associate($supplier);
            $transaction->date = $r->input('date');
            $transaction->transaction_type = $r->input('transaction_type');
            $transaction->tube_status = match ($r->input('transaction_type')) {
                'refill' => 'empty',
                'filled' => 'filled',
                'fixing' => 'broken',
                'fixed' => $r->input('tube_status')
            };
            $transaction->note = $r->input('note');
            $transaction->save();

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
                // check if tube is not broken/expired while trying to refill
                if ($transaction->transaction_type == 'refill' && !$tube->is_usable) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung rusak/afkir tidak dapat diproses'
                    ];
                    continue;
                }
                // check if tube is in the same site as transaction
                if (($transaction->transaction_type == 'refill' || $transaction->transaction_type == 'fixing') && $tube->site?->id !== $site->id) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak ditemukan'
                    ];
                    continue;
                }
                // check tube position should be in site
                if (($transaction->transaction_type == 'refill' || $transaction->transaction_type == 'fixing') && $tube->position != 'site') {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak dapat diproses. CODE: OUT-SITE'
                    ];
                    continue;
                }
                // check if tube position should be in supplier
                if (($transaction->transaction_type == 'filled' || $transaction->transaction_type == 'fixed') && $tube->position != 'supplier') {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak dapat diproses. CODE: OUT-SUPPLIER'
                    ];
                    continue;
                }

                $tubeTransaction = new TubeTransaction;
                $tubeTransaction->tube()->associate($tube);
                $tubeTransaction->site()->associate($site);
                $tubeTransaction->locationable()->associate($supplier);
                $tubeTransaction->date = $r->input('date');
                $tubeTransaction->transaction_type = $r->input('transaction_type');
                $tubeTransaction->tube_status = match ($r->input('transaction_type')) {
                    'refill' => 'empty',
                    'filled' => 'filled',
                    'fixing' => 'broken',
                    'fixed' => $r->input('tube_status')
                };
                $tubeTransaction->save();

                $transactionItem = new SupplierTransactionItem;
                $transactionItem->supplierTransaction()->associate($transaction);
                $transactionItem->tube()->associate($tube);
                $transactionItem->tubeTransaction()->associate($tubeTransaction);
                $transactionItem->save();
            }
            if (!empty($errorBarcodes)) {
                $errorCount = count($errorBarcodes);
                return Response::errorData($errorBarcodes, "{$errorCount} tabung tidak dapat diproses");
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
        $transaction = SupplierTransaction::where('uid', $uid)
        ->when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('site_id', $sites);
        })
        ->firstOrFail();
        try {
            foreach ($transaction->supplierTransactionItems as $transactionItem) {
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
}
