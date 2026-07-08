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

    public function detail(string $uid)
    {
        $transaction = Transaction::where('uid', $uid)->firstOrFail();
        return new DetailTransactionResource($transaction);
    }

    public function create(Request $r)
    {
        $r->validate([
            'site' => 'bail|required|exists:sites,uid',
            'member' => 'bail|nullable|exists:members,uid',
            'date' => 'bail|required|date_format:Y-m-d H:i|before:tomorrow',
            'transaction_type' => 'bail|required|in:in,out,return,sell',
            'tube_status' => 'bail|required|in:filled,empty,broken,expired,display',
            'note' => 'bail|nullable|string|max:500',
            'document' => 'bail|nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'nominal' => 'bail|nullable|numeric|min:0',
        ], [
            'site.required' => 'Tentukan cabang',
            'date.required' => 'Masukkan tanggal transaksi',
            'date.date_format' => 'Format tanggal tidak valid',
            'date.before' => 'Tanggal tidak valid',
            'transaction_type.required' => 'Tentukan jenis transaksi',
            'tube_status.required' => 'Tentukan status tabung',
            'note.max' => 'Catatan maksimal 500 karakter',
            'document.max' => 'Ukuran file maksimal 10MB',
            'document.mimes' => 'Format file harus PDF, JPG, JPEG, atau PNG',
            'nominal.min' => 'Nominal tidak valid'
        ]);

        if ($r->input('transaction_type') == 'sell' && $r->isNotFilled('member')) {
            return Response::validation(['member' => ['Tentukan member']]);
        }

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
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function createItems(Request $r, string $uid)
    {
        $r->validate([
            'barcodes' => 'bail|required|array|min:1',
            'barcodes.*' => 'bail|required|string',
        ], [
            'barcodes.required' => 'Input tidak sesuai',
            'barcodes.min' => 'Input tidak sesuai',
        ]);

        $transaction = Transaction::where('uid', $uid)->firstOrFail();
        DB::beginTransaction();
        try {
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
                // check if barcode has change
                if ($tube->barcode != $tubeBarcode->barcode) {
                    $errorBarcodes[] = [
                        'barcode' => $barcode,
                        'message' => 'Kode barcode telah berubah'
                    ];
                    continue;
                }
                
                $lastTransaction = $tube->latestTubeTransaction;
                if ($lastTransaction) {
                    // check if tube is not broken/expired/display
                    if (($transaction->transaction_type == 'out' || $transaction->transaction_type == 'sell') && !$tube->is_status_ready_to_sell_member) {
                        $errorBarcodes[] = [
                            'barcode' => $barcode,
                            'message' => 'Tabung rusak/afkir tidak dapat diproses'
                        ];
                        continue;
                    }
                    // check if tube already out of site
                    if (($transaction->transaction_type == 'out' || $transaction->transaction_type == 'sell') && !$tube->is_position_ready_to_out_member) {
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
            DB::commit();
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

    public function delete(string $uid)
    {
        $transaction = Transaction::where('uid', $uid)->firstOrFail();
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

    public function deleteItem(string $uid)
    {
        $transactionItem = TransactionItem::whereRelation('tube', 'uid', $uid)->firstOrFail();
        try {
            if ($transactionItem->tubeTransaction->is_past) {
                return Response::error('Item tidak dapat dihapus');
            }
            $transactionItem->tubeTransaction->delete();
            $transactionItem->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
