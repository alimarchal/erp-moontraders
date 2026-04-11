<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
                Goods Issue: {{ $goodsIssue->issue_number }}
            </h2>

            <div class="flex justify-center items-center space-x-2 no-print">
                @if ($goodsIssue->status === 'draft')
                    @can('goods-issue-post')
                        <button type="button" x-data
                            @click="$dispatch('open-post-gi-modal', { url: '{{ route('goods-issues.post', $goodsIssue->id) }}' })"
                            class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                            <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Post Issue
                        </button>
                    @endcan
                    @can('goods-issue-edit')
                        <a href="{{ route('goods-issues.edit', $goodsIssue->id) }}"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                            Edit
                        </a>
                    @endcan
                @endif

                @if ($goodsIssue->canAcceptSupplementaryItems())
                    @can('goods-issue-edit')
                        <button type="button" x-data
                            @click="$dispatch('open-append-items-confirm', { url: '{{ route('goods-issues.append-items', $goodsIssue->id) }}', issueNumber: '{{ $goodsIssue->issue_number }}', existingLineCount: {{ $goodsIssue->items->count() }} })"
                            class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 transition">
                            <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add More Items
                        </button>
                    @endcan
                @endif

                <button onclick="window.print();"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                    </svg>
                </button>

                <a href="{{ route('goods-issues.index') }}"
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
                        Goods Issue Note<br>
                        <span class="print-only print-info text-xs text-center">
                            Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                        </span>
                    </p>

                    <table class="header-table w-full mb-4" style="border-collapse: collapse; font-size: 13px;">
                        <tr>
                            <td class="py-1 px-2 font-semibold" style="width: 15%;">Issue Number:</td>
                            <td class="py-1 px-2 font-bold" style="width: 35%;">{{ $goodsIssue->issue_number }}</td>
                            <td class="py-1 px-2 font-semibold" style="width: 15%;">Issue Date:</td>
                            <td class="py-1 px-2" style="width: 35%;">
                                {{ \Carbon\Carbon::parse($goodsIssue->issue_date)->format('d-M-Y') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1 px-2 font-semibold">Status:</td>
                            <td class="py-1 px-2">
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full
                                    {{ $goodsIssue->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                    {{ $goodsIssue->status === 'issued' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                    {{ ucfirst($goodsIssue->status) }}
                                </span>
                            </td>
                            <td class="py-1 px-2 font-semibold">Warehouse:</td>
                            <td class="py-1 px-2">{{ $goodsIssue->warehouse->warehouse_name }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 px-2 font-semibold">Salesman:</td>
                            <td class="py-1 px-2">{{ $goodsIssue->employee->name }}
                                ({{ $goodsIssue->employee->employee_code }})</td>
                            <td class="py-1 px-2 font-semibold">Vehicle:</td>
                            <td class="py-1 px-2">{{ $goodsIssue->vehicle->vehicle_number }}
                                ({{ $goodsIssue->vehicle->vehicle_type }})</td>
                        </tr>
                        <tr>
                            <td class="py-1 px-2 font-semibold">Supplier:</td>
                            <td class="py-1 px-2">{{ $goodsIssue->supplier->supplier_name ?? 'N/A' }}</td>
                            @if ($goodsIssue->posted_at)
                                <td class="py-1 px-2 font-semibold">Posted At:</td>
                                <td class="py-1 px-2">{{ $goodsIssue->posted_at->format('d-M-Y h:i A') }}</td>
                            @else
                                <td class="py-1 px-2" colspan="2"></td>
                            @endif
                        </tr>
                        @if ($goodsIssue->notes)
                            <tr>
                                <td class="py-1 px-2 font-semibold">Notes:</td>
                                <td class="py-1 px-2" colspan="3">{{ $goodsIssue->notes }}</td>
                            </tr>
                        @endif
                    </table>

                    @php
                        $grandTotal = $goodsIssue->items->sum(function ($item) {
                            return $item->calculated_total ?? $item->total_value;
                        });
                        $totalQty = $goodsIssue->items->sum('quantity_issued');
                    @endphp

                    <table class="report-table">
                        <thead>
                            <tr class="bg-gray-50">
                                <th style="width: 40px;">Sr#</th>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Qty Issued</th>
                                <th>UOM</th>
                                <th>Batch Breakdown</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($goodsIssue->items as $item)
                                <tr>
                                    <td class="text-center" style="vertical-align: middle;">{{ $item->line_no }}</td>
                                    <td style="vertical-align: middle;" class="font-semibold">
                                        {{ $item->product->product_code }}
                                    </td>
                                    <td style="vertical-align: middle;">
                                        {{ $item->product->product_name }}
                                        @if($item->exclude_promotional)
                                            <span
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700 print:border print:border-indigo-300">Non-Promo</span>
                                        @endif
                                        @if($item->is_supplementary)
                                            <span
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 print:border print:border-purple-300">Supplementary</span>
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums" style="vertical-align: middle;">
                                        {{ number_format($item->quantity_issued, 2) }}
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">{{ $item->uom->uom_name }}</td>
                                    <td style="vertical-align: middle;">
                                        @if(isset($item->batch_breakdown) && count($item->batch_breakdown) > 0)
                                            @if(count($item->batch_breakdown) === 1)
                                                @php $b = $item->batch_breakdown[0]; @endphp
                                                <span class="font-semibold text-emerald-600">
                                                    {{ number_format($b['quantity'], 0) }} ×
                                                    ₨{{ number_format($b['selling_price'], 2) }}
                                                    = ₨{{ number_format($b['value'], 2) }}
                                                </span>
                                                @if($b['is_promotional'])
                                                    <span class="text-xs text-orange-600 font-semibold">🎁 Promo</span>
                                                @endif
                                            @else
                                                @foreach($item->batch_breakdown as $b)
                                                    <div class="text-xs">
                                                        {{ number_format($b['quantity'], 0) }} ×
                                                        ₨{{ number_format($b['selling_price'], 2) }}
                                                        = ₨{{ number_format($b['value'], 2) }}
                                                        @if($b['is_promotional'])
                                                            🎁
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif
                                        @else
                                            <span class="text-gray-400 text-xs">Avg:
                                                ₨{{ number_format($item->unit_cost, 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right  tabular-nums font-bold text-emerald-600"
                                        style="vertical-align: middle;">
                                        ₨{{ number_format($item->calculated_total ?? $item->total_value, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="3" class="text-center px-2 py-1">Grand Total</td>
                                <td class="text-right tabular-nums px-2 py-1">{{ number_format($totalQty, 2) }}</td>
                                <td class="px-2 py-1"></td>
                                <td class="px-2 py-1"></td>
                                <td class="text-right tabular-nums px-2 py-1 text-emerald-600">
                                    ₨{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>

                </div>
            </div>
        </div>
    </div>

    <x-alpine-confirmation-modal event-name="open-post-gi-modal" title="Post Goods Issue"
        message="Are you sure you want to post this Goods Issue? This will transfer inventory from warehouse to vehicle, lock the vehicle until settlement, and create the journal entries. Do you agree?"
        confirm-button-text="Yes, Post Issue" confirm-button-class="bg-emerald-600 hover:bg-emerald-700"
        icon-bg-class="bg-emerald-100" icon-color-class="text-emerald-600" icon-path="M5 13l4 4L19 7" />

    {{-- Append-items navigation confirmation: clicking "Add More Items" opens this modal first
         so the user explicitly agrees they intend to add items to *this* GI (rather than create a new one). --}}
    <div x-data="{ show: false, navigateUrl: '', issueNumber: '', existingLineCount: 0 }"
         x-on:open-append-items-confirm.window="show = true; navigateUrl = $event.detail.url; issueNumber = $event.detail.issueNumber; existingLineCount = $event.detail.existingLineCount"
         x-on:keydown.escape.window="if (show) { show = false }"
         x-show="show"
         x-cloak
         class="fixed inset-0 z-50"
         style="display: none;">
        <div x-show="show"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 backdrop-blur-none" x-transition:enter-end="opacity-100 backdrop-blur-sm"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 backdrop-blur-sm" x-transition:leave-end="opacity-0 backdrop-blur-none"
             class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all" @click="show = false"></div>

        <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
            <div x-show="show"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
                 @click.outside="show = false">

                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-purple-100 sm:mx-0 sm:size-10">
                            <svg class="size-6 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Add Items to Existing Goods Issue</h3>
                            <div class="mt-2 text-sm text-gray-600">
                                <p>You are about to add supplementary items to <strong x-text="issueNumber"></strong>, which already has <strong x-text="existingLineCount"></strong> line(s).</p>
                                <p class="mt-2">New items will be appended to <em>this same</em> Goods Issue and posted as a separate stock movement. Do you agree?</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-row justify-end gap-3 bg-gray-100 px-6 py-4">
                    <button type="button" @click="show = false"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" @click="window.location.href = navigateUrl"
                        class="inline-flex items-center rounded-md border border-transparent bg-purple-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-purple-700">
                        Yes, Add More Items
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>