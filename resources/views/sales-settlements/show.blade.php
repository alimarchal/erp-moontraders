<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Sales Settlement: {{ $settlement->settlement_number }}
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            @if ($settlement->status === 'draft')
            <a href="{{ route('sales-settlements.edit', $settlement) }}"
                class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </a>
            <form action="{{ route('sales-settlements.post', $settlement->id) }}" method="POST"
                onsubmit="return confirm('Are you sure you want to post this Sales Settlement? This will record sales and update inventory.');"
                class="inline-block">
                @csrf
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                    <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Post Settlement
                </button>
            </form>
            <form action="{{ route('sales-settlements.destroy', $settlement->id) }}" method="POST"
                onsubmit="return confirm('Are you sure you want to delete this draft settlement?');"
                class="inline-block">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                    Delete Draft
                </button>
            </form>
            @endif
            <a href="{{ route('sales-settlements.index') }}"
                class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    {{-- Professional Settlement Header --}}
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 20px;"
                        border="1">
                        <thead>
                            <tr style="background-color: #1e3a5f;">
                                <th colspan="6"
                                    style="border: 1px solid #000; padding: 12px; text-align: center; color: white; font-size: 18px; font-weight: bold; letter-spacing: 1px;">
                                    SALES SETTLEMENT
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold; width: 15%;">
                                    Settlement No.</td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; width: 18%; font-weight: bold; color: #1e3a5f;">
                                    {{ $settlement->settlement_number }}</td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold; width: 15%;">
                                    Settlement Date</td>
                                <td style="border: 1px solid #000; padding: 8px; width: 18%;">{{
                                    \Carbon\Carbon::parse($settlement->settlement_date)->format('d M Y') }}</td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold; width: 15%;">
                                    Status</td>
                                <td style="border: 1px solid #000; padding: 8px; width: 19%;">
                                    <span
                                        style="padding: 4px 12px; border-radius: 4px; font-weight: bold; 
                                        {{ $settlement->status === 'draft' ? 'background-color: #fef3c7; color: #92400e;' : '' }}
                                        {{ $settlement->status === 'posted' ? 'background-color: #d1fae5; color: #065f46;' : '' }}">
                                        {{ strtoupper($settlement->status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold;">
                                    Goods Issue</td>
                                <td style="border: 1px solid #000; padding: 8px; font-weight: bold; color: #1e3a5f;">{{
                                    $settlement->goodsIssue->issue_number }}</td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold;">
                                    Salesman</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $settlement->employee->full_name }}
                                </td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold;">
                                    Vehicle</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{
                                    $settlement->vehicle->vehicle_number }}</td>
                            </tr>
                            <tr>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold;">
                                    Warehouse</td>
                                <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{
                                    $settlement->warehouse->warehouse_name }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <x-detail-table title="Product-wise Settlement" :headers="[
                        ['label' => '#', 'align' => 'text-center'],
                        ['label' => 'Product', 'align' => 'text-left'],
                        ['label' => 'BF In', 'align' => 'text-right'],
                        ['label' => 'Issued', 'align' => 'text-right'],
                        ['label' => 'Batch Breakdown', 'align' => 'text-left'],
                        ['label' => 'Sold', 'align' => 'text-right'],
                        ['label' => 'Returned', 'align' => 'text-right'],
                        ['label' => 'Shortage', 'align' => 'text-right'],
                        ['label' => 'BF Out', 'align' => 'text-right'],
                        ['label' => 'Sales Value', 'align' => 'text-right'],
                    ]">
                        @foreach ($settlement->items as $item)
                        <tr class="border-b border-gray-200 text-sm">
                            <td class="py-1 px-2 text-center">{{ $item->line_no }}</td>
                            <td class="py-1 px-2">
                                <div class="font-semibold text-gray-900">{{ $item->product->product_code }}</div>
                                <div class="text-xs text-gray-500">{{ $item->product->product_name }}</div>
                            </td>
                            <td class="py-1 px-2 text-right">
                                @php
                                // BF In = Total balance forward from previous period for this product
                                // This would be calculated from previous settlement balance or initial stock
                                $bfIn = 0; // You may want to link this from a balance forward calculation
                                @endphp
                                {{ number_format($bfIn, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->quantity_issued, 2) }}</td>
                            <td class="py-1 px-2">
                                @if($item->batches->count() > 0)
                                @if($item->batches->count() === 1)
                                @php $b = $item->batches->first(); @endphp
                                <div class="flex items-center space-x-1">
                                    <span class="font-semibold text-green-600">
                                        {{ number_format($b->quantity_issued, 0) }} × ₨{{
                                        number_format($b->selling_price, 2) }}
                                    </span>
                                    @if($b->is_promotional)
                                    <span
                                        class="px-2 py-1 ml-1 text-xs font-semibold rounded bg-orange-100 text-orange-800">
                                        Promotional
                                    </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-600 mt-1">
                                    Sold: {{ number_format($b->quantity_sold, 0) }} |
                                    Returned: {{ number_format($b->quantity_returned, 0) }} |
                                    Shortage: {{ number_format($b->quantity_shortage, 0) }}
                                </div>
                                @else
                                <div class="space-y-1">
                                    @foreach($item->batches as $b)
                                    <div
                                        class="text-xs border-l-2 pl-2 {{ $b->is_promotional ? 'border-orange-400' : 'border-gray-300' }}">
                                        <div class="flex justify-between">
                                            <span class="text-gray-700 font-medium">
                                                {{ number_format($b->quantity_issued, 0) }} × ₨{{
                                                number_format($b->selling_price, 2) }}
                                                @if($b->is_promotional)
                                                <span title="Promotional">🎁</span>
                                                @endif
                                            </span>
                                            <span class="font-semibold">= ₨{{ number_format($b->quantity_issued *
                                                $b->selling_price, 2) }}</span>
                                        </div>
                                        <div class="text-gray-600 mt-0.5">
                                            S: {{ number_format($b->quantity_sold, 0) }} |
                                            R: {{ number_format($b->quantity_returned, 0) }} |
                                            Sh: {{ number_format($b->quantity_shortage, 0) }}
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                                @else
                                <span class="text-gray-400 text-xs">No batch data</span>
                                @endif
                            </td>
                            <td class="py-1 px-2 text-right font-semibold text-green-700">
                                {{ number_format($item->quantity_sold, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right text-blue-700">
                                {{ number_format($item->quantity_returned, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right text-red-700">
                                {{ number_format($item->quantity_shortage, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right text-orange-700">
                                @php
                                // BF Out = BF In + Issued - Sold - Returned - Shortage
                                $bfOut = $bfIn + $item->quantity_issued - $item->quantity_sold -
                                $item->quantity_returned - $item->quantity_shortage;
                                @endphp
                                {{ number_format($bfOut, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-emerald-600">
                                ₨ {{ number_format($item->total_sales_value, 2) }}
                            </td>
                        </tr>
                        @endforeach

                        <x-slot name="footer">
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="2" class="py-1 px-2 text-right font-bold text-lg">Totals:</td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-purple-700">-</td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-purple-700">
                                    {{ number_format($settlement->items->sum('quantity_issued'), 2) }}
                                </td>
                                <td class="py-1 px-2"></td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-green-700">
                                    {{ number_format($settlement->total_quantity_sold, 2) }}
                                </td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-blue-700">
                                    {{ number_format($settlement->total_quantity_returned, 2) }}
                                </td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-red-700">
                                    {{ number_format($settlement->total_quantity_shortage, 2) }}
                                </td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-orange-700">-</td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-emerald-600">
                                    ₨ {{ number_format($settlement->items->sum('total_sales_value'), 2) }}
                                </td>
                            </tr>
                        </x-slot>
                    </x-detail-table>

                    {{-- Payment Details Tables - Full Width --}}

                    {{-- Credit Sales / Creditors Breakdown --}}
                    @if ($settlement->creditSales->count() > 0)
                    <table style="width:100%; border-collapse: collapse; font-size: 14px; margin-bottom: 16px;"
                        border="1">
                        <thead>
                            <tr style="background-color: #fef3c7;">
                                <th colspan="6"
                                    style="border: 1px solid #000; padding: 8px; text-align: left; font-weight: bold;">
                                    Creditors / Credit Sales ({{ $settlement->creditSales->count() }} transactions)
                                </th>
                            </tr>
                            <tr style="background-color: #f3f4f6;">
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 5%;">#</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 25%;">Customer
                                </th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 15%;">Customer
                                    Code</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 20%;">Salesman
                                </th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 15%;">Invoice
                                    #</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: right; width: 20%;">Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($settlement->creditSales as $index => $creditSale)
                            <tr>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $index + 1 }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{
                                    $creditSale->customer->customer_name }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{
                                    $creditSale->customer->customer_code }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $creditSale->employee->full_name }}
                                </td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $creditSale->invoice_number ?? '-'
                                    }}</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">
                                    ₨ {{ number_format($creditSale->sale_amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background-color: #fef3c7;">
                                <td colspan="5"
                                    style="border: 1px solid #000; padding: 8px; font-weight: bold; text-align: right;">
                                    Total Credit Sales</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">
                                    ₨ {{ number_format($settlement->creditSales->sum('sale_amount'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    @endif

                    {{-- Cheque Details Section --}}
                    @if($settlement->cheques->count() > 0)
                    <table style="width:100%; border-collapse: collapse; font-size: 14px; margin-bottom: 16px;"
                        border="1">
                        <thead>
                            <tr style="background-color: #ede9fe;">
                                <th colspan="7"
                                    style="border: 1px solid #000; padding: 8px; text-align: left; font-weight: bold;">
                                    Cheque Payments ({{ $settlement->cheques->count() }} cheques)
                                </th>
                            </tr>
                            <tr style="background-color: #f3f4f6;">
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 5%;">#</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 15%;">Cheque
                                    No.</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 20%;">Bank
                                    Name</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 12%;">Cheque
                                    Date</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 18%;">Account
                                    Holder</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: right; width: 15%;">Amount
                                </th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: center; width: 15%;">Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlement->cheques as $index => $cheque)
                            <tr>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $index + 1 }}</td>
                                <td style="border: 1px solid #000; padding: 8px; font-family: monospace;">{{
                                    $cheque->cheque_number }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $cheque->bank_name }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $cheque->cheque_date ?
                                    $cheque->cheque_date->format('d M Y') : '-' }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $cheque->account_holder_name ?? '-'
                                    }}</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">
                                    ₨ {{ number_format($cheque->amount, 2) }}</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: center;">
                                    <span style="padding: 2px 8px; border-radius: 4px; font-size: 12px; 
                                        @if($cheque->status === 'cleared') background-color: #d1fae5; color: #065f46;
                                        @elseif($cheque->status === 'bounced') background-color: #fee2e2; color: #991b1b;
                                        @elseif($cheque->status === 'cancelled') background-color: #e5e7eb; color: #374151;
                                        @else background-color: #fef3c7; color: #92400e; @endif">
                                        {{ ucfirst($cheque->status) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background-color: #ede9fe;">
                                <td colspan="5"
                                    style="border: 1px solid #000; padding: 8px; font-weight: bold; text-align: right;">
                                    Total Cheques</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">
                                    ₨ {{ number_format($settlement->cheques->sum('amount'), 2) }}</td>
                                <td style="border: 1px solid #000; padding: 8px;"></td>
                            </tr>
                        </tfoot>
                    </table>
                    @endif

                    {{-- Bank Transfer / Online Payment Details Section --}}
                    @if($settlement->bankTransfers->count() > 0)
                    <table style="width:100%; border-collapse: collapse; font-size: 14px; margin-bottom: 16px;"
                        border="1">
                        <thead>
                            <tr style="background-color: #dbeafe;">
                                <th colspan="6"
                                    style="border: 1px solid #000; padding: 8px; text-align: left; font-weight: bold;">
                                    Bank Transfer / Online Payments ({{ $settlement->bankTransfers->count() }}
                                    transfers)
                                </th>
                            </tr>
                            <tr style="background-color: #f3f4f6;">
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 5%;">#</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 30%;">Bank
                                    Account</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 15%;">Transfer
                                    Date</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 20%;">
                                    Reference Number</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: right; width: 15%;">Amount
                                </th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 15%;">Notes
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlement->bankTransfers as $index => $transfer)
                            <tr>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $index + 1 }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $transfer->bankAccount->bank_name
                                    }} - {{ $transfer->bankAccount->account_name }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $transfer->transfer_date ?
                                    $transfer->transfer_date->format('d M Y') : '-' }}</td>
                                <td style="border: 1px solid #000; padding: 8px; font-family: monospace;">{{
                                    $transfer->reference_number ?? '-' }}</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">
                                    ₨ {{ number_format($transfer->amount, 2) }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $transfer->notes ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background-color: #dbeafe;">
                                <td colspan="4"
                                    style="border: 1px solid #000; padding: 8px; font-weight: bold; text-align: right;">
                                    Total Bank Transfers</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">
                                    ₨ {{ number_format($settlement->bankTransfers->sum('amount'), 2) }}</td>
                                <td style="border: 1px solid #000; padding: 8px;"></td>
                            </tr>
                        </tfoot>
                    </table>
                    @endif

                    {{-- Advance Tax Section --}}
                    @if($settlement->advanceTaxes->count() > 0)
                    <table style="width:100%; border-collapse: collapse; font-size: 14px; margin-bottom: 16px;"
                        border="1">
                        <thead>
                            <tr style="background-color: #fef3c7;">
                                <th colspan="5"
                                    style="border: 1px solid #000; padding: 8px; text-align: left; font-weight: bold;">
                                    Advance Tax Deduction - Account 1171 ({{ $settlement->advanceTaxes->count() }}
                                    entries)
                                </th>
                            </tr>
                            <tr style="background-color: #f3f4f6;">
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 5%;">#</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 35%;">Customer
                                    Name</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left; width: 20%;">Invoice
                                    #</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: right; width: 20%;">Sale
                                    Amount</th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: right; width: 20%;">Tax
                                    Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlement->advanceTaxes as $index => $tax)
                            <tr>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $index + 1 }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $tax->customer->customer_name ??
                                    'N/A' }}</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $tax->invoice_number ?? '-' }}</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: right;">₨ {{
                                    number_format($tax->sale_amount ?? 0, 2) }}</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">
                                    ₨ {{ number_format($tax->tax_amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background-color: #fef3c7;">
                                <td colspan="4"
                                    style="border: 1px solid #000; padding: 8px; font-weight: bold; text-align: right;">
                                    Total Advance Tax</td>
                                <td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">
                                    ₨ {{ number_format($settlement->advanceTaxes->sum('tax_amount'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    @endif

                    {{-- Cash Reconciliation Section --}}
                    <hr class="my-6 border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Cash Reconciliation & Settlement
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        {{-- Left Column: Cash Denomination Breakdown --}}
                        <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                            <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 py-2">
                                <h4 class="text-sm font-bold text-white">Cash Detail (Denomination Breakdown)</h4>
                            </div>
                            <div class="p-4">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-2 px-2 text-left text-gray-700">Denomination</th>
                                            <th class="py-2 px-2 text-right text-gray-700">Quantity</th>
                                            <th class="py-2 px-2 text-right text-gray-700">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php
                                        $denomData = $settlement->cashDenominations->first();
                                        $denominations = [
                                        ['label' => '₨ 5,000 Notes', 'qty' => $denomData?->denom_5000 ?? 0, 'value' =>
                                        5000],
                                        ['label' => '₨ 1,000 Notes', 'qty' => $denomData?->denom_1000 ?? 0, 'value' =>
                                        1000],
                                        ['label' => '₨ 500 Notes', 'qty' => $denomData?->denom_500 ?? 0, 'value' =>
                                        500],
                                        ['label' => '₨ 100 Notes', 'qty' => $denomData?->denom_100 ?? 0, 'value' =>
                                        100],
                                        ['label' => '₨ 50 Notes', 'qty' => $denomData?->denom_50 ?? 0, 'value' => 50],
                                        ['label' => '₨ 20 Notes', 'qty' => $denomData?->denom_20 ?? 0, 'value' => 20],
                                        ['label' => '₨ 10 Notes', 'qty' => $denomData?->denom_10 ?? 0, 'value' => 10],
                                        ['label' => 'Loose Cash/Coins', 'qty' => '-', 'value' =>
                                        $denomData?->denom_coins ?? 0, 'is_coins' => true],
                                        ];
                                        $totalCash = 0;
                                        @endphp
                                        @foreach($denominations as $denom)
                                        @php
                                        if(isset($denom['is_coins']) && $denom['is_coins']) {
                                        $amount = $denom['value'];
                                        } else {
                                        $amount = $denom['qty'] * $denom['value'];
                                        }
                                        $totalCash += $amount;
                                        @endphp
                                        <tr class="{{ $amount > 0 ? 'bg-white' : 'bg-gray-50 text-gray-400' }}">
                                            <td class="py-1.5 px-2">{{ $denom['label'] }}</td>
                                            <td class="py-1.5 px-2 text-right">
                                                @if(isset($denom['is_coins']) && $denom['is_coins'])
                                                -
                                                @else
                                                {{ number_format($denom['qty'], 0) }}
                                                @endif
                                            </td>
                                            <td class="py-1.5 px-2 text-right font-semibold">
                                                ₨ {{ number_format($amount, 2) }}
                                            </td>
                                        </tr>
                                        @endforeach
                                        <tr class="bg-green-100 border-t-2 border-green-300">
                                            <td colspan="2" class="py-2 px-2 text-left font-bold text-green-900">Total
                                                Physical Cash</td>
                                            <td class="py-2 px-2 text-right font-bold text-green-900 text-base">
                                                ₨ {{ number_format($totalCash, 2) }}
                                            </td>
                                        </tr>

                                        {{-- Bank Transfer --}}
                                        @if($settlement->bank_transfer_amount > 0)
                                        <tr class="bg-blue-50">
                                            <td colspan="2" class="py-1.5 px-2">
                                                <span class="font-semibold text-blue-900">Bank Transfer / Online
                                                    Payment</span>
                                                @if($settlement->bankAccount)
                                                <div class="text-xs text-gray-600 mt-0.5">
                                                    {{ $settlement->bankAccount->account_name }} - {{
                                                    $settlement->bankAccount->bank_name }}
                                                </div>
                                                @endif
                                            </td>
                                            <td class="py-1.5 px-2 text-right font-semibold text-blue-900">
                                                ₨ {{ number_format($settlement->bank_transfer_amount, 2) }}
                                            </td>
                                        </tr>
                                        @endif

                                        {{-- Cheques --}}
                                        @if($settlement->cheque_count > 0 && $settlement->cheque_details)
                                        <tr class="bg-purple-50">
                                            <td colspan="3" class="py-2 px-2">
                                                <div class="font-semibold text-purple-900 mb-2">Cheque Details ({{
                                                    $settlement->cheque_count }} cheque(s))</div>
                                                <div class="space-y-1">
                                                    @foreach($settlement->cheque_details as $cheque)
                                                    <div class="text-xs bg-white p-2 rounded border border-purple-200">
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div><span class="font-medium">Cheque #:</span> {{
                                                                $cheque['cheque_number'] ?? 'N/A' }}</div>
                                                            <div><span class="font-medium">Amount:</span> ₨ {{
                                                                number_format($cheque['amount'] ?? 0, 2) }}</div>
                                                            <div><span class="font-medium">Bank:</span> {{
                                                                $cheque['bank_name'] ?? 'N/A' }}</div>
                                                            <div><span class="font-medium">Date:</span> {{
                                                                isset($cheque['cheque_date']) ?
                                                                \Carbon\Carbon::parse($cheque['cheque_date'])->format('d
                                                                M Y') : 'N/A' }}</div>
                                                        </div>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="bg-purple-100">
                                            <td colspan="2" class="py-1.5 px-2 font-semibold text-purple-900">Total
                                                Cheques</td>
                                            <td class="py-1.5 px-2 text-right font-semibold text-purple-900">
                                                ₨ {{ number_format($settlement->cheques_collected, 2) }}
                                            </td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Right Column: Expense Detail --}}
                        <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                            <div class="bg-gradient-to-r from-red-500 to-red-600 px-4 py-2">
                                <h4 class="text-sm font-bold text-white">Expense Detail</h4>
                            </div>
                            <div class="p-4">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-2 px-2 text-left text-gray-700">Expense Account</th>
                                            <th class="py-2 px-2 text-right text-gray-700">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php $totalExpenses = 0; @endphp
                                        @forelse($settlement->expenses as $expense)
                                        @php $totalExpenses += $expense->amount; @endphp
                                        <tr
                                            class="{{ $expense->amount > 0 ? 'bg-white' : 'bg-gray-50 text-gray-400' }}">
                                            <td class="py-1.5 px-2">
                                                @if($expense->expenseAccount)
                                                {{ $expense->expenseAccount->account_name }}
                                                <span class="text-xs text-gray-500">({{
                                                    $expense->expenseAccount->account_code }})</span>
                                                @else
                                                {{ $expense->description ?? 'Unknown Account' }}
                                                @endif
                                            </td>
                                            <td class="py-1.5 px-2 text-right font-semibold">
                                                ₨ {{ number_format($expense->amount, 2) }}
                                            </td>
                                        </tr>
                                        @empty
                                        <tr class="bg-gray-50 text-gray-400">
                                            <td class="py-1.5 px-2" colspan="2">No expenses recorded</td>
                                        </tr>
                                        @endforelse
                                        <tr class="bg-red-100 border-t-2 border-red-300">
                                            <td class="py-2 px-2 text-left font-bold text-red-900">Total Expenses</td>
                                            <td class="py-2 px-2 text-right font-bold text-red-900 text-base">
                                                ₨ {{ number_format($totalExpenses, 2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Third Column: Sales Summary --}}
                        <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2">
                                <h4 class="text-sm font-bold text-white">Sales Summary</h4>
                            </div>
                            <div class="p-4">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1.5 px-2 text-left text-gray-700">Description</th>
                                            <th class="py-1.5 px-2 text-right text-gray-700">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr>
                                            <td class="py-1 px-2">Cash Sales</td>
                                            <td class="py-1 px-2 text-right font-semibold text-green-700">
                                                ₨ {{ number_format($settlement->cash_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 px-2">Cheque Sales</td>
                                            <td class="py-1 px-2 text-right font-semibold text-purple-700">
                                                ₨ {{ number_format($settlement->cheque_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 px-2">Credit Sales</td>
                                            <td class="py-1 px-2 text-right font-semibold text-orange-700">
                                                ₨ {{ number_format($settlement->credit_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-blue-100 border-t-2 border-blue-300">
                                            <td class="py-1.5 px-2 font-bold text-blue-900">Total Sales</td>
                                            <td class="py-1.5 px-2 text-right font-bold text-blue-900 text-base">
                                                ₨ {{ number_format($settlement->total_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 px-2">Credit Recoveries</td>
                                            <td class="py-1 px-2 text-right font-semibold text-teal-700">
                                                ₨ {{ number_format($settlement->credit_recoveries, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-green-50">
                                            <td class="py-1 px-2 font-semibold">Grand Total</td>
                                            <td class="py-1 px-2 text-right font-bold text-green-900">
                                                ₨ {{ number_format($settlement->total_sales_amount +
                                                $settlement->credit_recoveries, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t-2 border-gray-300">
                                            <td class="py-1 px-2 text-red-700">Less: Expenses</td>
                                            <td class="py-1 px-2 text-right font-semibold text-red-700">
                                                ₨ {{ number_format($settlement->expenses_claimed, 2) }}
                                            </td>
                                        </tr>
                                        <tr
                                            class="bg-gradient-to-r from-green-100 to-emerald-100 border-t-2 border-green-300">
                                            <td class="py-2 px-2 font-bold text-green-900">Net Cash to Deposit</td>
                                            <td class="py-2 px-2 text-right font-bold text-green-900 text-lg">
                                                ₨ {{ number_format($settlement->cash_to_deposit, 2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Cash Reconciliation Summary (MOVED BELOW) --}}
                    <div
                        class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-indigo-300 rounded-lg p-4 mb-6">
                        <h4 class="text-base font-bold text-indigo-900 mb-3">Cash Reconciliation Summary</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="bg-white rounded p-3 border border-gray-200">
                                <div class="text-xs text-gray-600 mb-1">Cash Collected (Sales)</div>
                                <div class="text-lg font-bold text-green-700">₨ {{
                                    number_format($settlement->cash_collected, 2) }}</div>
                            </div>
                            <div class="bg-white rounded p-3 border border-gray-200">
                                <div class="text-xs text-gray-600 mb-1">Credit Recoveries</div>
                                <div class="text-lg font-bold text-blue-700">₨ {{
                                    number_format($settlement->credit_recoveries, 2) }}</div>
                            </div>
                            <div class="bg-white rounded p-3 border border-gray-200">
                                <div class="text-xs text-gray-600 mb-1">Total Expenses</div>
                                <div class="text-lg font-bold text-red-700">₨ {{
                                    number_format($settlement->expenses_claimed, 2) }}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div
                                class="bg-gradient-to-r from-green-100 to-emerald-100 rounded p-3 border-2 border-green-500">
                                <div class="text-xs text-gray-700 font-semibold mb-1">Net Cash to Deposit</div>
                                <div class="text-2xl font-bold text-green-900">₨ {{
                                    number_format($settlement->cash_to_deposit, 2) }}</div>
                                <div class="text-xs text-gray-600 mt-1">Cash + Cheques + Recoveries - Expenses</div>
                            </div>
                            <div
                                class="bg-gradient-to-r from-yellow-100 to-amber-100 rounded p-3 border-2 border-yellow-500">
                                <div class="text-xs text-gray-700 font-semibold mb-1">Total Sales Value</div>
                                <div class="text-2xl font-bold text-yellow-900">₨ {{
                                    number_format($settlement->items->sum('total_sales_value'), 2) }}</div>
                            </div>
                            <div
                                class="bg-gradient-to-r from-purple-100 to-indigo-100 rounded p-3 border-2 border-purple-500">
                                <div class="text-xs text-gray-700 font-semibold mb-1">Gross Profit</div>
                                @php
                                $totalCOGS = $settlement->items->sum('total_cogs');
                                $totalSalesValue = $settlement->items->sum('total_sales_value');
                                $grossProfit = $totalSalesValue - $totalCOGS;
                                $grossProfitMargin = $totalSalesValue > 0 ? ($grossProfit / $totalSalesValue) * 100 : 0;
                                @endphp
                                <div
                                    class="text-2xl font-bold {{ $grossProfit >= 0 ? 'text-green-900' : 'text-red-900' }}">
                                    ₨ {{ number_format($grossProfit, 2) }}</div>
                                <div
                                    class="text-xs {{ $grossProfitMargin >= 0 ? 'text-green-700' : 'text-red-700' }} mt-1">
                                    Margin: {{ number_format($grossProfitMargin, 2) }}%
                                </div>
                            </div>
                        </div>
                        {{-- Net Profit Row --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div class="bg-white rounded p-3 border border-gray-200">
                                <div class="text-xs text-gray-600 mb-1">Gross Profit</div>
                                <div
                                    class="text-lg font-bold {{ $grossProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    ₨ {{ number_format($grossProfit, 2) }}</div>
                            </div>
                            <div class="bg-white rounded p-3 border border-gray-200">
                                <div class="text-xs text-gray-600 mb-1">Less: Expenses</div>
                                <div class="text-lg font-bold text-red-700">
                                    ₨ {{ number_format($settlement->expenses_claimed, 2) }}</div>
                            </div>
                            @php
                            $netProfit = $grossProfit - $settlement->expenses_claimed;
                            $netProfitMargin = $totalSalesValue > 0 ? ($netProfit / $totalSalesValue) * 100 : 0;
                            @endphp
                            <div
                                class="bg-gradient-to-r {{ $netProfit >= 0 ? 'from-green-100 to-emerald-100 border-green-500' : 'from-red-100 to-rose-100 border-red-500' }} rounded p-3 border-2">
                                <div class="text-xs text-gray-700 font-semibold mb-1">Net Profit (After Expenses)</div>
                                <div
                                    class="text-2xl font-bold {{ $netProfit >= 0 ? 'text-green-900' : 'text-red-900' }}">
                                    ₨ {{ number_format($netProfit, 2) }}</div>
                                <div
                                    class="text-xs {{ $netProfitMargin >= 0 ? 'text-green-700' : 'text-red-700' }} mt-1">
                                    Net Margin: {{ number_format($netProfitMargin, 2) }}%
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($settlement->notes)
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Notes</h3>
                        <p class="text-sm text-gray-700">{{ $settlement->notes }}</p>
                    </div>
                    @endif

                    @if ($settlement->posted_at)
                    <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                        <p class="text-sm text-green-800">
                            This settlement was posted on {{ $settlement->posted_at->format('d M Y, h:i A') }}
                        </p>
                        @if ($settlement->journalEntry)
                        <p class="text-sm text-green-800 mt-2">
                            Journal Entry: <a href="{{ route('journal-entries.show', $settlement->journalEntry) }}"
                                class="font-semibold underline">{{ $settlement->journalEntry->entry_number }}</a>
                        </p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>