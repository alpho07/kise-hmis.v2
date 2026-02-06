<div class="space-y-4">
    {{-- Summary Cards --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
            <div class="text-sm text-blue-600 font-medium">Total Amount</div>
            <div class="text-2xl font-bold text-blue-900">
                KES {{ number_format($invoice->total_amount, 2) }}
            </div>
        </div>
        
        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
            <div class="text-sm text-purple-600 font-medium">Sponsor/Insurance Pays</div>
            <div class="text-2xl font-bold text-purple-900">
                KES {{ number_format($invoice->total_sponsor_amount, 2) }}
            </div>
        </div>
        
        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
            <div class="text-sm text-green-600 font-medium">Client Pays</div>
            <div class="text-2xl font-bold text-green-900">
                KES {{ number_format($invoice->total_client_amount, 2) }}
            </div>
        </div>
    </div>

    {{-- Detailed Breakdown Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sponsor</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Client</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Split</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($invoice->items as $item)
                <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ $item->service->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ $item->quantity }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900 text-right">
                        {{ number_format($item->unit_price, 2) }}
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">
                        {{ number_format($item->total_amount, 2) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-purple-700 font-medium text-right">
                        {{ number_format($item->sponsor_amount, 2) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-green-700 font-medium text-right">
                        {{ number_format($item->client_amount, 2) }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @php
                            $sponsorPct = $item->total_amount > 0 
                                ? ($item->sponsor_amount / $item->total_amount) * 100 
                                : 0;
                            $clientPct = 100 - $sponsorPct;
                        @endphp
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                            {{ number_format($sponsorPct, 0) }}%
                        </span>
                        <span class="mx-1">/</span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            {{ number_format($clientPct, 0) }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-100 font-semibold">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-sm text-gray-900 text-right">TOTALS:</td>
                    <td class="px-4 py-3 text-sm text-gray-900 text-right">
                        KES {{ number_format($invoice->total_amount, 2) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-purple-700 text-right">
                        KES {{ number_format($invoice->total_sponsor_amount, 2) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-green-700 text-right">
                        KES {{ number_format($invoice->total_client_amount, 2) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>