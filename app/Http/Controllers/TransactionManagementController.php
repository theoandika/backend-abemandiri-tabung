<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\SimpleTransactionResource;
use App\Models\Member;
use App\Models\Site;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TubeBarcode;
use App\Models\TubeTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionManagementController extends Controller
{
    public function index(Request $r)
    {
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

    public function create(Request $r)
    {
        $r->validate([
            'site' => 'bail|required|exists:sites,uid',
            'member' => 'bail|nullable|exists:members,uid',
            'date' => 'bail|required|date_format:Y-m-d H:i',
            'transaction_type' => 'bail|required|in:in,out,return,sell',
            'tube_status' => 'bail|required|in:filled,empty,broken,expired,display',
            'note' => 'bail|nullable|string|max:500',
        ], [
            'site.required' => 'Tentukan cabang',
            'date.required' => 'Masukkan tanggal transaksi',
            'date.date_format' => 'Format tanggal tidak valid',
            'transaction_type.required' => 'Tentukan jenis transaksi',
            'tube_status.required' => 'Tentukan status tabung',
            'note.max' => 'Catatan maksimal 500 karakter',
        ]);

        DB::beginTransaction();
        try {
            $site = Site::where('uid', $r->input('site'))->firstOrFail();
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
            $transaction->save();
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function createItems(Request $r)
    {
        $r->validate([
            'transaction' => 'bail|required|exists:transactions,uid',
            'barcodes' => 'bail|required|array|min:1',
            'barcodes.*' => 'bail|required|string',
        ], [
            'transaction.required' => 'Input tidak sesuai',
            'barcodes.required' => 'Input tidak sesuai',
            'barcodes.min' => 'Input tidak sesuai',
        ]);

        DB::beginTransaction();
        try {
            $transaction = Transaction::where('uid', $r->input('transaction'))->firstOrFail();
            $errorBarcodes = [];
            foreach ($r->input('barcodes') as $barcode) {
                $tubeBarcode = TubeBarcode::where('barcode', $barcode)->first();
                if (!$tubeBarcode) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak ditemukan'
                    ];
                    continue;
                }
                $tube = $tubeBarcode->tube;
                if ($tube->site?->id !== $transaction->site_id) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Tabung tidak ditemukan'
                    ];
                    continue;
                }
                if ($tube?->barcode != $tubeBarcode->barcode) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Kode barcode telah berubah'
                    ];
                    continue;
                }
                
                $lastTransaction = $tube->latestTubeTransaction;
                if ($lastTransaction) {
                    // check if tube is available to distribute or sell
                    if (($transaction->transaction_type == 'out' || $transaction->transaction_type == 'sell') && $tube->is_not_available_transaction) {
                        $errorBarcodes[] = [
                            'barcode' => $barcode,
                            'message' => 'Tabung tidak dapat diproses'
                        ];
                        continue;
                    }
                    if (($transaction->transaction_type == 'out' || $transaction->transaction_type == 'sell') && ($tube->position == 'member' || $tube->position == 'transit' || $tube->position == 'supplier')) {
                        $errorBarcodes[] = [
                            'barcode' => $barcode,
                            'message' => 'Tabung tidak dapat diproses'
                        ];
                        continue;
                    }
                }

                $tubeTransaction = new TubeTransaction;
                $tubeTransaction->tube()->associate($tubeBarcode->tube);
                $tubeTransaction->site()->associate($transaction->site);
                if ($transaction->member) {
                    $tubeTransaction->locationable()->associate($transaction->member);
                }
                $tubeTransaction->date = $transaction->date;
                $tubeTransaction->transaction_type = $transaction->transaction_type;
                $tubeTransaction->tube_status = $transaction->tube_status;
                $tubeTransaction->save();

                $transactionItem = new TransactionItem;
                $transactionItem->transaction()->associate($transaction);
                $transactionItem->tube()->associate($tubeBarcode->tube);
                $transactionItem->tubeTransaction()->associate($tubeTransaction);
                $transactionItem->save();
            }
            // DB::commit();
            if (!empty($errorBarcodes)) {
                $errorCount = count($errorBarcodes);
                return Response::successData($errorBarcodes, "{$errorCount} tabung tidak dapat diproses");
            }
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
