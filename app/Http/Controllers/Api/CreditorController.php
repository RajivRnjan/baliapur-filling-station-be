<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Creditor;
use App\Models\CreditorTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CreditorController extends Controller
{
    // --- Creditor CRUD ---

    public function index(Request $request)
    {
        try {
            $perPage = intval($request->get('per_page', 10));
            $query = Creditor::query();

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('phone', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%');
                });
            }

            $creditors = $query->orderBy('name', 'asc')->paginate($perPage);
            return response()->json([
                'success' => true,
                'creditors' => $creditors
            ]);
        } catch (\Exception $e) {
            Log::error('Creditor Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve creditors.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:creditors,name',
                'phone' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
                'opening_balance' => 'nullable|numeric|min:0',
            ]);

            $data = $request->all();
            if (!isset($data['opening_balance'])) {
                $data['opening_balance'] = 0.00;
            }

            $creditor = Creditor::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Creditor added successfully',
                'creditor' => $creditor
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Creditor Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the creditor.'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $creditor = Creditor::findOrFail($id);
            return response()->json([
                'success' => true,
                'creditor' => $creditor
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Creditor not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Creditor Show Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve creditor.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can edit creditors.'
                ], 403);
            }

            $creditor = Creditor::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255|unique:creditors,name,' . $id,
                'phone' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
                'opening_balance' => 'required|numeric|min:0',
            ]);

            $creditor->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Creditor updated successfully',
                'creditor' => $creditor
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Creditor not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Creditor Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the creditor.'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can delete creditors.'
                ], 403);
            }

            $creditor = Creditor::findOrFail($id);
            $creditor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Creditor deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Creditor not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Creditor Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the creditor.'
            ], 500);
        }
    }

    // --- Creditor Ledger & Transactions ---

    public function getLedger(Request $request, $id)
    {
        try {
            $perPage = intval($request->get('per_page', 10));
            $page = intval($request->get('page', 1));

            $creditor = Creditor::findOrFail($id);
            $transactions = $creditor->transactions()
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $balance = floatval($creditor->opening_balance);
            $ledgerEntries = [];

            // Add opening balance entry first for clean presentation
            $ledgerEntries[] = [
                'id' => null,
                'date' => 'Opening Balance',
                'credit_amount' => 0.00,
                'payment_amount' => 0.00,
                'balance' => round($balance, 2),
                'remark' => 'Starting balance',
                'is_opening' => true,
            ];

            foreach ($transactions as $txn) {
                $credit = floatval($txn->credit_amount);
                $payment = floatval($txn->payment_amount);
                $balance = round($balance + $credit - $payment, 2);

                $ledgerEntries[] = [
                    'id' => $txn->id,
                    'date' => $txn->date->format('d/m/Y'),
                    'credit_amount' => $credit,
                    'payment_amount' => $payment,
                    'balance' => $balance,
                    'remark' => $txn->remark,
                    'is_opening' => false,
                ];
            }

            $currentBalance = $balance;

            // Running balance must be computed in chronological order first (above),
            // then reversed so the ledger displays newest entries first.
            $ledgerEntries = array_reverse($ledgerEntries);

            $total = count($ledgerEntries);
            $pagedEntries = array_slice($ledgerEntries, ($page - 1) * $perPage, $perPage);

            $paginatedLedger = new \Illuminate\Pagination\LengthAwarePaginator(
                $pagedEntries,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'success' => true,
                'creditor' => [
                    'id' => $creditor->id,
                    'name' => $creditor->name,
                    'opening_balance' => floatval($creditor->opening_balance),
                    'current_balance' => round($currentBalance, 2),
                ],
                'ledger' => $paginatedLedger
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Creditor not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Creditor Ledger Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ledger.'
            ], 500);
        }
    }

    public function exportLedger($id)
    {
        try {
            $creditor = Creditor::findOrFail($id);
            $transactions = $creditor->transactions()
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename={$creditor->name}_ledger.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $columns = [
                'Sl. No.', 
                'Date', 
                'Details/Remark', 
                'Credit (+)', 
                'Payment (-)', 
                'Balance'
            ];

            $callback = function() use($creditor, $transactions, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                $slNo = 1;
                $balance = floatval($creditor->opening_balance);

                // Opening Balance Row
                fputcsv($file, [
                    $slNo++,
                    'Opening Balance',
                    'Starting balance',
                    '0.00',
                    '0.00',
                    number_format($balance, 2, '.', '')
                ]);

                foreach ($transactions as $txn) {
                    $credit = floatval($txn->credit_amount);
                    $payment = floatval($txn->payment_amount);
                    $balance = round($balance + $credit - $payment, 2);

                    fputcsv($file, [
                        $slNo++,
                        $txn->date->format('d-m-Y'),
                        $txn->remark,
                        number_format($credit, 2, '.', ''),
                        number_format($payment, 2, '.', ''),
                        number_format($balance, 2, '.', '')
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Creditor not found.'], 404);
        } catch (\Exception $e) {
            Log::error('Creditor Ledger Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to export ledger.'], 500);
        }
    }

    public function exportLedgerPdf($id)
    {
        try {
            $creditor = Creditor::findOrFail($id);
            $transactions = $creditor->transactions()
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $balance = floatval($creditor->opening_balance);
            $ledgerEntries = [];

            // Add opening balance entry first
            $ledgerEntries[] = [
                'date' => 'Opening Balance',
                'credit_amount' => 0.00,
                'payment_amount' => 0.00,
                'balance' => round($balance, 2),
                'remark' => 'Starting balance',
            ];

            foreach ($transactions as $txn) {
                $credit = floatval($txn->credit_amount);
                $payment = floatval($txn->payment_amount);
                $balance = round($balance + $credit - $payment, 2);

                $ledgerEntries[] = [
                    'date' => $txn->date->format('d-m-Y'),
                    'credit_amount' => $credit,
                    'payment_amount' => $payment,
                    'balance' => $balance,
                    'remark' => $txn->remark,
                ];
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.ledger_pdf', [
                'creditor' => $creditor,
                'entries' => $ledgerEntries,
                'currentBalance' => $balance
            ]);

            return $pdf->download(str_replace(' ', '_', strtolower($creditor->name)) . '_ledger.pdf');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Creditor not found.'], 404);
        } catch (\Exception $e) {
            Log::error('Creditor Ledger PDF Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to export ledger PDF.'], 500);
        }
    }

    public function storeTransaction(Request $request, $id)
    {
        try {
            $creditor = Creditor::findOrFail($id);

            $request->validate([
                'date' => 'required|date',
                'credit_amount' => 'nullable|numeric|min:0',
                'payment_amount' => 'nullable|numeric|min:0',
                'remark' => 'nullable|string|max:500',
            ]);

            $txn = $creditor->transactions()->create([
                'date' => $request->date,
                'credit_amount' => $request->credit_amount ?? 0.00,
                'payment_amount' => $request->payment_amount ?? 0.00,
                'remark' => $request->remark,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction added successfully',
                'transaction' => $txn,
                'current_balance' => $creditor->fresh()->current_balance,
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Creditor not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Creditor Store Transaction Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction.'
            ], 500);
        }
    }

    public function updateTransaction(Request $request, $transaction_id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can edit transaction entries.'
                ], 403);
            }

            $txn = CreditorTransaction::findOrFail($transaction_id);

            $request->validate([
                'date' => 'required|date',
                'credit_amount' => 'required|numeric|min:0',
                'payment_amount' => 'required|numeric|min:0',
                'remark' => 'nullable|string|max:500',
            ]);

            $txn->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully',
                'transaction' => $txn,
                'current_balance' => $txn->creditor->fresh()->current_balance,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Creditor Update Transaction Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transaction.'
            ], 500);
        }
    }

    public function destroyTransaction(Request $request, $transaction_id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can delete transaction entries.'
                ], 403);
            }

            $txn = CreditorTransaction::findOrFail($transaction_id);
            $creditor = $txn->creditor;
            $txn->delete();

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully',
                'current_balance' => $creditor->fresh()->current_balance,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Creditor Delete Transaction Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transaction.'
            ], 500);
        }
    }
}
