<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailTubeTransactionResource;
use App\Http\Resources\StockOpnameReportResource;
use App\Models\StockOpnameItem;
use App\Models\Tube;
use App\Models\TubeTransaction;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function tubeActivity(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');

        try {
            $tubeTransaction = TubeTransaction::when($user->level != 0, function ($q) use ($sites) {
                $q->where(function ($q) use ($sites) {
                    $q->whereIn('site_id', $sites)
                    ->whereNot('transaction_type', 'out')
                    ->whereNotNull('locationable_type');
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();
            return DetailTubeTransactionResource::collection($tubeTransaction);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function tubeStockOpname(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');

        try {
            $tubes = StockOpnameItem::when($user->level != 0, function ($q) use ($sites) {
                $q->whereHas('stockOpname', function ($q) use ($sites) {
                    $q->whereIn('site_id', $sites);
                });
            })
            ->orderByDesc('id')
            ->get();
            return StockOpnameReportResource::collection($tubes);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function tubeLastPosition(Request $r)
    {
        $r->validate([
            'barcode' => 'bail|required|string|max:50'
        ],[
            'barcode.required' => 'Masukkan barcode tabung',
            'barcode.max' => 'Barcode maksimal 50 karakter'
        ]);

        try {
            $tube = Tube::whereHas('latestTubeBarcode', function ($q) use ($r) {
                $q->where('barcode', $r->input('barcode'));
            })->first();
            if (!$tube) {
                return Response::error("Tabung tidak ditemukan");
            }
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }
}
