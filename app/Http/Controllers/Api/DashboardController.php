<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySale;
use App\Models\NozzleReading;
use App\Models\Purchase;
use App\Models\Lubricant;
use App\Models\Nozzle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardController extends Controller
{
    /**
     * Parse date supporting both standard yyyy-mm-dd and user format dd/mm/yyyy.
     */
    private function parseDate($dateStr)
    {
        if (!$dateStr) return null;
        try {
            if (str_contains($dateStr, '/')) {
                return Carbon::createFromFormat('d/m/Y', $dateStr)->format('Y-m-d');
            }
            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * GET /api/dashboard/stats
     * Returns remaining pump fuel inventory, lubricant stocks, and general sales aggregates.
     */
    public function getStats(Request $request)
    {
        try {
            $date = $this->parseDate($request->get('date')) ?: Carbon::today()->toDateString();

            // 1. Calculate Pump Fuel Inventory Remaining
            $purchasedMs = floatval(Purchase::where('date', '<=', $date)->sum('ms'));
            $purchasedHsd = floatval(Purchase::where('date', '<=', $date)->sum('hsd'));
            $purchasedXp = floatval(Purchase::where('date', '<=', $date)->sum('xp'));

            $soldMs = floatval(NozzleReading::whereHas('nozzle', function ($q) {
                $q->where('fuel_type', 'MS');
            })->whereHas('dailySale', function ($q) use ($date) {
                $q->where('date', '<=', $date);
            })->select(DB::raw('SUM(sale_qty - COALESCE(testing_qty, 5.00)) as total'))->value('total'));

            $soldHsd = floatval(NozzleReading::whereHas('nozzle', function ($q) {
                $q->where('fuel_type', 'HSD');
            })->whereHas('dailySale', function ($q) use ($date) {
                $q->where('date', '<=', $date);
            })->select(DB::raw('SUM(sale_qty - COALESCE(testing_qty, 5.00)) as total'))->value('total'));

            $soldXp = floatval(NozzleReading::whereHas('nozzle', function ($q) {
                $q->where('fuel_type', 'XP');
            })->whereHas('dailySale', function ($q) use ($date) {
                $q->where('date', '<=', $date);
            })->select(DB::raw('SUM(sale_qty - COALESCE(testing_qty, 5.00)) as total'))->value('total'));

            $remainingMs = round(max(0.00, $purchasedMs - $soldMs), 2);
            $remainingHsd = round(max(0.00, $purchasedHsd - $soldHsd), 2);
            $remainingXp = round(max(0.00, $purchasedXp - $soldXp), 2);

            // 2. Lubricant Stocks
            $lubricants = Lubricant::orderBy('name', 'asc')->get()->map(function ($l) {
                return [
                    'id' => $l->id,
                    'name' => $l->name,
                    'qty_name' => $l->qty_name,
                    'stock' => intval($l->stock),
                    'price' => floatval($l->price),
                ];
            });

            // 3. Sales Overview Aggregates
            $today = Carbon::today()->toDateString();
            $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
            $startOfQuarter = Carbon::now()->startOfQuarter()->toDateString();

            // Load daily sales with nozzle readings sum amount
            $salesQuery = DailySale::with('nozzleReadings');

            // Today's Sales
            $todaySaleRecords = (clone $salesQuery)->where('date', $today)->get();
            $todaySalesAmount = 0.00;
            foreach ($todaySaleRecords as $sale) {
                $todaySalesAmount += floatval($sale->nozzleReadings->sum('amount')) + floatval($sale->lubricant_sale);
            }

            // This Week's Sales
            $weekSaleRecords = (clone $salesQuery)->where('date', '>=', $startOfWeek)->get();
            $weekSalesAmount = 0.00;
            foreach ($weekSaleRecords as $sale) {
                $weekSalesAmount += floatval($sale->nozzleReadings->sum('amount')) + floatval($sale->lubricant_sale);
            }

            // This Quarter's Sales
            $quarterSaleRecords = (clone $salesQuery)->where('date', '>=', $startOfQuarter)->get();
            $quarterSalesAmount = 0.00;
            foreach ($quarterSaleRecords as $sale) {
                $quarterSalesAmount += floatval($sale->nozzleReadings->sum('amount')) + floatval($sale->lubricant_sale);
            }

            // Grand Total Sales
            $allSaleRecords = (clone $salesQuery)->get();
            $grandTotalSalesAmount = 0.00;
            foreach ($allSaleRecords as $sale) {
                $grandTotalSalesAmount += floatval($sale->nozzleReadings->sum('amount')) + floatval($sale->lubricant_sale);
            }

            return response()->json([
                'success' => true,
                'stats' => [
                    'fuel_remaining' => [
                        'MS' => $remainingMs,
                        'HSD' => $remainingHsd,
                        'XP' => $remainingXp,
                        'total' => round($remainingMs + $remainingHsd + $remainingXp, 2)
                    ],
                    'lubricant_stock' => $lubricants,
                    'sales_overview' => [
                        'today_sales' => round($todaySalesAmount, 2),
                        'this_week_sales' => round($weekSalesAmount, 2),
                        'this_quarter_sales' => round($quarterSalesAmount, 2),
                        'grand_total_sales' => round($grandTotalSalesAmount, 2)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard Stats Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard stats.'
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/sales
     * Dynamic aggregated, filterable, and paginated sales endpoint (daily, weekly, quarterly).
     */
    public function getSales(Request $request)
    {
        try {
            // Determine grouping type: day, week, quarter
            $groupBy = $request->get('group_by', 'day');
            if (!in_array($groupBy, ['day', 'week', 'quarter'])) {
                $groupBy = 'day';
            }

            $query = DailySale::with(['nozzleReadings.nozzle', 'creditGivens'])->orderBy('date', 'desc');

            // Apply optional Date Range Filters
            if ($request->has('start_date') && $request->start_date) {
                $startDate = $this->parseDate($request->start_date);
                if ($startDate) {
                    $query->where('date', '>=', $startDate);
                }
            }

            if ($request->has('end_date') && $request->end_date) {
                $endDate = $this->parseDate($request->end_date);
                if ($endDate) {
                    $query->where('date', '<=', $endDate);
                }
            }

            $sales = $query->get();

            // Perform Grouping & Calculations
            if ($groupBy === 'day') {
                $mappedSales = $sales->map(function ($sale) {
                    $fuelSales = floatval($sale->nozzleReadings->sum('amount'));
                    $lubricantSales = floatval($sale->lubricant_sale);
                    return [
                        'period' => $sale->date->format('d/m/Y'),
                        'raw_date' => $sale->date->format('Y-m-d'),
                        'fuel_sales' => round($fuelSales, 2),
                        'lubricant_sales' => round($lubricantSales, 2),
                        'total_sales' => round($fuelSales + $lubricantSales, 2),
                        'credit_sales' => round(floatval($sale->creditGivens->sum('amount') ?: $sale->creditor), 2),
                        'cash_collected' => round(floatval($sale->cash_collection), 2),
                    ];
                });
            } elseif ($groupBy === 'week') {
                $mappedSales = $sales->groupBy(function ($sale) {
                    return $sale->date->startOfWeek()->format('d/m/Y');
                })->map(function ($group, $weekStart) {
                    $fuelSales = floatval($group->sum(function ($sale) {
                        return $sale->nozzleReadings->sum('amount');
                    }));
                    $lubricantSales = floatval($group->sum('lubricant_sale'));
                    $creditSales = floatval($group->sum(function ($sale) {
                        return $sale->creditGivens->sum('amount') ?: $sale->creditor;
                    }));
                    $cashCollected = floatval($group->sum('cash_collection'));

                    return [
                        'period' => "Week of {$weekStart}",
                        'raw_date' => Carbon::createFromFormat('d/m/Y', $weekStart)->format('Y-m-d'),
                        'fuel_sales' => round($fuelSales, 2),
                        'lubricant_sales' => round($lubricantSales, 2),
                        'total_sales' => round($fuelSales + $lubricantSales, 2),
                        'credit_sales' => round($creditSales, 2),
                        'cash_collected' => round($cashCollected, 2),
                    ];
                })->values();
            } else { // quarter
                $mappedSales = $sales->groupBy(function ($sale) {
                    $quarter = ceil($sale->date->month / 3);
                    return $sale->date->year . '-Q' . $quarter;
                })->map(function ($group, $quarterKey) {
                    $fuelSales = floatval($group->sum(function ($sale) {
                        return $sale->nozzleReadings->sum('amount');
                    }));
                    $lubricantSales = floatval($group->sum('lubricant_sale'));
                    $creditSales = floatval($group->sum(function ($sale) {
                        return $sale->creditGivens->sum('amount') ?: $sale->creditor;
                    }));
                    $cashCollected = floatval($group->sum('cash_collection'));

                    return [
                        'period' => $quarterKey,
                        'raw_date' => $quarterKey,
                        'fuel_sales' => round($fuelSales, 2),
                        'lubricant_sales' => round($lubricantSales, 2),
                        'total_sales' => round($fuelSales + $lubricantSales, 2),
                        'credit_sales' => round($creditSales, 2),
                        'cash_collected' => round($cashCollected, 2),
                    ];
                })->values();
            }

            // Database-Agnostic manual pagination to keep consistent with SQLite & MySQL
            $page = intval($request->get('page', 1));
            $perPage = intval($request->get('per_page', 10));
            $offset = ($page - 1) * $perPage;

            $paginatedItems = $mappedSales->slice($offset, $perPage)->values();

            $paginatedSales = new LengthAwarePaginator(
                $paginatedItems,
                $mappedSales->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'success' => true,
                'group_by' => $groupBy,
                'sales' => $paginatedSales
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard Sales Aggregation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while compiling dashboard sales.'
            ], 500);
        }
    }
}
