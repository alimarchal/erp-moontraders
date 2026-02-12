<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
                Stock by Batch: {{ $product?->product_code }}
            </h2>

            <div class="flex justify-center items-center space-x-2 no-print">
                <button onclick="window.print();"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                    </svg>
                </button>

                <a href="{{ route('inventory.current-stock.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
            </div>
        </div>
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 14px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;

                    @bottom-center {
                        content: "Page " counter(page) " of " counter(pages);
                    }
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    counter-reset: page 1;
                }

                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 10px !important;
                    box-shadow: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 11px !important;
                    width: 100% !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }

                .text-emerald-600 {
                    color: #000 !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 8px !important;
                }

                .print-info {
                    font-size: 9px !important;
                    margin-top: 5px !important;
                    margin-bottom: 10px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }

                .page-footer {
                    display: none;
                }

                .header-table {
                    font-size: 11px !important;
                }
            }
        </style>
    @endpush

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md no-print" />

            <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
                <div class="overflow-x-auto">

                    <p class="text-center font-extrabold mb-2">
                        {{ config('app.name') }}<br>
                        Stock by Batch Report<br>
                        <span class="print-only print-info text-xs text-center">
                            Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                        </span>
                    </p>

                    <table class="header-table w-full mb-4" style="border-collapse: collapse; font-size: 13px;">
                        <tr>
                            <td class="py-1 px-2 font-semibold" style="width: 15%;">Product Code:</td>
                            <td class="py-1 px-2 font-bold" style="width: 35%;">{{ $product?->product_code }}</td>
                            <td class="py-1 px-2 font-semibold" style="width: 15%;">Warehouse:</td>
                            <td class="py-1 px-2" style="width: 35%;">{{ $warehouse?->warehouse_name }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 px-2 font-semibold">Product Name:</td>
                            <td class="py-1 px-2">{{ $product?->product_name }}</td>
                            <td class="py-1 px-2 font-semibold">Total Stock:</td>
                            <td class="py-1 px-2 font-bold">{{ number_format($currentStock->quantity_on_hand ?? 0, 2) }}
                            </td>
                        </tr>
                        @if($currentStock)
                            <tr>
                                <td class="py-1 px-2 font-semibold">Qty Available:</td>
                                <td class="py-1 px-2">{{ number_format($currentStock->quantity_available, 2) }}</td>
                                <td class="py-1 px-2 font-semibold">Average Cost:</td>
                                <td class="py-1 px-2">₨ {{ number_format($currentStock->average_cost, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="py-1 px-2 font-semibold">Total Value:</td>
                                <td class="py-1 px-2 font-bold">₨ {{ number_format($currentStock->total_value, 2) }}</td>
                                <td class="py-1 px-2 font-semibold">Batches:</td>
                                <td class="py-1 px-2">
                                    {{ $currentStock->total_batches }}
                                    @if($currentStock->promotional_batches > 0)
                                        <span class="text-xs text-orange-600">({{ $currentStock->promotional_batches }}
                                            promotional)</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </table>

                    @php
                        $grandTotalQty = $batches->sum('quantity_on_hand');
                        $grandTotalValue = $batches->sum('total_value');
                    @endphp

                    @if($batches->count() > 0)
                        <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th>Sr#</th>
                                    <th>Batch Code</th>
                                    <th>Receipt Date</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Selling Price</th>
                                    <th>Total Value</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($batches as $index => $batch)
                                    <tr>
                                        <td class="text-center" style="vertical-align: middle;">{{ $index + 1 }}</td>
                                        <td style="vertical-align: middle;">
                                            <span class="font-semibold">{{ $batch->stockBatch->batch_code }}</span>
                                            @if($batch->is_promotional)
                                                <br>
                                                <span class="text-xs text-orange-600 font-semibold">🎁 Promotional</span>
                                                @if($batch->promotional_price)
                                                    <br>
                                                    <span class="text-xs text-orange-600">Promo: ₨
                                                        {{ number_format($batch->promotional_price, 2) }}</span>
                                                @endif
                                            @endif
                                            @if($batch->must_sell_before)
                                                <br>
                                                <span class="text-xs text-red-600">Sell by:
                                                    {{ \Carbon\Carbon::parse($batch->must_sell_before)->format('d M Y') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center" style="vertical-align: middle;">
                                            {{ \Carbon\Carbon::parse($batch->stockBatch->receipt_date)->format('d-M-Y') }}
                                        </td>
                                        <td class="text-center tabular-nums font-semibold" style="vertical-align: middle;">
                                            {{ number_format($batch->quantity_on_hand, 2) }}
                                        </td>
                                        <td class="text-center tabular-nums" style="vertical-align: middle;">
                                            ₨{{ number_format($batch->unit_cost, 2) }}
                                        </td>
                                        <td class="text-center tabular-nums" style="vertical-align: middle;">
                                            @if($batch->stockBatch->selling_price)
                                                ₨{{ number_format($batch->stockBatch->selling_price, 2) }}
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center tabular-nums font-bold text-emerald-600"
                                            style="vertical-align: middle;">
                                            ₨{{ number_format($batch->total_value, 2) }}
                                        </td>
                                        <td class="text-center" style="vertical-align: middle;">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $batch->priority_order < 50 ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ $batch->priority_order }}
                                            </span>
                                        </td>
                                        <td class="text-center" style="vertical-align: middle;">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $batch->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ ucfirst($batch->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td colspan="3" class="text-center px-2 py-1">Grand Total</td>
                                    <td class="text-center tabular-nums px-2 py-1">{{ number_format($grandTotalQty, 2) }}
                                    </td>
                                    <td class="px-2 py-1"></td>
                                    <td class="px-2 py-1"></td>
                                    <td class="text-center tabular-nums px-2 py-1 text-emerald-600">
                                        ₨{{ number_format($grandTotalValue, 2) }}</td>
                                    <td class="px-2 py-1"></td>
                                    <td class="px-2 py-1"></td>
                                </tr>
                            </tfoot>
                        </table>
                    @else
                        <p class="text-gray-700 text-center py-4">No stock batches found.</p>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>