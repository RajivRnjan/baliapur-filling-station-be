<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    private function klToLitres($kl)
    {
        return $kl ? $kl * 1000 : 0;
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

    public function index(Request $request)
    {
        try {
            $perPage = intval($request->get('per_page', 10));
            $query = Purchase::query();

            if ($request->has('is_opening')) {
                $isOpening = filter_var($request->is_opening, FILTER_VALIDATE_BOOLEAN);
                $query->where('is_opening', $isOpening);
            }

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

            $sumMs = floatval($query->sum('ms'));
            $sumHsd = floatval($query->sum('hsd'));
            $sumXp = floatval($query->sum('xp'));

            $totals = [
                'ms_kl' => $sumMs / 1000,
                'ms' => $sumMs,
                'hsd_kl' => $sumHsd / 1000,
                'hsd' => $sumHsd,
                'xp_kl' => $sumXp / 1000,
                'xp' => $sumXp
            ];

            $purchases = $query->orderBy('is_opening', 'desc')
                ->orderBy('date', 'desc')
                ->orderBy('sl_no', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'purchases' => $purchases,
                'totals' => $totals
            ]);
        } catch (\Exception $e) {
            Log::error('Purchase Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve purchase records.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'date' => 'nullable|date',
                'ms_kl' => 'nullable|numeric',
                'hsd_kl' => 'nullable|numeric',
                'xp_kl' => 'nullable|numeric',
                'ms' => 'nullable|numeric',
                'hsd' => 'nullable|numeric',
                'xp' => 'nullable|numeric',
                'is_opening' => 'nullable|boolean',
            ]);

            $data = $request->all();

            // Handle KL to Litre conversions
            if ($request->has('ms_kl')) $data['ms'] = $this->klToLitres($request->ms_kl);
            if ($request->has('hsd_kl')) $data['hsd'] = $this->klToLitres($request->hsd_kl);
            if ($request->has('xp_kl')) $data['xp'] = $this->klToLitres($request->xp_kl);

            if (!isset($data['date'])) {
                $data['date'] = now()->toDateString();
            }

            $purchase = Purchase::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Purchase entry created successfully',
                'purchase' => $purchase
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Purchase Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the purchase entry.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can edit purchase records.'
                ], 403);
            }

            $purchase = Purchase::findOrFail($id);
            
            $request->validate([
                'date' => 'nullable|date',
                'ms_kl' => 'nullable|numeric',
                'hsd_kl' => 'nullable|numeric',
                'xp_kl' => 'nullable|numeric',
                'ms' => 'nullable|numeric',
                'hsd' => 'nullable|numeric',
                'xp' => 'nullable|numeric',
                'is_opening' => 'nullable|boolean',
            ]);

            $data = $request->all();

            if ($request->has('ms_kl')) $data['ms'] = $this->klToLitres($request->ms_kl);
            if ($request->has('hsd_kl')) $data['hsd'] = $this->klToLitres($request->hsd_kl);
            if ($request->has('xp_kl')) $data['xp'] = $this->klToLitres($request->xp_kl);

            $purchase->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Purchase entry updated successfully',
                'purchase' => $purchase
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase record not found.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Purchase Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the purchase entry.'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'SUPER_ADMIN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only Super Admin can delete purchase records.'
                ], 403);
            }

            $purchase = Purchase::findOrFail($id);
            $purchase->delete();

            return response()->json([
                'success' => true,
                'message' => 'Purchase entry deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase record not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Purchase Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the purchase record.'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $query = Purchase::query();

            if ($request->has('is_opening')) {
                $isOpening = filter_var($request->is_opening, FILTER_VALIDATE_BOOLEAN);
                $query->where('is_opening', $isOpening);
            }

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

            $purchases = $query->orderBy('is_opening', 'desc')
                ->orderBy('date', 'asc')
                ->orderBy('sl_no', 'asc')
                ->get();

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=purchases_report.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $columns = [
                'Sl. No.', 
                'DATE', 
                'MS (KL)', 
                'MS (Litres)', 
                'HSD (KL)', 
                'HSD (Litres)', 
                'XP (KL)', 
                'XP (Litres)',
                'TYPE'
            ];

            $callback = function() use($purchases, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                $slNo = 1;
                $t_ms_kl = 0; $t_ms = 0;
                $t_hsd_kl = 0; $t_hsd = 0;
                $t_xp_kl = 0; $t_xp = 0;

                foreach ($purchases as $purchase) {
                    $ms = floatval($purchase->ms);
                    $hsd = floatval($purchase->hsd);
                    $xp = floatval($purchase->xp);

                    $t_ms += $ms;
                    $t_hsd += $hsd;
                    $t_xp += $xp;
                    
                    $t_ms_kl += ($ms / 1000);
                    $t_hsd_kl += ($hsd / 1000);
                    $t_xp_kl += ($xp / 1000);

                    $row = [
                        $slNo++,
                        $purchase->date ? \Carbon\Carbon::parse($purchase->date)->format('d-m-Y') : '-',
                        $ms / 1000,
                        $ms,
                        $hsd / 1000,
                        $hsd,
                        $xp / 1000,
                        $xp,
                        $purchase->is_opening ? 'Opening Stock' : 'Purchase'
                    ];
                    fputcsv($file, $row);
                }

                if ($slNo > 1) {
                    fputcsv($file, [
                        '', 'TOTAL',
                        $t_ms_kl, $t_ms,
                        $t_hsd_kl, $t_hsd,
                        $t_xp_kl, $t_xp,
                        ''
                    ]);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Purchase Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report.'
            ], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $query = Purchase::query();

            if ($request->has('is_opening')) {
                $isOpening = filter_var($request->is_opening, FILTER_VALIDATE_BOOLEAN);
                $query->where('is_opening', $isOpening);
            }

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

            $purchases = $query->orderBy('is_opening', 'desc')
                ->orderBy('date', 'asc')
                ->orderBy('sl_no', 'asc')
                ->get();

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.purchases_pdf', [
                'purchases' => $purchases,
                'startDate' => $request->start_date ?? null,
                'endDate' => $request->end_date ?? null
            ]);

            return $pdf->download('purchases_report.pdf');
        } catch (\Exception $e) {
            Log::error('Purchase PDF Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF report.'
            ], 500);
        }
    }
}
