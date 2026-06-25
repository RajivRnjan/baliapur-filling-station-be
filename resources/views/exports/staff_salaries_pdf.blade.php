<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Staff Salaries Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #333;
        }
        h2 {
            text-align: center;
            margin-bottom: 10px;
            color: #1a202c;
        }
        .date-range {
            text-align: center;
            margin-bottom: 15px;
            color: #64748b;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 5px;
            text-align: center;
        }
        th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #475569;
        }
        .text-right {
            text-align: right;
        }
        .text-left {
            text-align: left;
        }
        .total-row {
            background-color: #e2e8f0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Staff Salaries Report</h2>
    <div class="date-range">
        Salary Month: {{ date("F", mktime(0, 0, 0, $month, 10)) }} {{ $year }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Sl. No.</th>
                <th>STAFF NAME</th>
                <th>MONTHLY SALARY</th>
                <th>PAID THIS MONTH</th>
                <th>REMAINING</th>
            </tr>
        </thead>
        <tbody>
            @php
                $slNo = 1;
                $totalSalary = 0;
                $totalPaid = 0;
                $totalRemaining = 0;
            @endphp
            @foreach ($reports as $r)
                @php
                    $totalSalary += $r['salary'];
                    $totalPaid += $r['total_paid'];
                    $totalRemaining += $r['remaining'];
                @endphp
                <tr>
                    <td>{{ $slNo++ }}</td>
                    <td class="text-left">{{ $r['name'] }}</td>
                    <td>{{ $r['salary'] > 0 ? number_format($r['salary'], 2) : '' }}</td>
                    <td>{{ $r['total_paid'] > 0 ? number_format($r['total_paid'], 2) : '' }}</td>
                    <td>{{ $r['remaining'] != 0 ? number_format($r['remaining'], 2) : '0.00' }}</td>
                </tr>
            @endforeach
            @if ($slNo > 1)
            <tr class="total-row">
                <th colspan="2">TOTAL AMOUNT</th>
                <th>{{ number_format($totalSalary, 2) }}</th>
                <th>{{ number_format($totalPaid, 2) }}</th>
                <th>{{ number_format($totalRemaining, 2) }}</th>
            </tr>
            @else
            <tr>
                <td colspan="5">No records found.</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
