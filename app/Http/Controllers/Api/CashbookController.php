<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cashbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CashbookController extends Controller
{
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

    public function index(Request $request)
    {
        try {
            $query = Cashbook::query();

            if ($request->has('start_date') && $request->start_date) {
                $startDate = $this->parseDate($request->start_date);
                if ($startDate) $query->where('date', '>=', $startDate);
            }

            if ($request->has('end_date') && $request->end_date) {
                $endDate = $this->parseDate($request->end_date);
                if ($endDate) $query->where('date', '<=', $endDate);
            }
            
            if ($request->has('date') && $request->date) {
                $date = $this->parseDate($request->date);
                if ($date) $query->where('date', $date);
            }

            if ($request->has('type') && in_array($request->type, ['receipt', 'payment'])) {
                $query->where('type', $request->type);
            }

            $totalsQuery = clone $query;
            $receiptsQuery = (clone $totalsQuery)->where('type', 'receipt');
            $paymentsQuery = (clone $totalsQuery)->where('type', 'payment');

            $totals = [
                'receipts_cash' => floatval($receiptsQuery->sum('cash_amount')),
                'receipts_bank' => floatval($receiptsQuery->sum('bank_amount')),
                'payments_cash' => floatval($paymentsQuery->sum('cash_amount')),
                'payments_bank' => floatval($paymentsQuery->sum('bank_amount')),
            ];

            $perPage = intval($request->get('per_page', 20));
            $records = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'cashbooks' => $records,
                'totals' => $totals
            ]);
        } catch (\Exception $e) {
            Log::error('Cashbook Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cashbook records.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'type' => 'required|in:receipt,payment',
                'particulars' => 'required|string|max:255',
                'cash_amount' => 'nullable|numeric|min:0',
                'bank_amount' => 'nullable|numeric|min:0',
                'remark' => 'nullable|string|max:255',
            ]);

            $cashbook = Cashbook::create([
                'date' => $request->date,
                'type' => $request->type,
                'particulars' => $request->particulars,
                'cash_amount' => $request->cash_amount ?? 0.00,
                'bank_amount' => $request->bank_amount ?? 0.00,
                'remark' => $request->remark,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Record added successfully.',
                'cashbook' => $cashbook
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Cashbook Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add record.'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $cashbook = Cashbook::findOrFail($id);
            return response()->json([
                'success' => true,
                'cashbook' => $cashbook
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $cashbook = Cashbook::findOrFail($id);

            $request->validate([
                'date' => 'required|date',
                'type' => 'required|in:receipt,payment',
                'particulars' => 'required|string|max:255',
                'cash_amount' => 'nullable|numeric|min:0',
                'bank_amount' => 'nullable|numeric|min:0',
                'remark' => 'nullable|string|max:255',
            ]);

            $cashbook->update([
                'date' => $request->date,
                'type' => $request->type,
                'particulars' => $request->particulars,
                'cash_amount' => $request->cash_amount ?? 0.00,
                'bank_amount' => $request->bank_amount ?? 0.00,
                'remark' => $request->remark,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Record updated successfully.',
                'cashbook' => $cashbook
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Cashbook Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update record.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $cashbook = Cashbook::findOrFail($id);
            $cashbook->delete();

            return response()->json([
                'success' => true,
                'message' => 'Record deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete record.'
            ], 500);
        }
    }

    /**
     * Export dual-pane CSV for Receipts and Payments matching the screenshot.
     */
    public function export(Request $request)
    {
        try {
            $query = Cashbook::query();

            if ($request->has('start_date') && $request->start_date) {
                $startDate = $this->parseDate($request->start_date);
                if ($startDate) $query->where('date', '>=', $startDate);
            }

            if ($request->has('end_date') && $request->end_date) {
                $endDate = $this->parseDate($request->end_date);
                if ($endDate) $query->where('date', '<=', $endDate);
            }

            // Fetch and separate
            $records = $query->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
            $receipts = $records->where('type', 'receipt')->values();
            $payments = $records->where('type', 'payment')->values();

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=cashbook_report.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            // Create dual pane layout
            $maxRows = max($receipts->count(), $payments->count());

            $callback = function() use($receipts, $payments, $maxRows) {
                $file = fopen('php://output', 'w');
                
                // Top Titles
                fputcsv($file, ['', 'RECEIPTS FROM PUMP', '', '', '', '', '', 'PAYMENTS', '', '', '']);
                
                // Column Headers
                fputcsv($file, [
                    'DATE', 'PARTICULARS', 'CASH', 'BANK', 'REMARK',
                    '', // Spacer
                    'DATE', 'PARTICULARS', 'CASH', 'BANK', 'REMARK'
                ]);

                for ($i = 0; $i < $maxRows; $i++) {
                    $r = $receipts->get($i);
                    $p = $payments->get($i);

                    $row = [
                        $r ? \Carbon\Carbon::parse($r->date)->format('d-m-Y') : '',
                        $r ? $r->particulars : '',
                        $r ? ($r->cash_amount > 0 ? $r->cash_amount : '') : '',
                        $r ? ($r->bank_amount > 0 ? $r->bank_amount : '') : '',
                        $r ? $r->remark : '',
                        
                        '', // Spacer column

                        $p ? \Carbon\Carbon::parse($p->date)->format('d-m-Y') : '',
                        $p ? $p->particulars : '',
                        $p ? ($p->cash_amount > 0 ? $p->cash_amount : '') : '',
                        $p ? ($p->bank_amount > 0 ? $p->bank_amount : '') : '',
                        $p ? $p->remark : ''
                    ];

                    fputcsv($file, $row);
                }
                
                // Totals Row
                $rCashTotal = $receipts->sum('cash_amount');
                $rBankTotal = $receipts->sum('bank_amount');
                $pCashTotal = $payments->sum('cash_amount');
                $pBankTotal = $payments->sum('bank_amount');
                
                fputcsv($file, [
                    '', 'TOTAL', $rCashTotal > 0 ? $rCashTotal : '', $rBankTotal > 0 ? $rBankTotal : '', '',
                    '',
                    '', 'TOTAL', $pCashTotal > 0 ? $pCashTotal : '', $pBankTotal > 0 ? $pBankTotal : '', ''
                ]);

                // Net Balance Row
                fputcsv($file, [
                    '', 'NET BALANCE', ($rCashTotal - $pCashTotal), ($rBankTotal - $pBankTotal), '',
                    '',
                    '', '', '', '', ''
                ]);

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Cashbook Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export cashbook report.'
            ], 500);
        }
    }

    /**
     * Export dual-pane PDF for Receipts and Payments.
     */
    public function exportPdf(Request $request)
    {
        try {
            $query = Cashbook::query();

            if ($request->has('start_date') && $request->start_date) {
                $startDate = $this->parseDate($request->start_date);
                if ($startDate) $query->where('date', '>=', $startDate);
            }

            if ($request->has('end_date') && $request->end_date) {
                $endDate = $this->parseDate($request->end_date);
                if ($endDate) $query->where('date', '<=', $endDate);
            }

            // Fetch and separate
            $records = $query->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
            $receipts = $records->where('type', 'receipt')->values();
            $payments = $records->where('type', 'payment')->values();

            $maxRows = max($receipts->count(), $payments->count());

            $rCashTotal = $receipts->sum('cash_amount');
            $rBankTotal = $receipts->sum('bank_amount');
            $pCashTotal = $payments->sum('cash_amount');
            $pBankTotal = $payments->sum('bank_amount');

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.cashbook_pdf', [
                'receipts' => $receipts,
                'payments' => $payments,
                'maxRows' => $maxRows,
                'rCashTotal' => $rCashTotal,
                'rBankTotal' => $rBankTotal,
                'pCashTotal' => $pCashTotal,
                'pBankTotal' => $pBankTotal,
                'startDate' => $request->start_date ?? null,
                'endDate' => $request->end_date ?? null
            ])->setPaper('a4', 'landscape');

            return $pdf->download('cashbook_report.pdf');

        } catch (\Exception $e) {
            Log::error('Cashbook PDF Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF report.'
            ], 500);
        }
    }
}
