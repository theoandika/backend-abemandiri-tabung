<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailTubeTransactionResource;
use App\Http\Resources\LastTubePositionResource;
use App\Http\Resources\StockOpnameReportResource;
use App\Models\StockOpnameItem;
use App\Models\Tube;
use App\Models\TubeTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function tubeActivity(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'date' => 'bail|nullable|date_format:Y-m-d',
            'site' => 'bail|nullable|string',
            'content' => 'bail|nullable|string',
            'transaction_type' => 'bail|nullable|string',
            'condition' => 'bail|nullable|string',
            'location' => 'bail|nullable|string',
            'opname_adjust' => 'bail|nullable|boolean',
            'paginate' => 'bail|nullable|integer|min:1'
        ]);

        try {
            $tubeTransaction = TubeTransaction::query()
            ->with([
                'tube',
                'site',
            ])
            ->when($user->level != 0, function ($q) use ($sites) {
                $q->where(function ($q) use ($sites) {
                    $q->whereIn('site_id', $sites)
                    ->whereNot('transaction_type', 'out')
                    ->whereNotNull('locationable_type');
                });
            })
            ->when($r->input('search'), function ($q, $search) {
                $keyword = "%{$search}%";
                $q->where(function ($q) use ($keyword) {
                    $q->whereHas('tube', function ($q) use ($keyword) {
                        $q->where('number', 'like', $keyword);
                    })
                    ->orWhereRaw("
                        (
                            SELECT tb.barcode
                            FROM tube_barcodes tb
                            WHERE tb.tube_id = tube_transactions.tube_id
                            ORDER BY
                                CASE
                                    WHEN tb.created_at <= tube_transactions.date THEN 0
                                    ELSE 1
                                END,
                                tb.created_at DESC,
                                tb.id DESC
                            LIMIT 1
                        ) LIKE ?
                    ", [$keyword])
                    ->orWhereRaw("
                        CASE
                            WHEN transaction_type = 'out'
                                AND locationable_type IS NULL
                                THEN 'Transit'

                            WHEN transaction_type IN ('out', 'sell')
                                AND locationable_type = 'App\\\\Models\\\\Member'
                                THEN (
                                    SELECT CONCAT(m.code, ' - ', m.name)
                                    FROM members m
                                    WHERE m.id = tube_transactions.locationable_id
                                    LIMIT 1
                                )

                            WHEN (
                                transaction_type IN ('in', 'return')
                                AND (
                                    locationable_type = 'App\\\\Models\\\\Member'
                                    OR locationable_type IS NULL
                                )
                            )
                            OR (
                                transaction_type IN ('filled', 'fixed')
                                AND locationable_type = 'App\\\\Models\\\\Supplier'
                            )
                                THEN (
                                    SELECT s.name
                                    FROM sites s
                                    WHERE s.id = tube_transactions.site_id
                                    LIMIT 1
                                )

                            WHEN transaction_type IN ('refill', 'fixing')
                                AND locationable_type = 'App\\\\Models\\\\Supplier'
                                THEN (
                                    SELECT CONCAT(sp.code, ' - ', sp.name)
                                    FROM suppliers sp
                                    WHERE sp.id = tube_transactions.locationable_id
                                    LIMIT 1
                                )

                            ELSE 'Tidak diketahui'
                        END LIKE ?
                    ", [$keyword]);
                });
            })
            ->when($r->input('content'), function ($q, $content) {
                $q->whereRaw("
                    COALESCE(
                        (
                            SELECT tc.tube_content_type_id
                            FROM tube_contents tc
                            WHERE tc.tube_id = tube_transactions.tube_id
                            AND tc.created_at <= tube_transactions.date
                            ORDER BY tc.created_at DESC, tc.id DESC
                            LIMIT 1
                        ),
                        (
                            SELECT tc.tube_content_type_id
                            FROM tube_contents tc
                            WHERE tc.tube_id = tube_transactions.tube_id
                            ORDER BY tc.created_at DESC, tc.id ASC
                            LIMIT 1
                        )
                    ) IN (
                        SELECT tct.id
                        FROM tube_content_types tct
                        WHERE tct.uid = ?
                    )
                ", [$content]);
            })
            ->when($r->input('date'), function ($q, $date) {
                $q->whereDate('date', $date);
            })
            ->when($r->input('site'), function ($q, $site) {
                $q->whereIn('site_id', function ($sub) use ($site) {
                    $sub->select('id')
                        ->from('sites')
                        ->where('uid', $site);
                });
            })
            ->when($r->input('transaction_type'), function ($q, $transaction_type) {
                $q->where('transaction_type', $transaction_type);
            })
            ->when($r->input('condition'), function ($q, $condition) {
                $q->where('tube_status', $condition);
            })
            ->when($r->input('location'), function ($q, $location) {
                $q->whereRaw("
                    CASE
                        WHEN transaction_type = 'out'
                            AND locationable_type IS NULL
                            THEN 'transit'

                        WHEN transaction_type = 'out'
                            AND locationable_type = 'App\\\\Models\\\\Member'
                            THEN 'member'

                        WHEN transaction_type IN ('in', 'return')
                            AND (
                                locationable_type = 'App\\\\Models\\\\Member'
                                OR locationable_type IS NULL
                            )
                            THEN 'site'

                        WHEN transaction_type = 'refill'
                            AND locationable_type = 'App\\\\Models\\\\Supplier'
                            THEN 'supplier'

                        WHEN transaction_type = 'filled'
                            AND locationable_type = 'App\\\\Models\\\\Supplier'
                            THEN 'site'

                        WHEN transaction_type = 'sell'
                            AND locationable_type = 'App\\\\Models\\\\Member'
                            THEN 'member'

                        WHEN transaction_type = 'fixing'
                            AND locationable_type = 'App\\\\Models\\\\Supplier'
                            THEN 'supplier'

                        WHEN transaction_type = 'fixed'
                            AND locationable_type = 'App\\\\Models\\\\Supplier'
                            THEN 'site'

                        ELSE 'unknown'
                    END = ?
                ", [$location]);
            })
            ->when($r->filled('opname_adjust'), function ($q) use ($r) {
                $isAdjusted = $r->boolean('opname_adjust');
                if ($isAdjusted) {
                    $q->whereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('stock_opname_items as soi')
                            ->whereColumn('soi.tube_transaction_id', 'tube_transactions.id')
                            ->where('soi.adjust', true);
                    });
                } else {
                    $q->whereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('stock_opname_items as soi')
                            ->whereColumn('soi.tube_transaction_id', 'tube_transactions.id')
                            ->where('soi.adjust', true);
                    });
                }
            })
            ->orderByDesc('date')
            ->orderByDesc('created_at');
            if ($r->filled('paginate')) {
                $tubeTransaction = $tubeTransaction->paginate($r->integer('paginate'));
            } else {
                $tubeTransaction = $tubeTransaction->get();
            }
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
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'barcode' => 'bail|required|string|max:50'
        ],[
            'barcode.required' => 'Masukkan barcode tabung',
            'barcode.max' => 'Barcode maksimal 50 karakter'
        ]);

        try {
            $tube = Tube::when($user->level != 0, function ($q) use ($sites) {
                $q->whereHas('latestTubeTransaction', function ($q) use ($sites) {
                    $q->whereIn('site_id', $sites)
                    ->where(function ($q) {
                        $q->where(function ($q) {
                            $q->where('transaction_type', 'out')
                            ->whereNotNull('locationable_type');
                        })
                        ->orWhereIn('transaction_type', ['in', 'sell', 'return', 'refill', 'fixing']);
                    });
                });
            })
            ->whereHas('latestTubeBarcode', function ($q) use ($r) {
                $q->where('barcode', $r->input('barcode'));
            })->first();
            if (!$tube) {
                return Response::error("Tabung tidak ditemukan", 404);
            }
            if (!$tube->latestTubeTransaction) {
                return Response::error("Posisi tabung tidak diketahui", 404);
            }
            return new LastTubePositionResource($tube);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function exportTubeActivity(Request $r)
    {
        $user = $r->user();
        $sites = $user->userSites->pluck('site_id');
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'date' => 'bail|required|date_format:Y-m-d',
            'site' => 'bail|nullable|string',
            'content' => 'bail|nullable|string',
            'transaction_type' => 'bail|nullable|string',
            'condition' => 'bail|nullable|string',
            'location' => 'bail|nullable|string',
            'opname_adjust' => 'bail|nullable|boolean',
            'paginate' => 'bail|nullable|integer|min:1'
        ], [
            'date.required' => 'Tanggal harus ditentukan'
        ]);

        try {
            $tubeTransaction = DB::table('tube_transactions as tt')
            ->leftJoin('tubes as t', 't.id', '=', 'tt.tube_id')
            ->leftJoin('sites as s', 's.id', '=', 'tt.site_id')

            ->select([
                'tt.uid',
                'tt.date',
                't.number',
                's.name as site_name',
                'tt.transaction_type',
                'tt.tube_status',
            ])

            // Barcode
            ->selectSub(function ($q) {
                $q->select('tb.barcode')
                    ->from('tube_barcodes as tb')
                    ->whereColumn('tb.tube_id', 'tt.tube_id')
                    ->orderByRaw("
                        CASE
                            WHEN tb.created_at <= tt.date THEN 0
                            ELSE 1
                        END
                    ")
                    ->orderByDesc('tb.created_at')
                    ->orderByDesc('tb.id')
                    ->limit(1);
            }, 'barcode')

            // Content code
            ->selectSub(function ($q) {
                $q->select('tct.code')
                    ->from('tube_content_types as tct')
                    ->whereRaw("
                        tct.id = COALESCE(
                            (
                                SELECT tc.tube_content_type_id
                                FROM tube_contents tc
                                WHERE tc.tube_id = tt.tube_id
                                AND tc.created_at <= tt.date
                                ORDER BY tc.created_at DESC, tc.id DESC
                                LIMIT 1
                            ),
                            (
                                SELECT tc.tube_content_type_id
                                FROM tube_contents tc
                                WHERE tc.tube_id = tt.tube_id
                                ORDER BY tc.created_at DESC, tc.id DESC
                                LIMIT 1
                            )
                        )
                    ");
            }, 'content_code')

            // Content name
            ->selectSub(function ($q) {
                $q->select('tct.name')
                    ->from('tube_content_types as tct')
                    ->whereRaw("
                        tct.id = COALESCE(
                            (
                                SELECT tc.tube_content_type_id
                                FROM tube_contents tc
                                WHERE tc.tube_id = tt.tube_id
                                AND tc.created_at <= tt.date
                                ORDER BY tc.created_at DESC, tc.id DESC
                                LIMIT 1
                            ),
                            (
                                SELECT tc.tube_content_type_id
                                FROM tube_contents tc
                                WHERE tc.tube_id = tt.tube_id
                                ORDER BY tc.created_at DESC, tc.id DESC
                                LIMIT 1
                            )
                        )
                    ");
            }, 'content_name')

            // Position
            ->selectRaw("
                CASE
                    WHEN tt.transaction_type = 'out'
                        AND tt.locationable_type IS NULL
                        THEN 'transit'

                    WHEN tt.transaction_type IN ('out', 'sell')
                        AND tt.locationable_type = ?
                        THEN 'member'

                    WHEN tt.transaction_type IN ('in', 'return')
                        AND (
                            tt.locationable_type = ?
                            OR tt.locationable_type IS NULL
                        )
                        THEN 'site'

                    WHEN tt.transaction_type IN ('refill', 'fixing')
                        AND tt.locationable_type = ?
                        THEN 'supplier'

                    WHEN tt.transaction_type IN ('filled', 'fixed')
                        AND tt.locationable_type = ?
                        THEN 'site'

                    ELSE 'unknown'
                END AS position
            ", [
                \App\Models\Member::class,
                \App\Models\Member::class,
                \App\Models\Supplier::class,
                \App\Models\Supplier::class,
            ])

            // Position name
            ->selectRaw("
                CASE
                    WHEN tt.transaction_type = 'out'
                        AND tt.locationable_type IS NULL
                        THEN 'Transit'

                    WHEN tt.transaction_type IN ('out', 'sell')
                        AND tt.locationable_type = ?
                        THEN (
                            SELECT CONCAT(m.code, ' - ', m.name)
                            FROM members m
                            WHERE m.id = tt.locationable_id
                            LIMIT 1
                        )

                    WHEN (
                        tt.transaction_type IN ('in', 'return')
                        AND (
                            tt.locationable_type = ?
                            OR tt.locationable_type IS NULL
                        )
                    )
                    OR (
                        tt.transaction_type IN ('filled', 'fixed')
                        AND tt.locationable_type = ?
                    )
                        THEN s.name

                    WHEN tt.transaction_type IN ('refill', 'fixing')
                        AND tt.locationable_type = ?
                        THEN (
                            SELECT CONCAT(sp.code, ' - ', sp.name)
                            FROM suppliers sp
                            WHERE sp.id = tt.locationable_id
                            LIMIT 1
                        )

                    ELSE 'Tidak diketahui'
                END AS position_name
            ", [
                \App\Models\Member::class,
                \App\Models\Member::class,
                \App\Models\Supplier::class,
                \App\Models\Supplier::class,
            ])

            // Stock opname adjustment
            ->selectRaw("
                EXISTS (
                    SELECT 1
                    FROM stock_opname_items soi
                    WHERE soi.tube_transaction_id = tt.id
                    AND soi.adjust = 1
                ) AS is_adjust_by_stock_opname
            ")

            // Filter hak akses
            ->when($user->level != 0, function ($q) use ($sites) {
                $q->whereIn('tt.site_id', $sites)
                    ->whereNot('tt.transaction_type', 'out')
                    ->whereNotNull('tt.locationable_type');
            })

            // Filter search
            ->when($r->input('search'), function ($q, $search) {
                $keyword = "%{$search}%";

                $q->where(function ($q) use ($keyword) {
                    $q->where('t.number', 'like', $keyword)
                        ->orWhereRaw("
                            (
                                SELECT tb.barcode
                                FROM tube_barcodes tb
                                WHERE tb.tube_id = tt.tube_id
                                ORDER BY
                                    CASE
                                        WHEN tb.created_at <= tt.date THEN 0
                                        ELSE 1
                                    END,
                                    tb.created_at DESC,
                                    tb.id DESC
                                LIMIT 1
                            ) LIKE ?
                        ", [$keyword])
                        ->orWhereRaw("
                            CASE
                                WHEN tt.transaction_type = 'out'
                                    AND tt.locationable_type IS NULL
                                    THEN 'Transit'

                                WHEN tt.transaction_type IN ('out', 'sell')
                                    AND tt.locationable_type = ?
                                    THEN (
                                        SELECT CONCAT(m.code, ' - ', m.name)
                                        FROM members m
                                        WHERE m.id = tt.locationable_id
                                        LIMIT 1
                                    )

                                WHEN (
                                    tt.transaction_type IN ('in', 'return')
                                    AND (
                                        tt.locationable_type = ?
                                        OR tt.locationable_type IS NULL
                                    )
                                )
                                OR (
                                    tt.transaction_type IN ('filled', 'fixed')
                                    AND tt.locationable_type = ?
                                )
                                    THEN s.name

                                WHEN tt.transaction_type IN ('refill', 'fixing')
                                    AND tt.locationable_type = ?
                                    THEN (
                                        SELECT CONCAT(sp.code, ' - ', sp.name)
                                        FROM suppliers sp
                                        WHERE sp.id = tt.locationable_id
                                        LIMIT 1
                                    )

                                ELSE 'Tidak diketahui'
                            END LIKE ?
                        ", [
                            \App\Models\Member::class,
                            \App\Models\Member::class,
                            \App\Models\Supplier::class,
                            \App\Models\Supplier::class,
                            $keyword,
                        ]);
                });
            })

            // Filter content UID
            ->when($r->input('content'), function ($q, $content) {
                $q->whereRaw("
                    COALESCE(
                        (
                            SELECT tc.tube_content_type_id
                            FROM tube_contents tc
                            WHERE tc.tube_id = tt.tube_id
                            AND tc.created_at <= tt.date
                            ORDER BY tc.created_at DESC, tc.id DESC
                            LIMIT 1
                        ),
                        (
                            SELECT tc.tube_content_type_id
                            FROM tube_contents tc
                            WHERE tc.tube_id = tt.tube_id
                            ORDER BY tc.created_at DESC, tc.id DESC
                            LIMIT 1
                        )
                    ) IN (
                        SELECT tct.id
                        FROM tube_content_types tct
                        WHERE tct.uid = ?
                    )
                ", [$content]);
            })

            // Filter tanggal
            ->when($r->input('date'), function ($q, $date) {
                $q->whereDate('tt.date', $date);
            })

            // Filter site UID
            ->when($r->input('site'), function ($q, $site) {
                $q->where('s.uid', $site);
            })

            // Filter transaction type
            ->when($r->input('transaction_type'), function ($q, $type) {
                $q->where('tt.transaction_type', $type);
            })

            // Filter kondisi
            ->when($r->input('condition'), function ($q, $condition) {
                $q->where('tt.tube_status', $condition);
            })

            // Filter position
            ->when($r->input('location'), function ($q, $location) {
                $q->whereRaw("
                    CASE
                        WHEN tt.transaction_type = 'out'
                            AND tt.locationable_type IS NULL
                            THEN 'transit'

                        WHEN tt.transaction_type = 'out'
                            AND tt.locationable_type = ?
                            THEN 'member'

                        WHEN tt.transaction_type IN ('in', 'return')
                            AND (
                                tt.locationable_type = ?
                                OR tt.locationable_type IS NULL
                            )
                            THEN 'site'

                        WHEN tt.transaction_type = 'refill'
                            AND tt.locationable_type = ?
                            THEN 'supplier'

                        WHEN tt.transaction_type = 'filled'
                            AND tt.locationable_type = ?
                            THEN 'site'

                        WHEN tt.transaction_type = 'sell'
                            AND tt.locationable_type = ?
                            THEN 'member'

                        WHEN tt.transaction_type = 'fixing'
                            AND tt.locationable_type = ?
                            THEN 'supplier'

                        WHEN tt.transaction_type = 'fixed'
                            AND tt.locationable_type = ?
                            THEN 'site'

                        ELSE 'unknown'
                    END = ?
                ", [
                    \App\Models\Member::class,
                    \App\Models\Member::class,
                    \App\Models\Supplier::class,
                    \App\Models\Supplier::class,
                    \App\Models\Member::class,
                    \App\Models\Supplier::class,
                    \App\Models\Supplier::class,
                    $location,
                ]);
            })

            // Filter stock opname
            ->when($r->filled('opname_adjust'), function ($q) use ($r) {
                $isAdjusted = $r->boolean('opname_adjust');

                $condition = function ($sub) {
                    $sub->selectRaw('1')
                        ->from('stock_opname_items as soi')
                        ->whereColumn('soi.tube_transaction_id', 'tt.id')
                        ->where('soi.adjust', true);
                };

                if ($isAdjusted) {
                    $q->whereExists($condition);
                } else {
                    $q->whereNotExists($condition);
                }
            })
            ->orderByDesc('tt.date')
            ->orderByDesc('tt.created_at')
            ->get();
            return Response::successData($tubeTransaction);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }
}
