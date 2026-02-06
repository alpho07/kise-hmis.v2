<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Batch Invoice - {{ $batch->batch_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        .header { text-align: center; margin-bottom: 20px; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; }
        .claims-table { width: 100%; border-collapse: collapse; }
        .claims-table th, .claims-table td { border: 1px solid #ddd; padding: 8px; }
        .claims-table th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .footer { margin-top: 40px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KISE Health Management System</h2>
        <h3>Batch Invoice</h3>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Batch Number:</strong> {{ $batch->batch_number }}</td>
            <td><strong>Date Generated:</strong> {{ $generatedAt }}</td>
        </tr>
        <tr>
            <td><strong>Insurance Provider:</strong> {{ $batch->provider->name }}</td>
            <td><strong>Billing Period:</strong> 
                {{ $batch->billing_period_start->format('d/m/Y') }} - {{ $batch->billing_period_end->format('d/m/Y') }}
            </td>
        </tr>
        <tr>
            <td><strong>Total Claims:</strong> {{ $batch->total_claims }}</td>
            <td><strong>Total Amount:</strong> KES {{ number_format($batch->total_amount, 2) }}</td>
        </tr>
    </table>

    <table class="claims-table">
        <thead>
            <tr>
                <th>Claim #</th>
                <th>Client</th>
                <th>Service Date</th>
                <th>Services</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($batch->claims as $claim)
            <tr>
                <td>{{ $claim->claim_number }}</td>
                <td>{{ $claim->client->full_name }}</td>
                <td>{{ $claim->service_date->format('d/m/Y') }}</td>
                <td>
                    @foreach($claim->items as $item)
                        {{ $item->service->name }} ({{ $item->quantity }})<br>
                    @endforeach
                </td>
                <td class="text-right">{{ number_format($claim->approved_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-right">TOTAL:</th>
                <th class="text-right">KES {{ number_format($batch->total_amount, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>This is a system-generated document. For queries, contact KISE Finance Department.</p>
    </div>
</body>
</html>