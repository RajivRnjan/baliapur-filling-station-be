<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Owner Payouts Report</title>
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
    <h2>Owner Payouts Report</h2>
    @if(isset($startDate) || isset($endDate))
    <div class="date-range">
        Reporting Period: 
        {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d-m-Y') : 'Start' }}
        to 
        {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d-m-Y') : 'Present' }}
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Sl. No.</th>
                <th>DATE</th>
                <th>OWNER 1 (Umesh)</th>
                <th>OWNER 2 (Saket)</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @php
                $slNo = 1;
                $total1 = 0;
                $total2 = 0;
            @endphp
            @foreach ($payouts as $p)
                @php
                    $amt1 = floatval($p->owner_1);
                    $amt2 = floatval($p->owner_2);
                    $total1 += $amt1;
                    $total2 += $amt2;
                @endphp
                <tr>
                    <td>{{ $slNo++ }}</td>
                    <td>{{ \Carbon\Carbon::parse($p->date)->format('d-m-Y') }}</td>
                    <td>{{ $amt1 > 0 ? number_format($amt1, 2) : '' }}</td>
                    <td>{{ $amt2 > 0 ? number_format($amt2, 2) : '' }}</td>
                    <td>{{ number_format($amt1 + $amt2, 2) }}</td>
                </tr>
            @endforeach
            @if ($slNo > 1)
            <tr class="total-row">
                <th colspan="2">TOTAL AMOUNT</th>
                <th>{{ number_format($total1, 2) }}</th>
                <th>{{ number_format($total2, 2) }}</th>
                <th>{{ number_format($total1 + $total2, 2) }}</th>
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
