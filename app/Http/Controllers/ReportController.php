<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailTubeTransactionResource;
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
}
