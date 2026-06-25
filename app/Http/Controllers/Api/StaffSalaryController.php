<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DailySaleStaffPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StaffSalaryController extends Controller
{
    private function getReportsData(Request $request)
    {
        // Only Admin/Super Admin can view all salaries, or staff can view their own
        $user = $request->user();
        
        $month = intval($request->get('month', date('m')));
        $year  = intval($request->get('year',  date('Y')));

        // Query staff users
        $query = User::where('role', 'STAFF');
        
        if ($user->role === 'STAFF') {
            $query->where('id', $user->id);
        }

        $staffMembers = $query->get();

        $reports = [];

        foreach ($staffMembers as $staff) {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
            $endDate   = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $payouts = DailySaleStaffPayout::where('user_id', $staff->id)
                ->where(function ($q) use ($month, $year, $startDate, $endDate) {
                    $q->where(function ($q2) use ($month, $year) {
                        $q2->where('salary_month', $month)
                           ->where('salary_year', $year);
                    })
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereNull('salary_month')
                           ->join('daily_sales', 'daily_sale_staff_payouts.daily_sale_id', '=', 'daily_sales.id')
                           ->whereBetween('daily_sales.date', [$startDate, $endDate]);
                    });
                })
                ->leftJoin('daily_sales', 'daily_sale_staff_payouts.daily_sale_id', '=', 'daily_sales.id')
                ->select(
                    'daily_sale_staff_payouts.id',
                    'daily_sale_staff_payouts.amount',
                    'daily_sale_staff_payouts.salary_month',
                    'daily_sale_staff_payouts.salary_year',
                    'daily_sales.date as sale_date'
                )
                ->orderBy('daily_sales.date', 'asc')
                ->get();

            $totalPaid  = $payouts->sum('amount');
            $salary     = floatval($staff->salary);
            $remaining  = round($salary - $totalPaid, 2);

            $reports[] = [
                'id'         => $staff->id,
                'name'       => $staff->name,
                'email'      => $staff->email,
                'phone'      => $staff->phone,
                'salary'     => $salary,
                'total_paid' => floatval($totalPaid),
                'remaining'  => $remaining,
                'payouts'    => $payouts->map(function ($p) {
                    $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                                       'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    $salaryLabel = ($p->salary_month && $p->salary_year)
                        ? $monthNames[$p->salary_month] . ' ' . $p->salary_year
                        : null;
                    return [
                        'id'           => $p->id,
                        'date'         => $p->sale_date ? Carbon::parse($p->sale_date)->format('d/m/Y') : null,
                        'salary_month' => $p->salary_month,
                        'salary_year'  => $p->salary_year,
                        'salary_label' => $salaryLabel,
                        'amount'       => floatval($p->amount),
                    ];
                })->toArray()
            ];
        }

        return ['month' => $month, 'year' => $year, 'reports' => $reports];
    }

    public function getStaffSalaries(Request $request)
    {
        try {
            $data = $this->getReportsData($request);

            return response()->json([
                'success' => true,
                'month'   => $data['month'],
                'year'    => $data['year'],
                'reports' => $data['reports']
            ]);

        } catch (\Exception $e) {
            Log::error('Staff Salaries Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff salaries.'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $data = $this->getReportsData($request);
            $reports = $data['reports'];

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=staff_salaries.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use($reports) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['STAFF NAME', 'MONTHLY SALARY', 'PAID THIS MONTH', 'REMAINING']);

                $totalSalary = 0;
                $totalPaid = 0;
                $totalRemaining = 0;

                foreach ($reports as $r) {
                    $totalSalary += $r['salary'];
                    $totalPaid += $r['total_paid'];
                    $totalRemaining += $r['remaining'];

                    fputcsv($file, [
                        $r['name'],
                        $r['salary'],
                        $r['total_paid'],
                        $r['remaining']
                    ]);
                }
                
                fputcsv($file, [
                    'TOTAL',
                    $totalSalary,
                    $totalPaid,
                    $totalRemaining
                ]);
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Staff Salaries Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export staff salaries.'
            ], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $data = $this->getReportsData($request);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.staff_salaries_pdf', [
                'reports' => $data['reports'],
                'month'   => $data['month'],
                'year'    => $data['year']
            ]);

            return $pdf->download('staff_salaries.pdf');
        } catch (\Exception $e) {
            Log::error('Staff Salaries PDF Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF report.'
            ], 500);
        }
    }

}
