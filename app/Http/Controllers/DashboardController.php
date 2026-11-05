<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Models\Site;
use App\Models\Tube;
use App\Models\TubeContentType;
use App\Models\TubeTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

                    $result = DB::table('tubes as tb')
                    ->join('tube_transactions as tt', function ($join) {
                        $join->on('tt.id', '=', DB::raw('(
                            SELECT MAX(t.id)
                            FROM tube_transactions t
                            WHERE t.tube_id = tb.id
                        )'));
                    })
                    ->join('tube_contents as tc', function ($join) {
                        $join->on('tc.id', '=', DB::raw('(
                            SELECT MAX(c.id)
                            FROM tube_contents c
                            WHERE c.tube_id = tb.id
                        )'));
                    })
                    ->where('tb.own', 1)
                    ->where('tt.site_id', $site->id)
                    ->whereIn('tt.transaction_type', ['in', 'return', 'fixed'])
                    ->whereIn('tt.tube_status', ['empty', 'filled', 'broken', 'expired', 'display'])
                    ->where('tc.tube_content_type_id', $contentType->id)
                    ->whereNotExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('tube_transactions as sold')
                            ->whereColumn('sold.tube_id', 'tb.id')
                            ->where('sold.transaction_type', 'sell');
                    })
                    ->selectRaw("
                        COUNT(CASE WHEN tt.tube_status = 'empty' THEN 1 END) AS empty_tubes,
                        COUNT(CASE WHEN tt.tube_status = 'filled' THEN 1 END) AS filled_tubes,
                        COUNT(CASE WHEN tt.tube_status = 'broken' THEN 1 END) AS broken_tubes,
                        COUNT(CASE WHEN tt.tube_status = 'expired' THEN 1 END) AS expired_tubes,
                        COUNT(CASE WHEN tt.tube_status = 'display' THEN 1 END) AS display_tubes
                    ")
                    ->first();

                    $dataContentType['empty'] = $result->empty_tubes;
                    $dataContentType['filled'] = $result->filled_tubes;
                    $dataContentType['broken'] = $result->broken_tubes;
                    $dataContentType['expired'] = $result->expired_tubes;
                    $dataContentType['display'] = $result->display_tubes;
                    // $conditions = [
                    //     "empty",
                    //     "filled",
                    //     "broken",
                    //     "expired",
                    //     "display",
                    //     "refill",
                    //     "borrow",
                    //     "fixing"
                    // ];
                    // foreach ($conditions as $key3 => $condition) {
                        // $tubes = Tube::where('active', true)
                        // ->whereRelation('latestTubeContent', 'tube_content_type_id', $contentType->id)
                        // ->whereRelation('latestTubeTransaction', 'site_id', $site->id)
                        // ->where(function ($q) {
                        //     $q->whereDoesntHaveRelation('latestTubeTransaction', 'transaction_type', 'sell')
                        //     ->where('own', '!=', false);
                        // })->get();
                        // $dataContentType[$condition] = $tubes->filter(fn ($model) => $model->status_for_active_own_count == $condition)->count();
                        // foreach ($tubes->filter(fn ($model) => $model->status_for_active_own_count == $condition) as $tube) {
                        //     $dataContentType[$condition . "_tube"][] = [
                        //         "id" => $tube->uid,
                        //         "number" => $tube->number,
                        //         "barcode" => $tube->latestTubeBarcode->barcode
                        //     ];
                        // }
                    // }
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
