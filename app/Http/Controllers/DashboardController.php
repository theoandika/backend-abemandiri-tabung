<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Models\Site;
use App\Models\TubeContentType;
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
                    "site_id" => $site->uid,
                    "site" => $site->name,
                    "content_type" => []
                ];
                $contentTypes = TubeContentType::all();
                $dataContentTypes = [];
                foreach ($contentTypes as $key2 => $contentType) {
                    $dataContentType = [
                        "content_type_id" => $contentType->uid,
                        "content_type" => $contentType->code . " - " . $contentType->name,
                        "empty" => 0,
                        "filled" => 0,
                        "broken" => 0,
                        "expired" => 0,
                        "display" => 0,
                        "refill" => 0,
                        "borrow" => 0,
                        "fixing" => 0,
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

                    $result2 = DB::table('tubes as tb')
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
                    ->where('tt.transaction_type', 'out')
                    ->where('tc.tube_content_type_id', $contentType->id)
                    ->whereNotExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('tube_transactions as sold')
                            ->whereColumn('sold.tube_id', 'tb.id')
                            ->where('sold.transaction_type', 'sell');
                    })
                    ->selectRaw("COUNT(*) AS borrow_tubes")
                    ->first();
                    $dataContentType['borrow'] = $result2->borrow_tubes;

                    $result3 = DB::table('tubes as tb')
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
                    ->whereIn('tt.transaction_type', ['refill', 'fixing'])
                    ->where('tc.tube_content_type_id', $contentType->id)
                    ->whereNotExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('tube_transactions as sold')
                            ->whereColumn('sold.tube_id', 'tb.id')
                            ->where('sold.transaction_type', 'sell');
                    })
                    ->selectRaw("
                        COUNT(CASE WHEN tt.transaction_type = 'refill' THEN 1 END) AS refill_tubes,
                        COUNT(CASE WHEN tt.transaction_type = 'fixing' THEN 1 END) AS fixing_tubes
                    ")
                    ->first();
                    $dataContentType['refill'] = $result3->refill_tubes;
                    $dataContentType['fixing'] = $result3->fixing_tubes;

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

    public function tubesByStatus(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $site = Site::when($user->level != 0, function ($q) use ($sites) {
            $q->whereIn('id', $sites);
        })
        ->where('uid', $r->input('site'))
        ->first();
        $contentType = TubeContentType::where('uid', $r->input('content_type'))->first();
        $status = $r->input('status');

        try {
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
            ->leftJoin('tube_barcodes as tbcode', function ($join) {
                $join->on('tbcode.id', '=', DB::raw('(
                    SELECT MAX(b.id)
                    FROM tube_barcodes b
                    WHERE b.tube_id = tb.id
                )'));
            })
            ->where('tb.own', 1)
            ->where('tt.site_id', $site->id)
            ->when($status == 'empty' || $status == 'filled' || $status == 'broken' || $status == 'expired' || $status == 'display', function ($q) use ($status) {
                $q->whereIn('tt.transaction_type', ['in', 'return', 'fixed'])
                ->where('tt.tube_status', $status);
            })
            ->when($status == 'borrow', function ($q) {
                $q->where('tt.transaction_type', 'out');
            })
            ->when($status == 'refill', function ($q) {
                $q->where('tt.transaction_type', 'refill');
            })
            ->when($status == 'fixing', function ($q) {
                $q->where('tt.transaction_type', 'fixing');
            })
            ->where('tc.tube_content_type_id', $contentType->id)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('tube_transactions as sold')
                    ->whereColumn('sold.tube_id', 'tb.id')
                    ->where('sold.transaction_type', 'sell');
            })
            ->selectRaw("tb.uid as id, tb.number, tbcode.barcode")
            ->get();
            return Response::successData($result);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }
}
