<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\StockOpnameTubeListResource;
use App\Models\Site;
use App\Models\Tube;
use Illuminate\Http\Request;

class StockOpnameManagementController extends Controller
{
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
                ->whereNot('transaction_type', 'out')
                ->whereNotNull('locationable_type');
            })
            ->orderBy('number')
            ->get();
            return StockOpnameTubeListResource::collection($tubes);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }
}
