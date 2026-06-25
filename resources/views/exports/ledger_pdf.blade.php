<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Creditor Ledger - {{ $creditor->name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h2 {
            margin: 0;
            margin-bottom: 5px;
            color: #1a202c;
        }
        .header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }
        .summary-box {
            border: 1px solid #cbd5e1;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f8fafc;
            border-radius: 4px;
        }
        .summary-box div {
            margin-bottom: 5px;
        }
        .summary-box strong {
            color: #475569;
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
        .credit {
            color: #ef4444; /* Red for Credit Given to them */
        }
        .payment {
            color: #10b981; /* Green for Payment Received */
        }
        .balance {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Ledger Statement</h2>
        <p>For: <strong>{{ $creditor->name }}</strong></p>
    </div>

    <div class="summary-box">
        <div><strong>Contact:</strong> {{ $creditor->phone ?? 'N/A' }}</div>
        @if($creditor->email)
        <div><strong>Email:</strong> {{ $creditor->email }}</div>
        @endif
        <div><strong>Current Balance:</strong> ₹{{ number_format($currentBalance, 2) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Sl. No.</th>
                <th>Date</th>
                <th>Details/Remark</th>
                <th>Credit (+)</th>
                <th>Payment (-)</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $index => $entry)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $entry['date'] }}</td>
                <td class="text-left">{{ $entry['remark'] ?? '-' }}</td>
                <td class="credit">{{ $entry['credit_amount'] > 0 ? '₹' . number_format($entry['credit_amount'], 2) : '-' }}</td>
                <td class="payment">{{ $entry['payment_amount'] > 0 ? '₹' . number_format($entry['payment_amount'], 2) : '-' }}</td>
                <td class="balance">₹{{ number_format($entry['balance'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
