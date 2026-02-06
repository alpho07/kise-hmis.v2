<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $payment->reference_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: 80mm auto; margin: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 80mm; padding: 5mm; font-size: 11px; line-height: 1.4;
            background: white;
        }
        .header {
            text-align: center; border-bottom: 2px dashed #000;
            padding-bottom: 8px; margin-bottom: 10px;
        }
        .logo { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .hospital-name { font-size: 14px; font-weight: bold; }
        .hospital-details { font-size: 9px; margin-top: 3px; }
        .receipt-title {
            font-size: 16px; font-weight: bold; text-align: center;
            margin: 10px 0; text-transform: uppercase;
        }
        .section {
            margin: 10px 0; border-bottom: 1px dashed #000; padding-bottom: 8px;
        }
        .row { display: flex; justify-content: space-between; margin: 3px 0; }
        .label { font-weight: bold; }
        .value { text-align: right; }
        .items-table { width: 100%; margin: 10px 0; }
        .items-table th {
            text-align: left; border-bottom: 1px solid #000;
            padding: 3px 0; font-size: 10px;
        }
        .items-table td { padding: 3px 0; font-size: 10px; }
        .items-table .qty { text-align: center; width: 15%; }
        .items-table .price { text-align: right; width: 25%; }
        .items-table .amount { text-align: right; width: 25%; }
        .total-section { margin-top: 10px; font-size: 12px; }
        .total-row { display: flex; justify-content: space-between; margin: 5px 0; }
        .grand-total {
            font-size: 14px; font-weight: bold;
            border-top: 2px solid #000; border-bottom: 2px solid #000;
            padding: 5px 0; margin: 5px 0;
        }
        .payment-details {
            margin: 10px 0; background: #f5f5f5;
            padding: 8px; border: 1px solid #ddd;
        }
        .payment-method-item {
            display: flex; justify-content: space-between;
            margin: 3px 0; font-size: 10px;
        }
        .credit-account-box {
            background: #e3f2fd; border: 1px solid #2196f3;
            padding: 6px; margin: 8px 0; border-radius: 3px;
        }
        .footer {
            text-align: center; margin-top: 15px; font-size: 9px;
            border-top: 2px dashed #000; padding-top: 10px;
        }
        .thank-you { font-size: 12px; font-weight: bold; margin: 10px 0; }
        .barcode {
            text-align: center; margin: 10px 0;
            font-size: 24px; font-family: 'Libre Barcode 39', cursive;
        }
        @media print {
            body { width: 80mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <div class="logo">🏥 KISE</div>
        <div class="hospital-name">KENYA INSTITUTE OF SPECIAL EDUCATION</div>
        <div class="hospital-details">
            P.O. Box 1234-00100, Nairobi<br>
            Tel: +254 700 000 000 | Email: info@kise.ac.ke
        </div>
    </div>

    <div class="receipt-title">PAYMENT RECEIPT</div>

    <!-- RECEIPT INFO -->
    <div class="section">
        <div class="row">
            <span class="label">Receipt #:</span>
            <span class="value"><strong>{{ $payment->reference_number }}</strong></span>
        </div>
        <div class="row">
            <span class="label">Date:</span>
            <span class="value">{{ $payment->payment_date->format('d M Y, H:i') }}</span>
        </div>
        <div class="row">
            <span class="label">Invoice #:</span>
            <span class="value">{{ $payment->invoice->invoice_number ?? 'N/A' }}</span>
        </div>
        <div class="row">
            <span class="label">Visit #:</span>
            <span class="value">{{ $payment->invoice->visit->visit_number ?? 'N/A' }}</span>
        </div>
    </div>

    <!-- CLIENT INFO -->
    <div class="section">
        <div class="row">
            <span class="label">Client:</span>
            <span class="value">{{ $payment->client->full_name ?? 'N/A' }}</span>
        </div>
        <div class="row">
            <span class="label">UCI:</span>
            <span class="value">{{ $payment->client->uci ?? 'N/A' }}</span>
        </div>
        @if($payment->client->phone)
        <div class="row">
            <span class="label">Phone:</span>
            <span class="value">{{ $payment->client->phone }}</span>
        </div>
        @endif
    </div>

    <!-- SERVICES/ITEMS -->
    @if($payment->invoice && $payment->invoice->items->count() > 0)
    <div class="section">
        <table class="items-table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th class="qty">Qty</th>
                    <th class="price">Price</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payment->invoice->items as $item)
                <tr>
                    <td>{{ $item->service->name ?? $item->description }}</td>
                    <td class="qty">{{ $item->quantity }}</td>
                    <td class="price">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="amount">{{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- TOTALS -->
    <div class="total-section">
        @if($payment->invoice)
            <div class="total-row grand-total">
                <span>TOTAL AMOUNT:</span>
                <span>KES {{ number_format($payment->invoice->total_amount, 2) }}</span>
            </div>
        @endif
    </div>

    <!-- PAYMENT DETAILS -->
    <div class="payment-details section">
        <div style="font-weight: bold; margin-bottom: 5px;">PAYMENT BREAKDOWN:</div>

        <!-- CREDIT ACCOUNT if used -->
        @if($payment->usedAccountCredit())
            <div class="credit-account-box">
                <div style="font-size: 9px; font-weight: bold; margin-bottom: 3px;">
                    💰 CREDIT ACCOUNT USED
                </div>
                <div class="row" style="font-size: 9px; margin: 2px 0;">
                    <span>Account:</span>
                    <span>{{ $payment->creditAccount->account_number ?? 'N/A' }}</span>
                </div>
                <div class="row" style="font-weight: bold;">
                    <span>Credit Applied:</span>
                    <span>-KES {{ number_format($payment->account_credit_used, 2) }}</span>
                </div>
                <div class="row" style="font-size: 9px; margin-top: 3px; border-top: 1px dashed #2196f3; padding-top: 3px;">
                    <span>New Balance:</span>
                    <span><strong>KES {{ number_format($payment->getRemainingCreditBalance() ?? 0, 2) }}</strong></span>
                </div>
            </div>
        @endif

        <!-- OTHER PAYMENT METHODS -->
        @php
            $breakdown = $payment->getPaymentBreakdown();
        @endphp

        @foreach($breakdown as $method)
            @if($method['method'] !== 'account_credit')
            <div class="payment-method-item">
                <span>
                    {{ match($method['method']) {
                        'cash' => '💵 Cash',
                        'mpesa' => '📱 M-PESA',
                        'card' => '💳 Card',
                        'bank_transfer' => '🏦 Bank Transfer',
                        default => ucfirst($method['method'])
                    } }}
                    @if(isset($method['reference']) && $method['reference'])
                        <br><small style="font-size: 8px;">Ref: {{ $method['reference'] }}</small>
                    @endif
                </span>
                <span><strong>KES {{ number_format($method['amount'], 2) }}</strong></span>
            </div>
            @endif
        @endforeach

        @if($payment->change_given > 0)
            <div class="payment-method-item" style="border-top: 1px dashed #000; margin-top: 5px; padding-top: 5px;">
                <span>Change Given:</span>
                <span><strong>KES {{ number_format($payment->change_given, 2) }}</strong></span>
            </div>
        @endif
    </div>

    <!-- BARCODE -->
    <div class="barcode">*{{ $payment->reference_number }}*</div>

    <!-- FOOTER -->
    <div class="footer">
        <div class="thank-you">THANK YOU FOR YOUR VISIT!</div>
        <div style="margin: 5px 0;">
            Served by: {{ $payment->processedBy->name ?? 'System' }}<br>
            This is a computer-generated receipt.<br>
            No signature required.
        </div>
        <div style="margin-top: 8px; font-size: 8px;">
            For queries, contact: +254 700 000 000<br>
            Email: finance@kise.ac.ke
        </div>
        <div style="margin-top: 8px; font-style: italic;">
            "Empowering Special Education"
        </div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #4CAF50; color: white; border: none; border-radius: 4px;">
            🖨️ Print Receipt
        </button>
    </div>
</body>
</html>