<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pump Expenses Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
        }
        h2 {
            text-align: center;
            margin-bottom: 10px;
            color: #1a202c;
        }
        .date-range {
            text-align: center;
            margin-bottom: 20px;
            color: #64748b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #475569;
        }
    </style>
</head>
<body>
    <h2>Pump Expenses Report</h2>
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
                <th>REMARK / PARTICULARS</th>
                <th>AMOUNT (Rs)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $index => $expense)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $expense['date'] }}</td>
                <td>{{ $expense['remark'] ?: 'Unspecified Expense' }}</td>
                <td>{{ number_format($expense['amount'], 2) }}</td>
            </tr>
            @endforeach
            @if(count($expenses) === 0)
            <tr>
                <td colspan="4">No pump expenses found for this period.</td>
            </tr>
            @else
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold;">Total:</td>
                <td style="font-weight: bold;">{{ number_format(collect($expenses)->sum('amount'), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
