<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Sales Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #1a202c;
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
        .text-right {
            text-align: right;
        }
        .text-left {
            text-align: left;
        }
        .date-range {
            text-align: center;
            margin-bottom: 20px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <h2>Daily Sales Report</h2>
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
                <th>MS SALE</th>
                <th>AMOUNT</th>
                <th>HSD SALE</th>
                <th>AMOUNT</th>
                <th>XP</th>
                <th>AMOUNT</th>
                <th>LUBRICANT</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalMsQty = 0; $totalMsAmt = 0;
                $totalHsdQty = 0; $totalHsdAmt = 0;
                $totalXpQty = 0; $totalXpAmt = 0;
                $totalLubricant = 0;
            @endphp
            @foreach($sales as $index => $sale)
            @php
                $totalMsQty += $sale['ms_qty'];
                $totalMsAmt += $sale['ms_amt'];
                $totalHsdQty += $sale['hsd_qty'];
                $totalHsdAmt += $sale['hsd_amt'];
                $totalXpQty += $sale['xp_qty'];
                $totalXpAmt += $sale['xp_amt'];
                $totalLubricant += $sale['lubricant'];
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $sale['date'] }}</td>
                <td>{{ number_format($sale['ms_qty'], 2) }} L</td>
                <td>₹{{ number_format($sale['ms_amt'], 2) }}</td>
                <td>{{ number_format($sale['hsd_qty'], 2) }} L</td>
                <td>₹{{ number_format($sale['hsd_amt'], 2) }}</td>
                <td>{{ number_format($sale['xp_qty'], 2) }} L</td>
                <td>₹{{ number_format($sale['xp_amt'], 2) }}</td>
                <td>₹{{ number_format($sale['lubricant'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f8fafc;">
                <td colspan="2" style="text-align: right; padding-right: 15px;">TOTAL:</td>
                <td>{{ number_format($totalMsQty, 2) }} L</td>
                <td>₹{{ number_format($totalMsAmt, 2) }}</td>
                <td>{{ number_format($totalHsdQty, 2) }} L</td>
                <td>₹{{ number_format($totalHsdAmt, 2) }}</td>
                <td>{{ number_format($totalXpQty, 2) }} L</td>
                <td>₹{{ number_format($totalXpAmt, 2) }}</td>
                <td>₹{{ number_format($totalLubricant, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
