<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cashbook Report</title>
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
        .spacer {
            width: 10px;
            border: none;
            background: #fff !important;
        }
        .text-right {
            text-align: right;
        }
        .text-left {
            text-align: left;
        }
        .section-title {
            background-color: #e2e8f0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Cashbook Report</h2>
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
                <th colspan="5" class="section-title">RECEIPTS FROM PUMP</th>
                <th class="spacer" style="border:none;background:none;"></th>
                <th colspan="5" class="section-title">PAYMENTS</th>
            </tr>
            <tr>
                <th>DATE</th>
                <th>PARTICULARS</th>
                <th>CASH</th>
                <th>BANK</th>
                <th>REMARK</th>
                <th class="spacer" style="border:none;background:none;"></th>
                <th>DATE</th>
                <th>PARTICULARS</th>
                <th>CASH</th>
                <th>BANK</th>
                <th>REMARK</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $maxRows; $i++)
                @php
                    $r = $receipts->get($i);
                    $p = $payments->get($i);
                @endphp
                <tr>
                    <td>{{ $r ? \Carbon\Carbon::parse($r->date)->format('d-m-Y') : '' }}</td>
                    <td class="text-left">{{ $r ? $r->particulars : '' }}</td>
                    <td>{{ $r && $r->cash_amount > 0 ? number_format($r->cash_amount, 2) : '' }}</td>
                    <td>{{ $r && $r->bank_amount > 0 ? number_format($r->bank_amount, 2) : '' }}</td>
                    <td class="text-left">{{ $r ? $r->remark : '' }}</td>
                    
                    <td class="spacer" style="border:none;background:none;"></td>

                    <td>{{ $p ? \Carbon\Carbon::parse($p->date)->format('d-m-Y') : '' }}</td>
                    <td class="text-left">{{ $p ? $p->particulars : '' }}</td>
                    <td>{{ $p && $p->cash_amount > 0 ? number_format($p->cash_amount, 2) : '' }}</td>
                    <td>{{ $p && $p->bank_amount > 0 ? number_format($p->bank_amount, 2) : '' }}</td>
                    <td class="text-left">{{ $p ? $p->remark : '' }}</td>
                </tr>
            @endfor
            <tr>
                <th></th>
                <th>TOTAL</th>
                <th>{{ $rCashTotal > 0 ? number_format($rCashTotal, 2) : '' }}</th>
                <th>{{ $rBankTotal > 0 ? number_format($rBankTotal, 2) : '' }}</th>
                <th></th>
                
                <th class="spacer" style="border:none;background:none;"></th>

                <th></th>
                <th>TOTAL</th>
                <th>{{ $pCashTotal > 0 ? number_format($pCashTotal, 2) : '' }}</th>
                <th>{{ $pBankTotal > 0 ? number_format($pBankTotal, 2) : '' }}</th>
                <th></th>
            </tr>
            <tr>
                <th></th>
                <th>NET BALANCE</th>
                <th style="color: {{ ($rCashTotal - $pCashTotal) >= 0 ? '#166534' : '#991b1b' }}">{{ number_format($rCashTotal - $pCashTotal, 2) }}</th>
                <th style="color: {{ ($rBankTotal - $pBankTotal) >= 0 ? '#166534' : '#991b1b' }}">{{ number_format($rBankTotal - $pBankTotal, 2) }}</th>
                <th></th>
                
                <th class="spacer" style="border:none;background:none;"></th>

                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </tbody>
    </table>
</body>
</html>
