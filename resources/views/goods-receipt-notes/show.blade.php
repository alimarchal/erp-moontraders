<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
                GRN: {{ $grn->grn_number }}
            </h2>

            <div class="flex justify-center items-center space-x-2 no-print">
                @if ($grn->status === 'draft')
                    @can('goods-receipt-note-post')
                        <form action="{{ route('goods-receipt-notes.post', $grn->id) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to post this GRN to inventory? This action cannot be undone.');"
                            class="inline-block">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                                <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Post to Inventory
                            </button>
                        </form>
                    @endcan
                    @can('goods-receipt-note-edit')
                        <a href="{{ route('goods-receipt-notes.edit', $grn->id) }}"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                            Edit
                        </a>
                    @endcan
                @endif
                @if ($grn->status === 'posted')
                    @php
                        $hasPostedPayments = $grn->payments()->where('status', 'posted')->exists();
                    @endphp
                    @if (!$hasPostedPayments)
                        @can('goods-receipt-note-reverse')
                            <form id="reverseGrnForm" action="{{ route('goods-receipt-notes.reverse', $grn->id) }}" method="POST"
                                onsubmit="return confirmReverseGrn();" class="inline-block">
                                @csrf
                                <input type="hidden" id="password" name="password" value="">
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                    <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                    </svg>
                                    Reverse Entry
                                </button>
                            </form>
                        @endcan
                    @endif
                @endif

                <button onclick="window.print();"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                    </svg>
                </button>

                <a href="{{ route('goods-receipt-notes.index') }}"
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
                    size: landscape;

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

                .max-w-8xl,
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
                    font-size: 10px !important;
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
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md no-print" />

            <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
                <div class="overflow-x-auto">

                    <p class="text-center font-extrabold mb-2">
                        {{ config('app.name') }}<br>
                        Goods Receipt Note<br>
                        <span class="print-only print-info text-xs text-center">
                            Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                        </span>
                    </p>

                    <table class="header-table w-full mb-4" style="border-collapse: collapse; font-size: 13px;">
                        <tr>
                            <td class="py-1 px-2 font-semibold" style="width: 15%;">GRN Number:</td>
                            <td class="py-1 px-2 font-bold" style="width: 35%;">{{ $grn->grn_number }}</td>
                            <td class="py-1 px-2 font-semibold" style="width: 15%;">Warehouse:</td>
                            <td class="py-1 px-2" style="width: 35%;">{{ $grn->warehouse->warehouse_name }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 px-2 font-semibold">Supplier:</td>
                            <td class="py-1 px-2">{{ $grn->supplier->supplier_name }}</td>
                            <td class="py-1 px-2 font-semibold">Receipt Date:</td>
                            <td class="py-1 px-2">{{ \Carbon\Carbon::parse($grn->receipt_date)->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 px-2 font-semibold">Invoice #:</td>
                            <td class="py-1 px-2">{{ $grn->supplier_invoice_number ?? '—' }}</td>
                            <td class="py-1 px-2 font-semibold">Invoice Date:</td>
                            <td class="py-1 px-2">
                                {{ $grn->supplier_invoice_date ? \Carbon\Carbon::parse($grn->supplier_invoice_date)->format('d M Y') : '—' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1 px-2 font-semibold">Received By:</td>
                            <td class="py-1 px-2">{{ $grn->receivedBy->name ?? 'N/A' }}</td>
                            <td class="py-1 px-2 font-semibold">Status:</td>
                            <td class="py-1 px-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $grn->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                    {{ $grn->status === 'posted' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $grn->status === 'reversed' ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ ucfirst($grn->status) }}
                                </span>
                            </td>
                        </tr>
                        @if($grn->status === 'posted')
                        <tr>
                            <td class="py-1 px-2 font-semibold">Grand Total:</td>
                            <td class="py-1 px-2 font-bold text-emerald-600">
                                ₨ {{ number_format($grn->items->sum('total_value_with_taxes') ?: $grn->grand_total, 2) }}
                            </td>
                            <td class="py-1 px-2 font-semibold">Payment Status:</td>
                            <td class="py-1 px-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $grn->payment_status === 'unpaid' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $grn->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $grn->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                    {{ ucfirst($grn->payment_status) }}
                                </span>
                            </td>
                        </tr>
                        @endif
                    </table>

                    {{-- GRN Items Table --}}
                    @if($grn->items->count() > 0)
                        <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-center">#</th>
                                    <th>Product</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">UP/Case</th>
                                    <th class="text-right">Ext. Value</th>
                                    <th class="text-right">Discount</th>
                                    <th class="text-right">FMR</th>
                                    <th class="text-right">Before Tax</th>
                                    <th class="text-right">Excise</th>
                                    <th class="text-right">Sales Tax</th>
                                    <th class="text-right">Adv. IT</th>
                                    <th class="text-right">Qty Rec</th>
                                    <th class="text-right">Unit Cost</th>
                                    <th class="text-right">Sell Price</th>
                                    <th class="text-right">Total W/Tax</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grn->items as $item)
                                    <tr>
                                        <td class="text-center" style="vertical-align: middle;">{{ $item->line_no }}</td>
                                        <td style="vertical-align: middle;">
                                            <span class="font-semibold">{{ $item->product->product_code }}</span>
                                            <br>
                                            <span class="text-xs text-gray-500">{{ $item->product->product_name }}</span>
                                            @if ($item->is_promotional)
                                                <br>
                                                <span class="text-xs text-orange-600 font-semibold">Promotional</span>
                                            @endif
                                            @if ($item->batch_number || $item->lot_number)
                                                <br>
                                                <span class="text-xs text-gray-500">
                                                    @if ($item->batch_number)
                                                        Batch: {{ $item->batch_number }}
                                                    @endif
                                                    @if ($item->lot_number)
                                                        | Lot: {{ $item->lot_number }}
                                                    @endif
                                                </span>
                                            @endif
                                            @if ($item->expiry_date)
                                                <br>
                                                <span class="text-xs text-gray-500">
                                                    Exp: {{ \Carbon\Carbon::parse($item->expiry_date)->format('d M Y') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">{{ number_format($item->qty_in_purchase_uom ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">{{ number_format($item->unit_price_per_case ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">{{ number_format($item->extended_value ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">{{ number_format($item->discount_value ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">{{ number_format($item->fmr_allowance ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums font-semibold" style="vertical-align: middle;">{{ number_format($item->discounted_value_before_tax ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">{{ number_format($item->excise_duty ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">{{ number_format($item->sales_tax_value ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">{{ number_format($item->advance_income_tax ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">
                                            {{ number_format($item->quantity_received, 2) }}
                                            @if ($item->quantity_rejected > 0)
                                                <br>
                                                <span class="text-xs text-red-600">(Rej: {{ number_format($item->quantity_rejected, 2) }})</span>
                                            @endif
                                        </td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">{{ number_format($item->unit_cost, 2) }}</td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">
                                            @if ($item->selling_price)
                                                {{ number_format($item->selling_price, 2) }}
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="text-right tabular-nums font-bold text-emerald-600" style="vertical-align: middle;">
                                            {{ number_format($item->total_value_with_taxes ?? $item->total_cost, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td colspan="14" class="text-right px-2 py-1">Grand Total</td>
                                    <td class="text-right tabular-nums px-2 py-1 text-emerald-600">
                                        ₨ {{ number_format($grn->items->sum('total_value_with_taxes') ?: $grn->grand_total, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif

                    @if ($grn->notes)
                        <div class="mt-4 mb-2">
                            <span class="font-semibold text-gray-700">Notes:</span>
                            <span class="text-sm text-gray-700">{{ $grn->notes }}</span>
                        </div>
                    @endif

                    {{-- Payment History --}}
                    @if ($grn->payments()->count() > 0)
                        <p class="text-center font-extrabold mt-6 mb-2">Payment History</p>

                        <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th>Payment #</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-right">Allocated</th>
                                    <th class="text-center no-print">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grn->payments()->orderBy('payment_date')->get() as $payment)
                                    <tr>
                                        <td style="vertical-align: middle;">{{ $payment->payment_number }}</td>
                                        <td style="vertical-align: middle;">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                                        <td style="vertical-align: middle;">{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                        <td class="text-center" style="vertical-align: middle;">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                {{ $payment->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                                {{ $payment->status === 'posted' ? 'bg-green-100 text-green-800' : '' }}">
                                                {{ ucfirst($payment->status) }}
                                            </span>
                                        </td>
                                        <td class="text-right tabular-nums font-semibold" style="vertical-align: middle;">
                                            {{ number_format($payment->pivot->allocated_amount, 2) }}
                                        </td>
                                        <td class="text-center no-print" style="vertical-align: middle;">
                                            <a href="{{ route('supplier-payments.show', $payment->id) }}"
                                                class="text-blue-600 hover:text-blue-800 font-semibold text-xs">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    {{-- Payment Summary --}}
                    @if ($grn->status === 'posted')
                        <table class="header-table w-full mt-4" style="border-collapse: collapse; font-size: 13px;">
                            <tr>
                                <td class="py-1 px-2 font-semibold" style="width: 15%;">Payment Status:</td>
                                <td class="py-1 px-2" style="width: 35%;">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $grn->payment_status === 'unpaid' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $grn->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                        {{ $grn->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                        {{ ucfirst($grn->payment_status) }}
                                    </span>
                                </td>
                                <td class="py-1 px-2 font-semibold" style="width: 15%;">Total Amount:</td>
                                <td class="py-1 px-2 font-bold" style="width: 35%;">₨ {{ number_format($grn->grand_total, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="py-1 px-2 font-semibold">Total Paid:</td>
                                <td class="py-1 px-2 font-bold text-emerald-600">₨ {{ number_format($grn->total_paid, 2) }}</td>
                                <td class="py-1 px-2 font-semibold">Balance Due:</td>
                                <td class="py-1 px-2 font-bold {{ $grn->balance > 0 ? 'text-red-600' : 'text-gray-900' }}">
                                    ₨ {{ number_format($grn->balance, 2) }}
                                </td>
                            </tr>
                        </table>
                    @endif

                </div>
            </div>
        </div>
    </div>

    <!-- Password Modal -->
    <x-password-confirm-modal id="reverseGrnModal" title="Confirm GRN Reversal"
        message="WARNING: This will reverse all stock entries and draft payments. This action cannot be undone."
        warningClass="text-red-600" confirmButtonText="Confirm Reverse"
        confirmButtonClass="bg-red-600 hover:bg-red-700" />

    <script>
        function confirmReverseGrn() {
            if (!confirm('Are you sure you want to REVERSE this GRN? All stock entries and draft payments will be reversed. This action cannot be undone.')) {
                return false;
            }

            window.showPasswordModal('reverseGrnModal');
            return false;
        }

        // Listen for password confirmation event
        document.addEventListener('passwordConfirmed', function (event) {
            const { modalId, password } = event.detail;

            if (modalId === 'reverseGrnModal') {
                document.getElementById('password').value = password;
                document.getElementById('reverseGrnForm').submit();
            }
        });
    </script>
</x-app-layout>
