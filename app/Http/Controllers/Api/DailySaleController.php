<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySale;
use App\Models\Nozzle;
use App\Models\NozzleReading;
use App\Models\Lubricant;
use App\Models\DailySaleLubricant;
use App\Models\CreditorTransaction;
use App\Models\DailySaleCreditPayment;
use App\Models\DailySaleCreditGiven;
use App\Models\DailySaleOnlinePayment;
use App\Models\DailySaleStaffPayout;
use App\Models\DailySalePumpExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailySaleController extends Controller
{
    /**
     * Helper to get the previous closing reading for a nozzle.
     */
    private function getPreviousClosingReading($nozzleId, $date)
    {
        $prevReading = NozzleReading::where('nozzle_id', $nozzleId)
            ->join('daily_sales', 'nozzle_readings.daily_sale_id', '=', 'daily_sales.id')
            ->where('daily_sales.date', '<', $date)
            ->orderBy('daily_sales.date', 'desc')
            ->select('nozzle_readings.closing_reading')
            ->first();

        if ($prevReading) {
            return floatval($prevReading->closing_reading);
        }

        $nozzle = Nozzle::findOrFail($nozzleId);
        return floatval($nozzle->opening_reading);
    }

    /**
     * Helper to get the previous pump closing balance.
     * Defaults to the initial standard closing balance of 29120.00 if no prior daily sale exists.
     */
    private function getPreviousPumpBalance($date)
    {
        $prevSale = DailySale::where('date', '<', $date)
            ->orderBy('date', 'desc')
            ->first();

        if ($prevSale) {
            return floatval($prevSale->balance_in_pump);
        }

        return 29120.00;
    }

    /**
     * Cascade recalculation for subsequent nozzle readings when a prior reading changes.
     */
    private function recalculateSubsequentReadings($nozzleId, $startDate)
    {
        $readings = NozzleReading::where('nozzle_id', $nozzleId)
            ->join('daily_sales', 'nozzle_readings.daily_sale_id', '=', 'daily_sales.id')
            ->where('daily_sales.date', '>=', $startDate)
            ->orderBy('daily_sales.date', 'asc')
            ->select('nozzle_readings.*', 'daily_sales.date as sale_date', 'daily_sales.rate_ms', 'daily_sales.rate_hsd', 'daily_sales.rate_xp')
            ->get();

        foreach ($readings as $reading) {
            $prevClosing = $this->getPreviousClosingReading($nozzleId, $reading->sale_date);
            $saleQty = $reading->closing_reading - $prevClosing;
            
            $nozzle = Nozzle::findOrFail($nozzleId);
            $rate = 0.00;
            if ($nozzle->fuel_type === 'MS') {
                $rate = floatval($reading->rate_ms);
            } elseif ($nozzle->fuel_type === 'HSD') {
                $rate = floatval($reading->rate_hsd);
            } elseif ($nozzle->fuel_type === 'XP') {
                $rate = floatval($reading->rate_xp);
            }

            $amount = $saleQty * $rate;

            NozzleReading::where('id', $reading->id)->update([
                'sale_qty' => $saleQty,
                'amount' => $amount,
            ]);
        }
    }

    /**
     * Get previous closing readings for all nozzles before the specified date.
     * Helpful for pre-populating frontend forms!
     */
    public function getPreviousReadings(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date'
            ]);

            $date = $request->date;
            $nozzles = Nozzle::get()->sort(function($a, $b) {
                $orderMap = ['5' => 1, '3' => 2, '4' => 3, '6' => 4, '1' => 5, '2' => 6];
                
                preg_match('/\d+/', $a->nozzle_no, $matchesA);
                $numA = $matchesA[0] ?? $a->nozzle_no;
                
                preg_match('/\d+/', $b->nozzle_no, $matchesB);
                $numB = $matchesB[0] ?? $b->nozzle_no;

                $orderA = $orderMap[$numA] ?? 99;
                $orderB = $orderMap[$numB] ?? 99;

                return $orderA <=> $orderB;
            })->values();
            $result = [];

            foreach ($nozzles as $nozzle) {
                $prevClosing = $this->getPreviousClosingReading($nozzle->id, $date);
                $result[] = [
                    'nozzle_id' => $nozzle->id,
                    'nozzle_no' => $nozzle->nozzle_no,
                    'fuel_type' => $nozzle->fuel_type,
                    'previous_closing_reading' => $prevClosing
                ];
            }

            $latestSale = \App\Models\DailySale::where('date', '<', $date)->orderBy('date', 'desc')->first();
            if (!$latestSale) {
                $latestSale = \App\Models\DailySale::orderBy('date', 'desc')->first();
            }

            return response()->json([
                'success' => true,
                'previous_readings' => $result,
                'latest_rates' => [
                    'ms' => $latestSale ? $latestSale->rate_ms : 98.17,
                    'hsd' => $latestSale ? $latestSale->rate_hsd : 92.93,
                    'xp' => $latestSale ? $latestSale->rate_xp : 107.05,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get Previous Readings Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve previous readings.'
            ], 500);
        }
    }

    private function parseDate($dateStr)
    {
        if (!$dateStr) return null;
        try {
            if (str_contains($dateStr, '/')) {
                return \Carbon\Carbon::createFromFormat('d/m/Y', $dateStr)->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * List all daily sales with summarized calculations.
     */
    public function index(Request $request)
    {
        try {
            $perPage = intval($request->get('per_page', 10));
            $query = DailySale::query();

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

            $salesPaginator = $query->with(['nozzleReadings.nozzle', 'creditPayments', 'creditGivens', 'onlinePayments', 'staffPayouts', 'pumpExpenses'])
                ->orderBy('date', 'desc')
                ->paginate($perPage);

            // Fetch nozzle counts to calculate dynamic testing quantities
            $msCount = Nozzle::where('fuel_type', 'MS')->count();
            $hsdCount = Nozzle::where('fuel_type', 'HSD')->count();
            $xpCount = Nozzle::where('fuel_type', 'XP')->count();

            $mappedItems = collect($salesPaginator->items())->map(function ($sale) use ($msCount, $hsdCount, $xpCount) {
                $fuelTotalAmount = 0.00;
                $fuelTotalQty = 0.00;

                foreach ($sale->nozzleReadings as $reading) {
                    $fuelTotalQty += floatval($reading->sale_qty);
                }

                // Fuel summaries calculated based on total quantity per fuel type * rate
                $msQty = 0; $hsdQty = 0; $xpQty = 0;
                foreach ($sale->nozzleReadings as $reading) {
                    if ($reading->nozzle->fuel_type === 'MS') $msQty += floatval($reading->sale_qty);
                    elseif ($reading->nozzle->fuel_type === 'HSD') $hsdQty += floatval($reading->sale_qty);
                    elseif ($reading->nozzle->fuel_type === 'XP') $xpQty += floatval($reading->sale_qty);
                }

                $msAmt = round($msQty * floatval($sale->rate_ms), 2);
                $hsdAmt = round($hsdQty * floatval($sale->rate_hsd), 2);
                $xpAmt = round($xpQty * floatval($sale->rate_xp), 2);
                $fuelTotalAmount = $msAmt + $hsdAmt + $xpAmt;

                $lubricantSale = floatval($sale->lubricant_sale);
                $grandTotalAmount = $fuelTotalAmount + $lubricantSale;

                // Testing calculations
                $testingAmtMs = 0; $testingAmtHsd = 0; $testingAmtXp = 0;
                foreach ($sale->nozzleReadings as $reading) {
                    $qty = floatval($reading->testing_qty ?? 5.00);
                    if ($reading->nozzle->fuel_type === 'MS') $testingAmtMs += $qty * floatval($sale->rate_ms);
                    elseif ($reading->nozzle->fuel_type === 'HSD') $testingAmtHsd += $qty * floatval($sale->rate_hsd);
                    elseif ($reading->nozzle->fuel_type === 'XP') $testingAmtXp += $qty * floatval($sale->rate_xp);
                }

                $density = floatval($sale->density);
                $totalTestingAmount = round($testingAmtMs + $testingAmtHsd + $testingAmtXp + $density, 2);
                $finalSaleAmount = round($grandTotalAmount - $totalTestingAmount, 2);

                // Cash reconciliation dynamically sum arrays
                $creditPaymentReceived = floatval($sale->creditPayments->sum('amount') ?: $sale->credit_payment_received);
                $totalAmount = round($finalSaleAmount + $creditPaymentReceived, 2);

                $acTransfer = floatval($sale->ac_transfer);
                $online = floatval($sale->onlinePayments->sum('amount') ?: $sale->online);
                $creditor = floatval($sale->creditGivens->sum('amount') ?: $sale->creditor);
                $expenseVal = floatval($sale->pumpExpenses->sum('amount') ?: $sale->expense);
                $staff = floatval($sale->staffPayouts->sum('amount') ?: $sale->staff);
                $owner1 = floatval($sale->owner_1);
                $owner2 = floatval($sale->owner_2);

                // Cash to be collected = Total Amount - Credit Payment Received - A/C Transfer - Online - Creditor - Expense - Staff - Owner 1 - Owner 2
                $cashToBeCollected = round($totalAmount - $creditPaymentReceived - $acTransfer - $online - $creditor - $expenseVal - $staff - $owner1 - $owner2, 2);

                $prevPumpBalance = $this->getPreviousPumpBalance($sale->date->toDateString());
                $cashCollection = floatval($sale->cash_collection);
                $coin = floatval($sale->coin);
                $miss = floatval($sale->miss);
                
                $expectedBalance = round($prevPumpBalance + $cashToBeCollected - $cashCollection - $coin - $miss, 2);
                $actualBalanceInPump = floatval($sale->balance_in_pump);
                $errorVal = round($actualBalanceInPump - $expectedBalance, 2);

                return [
                    'id' => $sale->id,
                    'date' => $sale->date->format('d/m/Y'),
                    'rate_ms' => floatval($sale->rate_ms),
                    'rate_hsd' => floatval($sale->rate_hsd),
                    'rate_xp' => floatval($sale->rate_xp),
                    'lubricant_sale' => $lubricantSale,
                    'density' => $density,
                    'total_fuel_qty' => round($fuelTotalQty, 2),
                    'total_fuel_amount' => round($fuelTotalAmount, 2),
                    'ms_qty' => round($msQty, 2),
                    'hsd_qty' => round($hsdQty, 2),
                    'xp_qty' => round($xpQty, 2),
                    'ms_amount' => $msAmt,
                    'hsd_amount' => $hsdAmt,
                    'xp_amount' => $xpAmt,
                    'grand_total_amount' => round($grandTotalAmount, 2),
                    'total_testing_amount' => $totalTestingAmount,
                    'final_sale_amount' => $finalSaleAmount,

                    // Reconciliation
                    'credit_payment_received' => $creditPaymentReceived,
                    'total_amount' => $totalAmount,
                    'ac_transfer' => $acTransfer,
                    'online' => $online,
                    'creditor' => $creditor,
                    'expense' => $expenseVal,
                    'staff' => $staff,
                    'owner_1' => $owner1,
                    'owner_2' => $owner2,
                    'cash_to_be_collected' => $cashToBeCollected,
                    'previous_pump_balance' => $prevPumpBalance,
                    'cash_collection' => $cashCollection,
                    'coin' => $coin,
                    'miss' => $miss,
                    'expected_balance' => $expectedBalance,
                    'actual_balance_in_pump' => $actualBalanceInPump,
                    'error' => $errorVal,
                ];
            });

            $paginatedSales = new \Illuminate\Pagination\LengthAwarePaginator(
                $mappedItems,
                $salesPaginator->total(),
                $salesPaginator->perPage(),
                $salesPaginator->currentPage(),
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'success' => true,
                'daily_sales' => $paginatedSales
            ]);
        } catch (\Exception $e) {
            Log::error('Daily Sale Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily sales.'
            ], 500);
        }
    }

    /**
     * Get date-wise pump expenses.
     */
    public function getPumpExpenses(Request $request)
    {
        try {
            $perPage = intval($request->get('per_page', 10));

            $query = DailySale::whereHas('pumpExpenses')
                ->with('pumpExpenses')
                ->orderBy('date', 'desc');

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

            $expensesPaginator = $query->paginate($perPage);

            $mappedItems = collect($expensesPaginator->items())->map(function ($dailySale) {
                $totalAmount = $dailySale->pumpExpenses->sum('amount');
                
                $remarks = $dailySale->pumpExpenses->map(function ($exp) {
                    $amtStr = number_format(floatval($exp->amount), 2);
                    $remark = $exp->remark ?: 'Unspecified';
                    return "{$remark} (₹{$amtStr})";
                })->implode(', ');

                return [
                    'id' => $dailySale->id,
                    'date' => $dailySale->date->format('d/m/Y'),
                    'amount' => floatval($totalAmount),
                    'remark' => $remarks
                ];
            });

            $paginatedExpenses = new \Illuminate\Pagination\LengthAwarePaginator(
                $mappedItems,
                $expensesPaginator->total(),
                $expensesPaginator->perPage(),
                $expensesPaginator->currentPage(),
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $sumQuery = \App\Models\DailySalePumpExpense::query();

            if ($request->has('start_date') && $request->start_date) {
                $startDate = $this->parseDate($request->start_date);
                if ($startDate) {
                    $sumQuery->whereHas('dailySale', function($q) use ($startDate) {
                        $q->where('date', '>=', $startDate);
                    });
                }
            }

            if ($request->has('end_date') && $request->end_date) {
                $endDate = $this->parseDate($request->end_date);
                if ($endDate) {
                    $sumQuery->whereHas('dailySale', function($q) use ($endDate) {
                        $q->where('date', '<=', $endDate);
                    });
                }
            }
            
            $grandTotal = $sumQuery->sum('amount');

            return response()->json([
                'success' => true,
                'pump_expenses' => $paginatedExpenses,
                'total_amount' => floatval($grandTotal)
            ]);
        } catch (\Exception $e) {
            Log::error('Pump Expenses Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pump expenses.'
            ], 500);
        }
    }

    /**
     * Export pump expenses to CSV.
     */
    public function exportPumpExpenses(Request $request)
    {
        try {
            $query = DailySalePumpExpense::with('dailySale')->orderByDesc(
                DailySale::select('date')->whereColumn('daily_sales.id', 'daily_sale_pump_expenses.daily_sale_id')
            );

            if ($request->has('start_date') && $request->start_date) {
                $startDate = $this->parseDate($request->start_date);
                if ($startDate) {
                    $query->whereHas('dailySale', function($q) use ($startDate) {
                        $q->where('date', '>=', $startDate);
                    });
                }
            }

            if ($request->has('end_date') && $request->end_date) {
                $endDate = $this->parseDate($request->end_date);
                if ($endDate) {
                    $query->whereHas('dailySale', function($q) use ($endDate) {
                        $q->where('date', '<=', $endDate);
                    });
                }
            }

            $expenses = $query->get();

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=pump_expenses.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $columns = [
                'Sl. No.', 
                'DATE', 
                'REMARK / PARTICULARS', 
                'AMOUNT'
            ];

            $callback = function() use($expenses, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                $slNo = 1;
                $totalAmount = 0;
                foreach ($expenses as $expense) {
                    $amt = floatval($expense->amount);
                    $totalAmount += $amt;
                    $row = [
                        $slNo++,
                        $expense->dailySale ? $expense->dailySale->date->format('d-m-Y') : '-',
                        $expense->remark ?: 'Unspecified Expense',
                        round($amt, 2)
                    ];
                    fputcsv($file, $row);
                }
                
                if ($slNo > 1) {
                    fputcsv($file, ['', '', 'TOTAL AMOUNT', round($totalAmount, 2)]);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Pump Expenses Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export pump expenses.'
            ], 500);
        }
    }

    /**
     * Export pump expenses to PDF.
     */
    public function exportPumpExpensesPdf(Request $request)
    {
        try {
            $query = DailySalePumpExpense::with('dailySale')->orderBy('daily_sale_id', 'asc');

            if ($request->has('start_date') && $request->start_date) {
                $startDate = $this->parseDate($request->start_date);
                if ($startDate) {
                    $query->whereHas('dailySale', function($q) use ($startDate) {
                        $q->where('date', '>=', $startDate);
                    });
                }
            }

            if ($request->has('end_date') && $request->end_date) {
                $endDate = $this->parseDate($request->end_date);
                if ($endDate) {
                    $query->whereHas('dailySale', function($q) use ($endDate) {
                        $q->where('date', '<=', $endDate);
                    });
                }
            }

            $expensesQuery = $query->get();

            $mappedItems = collect($expensesQuery)->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'date' => $expense->dailySale ? $expense->dailySale->date->format('d-m-Y') : '-',
                    'amount' => floatval($expense->amount),
                    'remark' => $expense->remark,
                ];
            });

            // Sort by actual date ascending since we mapped it
            $mappedItems = $mappedItems->sortBy(function($item) {
                return \Carbon\Carbon::parse($item['date'])->timestamp;
            })->values()->all();

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.pump_expenses_pdf', [
                'expenses' => $mappedItems,
                'startDate' => $request->start_date ?? null,
                'endDate' => $request->end_date ?? null
            ]);

            return $pdf->download('pump_expenses.pdf');
        } catch (\Exception $e) {
            Log::error('Pump Expenses PDF Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF report.'
            ], 500);
        }
    }

    /**
     * Get cash balance report (Coins, Missing, Balance in Pump).
     */
    public function getCashBalance(Request $request)
    {
        try {
            $perPage = intval($request->get('per_page', 10));
            $query = DailySale::query();

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

            // Get totals before paginating
            $totalsQuery = clone $query;
            $totals = [
                'total_coin' => round($totalsQuery->sum('coin'), 2),
                'total_miss' => round($totalsQuery->sum('miss'), 2),
                'total_balance_in_pump' => round($totalsQuery->sum('balance_in_pump'), 2),
            ];

            // Get paginated data
            $paginator = $query->orderBy('date', 'desc')->paginate($perPage);

            $mappedItems = collect($paginator->items())->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'date' => $sale->date ? $sale->date->format('Y-m-d') : '-',
                    'coin' => floatval($sale->coin),
                    'miss' => floatval($sale->miss),
                    'balance_in_pump' => floatval($sale->balance_in_pump)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'current_page' => $paginator->currentPage(),
                    'data' => $mappedItems,
                    'first_page_url' => $paginator->url(1),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'last_page_url' => $paginator->url($paginator->lastPage()),
                    'next_page_url' => $paginator->nextPageUrl(),
                    'path' => $paginator->path(),
                    'per_page' => $paginator->perPage(),
                    'prev_page_url' => $paginator->previousPageUrl(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
                'totals' => $totals
            ]);
        } catch (\Exception $e) {
            Log::error('Get Cash Balance Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cash balance report.'
            ], 500);
        }
    }

    /**
     * Store a new day's readings and compute sales.
     */
    public function store(Request $request)
    {
        try {
            // Pre-process arrays to handle stringified JSON or singular names
            $arrayMap = [
                'credit_payment' => 'credit_payments',
                'credit_given' => 'credit_givens',
                'online_payment' => 'online_payments',
                'staff_payout' => 'staff_payouts',
                'pump_expense' => 'pump_expenses',
                'reading' => 'readings',
                'lubricant' => 'lubricants'
            ];
            
            foreach ($arrayMap as $singular => $plural) {
                if ($request->has($singular) && !$request->has($plural)) {
                    $request->merge([$plural => $request->$singular]);
                }
            }

            $arrayFields = ['credit_payments', 'credit_givens', 'online_payments', 'staff_payouts', 'pump_expenses', 'readings', 'lubricants'];
            foreach ($arrayFields as $field) {
                if ($request->has($field) && is_string($request->$field)) {
                    $decoded = json_decode($request->$field, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $request->merge([$field => $decoded]);
                    }
                }
            }

            if ($request->has('lubricants') && is_array($request->lubricants)) {
                $filteredLubricants = array_values(array_filter($request->lubricants, function($l) {
                    return !empty($l['lubricant_id']);
                }));
                $request->merge(['lubricants' => $filteredLubricants]);
            }

            $request->validate([
                'date' => 'required|date|unique:daily_sales,date',
                'rate_ms' => 'required|numeric|min:0',
                'rate_hsd' => 'required|numeric|min:0',
                'rate_xp' => 'required|numeric|min:0',
                'lubricant_sale' => 'nullable|numeric|min:0',
                'density' => 'nullable|numeric|min:0',
                
                // Array Reconciliation
                'credit_payments' => 'nullable|array',
                'credit_payments.*.creditor_id' => 'required|exists:creditors,id',
                'credit_payments.*.amount' => 'required|numeric|min:0',

                'credit_givens' => 'nullable|array',
                'credit_givens.*.creditor_id' => 'required|exists:creditors,id',
                'credit_givens.*.amount' => 'required|numeric|min:0',
                'credit_givens.*.remark' => 'nullable|string|max:255',

                'online_payments' => 'nullable|array',
                'online_payments.*.name' => 'nullable|string|max:255',
                'online_payments.*.amount' => 'required|numeric|min:0',

                'staff_payouts' => 'nullable|array',
                'staff_payouts.*.name' => 'nullable|string|max:255',
                'staff_payouts.*.user_id' => 'nullable|exists:users,id',
                'staff_payouts.*.amount' => 'required|numeric|min:0',
                'staff_payouts.*.salary_month' => 'nullable|integer|min:1|max:12',
                'staff_payouts.*.salary_year' => 'nullable|integer|min:2020|max:2100',

                'pump_expenses' => 'nullable|array',
                'pump_expenses.*.amount' => 'required|numeric|min:0',
                'pump_expenses.*.remark' => 'nullable|string|max:255',

                'ac_transfer' => 'nullable|numeric|min:0',
                'expense' => 'nullable|numeric|min:0',
                'owner_1' => 'nullable|numeric|min:0',
                'owner_2' => 'nullable|numeric|min:0',
                'cash_collection' => 'nullable|numeric|min:0',
                'coin' => 'nullable|numeric|min:0',
                'miss' => 'nullable|numeric|min:0',
                'balance_in_pump' => 'nullable|numeric|min:0',

                'readings' => 'required|array',
                'readings.*.nozzle_id' => 'required|exists:nozzles,id',
                'readings.*.closing_reading' => 'required|numeric|min:0',
                'readings.*.testing_qty' => 'nullable|numeric|min:0',

                'lubricants' => 'nullable|array',
                'lubricants.*.lubricant_id' => 'required|exists:lubricants,id',
                'lubricants.*.quantity' => 'required|integer|min:1',
                'lubricants.*.price' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            $lubricantSaleTotal = 0.00;
            if ($request->has('lubricants') && is_array($request->lubricants)) {
                foreach ($request->lubricants as $lubricantItem) {
                    $lubricantSaleTotal += floatval($lubricantItem['quantity']) * floatval($lubricantItem['price']);
                }
            } else {
                $lubricantSaleTotal = $request->lubricant_sale ?? 0.00;
            }

            // Create main record (we will no longer use the old scalar fields for these, leaving as 0)
            $dailySale = DailySale::create([
                'date' => $request->date,
                'rate_ms' => $request->rate_ms,
                'rate_hsd' => $request->rate_hsd,
                'rate_xp' => $request->rate_xp,
                'lubricant_sale' => $lubricantSaleTotal,
                'density' => $request->density ?? 0.00,
                'credit_payment_received' => 0.00,
                'ac_transfer' => $request->ac_transfer ?? 0.00,
                'online' => 0.00,
                'online_creditor_name' => null,
                'creditor' => 0.00,
                'expense' => 0.00,
                'staff' => 0.00,
                'owner_1' => $request->owner_1 ?? 0.00,
                'owner_2' => $request->owner_2 ?? 0.00,
                'cash_collection' => $request->cash_collection ?? 0.00,
                'coin' => $request->coin ?? 0.00,
                'miss' => $request->miss ?? 0.00,
                'balance_in_pump' => $request->balance_in_pump ?? 0.00,
            ]);

            // Save Credit Payments
            if ($request->has('credit_payments') && is_array($request->credit_payments)) {
                foreach ($request->credit_payments as $payment) {
                    $amt = floatval($payment['amount']);
                    if ($amt > 0) {
                        DailySaleCreditPayment::create([
                            'daily_sale_id' => $dailySale->id,
                            'creditor_id' => $payment['creditor_id'],
                            'amount' => $amt,
                        ]);
                        CreditorTransaction::create([
                            'creditor_id' => $payment['creditor_id'],
                            'date' => $request->date,
                            'credit_amount' => 0.00,
                            'payment_amount' => $amt,
                            'remark' => 'Daily Sale Credit Received',
                        ]);
                    }
                }
            }

            // Save Credit Givens
            if ($request->has('credit_givens') && is_array($request->credit_givens)) {
                foreach ($request->credit_givens as $given) {
                    $amt = floatval($given['amount']);
                    if ($amt > 0) {
                        $remark = isset($given['remark']) && $given['remark'] ? trim($given['remark']) : null;
                        DailySaleCreditGiven::create([
                            'daily_sale_id' => $dailySale->id,
                            'creditor_id' => $given['creditor_id'],
                            'amount' => $amt,
                            'remark' => $remark,
                        ]);
                        CreditorTransaction::create([
                            'creditor_id' => $given['creditor_id'],
                            'date' => $request->date,
                            'credit_amount' => $amt,
                            'payment_amount' => 0.00,
                            'remark' => $remark ? 'Daily Sale Credit Given - ' . $remark : 'Daily Sale Credit Given',
                        ]);
                    }
                }
            }

            // Save Online Payments
            if ($request->has('online_payments') && is_array($request->online_payments)) {
                foreach ($request->online_payments as $op) {
                    $amt = floatval($op['amount']);
                    if ($amt > 0) {
                        DailySaleOnlinePayment::create([
                            'daily_sale_id' => $dailySale->id,
                            'name' => $op['name'] ?? null,
                            'amount' => $amt,
                        ]);
                    }
                }
            }

            // Save Staff Payouts
            if ($request->has('staff_payouts') && is_array($request->staff_payouts)) {
                $saleDate = \Carbon\Carbon::parse($request->date);
                foreach ($request->staff_payouts as $sp) {
                    $amt = floatval($sp['amount']);
                    if ($amt > 0) {
                        DailySaleStaffPayout::create([
                            'daily_sale_id' => $dailySale->id,
                            'name' => $sp['name'] ?? null,
                            'user_id' => $sp['user_id'] ?? null,
                            'amount' => $amt,
                            'salary_month' => isset($sp['salary_month']) ? intval($sp['salary_month']) : $saleDate->month,
                            'salary_year'  => isset($sp['salary_year'])  ? intval($sp['salary_year'])  : $saleDate->year,
                        ]);
                    }
                }
            }

            // Save Pump Expenses
            if ($request->has('pump_expenses') && is_array($request->pump_expenses)) {
                foreach ($request->pump_expenses as $pe) {
                    $amt = floatval($pe['amount']);
                    if ($amt > 0) {
                        DailySalePumpExpense::create([
                            'daily_sale_id' => $dailySale->id,
                            'amount' => $amt,
                            'remark' => $pe['remark'] ?? null,
                        ]);
                    }
                }
            }

            if ($request->has('lubricants') && is_array($request->lubricants)) {
                foreach ($request->lubricants as $lubricantItem) {
                    $qty = intval($lubricantItem['quantity']);
                    $price = floatval($lubricantItem['price']);

                    DailySaleLubricant::create([
                        'daily_sale_id' => $dailySale->id,
                        'lubricant_id' => $lubricantItem['lubricant_id'],
                        'quantity' => $qty,
                        'price' => $price,
                        'amount' => $qty * $price,
                    ]);

                    $lubricant = Lubricant::findOrFail($lubricantItem['lubricant_id']);
                    $lubricant->decrement('stock', $qty);
                }
            }

            foreach ($request->readings as $item) {
                $nozzleId = $item['nozzle_id'];
                $closingReading = floatval($item['closing_reading']);

                $nozzle = Nozzle::findOrFail($nozzleId);
                $prevReadingVal = $this->getPreviousClosingReading($nozzleId, $request->date);

                $saleQty = $closingReading - $prevReadingVal;
                
                $rate = 0.00;
                if ($nozzle->fuel_type === 'MS') {
                    $rate = floatval($request->rate_ms);
                } elseif ($nozzle->fuel_type === 'HSD') {
                    $rate = floatval($request->rate_hsd);
                } elseif ($nozzle->fuel_type === 'XP') {
                    $rate = floatval($request->rate_xp);
                }

                $amount = $saleQty * $rate;

                NozzleReading::create([
                    'daily_sale_id' => $dailySale->id,
                    'nozzle_id' => $nozzleId,
                    'closing_reading' => $closingReading,
                    'testing_qty' => isset($item['testing_qty']) ? floatval($item['testing_qty']) : 5.00,
                    'sale_qty' => $saleQty,
                    'amount' => $amount,
                ]);
            }

            DB::commit();

            // Perform cascade recalculation on subsequent entries if needed
            foreach ($request->readings as $item) {
                $this->recalculateSubsequentReadings($item['nozzle_id'], $request->date);
            }

            return response()->json([
                'success' => true,
                'message' => 'Daily sale entry created successfully',
                'daily_sale' => $this->getFullDailyReport($dailySale->id)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Daily Sale Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the daily sale.'
            ], 500);
        }
    }

    /**
     * Show a detailed daily report matching the exact Excel grid structure.
     */
    public function show($id)
    {
        try {
            $report = $this->getFullDailyReport($id);
            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily sale record not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'report' => $report
            ]);
        } catch (\Exception $e) {
            Log::error('Daily Sale Show Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily sale record.'
            ], 500);
        }
    }

    /**
     * Update rates or closing readings for a specific day. (Super Admin Only)
     */
    public function update(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can edit daily sales.'
                ], 403);
            }

            $dailySale = DailySale::findOrFail($id);

            // Pre-process arrays to handle stringified JSON or singular names
            $arrayMap = [
                'credit_payment' => 'credit_payments',
                'credit_given' => 'credit_givens',
                'online_payment' => 'online_payments',
                'staff_payout' => 'staff_payouts',
                'pump_expense' => 'pump_expenses',
                'reading' => 'readings',
                'lubricant' => 'lubricants'
            ];
            
            foreach ($arrayMap as $singular => $plural) {
                if ($request->has($singular) && !$request->has($plural)) {
                    $request->merge([$plural => $request->$singular]);
                }
            }

            $arrayFields = ['credit_payments', 'credit_givens', 'online_payments', 'staff_payouts', 'pump_expenses', 'readings', 'lubricants'];
            foreach ($arrayFields as $field) {
                if ($request->has($field) && is_string($request->$field)) {
                    $decoded = json_decode($request->$field, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $request->merge([$field => $decoded]);
                    }
                }
            }

            if ($request->has('lubricants') && is_array($request->lubricants)) {
                $filteredLubricants = array_values(array_filter($request->lubricants, function($l) {
                    return !empty($l['lubricant_id']);
                }));
                $request->merge(['lubricants' => $filteredLubricants]);
            }

            $request->validate([
                'date' => 'required|date|unique:daily_sales,date,' . $id,
                'rate_ms' => 'required|numeric|min:0',
                'rate_hsd' => 'required|numeric|min:0',
                'rate_xp' => 'required|numeric|min:0',
                'lubricant_sale' => 'nullable|numeric|min:0',
                'density' => 'nullable|numeric|min:0',

                // Array Reconciliation
                'credit_payments' => 'nullable|array',
                'credit_payments.*.creditor_id' => 'required|exists:creditors,id',
                'credit_payments.*.amount' => 'required|numeric|min:0',

                'credit_givens' => 'nullable|array',
                'credit_givens.*.creditor_id' => 'required|exists:creditors,id',
                'credit_givens.*.amount' => 'required|numeric|min:0',
                'credit_givens.*.remark' => 'nullable|string|max:255',

                'online_payments' => 'nullable|array',
                'online_payments.*.name' => 'nullable|string|max:255',
                'online_payments.*.amount' => 'required|numeric|min:0',

                'staff_payouts' => 'nullable|array',
                'staff_payouts.*.name' => 'nullable|string|max:255',
                'staff_payouts.*.user_id' => 'nullable|exists:users,id',
                'staff_payouts.*.amount' => 'required|numeric|min:0',
                'staff_payouts.*.salary_month' => 'nullable|integer|min:1|max:12',
                'staff_payouts.*.salary_year' => 'nullable|integer|min:2020|max:2100',

                'pump_expenses' => 'nullable|array',
                'pump_expenses.*.amount' => 'required|numeric|min:0',
                'pump_expenses.*.remark' => 'nullable|string|max:255',

                'ac_transfer' => 'nullable|numeric|min:0',
                'expense' => 'nullable|numeric|min:0',
                'owner_1' => 'nullable|numeric|min:0',
                'owner_2' => 'nullable|numeric|min:0',
                'cash_collection' => 'nullable|numeric|min:0',
                'coin' => 'nullable|numeric|min:0',
                'miss' => 'nullable|numeric|min:0',
                'balance_in_pump' => 'nullable|numeric|min:0',

                'readings' => 'required|array',
                'readings.*.nozzle_id' => 'required|exists:nozzles,id',
                'readings.*.closing_reading' => 'required|numeric|min:0',
                'readings.*.testing_qty' => 'nullable|numeric|min:0',

                'lubricants' => 'nullable|array',
                'lubricants.*.lubricant_id' => 'required|exists:lubricants,id',
                'lubricants.*.quantity' => 'required|integer|min:1',
                'lubricants.*.price' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            $lubricantSaleTotal = 0.00;
            if ($request->has('lubricants') && is_array($request->lubricants)) {
                // Revert previous lubricant stocks
                $existingLubricantSales = DailySaleLubricant::where('daily_sale_id', $dailySale->id)->get();
                foreach ($existingLubricantSales as $existingSale) {
                    $lubricant = Lubricant::find($existingSale->lubricant_id);
                    if ($lubricant) {
                        $lubricant->increment('stock', $existingSale->quantity);
                    }
                }
                DailySaleLubricant::where('daily_sale_id', $dailySale->id)->delete();

                // Compute new total
                $lubricantSaleTotal = 0.00;
                if (is_array($request->lubricants)) {
                    foreach ($request->lubricants as $lubricantItem) {
                        $lubricantSaleTotal += floatval($lubricantItem['quantity']) * floatval($lubricantItem['price']);
                    }
                }

                // Create new entries and decrement stock
                if (is_array($request->lubricants)) {
                    foreach ($request->lubricants as $lubricantItem) {
                        $qty = intval($lubricantItem['quantity']);
                        $price = floatval($lubricantItem['price']);

                        DailySaleLubricant::create([
                            'daily_sale_id' => $dailySale->id,
                            'lubricant_id' => $lubricantItem['lubricant_id'],
                            'quantity' => $qty,
                            'price' => $price,
                            'amount' => $qty * $price,
                        ]);

                        $lubricant = Lubricant::findOrFail($lubricantItem['lubricant_id']);
                        $lubricant->decrement('stock', $qty);
                    }
                }
            } else if ($request->has('lubricant_sale')) {
                $lubricantSaleTotal = floatval($request->lubricant_sale);
            }

            $dailySale->update([
                'date' => $request->date,
                'rate_ms' => $request->rate_ms,
                'rate_hsd' => $request->rate_hsd,
                'rate_xp' => $request->rate_xp,
                'lubricant_sale' => $lubricantSaleTotal,
                'density' => $request->density ?? 0.00,
                'ac_transfer' => $request->ac_transfer ?? 0.00,
                'expense' => 0.00,
                'owner_1' => $request->owner_1 ?? 0.00,
                'owner_2' => $request->owner_2 ?? 0.00,
                'cash_collection' => $request->cash_collection ?? 0.00,
                'coin' => $request->coin ?? 0.00,
                'miss' => $request->miss ?? 0.00,
                'balance_in_pump' => $request->balance_in_pump ?? 0.00,
            ]);

            // Clean up previous array rows
            DailySaleCreditPayment::where('daily_sale_id', $dailySale->id)->delete();
            DailySaleCreditGiven::where('daily_sale_id', $dailySale->id)->delete();
            DailySaleOnlinePayment::where('daily_sale_id', $dailySale->id)->delete();
            DailySaleStaffPayout::where('daily_sale_id', $dailySale->id)->delete();
            DailySalePumpExpense::where('daily_sale_id', $dailySale->id)->delete();

            // Clean up previous creditor transactions for this daily sale
            CreditorTransaction::where('date', $request->date)
                ->where(function ($query) {
                    $query->where('remark', 'like', 'Daily Sale Credit Given%')
                          ->orWhere('remark', 'like', 'Daily Sale Credit Received%');
                })
                ->delete();

            // Save Credit Payments
            if ($request->has('credit_payments') && is_array($request->credit_payments)) {
                foreach ($request->credit_payments as $payment) {
                    $amt = floatval($payment['amount']);
                    if ($amt > 0) {
                        DailySaleCreditPayment::create([
                            'daily_sale_id' => $dailySale->id,
                            'creditor_id' => $payment['creditor_id'],
                            'amount' => $amt,
                        ]);
                        CreditorTransaction::create([
                            'creditor_id' => $payment['creditor_id'],
                            'date' => $request->date,
                            'credit_amount' => 0.00,
                            'payment_amount' => $amt,
                            'remark' => 'Daily Sale Credit Received',
                        ]);
                    }
                }
            }

            // Save Credit Givens
            if ($request->has('credit_givens') && is_array($request->credit_givens)) {
                foreach ($request->credit_givens as $given) {
                    $amt = floatval($given['amount']);
                    if ($amt > 0) {
                        $remark = isset($given['remark']) && $given['remark'] ? trim($given['remark']) : null;
                        DailySaleCreditGiven::create([
                            'daily_sale_id' => $dailySale->id,
                            'creditor_id' => $given['creditor_id'],
                            'amount' => $amt,
                            'remark' => $remark,
                        ]);
                        CreditorTransaction::create([
                            'creditor_id' => $given['creditor_id'],
                            'date' => $request->date,
                            'credit_amount' => $amt,
                            'payment_amount' => 0.00,
                            'remark' => $remark ? 'Daily Sale Credit Given - ' . $remark : 'Daily Sale Credit Given',
                        ]);
                    }
                }
            }

            // Save Online Payments
            if ($request->has('online_payments') && is_array($request->online_payments)) {
                foreach ($request->online_payments as $op) {
                    $amt = floatval($op['amount']);
                    if ($amt > 0) {
                        DailySaleOnlinePayment::create([
                            'daily_sale_id' => $dailySale->id,
                            'name' => $op['name'] ?? null,
                            'amount' => $amt,
                        ]);
                    }
                }
            }

            // Save Staff Payouts
            if ($request->has('staff_payouts') && is_array($request->staff_payouts)) {
                $saleDate = \Carbon\Carbon::parse($request->date);
                foreach ($request->staff_payouts as $sp) {
                    $amt = floatval($sp['amount']);
                    if ($amt > 0) {
                        DailySaleStaffPayout::create([
                            'daily_sale_id' => $dailySale->id,
                            'name' => $sp['name'] ?? null,
                            'user_id' => $sp['user_id'] ?? null,
                            'amount' => $amt,
                            'salary_month' => isset($sp['salary_month']) ? intval($sp['salary_month']) : $saleDate->month,
                            'salary_year'  => isset($sp['salary_year'])  ? intval($sp['salary_year'])  : $saleDate->year,
                        ]);
                    }
                }
            }

            // Save Pump Expenses
            if ($request->has('pump_expenses') && is_array($request->pump_expenses)) {
                foreach ($request->pump_expenses as $pe) {
                    $amt = floatval($pe['amount']);
                    if ($amt > 0) {
                        DailySalePumpExpense::create([
                            'daily_sale_id' => $dailySale->id,
                            'amount' => $amt,
                            'remark' => $pe['remark'] ?? null,
                        ]);
                    }
                }
            }

            // Save/re-calculate current readings
            foreach ($request->readings as $item) {
                $nozzleId = $item['nozzle_id'];
                $closingReading = floatval($item['closing_reading']);

                $nozzle = Nozzle::findOrFail($nozzleId);
                $prevReadingVal = $this->getPreviousClosingReading($nozzleId, $request->date);

                $saleQty = $closingReading - $prevReadingVal;
                
                $rate = 0.00;
                if ($nozzle->fuel_type === 'MS') {
                    $rate = floatval($request->rate_ms);
                } elseif ($nozzle->fuel_type === 'HSD') {
                    $rate = floatval($request->rate_hsd);
                } elseif ($nozzle->fuel_type === 'XP') {
                    $rate = floatval($request->rate_xp);
                }

                $amount = $saleQty * $rate;

                NozzleReading::updateOrCreate(
                    ['daily_sale_id' => $dailySale->id, 'nozzle_id' => $nozzleId],
                    [
                        'closing_reading' => $closingReading, 
                        'testing_qty' => isset($item['testing_qty']) ? floatval($item['testing_qty']) : 5.00,
                        'sale_qty' => $saleQty, 
                        'amount' => $amount
                    ]
                );
            }

            DB::commit();

            // Cascade recalculate subsequent dates since this reading changed
            foreach ($request->readings as $item) {
                $this->recalculateSubsequentReadings($item['nozzle_id'], $request->date);
            }

            return response()->json([
                'success' => true,
                'message' => 'Daily sale entry updated successfully',
                'daily_sale' => $this->getFullDailyReport($dailySale->id)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Daily sale record not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Daily Sale Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the daily sale.'
            ], 500);
        }
    }

    /**
     * Delete a daily sale record. (Super Admin Only)
     */
    public function destroy(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can delete daily sales.'
                ], 403);
            }

            $dailySale = DailySale::findOrFail($id);
            
            // Collect nozzle readings to trigger cascade update for subsequent days
            $readings = $dailySale->nozzleReadings;
            $date = $dailySale->date->toDateString();

            DB::beginTransaction();
            // Clean up creditor transactions for this daily sale
            CreditorTransaction::where('date', $date)
                ->where(function ($query) {
                    $query->where('remark', 'like', 'Daily Sale Credit Given%')
                          ->orWhere('remark', 'like', 'Daily Sale Credit Received%');
                })
                ->delete();

            // Revert lubricant stock changes before delete
            $existingLubricantSales = DailySaleLubricant::where('daily_sale_id', $dailySale->id)->get();
            foreach ($existingLubricantSales as $existingSale) {
                $lubricant = Lubricant::find($existingSale->lubricant_id);
                if ($lubricant) {
                    $lubricant->increment('stock', $existingSale->quantity);
                }
            }
            $dailySale->delete(); // Cascade deletes readings due to migration constraints
            DB::commit();

            // Since we deleted these readings, subsequent days must now pull from prior closing readings.
            foreach ($readings as $reading) {
                $this->recalculateSubsequentReadings($reading->nozzle_id, $date);
            }

            return response()->json([
                'success' => true,
                'message' => 'Daily sale record deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Daily sale record not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Daily Sale Destroy Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the daily sale.'
            ], 500);
        }
    }

    /**
     * Helper: Builds the full grid report for a daily sale.
     */
    private function getFullDailyReport($id)
    {
        $sale = DailySale::with(['nozzleReadings.nozzle', 'lubricants.lubricant', 'creditPayments.creditor', 'creditGivens.creditor', 'onlinePayments', 'staffPayouts', 'pumpExpenses'])->find($id);
        if (!$sale) return null;

        $nozzleDetails = [];
        $fuelSummaries = [
            'MS' => ['nozzles' => [], 'total_qty' => 0.00, 'rate' => floatval($sale->rate_ms), 'amount' => 0.00],
            'HSD' => ['nozzles' => [], 'total_qty' => 0.00, 'rate' => floatval($sale->rate_hsd), 'amount' => 0.00],
            'XP' => ['nozzles' => [], 'total_qty' => 0.00, 'rate' => floatval($sale->rate_xp), 'amount' => 0.00],
        ];

        foreach ($sale->nozzleReadings as $reading) {
            $fuelType = $reading->nozzle->fuel_type;
            $prevClosing = $this->getPreviousClosingReading($reading->nozzle_id, $sale->date->toDateString());
            $closing = floatval($reading->closing_reading);
            
            $testingQty = floatval($reading->testing_qty ?? 5.00);
            $grossQty = floatval($reading->sale_qty);
            $netQty = $grossQty - $testingQty;
            
            // Get rate
            $rate = 0.00;
            if ($fuelType === 'MS') $rate = floatval($sale->rate_ms);
            elseif ($fuelType === 'HSD') $rate = floatval($sale->rate_hsd);
            elseif ($fuelType === 'XP') $rate = floatval($sale->rate_xp);
            
            $netAmt = $netQty * $rate;

            $nozzleInfo = [
                'nozzle_id' => $reading->nozzle_id,
                'nozzle_no' => $reading->nozzle->nozzle_no,
                'fuel_type' => $fuelType,
                'previous_closing_reading' => round($prevClosing, 2),
                'closing_reading' => round($closing, 2),
                'testing_qty' => $testingQty,
                'sale_qty' => round($netQty, 2),
                'amount' => round($netAmt, 2),
            ];

            $nozzleDetails[] = $nozzleInfo;

            // Add to summaries (Keep gross for proper final testing subtractions)
            if (isset($fuelSummaries[$fuelType])) {
                $fuelSummaries[$fuelType]['nozzles'][] = $nozzleInfo;
                $fuelSummaries[$fuelType]['total_qty'] += $grossQty;
                $fuelSummaries[$fuelType]['amount'] += floatval($reading->amount);
            }
        }

        // Finalize summaries: amount is total quantity * rate
        foreach ($fuelSummaries as $type => &$summary) {
            $summary['total_qty'] = round($summary['total_qty'], 2);
            $summary['amount'] = round($summary['total_qty'] * $summary['rate'], 2);
        }

        $totalFuelAmount = $fuelSummaries['MS']['amount'] + $fuelSummaries['HSD']['amount'] + $fuelSummaries['XP']['amount'];
        $totalFuelQty = $fuelSummaries['MS']['total_qty'] + $fuelSummaries['HSD']['total_qty'] + $fuelSummaries['XP']['total_qty'];
        $lubricantSale = floatval($sale->lubricant_sale);
        $grandTotalAmount = $totalFuelAmount + $lubricantSale;

        // Dynamic Testing calculations based on nozzle readings
        $testingAmtMs = 0; $testingAmtHsd = 0; $testingAmtXp = 0;
        $msCount = 0; $hsdCount = 0; $xpCount = 0;
        $testingQtyMs = 0; $testingQtyHsd = 0; $testingQtyXp = 0;

        foreach ($sale->nozzleReadings as $reading) {
            $qty = floatval($reading->testing_qty ?? 5.00);
            if ($reading->nozzle->fuel_type === 'MS') {
                $testingAmtMs += $qty * floatval($sale->rate_ms);
                $msCount++;
                $testingQtyMs += $qty;
            } elseif ($reading->nozzle->fuel_type === 'HSD') {
                $testingAmtHsd += $qty * floatval($sale->rate_hsd);
                $hsdCount++;
                $testingQtyHsd += $qty;
            } elseif ($reading->nozzle->fuel_type === 'XP') {
                $testingAmtXp += $qty * floatval($sale->rate_xp);
                $xpCount++;
                $testingQtyXp += $qty;
            }
        }

        $density = floatval($sale->density);
        $totalTestingAmount = round($testingAmtMs + $testingAmtHsd + $testingAmtXp + $density, 2);

        // Final sale amount = Grand Total Sale - Total Testing Amount
        $finalSaleAmount = round($grandTotalAmount - $totalTestingAmount, 2);

        // Cash reconciliation with arrays
        $creditPaymentReceived = floatval($sale->creditPayments->sum('amount') ?: $sale->credit_payment_received);
        $totalAmount = round($finalSaleAmount + $creditPaymentReceived, 2);

        $acTransfer = floatval($sale->ac_transfer);
        $online = floatval($sale->onlinePayments->sum('amount') ?: $sale->online);
        $creditor = floatval($sale->creditGivens->sum('amount') ?: $sale->creditor);
        $expenseVal = floatval($sale->pumpExpenses->sum('amount') ?: $sale->expense);
        $staff = floatval($sale->staffPayouts->sum('amount') ?: $sale->staff);
        $owner1 = floatval($sale->owner_1);
        $owner2 = floatval($sale->owner_2);

        // Cash to be collected = Total Amount - Credit Payment Received - A/C Transfer - Online - Creditor - Expense - Staff - Owner 1 - Owner 2
        $cashToBeCollected = round($totalAmount - $creditPaymentReceived - $acTransfer - $online - $creditor - $expenseVal - $staff - $owner1 - $owner2, 2);

        // Expected balance (Check column) = Previous closing balance + Cash to be collected - Cash Collection - Coin - Miss
        $prevPumpBalance = $this->getPreviousPumpBalance($sale->date->toDateString());
        $cashCollection = floatval($sale->cash_collection);
        $coin = floatval($sale->coin);
        $miss = floatval($sale->miss);
        
        $expectedBalance = round($prevPumpBalance + $cashToBeCollected - $cashCollection - $coin - $miss, 2);

        // Error = Actual Closing Balance in Pump - Expected Balance
        $actualBalanceInPump = floatval($sale->balance_in_pump);
        $errorVal = round($actualBalanceInPump - $expectedBalance, 2);

        usort($nozzleDetails, function($a, $b) {
            $orderMap = ['5' => 1, '3' => 2, '4' => 3, '6' => 4, '1' => 5, '2' => 6];
            
            preg_match('/\d+/', $a['nozzle_no'], $matchesA);
            $numA = $matchesA[0] ?? $a['nozzle_no'];
            
            preg_match('/\d+/', $b['nozzle_no'], $matchesB);
            $numB = $matchesB[0] ?? $b['nozzle_no'];

            $orderA = $orderMap[$numA] ?? 99;
            $orderB = $orderMap[$numB] ?? 99;

            return $orderA <=> $orderB;
        });

        $lubricantsList = $sale->lubricants->map(function ($item) {
            return [
                'id' => $item->id,
                'lubricant_id' => $item->lubricant_id,
                'quantity' => intval($item->quantity),
                'price' => floatval($item->price),
                'amount' => floatval($item->amount),
                'lubricant' => $item->lubricant ? [
                    'id' => $item->lubricant->id,
                    'name' => $item->lubricant->name,
                    'qty_name' => $item->lubricant->qty_name,
                ] : null
            ];
        })->toArray();
        
        $creditPaymentsList = $sale->creditPayments->map(function ($item) {
            return [
                'id' => $item->id,
                'creditor_id' => $item->creditor_id,
                'amount' => floatval($item->amount),
                'creditor' => $item->creditor ? [
                    'id' => $item->creditor->id,
                    'name' => $item->creditor->name,
                ] : null
            ];
        })->toArray();
        
        $creditGivensList = $sale->creditGivens->map(function ($item) {
            return [
                'id' => $item->id,
                'creditor_id' => $item->creditor_id,
                'amount' => floatval($item->amount),
                'remark' => $item->remark,
                'creditor' => $item->creditor ? [
                    'id' => $item->creditor->id,
                    'name' => $item->creditor->name,
                ] : null
            ];
        })->toArray();
        
        $onlinePaymentsList = $sale->onlinePayments->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'amount' => floatval($item->amount),
            ];
        })->toArray();
        
        $staffPayoutsList = [];
        foreach ($sale->staffPayouts as $sp) {
            $staffPayoutsList[] = [
                'id' => $sp->id,
                'name' => $sp->name,
                'user_id' => $sp->user_id,
                'amount' => floatval($sp->amount),
                'salary_month' => $sp->salary_month,
                'salary_year' => $sp->salary_year,
            ];
        }

        $pumpExpensesList = $sale->pumpExpenses->map(function ($item) {
            return [
                'id' => $item->id,
                'amount' => floatval($item->amount),
                'remark' => $item->remark,
            ];
        })->toArray();

        return [
            'id' => $sale->id,
            'date' => $sale->date->format('d/m/Y'),
            'lubricants' => $lubricantsList,
            'rates' => [
                'MS' => floatval($sale->rate_ms),
                'HSD' => floatval($sale->rate_hsd),
                'XP' => floatval($sale->rate_xp),
            ],
            'lubricant_sale' => round($lubricantSale, 2),
            'density' => round($density, 2),
            'nozzle_readings' => $nozzleDetails,
            'fuel_summaries' => $fuelSummaries,
            'testing' => [
                'ms' => [
                    'nozzle_count' => $msCount,
                    'testing_qty' => $testingQtyMs,
                    'rate' => floatval($sale->rate_ms),
                    'amount' => $testingAmtMs
                ],
                'hsd' => [
                    'nozzle_count' => $hsdCount,
                    'testing_qty' => $testingQtyHsd,
                    'rate' => floatval($sale->rate_hsd),
                    'amount' => $testingAmtHsd
                ],
                'xp' => [
                    'nozzle_count' => $xpCount,
                    'testing_qty' => $testingQtyXp,
                    'rate' => floatval($sale->rate_xp),
                    'amount' => $testingAmtXp
                ],
                'density' => round($density, 2),
                'total_testing_amount' => $totalTestingAmount
            ],
            'totals' => [
                'total_fuel_qty' => round($totalFuelQty, 2),
                'ms_qty' => $fuelSummaries['MS']['total_qty'],
                'hsd_qty' => $fuelSummaries['HSD']['total_qty'],
                'xp_qty' => $fuelSummaries['XP']['total_qty'],
                'total_fuel_amount' => round($totalFuelAmount, 2),
                'lubricant_sale' => round($lubricantSale, 2),
                'grand_total_sale_amount' => round($grandTotalAmount, 2),
                'total_testing_amount' => $totalTestingAmount,
                'final_sale_amount' => $finalSaleAmount
            ],
            'reconciliation' => [
                'credit_payments' => $creditPaymentsList,
                'credit_givens' => $creditGivensList,
                'online_payments' => $onlinePaymentsList,
                'staff_payouts' => $staffPayoutsList,
                'pump_expenses' => $pumpExpensesList,
                'credit_payment_received' => round($creditPaymentReceived, 2), // Legacy total field
                'total_amount' => round($totalAmount, 2),
                'ac_transfer' => round($acTransfer, 2),
                'online' => round($online, 2), // Legacy total field
                'creditor' => round($creditor, 2), // Legacy total field
                'expense' => round($expenseVal, 2),
                'staff' => round($staff, 2), // Legacy total field
                'owner_1' => round($owner1, 2),
                'owner_2' => round($owner2, 2),
                'cash_to_be_collected' => round($cashToBeCollected, 2),
                'previous_pump_balance' => round($prevPumpBalance, 2),
                'cash_collection' => round($cashCollection, 2),
                'coin' => round($coin, 2),
                'miss' => round($miss, 2),
                'expected_balance' => round($expectedBalance, 2),
                'actual_balance_in_pump' => round($actualBalanceInPump, 2),
                'error' => round($errorVal, 2),
            ]
        ];
    }

    /**
     * Export daily sales to CSV format matching the Excel Monthly Report grid.
     */
    public function export(Request $request)
    {
        try {
            $query = DailySale::query();

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

            $sales = $query->with(['nozzleReadings.nozzle'])
                ->orderBy('date', 'asc')
                ->get();

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=sales_report.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $columns = [
                'Sl. No.', 
                'DATE', 
                'MS SALE', 
                'AMOUNT', 
                'HSD SALE', 
                'AMOUNT', 
                'XP', 
                'AMOUNT', 
                'LUBRICANT'
            ];

            $callback = function() use($sales, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                $slNo = 1;
                foreach ($sales as $sale) {
                    $msQty = 0; $hsdQty = 0; $xpQty = 0;
                    $testingAmtMs = 0; $testingAmtHsd = 0; $testingAmtXp = 0;
                    $testingQtyMs = 0; $testingQtyHsd = 0; $testingQtyXp = 0;

                    foreach ($sale->nozzleReadings as $reading) {
                        $qty = floatval($reading->testing_qty ?? 5.00);
                        if ($reading->nozzle->fuel_type === 'MS') {
                            $msQty += floatval($reading->sale_qty);
                            $testingAmtMs += $qty * floatval($sale->rate_ms);
                            $testingQtyMs += $qty;
                        } elseif ($reading->nozzle->fuel_type === 'HSD') {
                            $hsdQty += floatval($reading->sale_qty);
                            $testingAmtHsd += $qty * floatval($sale->rate_hsd);
                            $testingQtyHsd += $qty;
                        } elseif ($reading->nozzle->fuel_type === 'XP') {
                            $xpQty += floatval($reading->sale_qty);
                            $testingAmtXp += $qty * floatval($sale->rate_xp);
                            $testingQtyXp += $qty;
                        }
                    }

                    // Net qty = gross qty - testing qty
                    $netMsQty = max(0, $msQty - $testingQtyMs);
                    $netHsdQty = max(0, $hsdQty - $testingQtyHsd);
                    $netXpQty = max(0, $xpQty - $testingQtyXp);

                    $msAmt = round($netMsQty * floatval($sale->rate_ms), 2);
                    $hsdAmt = round($netHsdQty * floatval($sale->rate_hsd), 2);
                    $xpAmt = round($netXpQty * floatval($sale->rate_xp), 2);

                    $row = [
                        $slNo++,
                        $sale->date->format('d-m-Y'),
                        round($netMsQty, 2),
                        $msAmt,
                        round($netHsdQty, 2),
                        $hsdAmt,
                        round($netXpQty, 2),
                        $xpAmt,
                        round(floatval($sale->lubricant_sale), 2)
                    ];

                    fputcsv($file, $row);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Daily Sale Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report.'
            ], 500);
        }
    }

    /**
     * Export daily sales to PDF format matching the CSV/Excel report grid.
     */
    public function exportPdf(Request $request)
    {
        try {
            $query = DailySale::query();

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

            $salesQuery = $query->with(['nozzleReadings.nozzle'])
                ->orderBy('date', 'asc')
                ->get();

            $sales = [];

            foreach ($salesQuery as $sale) {
                $msQty = 0; $hsdQty = 0; $xpQty = 0;
                $testingAmtMs = 0; $testingAmtHsd = 0; $testingAmtXp = 0;
                $testingQtyMs = 0; $testingQtyHsd = 0; $testingQtyXp = 0;

                foreach ($sale->nozzleReadings as $reading) {
                    $qty = floatval($reading->testing_qty ?? 5.00);
                    if ($reading->nozzle->fuel_type === 'MS') {
                        $msQty += floatval($reading->sale_qty);
                        $testingAmtMs += $qty * floatval($sale->rate_ms);
                        $testingQtyMs += $qty;
                    } elseif ($reading->nozzle->fuel_type === 'HSD') {
                        $hsdQty += floatval($reading->sale_qty);
                        $testingAmtHsd += $qty * floatval($sale->rate_hsd);
                        $testingQtyHsd += $qty;
                    } elseif ($reading->nozzle->fuel_type === 'XP') {
                        $xpQty += floatval($reading->sale_qty);
                        $testingAmtXp += $qty * floatval($sale->rate_xp);
                        $testingQtyXp += $qty;
                    }
                }

                // Net qty = gross qty - testing qty
                $netMsQty = max(0, $msQty - $testingQtyMs);
                $netHsdQty = max(0, $hsdQty - $testingQtyHsd);
                $netXpQty = max(0, $xpQty - $testingQtyXp);

                $msAmt = round($netMsQty * floatval($sale->rate_ms), 2);
                $hsdAmt = round($netHsdQty * floatval($sale->rate_hsd), 2);
                $xpAmt = round($netXpQty * floatval($sale->rate_xp), 2);

                $sales[] = [
                    'date' => $sale->date->format('d-m-Y'),
                    'ms_qty' => $netMsQty,
                    'ms_amt' => $msAmt,
                    'hsd_qty' => $netHsdQty,
                    'hsd_amt' => $hsdAmt,
                    'xp_qty' => $netXpQty,
                    'xp_amt' => $xpAmt,
                    'lubricant' => floatval($sale->lubricant_sale)
                ];
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.daily_sales_pdf', [
                'sales' => $sales,
                'startDate' => $request->start_date ?? null,
                'endDate' => $request->end_date ?? null
            ]);

            return $pdf->download('sales_report.pdf');
        } catch (\Exception $e) {
            Log::error('Daily Sale PDF Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF report.'
            ], 500);
        }
    }

    /**
     * Get owner payouts with date range filtering.
     */
    public function getOwnerPayouts(Request $request)
    {
        try {
            $query = DailySale::query();

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

            $query->where(function ($q) {
                $q->where('owner_1', '>', 0)
                  ->orWhere('owner_2', '>', 0);
            });

            // Need to compute totals across all matched records, not just paginated
            $totals = [
                'total_owner_1' => round($query->sum('owner_1'), 2),
                'total_owner_2' => round($query->sum('owner_2'), 2)
            ];

            $perPage = $request->input('per_page', 10);
            $payouts = $query->orderBy('date', 'desc')->paginate($perPage);

            // Transform payouts collection
            $payouts->getCollection()->transform(function ($sale) {
                return [
                    'id' => $sale->id,
                    'date' => $sale->date->format('Y-m-d'),
                    'owner_1' => floatval($sale->owner_1),
                    'owner_2' => floatval($sale->owner_2),
                ];
            });

            return response()->json([
                'success' => true,
                'payouts' => $payouts,
                'totals' => $totals
            ]);
        } catch (\Exception $e) {
            Log::error('Owner Payouts Fetch Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch owner payouts.'
            ], 500);
        }
    }

    public function exportOwnerPayouts(Request $request)
    {
        try {
            $query = DailySale::query();

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

            $query->where(function ($q) {
                $q->where('owner_1', '>', 0)
                  ->orWhere('owner_2', '>', 0);
            });

            $payouts = $query->orderBy('date', 'asc')->get();

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=owner_payouts.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use($payouts) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Sl. No.', 'DATE', 'OWNER 1 (Umesh)', 'OWNER 2 (Saket)', 'TOTAL']);

                $slNo = 1;
                $total1 = 0;
                $total2 = 0;
                foreach ($payouts as $p) {
                    $amt1 = floatval($p->owner_1);
                    $amt2 = floatval($p->owner_2);
                    $total1 += $amt1;
                    $total2 += $amt2;
                    
                    fputcsv($file, [
                        $slNo++,
                        $p->date->format('d-m-Y'),
                        $amt1 > 0 ? $amt1 : '',
                        $amt2 > 0 ? $amt2 : '',
                        $amt1 + $amt2
                    ]);
                }
                
                if ($slNo > 1) {
                    fputcsv($file, ['', 'TOTAL AMOUNT', $total1, $total2, $total1 + $total2]);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Owner Payouts Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export owner payouts.'
            ], 500);
        }
    }

    public function exportOwnerPayoutsPdf(Request $request)
    {
        try {
            $query = DailySale::query();

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

            $query->where(function ($q) {
                $q->where('owner_1', '>', 0)
                  ->orWhere('owner_2', '>', 0);
            });

            $payouts = $query->orderBy('date', 'asc')->get();

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.owner_payouts_pdf', [
                'payouts' => $payouts,
                'startDate' => $request->start_date ?? null,
                'endDate' => $request->end_date ?? null
            ]);

            return $pdf->download('owner_payouts.pdf');
        } catch (\Exception $e) {
            Log::error('Owner Payouts PDF Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF report.'
            ], 500);
        }
    }

    /**
     * Get unique pump expense names/remarks
     */
    public function getPumpExpenseNames(Request $request)
    {
        try {
            $names = \App\Models\DailySalePumpExpense::whereNotNull('remark')
                ->where('remark', '!=', '')
                ->distinct()
                ->pluck('remark');

            return response()->json([
                'success' => true,
                'names' => $names
            ]);
        } catch (\Exception $e) {
            Log::error('Pump Expense Names Fetch Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pump expense names.'
            ], 500);
        }
    }
}
