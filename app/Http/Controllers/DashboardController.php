<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Models\Site;
use App\Models\Tube;
use App\Models\TubeContentType;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function activeTubeOwnCount(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        
        try {
            $data = [];
            $sites = Site::when($user->level != 0, function ($q) use ($sites) {
                $q->whereIn('id', $sites);
            })
            ->get();
            foreach ($sites as $key => $site) {
                $dataSite = [
                    "site" => $site->name,
                    "content_type" => []
                ];
                $contentTypes = TubeContentType::all();
                $dataContentTypes = [];
                foreach ($contentTypes as $key2 => $contentType) {
                    $dataContentType = [
                        "content_type" => $contentType->code . " - " . $contentType->name,
                        "empty" => 0,
                        "empty_tube" => [],
                        "filled" => 0,
                        "filled_tube" => [],
                        "broken" => 0,
                        "broken_tube" => [],
                        "expired" => 0,
                        "expired_tube" => [],
                        "display" => 0,
                        "display_tube" => [],
                        "refill" => 0,
                        "refill_tube" => [],
                        "borrow" => 0,
                        "borrow_tube" => [],
                        "fixing" => 0,
                        "fixing_tube" => []
                    ];
                    $conditions = [
                        "empty",
                        "filled",
                        "broken",
                        "expired",
                        "display",
                        "refill",
                        "borrow",
                        "fixing"
                    ];
                    foreach ($conditions as $key3 => $condition) {
                        $tubes = Tube::where('active', true)
                        ->whereRelation('latestTubeContent', 'tube_content_type_id', $contentType->id)
                        ->whereRelation('latestTubeTransaction', 'site_id', $site->id)
                        ->where(function ($q) {
                            $q->whereDoesntHaveRelation('latestTubeTransaction', 'transaction_type', 'sell')
                            ->where('own', '!=', false);
                        })->get();
                        $dataContentType[$condition] = $tubes->filter(fn ($model) => $model->status_for_active_own_count == $condition)->count();
                        foreach ($tubes->filter(fn ($model) => $model->status_for_active_own_count == $condition) as $tube) {
                            $dataContentType[$condition . "_tube"][] = [
                                "id" => $tube->uid,
                                "number" => $tube->number,
                                "barcode" => $tube->latestTubeBarcode->barcode
                            ];
                        }
                    }
                    $dataContentTypes[] = $dataContentType;
                }
                $dataSite['content_type'] = $dataContentTypes;
                $data[] = $dataSite;
            }
            return Response::successData($data);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }
}
