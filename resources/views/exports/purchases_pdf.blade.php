<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchases Report</title>
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
    <h2>Purchases Report</h2>
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
                <th>MS (KL)</th>
                <th>MS (Litres)</th>
                <th>HSD (KL)</th>
                <th>HSD (Litres)</th>
                <th>XP (KL)</th>
                <th>XP (Litres)</th>
                <th>TYPE</th>
            </tr>
        </thead>
        <tbody>
            @php
                $t_ms_kl = 0; $t_ms = 0;
                $t_hsd_kl = 0; $t_hsd = 0;
                $t_xp_kl = 0; $t_xp = 0;
            @endphp
            @foreach($purchases as $index => $purchase)
            @php
                $ms = floatval($purchase->ms);
                $hsd = floatval($purchase->hsd);
                $xp = floatval($purchase->xp);

                $t_ms += $ms;
                $t_hsd += $hsd;
                $t_xp += $xp;

                $t_ms_kl += ($ms / 1000);
                $t_hsd_kl += ($hsd / 1000);
                $t_xp_kl += ($xp / 1000);
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $purchase->date ? \Carbon\Carbon::parse($purchase->date)->format('d-m-Y') : '-' }}</td>
                <td>{{ $ms / 1000 }}</td>
                <td>{{ $ms }}</td>
                <td>{{ $hsd / 1000 }}</td>
                <td>{{ $hsd }}</td>
                <td>{{ $xp / 1000 }}</td>
                <td>{{ $xp }}</td>
                <td>{{ $purchase->is_opening ? 'Opening Stock' : 'Purchase' }}</td>
            </tr>
            @endforeach
            @if(count($purchases) > 0)
            <tr style="background-color: #e2e8f0; font-weight: bold;">
                <td colspan="2">TOTAL</td>
                <td>{{ $t_ms_kl > 0 ? $t_ms_kl : '0' }}</td>
                <td>{{ $t_ms > 0 ? $t_ms : '0' }}</td>
                <td>{{ $t_hsd_kl > 0 ? $t_hsd_kl : '0' }}</td>
                <td>{{ $t_hsd > 0 ? $t_hsd : '0' }}</td>
                <td>{{ $t_xp_kl > 0 ? $t_xp_kl : '0' }}</td>
                <td>{{ $t_xp > 0 ? $t_xp : '0' }}</td>
                <td></td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
