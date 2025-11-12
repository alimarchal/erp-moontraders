<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            GRN: {{ $grn->grn_number }}
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            @if ($grn->status === 'draft')
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
            <a href="{{ route('goods-receipt-notes.edit', $grn->id) }}"
                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                Edit
            </a>
            @endif
            @if ($grn->status === 'posted')
            @php
            $hasPostedPayments = $grn->payments()->where('status', 'posted')->exists();
            @endphp
            @if (!$hasPostedPayments)
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
            @endif
            @endif
            <a href="{{ route('goods-receipt-notes.index') }}"
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
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">GRN Number</h3>
                            <p class="text-lg font-bold text-gray-900">{{ $grn->grn_number }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Receipt Date
                            </h3>
                            <p class="text-lg text-gray-900">
                                {{ \Carbon\Carbon::parse($grn->receipt_date)->format('d M Y') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Status</h3>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full 
                                {{ $grn->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                {{ $grn->status === 'received' ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $grn->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                {{ ucfirst($grn->status) }}
                            </span>
                        </div>
                    </div>

                    <hr class="my-6 border-gray-200">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Supplier
                            </h3>
                            <p class="text-base font-semibold text-gray-900">
                                {{ $grn->supplier->supplier_name }}</p>
                            @if ($grn->supplier_invoice_number)
                            <p class="text-sm text-gray-600">
                                Invoice: {{ $grn->supplier_invoice_number }}
                                @if ($grn->supplier_invoice_date)
                                ({{ \Carbon\Carbon::parse($grn->supplier_invoice_date)->format('d M Y') }})
                                @endif
                            </p>
                            @endif
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Warehouse
                            </h3>
                            <p class="text-base font-semibold text-gray-900">
                                {{ $grn->warehouse->warehouse_name }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Received
                                By
                            </h3>
                            <p class="text-base text-gray-900">{{ $grn->receivedBy->name ?? 'N/A' }}
                            </p>
                        </div>
                        @if ($grn->verified_by)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Verified
                                By</h3>
                            <p class="text-base text-gray-900">{{ $grn->verifiedBy->name ?? 'N/A' }}
                            </p>
                        </div>
                        @endif
                    </div>

                    <hr class="my-6 border-gray-200">

                    @if ($grn->status === 'posted')
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Payment
                                Status</h3>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full 
                                {{ $grn->payment_status === 'unpaid' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $grn->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                {{ $grn->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                {{ ucfirst($grn->payment_status) }}
                            </span>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Total
                                Amount</h3>
                            <p class="text-lg font-bold text-gray-900">₨ {{
                                number_format($grn->grand_total, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Total Paid
                            </h3>
                            <p class="text-lg font-bold text-emerald-600">₨ {{
                                number_format($grn->total_paid, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Balance
                                Due</h3>
                            <p class="text-lg font-bold {{ $grn->balance > 0 ? 'text-red-600' : 'text-gray-900' }}">
                                ₨ {{ number_format($grn->balance, 2) }}
                            </p>
                        </div>
                    </div>

                    @if ($grn->payments()->count() > 0)
                    <div class="mb-6">
                        <x-detail-table title="Payment History" :headers="[
                            ['label' => 'Payment #', 'align' => 'text-left'],
                            ['label' => 'Date', 'align' => 'text-left'],
                            ['label' => 'Method', 'align' => 'text-left'],
                            ['label' => 'Status', 'align' => 'text-center'],
                            ['label' => 'Allocated', 'align' => 'text-right'],
                            ['label' => 'Action', 'align' => 'text-center'],
                        ]">
                            @foreach ($grn->payments()->orderBy('payment_date')->get() as $payment)
                            <tr class="border-b border-gray-200 text-sm">
                                <td class="py-1 px-2">{{ $payment->payment_number }}</td>
                                <td class="py-1 px-2">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y')
                                    }}</td>
                                <td class="py-1 px-2">{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}
                                </td>
                                <td class="py-1 px-2 text-center">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        {{ $payment->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                        {{ $payment->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </td>
                                <td class="py-1 px-2 text-right font-semibold">{{
                                    number_format($payment->pivot->allocated_amount, 2) }}</td>
                                <td class="py-1 px-2 text-center">
                                    <a href="{{ route('supplier-payments.show', $payment->id) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                                        title="View Payment">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </x-detail-table>
                    </div>
                    @endif
                    @endif

                    <hr class="my-6 border-gray-200">

                    <x-detail-table title="Line Items" :headers="[
                        ['label' => '#', 'align' => 'text-center'],
                        ['label' => 'Product', 'align' => 'text-left'],
                        ['label' => 'Qty Cases', 'align' => 'text-right'],
                        ['label' => 'Unit Price/Case', 'align' => 'text-right'],
                        ['label' => 'Extended Value', 'align' => 'text-right'],
                        ['label' => 'Discount', 'align' => 'text-right'],
                        ['label' => 'FMR Allowance', 'align' => 'text-right'],
                        ['label' => 'Value Before Tax', 'align' => 'text-right'],
                        ['label' => 'Excise Duty', 'align' => 'text-right'],
                        ['label' => 'Sales Tax', 'align' => 'text-right'],
                        ['label' => 'Adv. Income Tax', 'align' => 'text-right'],
                        ['label' => 'Qty Received', 'align' => 'text-right'],
                        ['label' => 'Unit Cost', 'align' => 'text-right'],
                        ['label' => 'Selling Price', 'align' => 'text-right'],
                        ['label' => 'Total Value w/Taxes', 'align' => 'text-right'],
                    ]">
                        @foreach ($grn->items as $item)
                        <tr class="border-b border-gray-200 text-sm">
                            <td class="py-1 px-2 text-center">{{ $item->line_no }}</td>
                            <td class="py-1 px-2">
                                <div class="font-semibold text-gray-900">{{ $item->product->product_code }}</div>
                                <div class="text-xs text-gray-500">{{ $item->product->product_name }}</div>
                                @if ($item->is_promotional)
                                <span
                                    class="px-2 py-1 mt-1 text-xs font-semibold rounded bg-orange-100 text-orange-800">
                                    Promotional
                                </span>
                                @endif
                                @if ($item->batch_number || $item->lot_number)
                                <div class="text-xs text-gray-500 mt-1">
                                    @if ($item->batch_number)
                                    Batch: {{ $item->batch_number }}
                                    @endif
                                    @if ($item->lot_number)
                                    | Lot: {{ $item->lot_number }}
                                    @endif
                                </div>
                                @endif
                                @if ($item->expiry_date)
                                <div class="text-xs text-gray-500">
                                    Exp: {{ \Carbon\Carbon::parse($item->expiry_date)->format('d M Y') }}
                                </div>
                                @endif
                            </td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->qty_cases ?? 0, 2) }}</td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->unit_price_per_case ?? 0, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->extended_value ?? 0, 2) }}</td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->discount_value ?? 0, 2) }}</td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->fmr_allowance ?? 0, 2) }}</td>
                            <td class="py-1 px-2 text-right font-semibold">{{
                                number_format($item->discounted_value_before_tax ?? 0, 2) }}</td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->excise_duty ?? 0, 2) }}</td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->sales_tax_value ?? 0, 2) }}</td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->advance_income_tax ?? 0, 2) }}</td>
                            <td class="py-1 px-2 text-right">
                                {{ number_format($item->quantity_received, 2) }}
                                @if ($item->quantity_rejected > 0)
                                <div class="text-xs text-red-600">
                                    (Rej: {{ number_format($item->quantity_rejected, 2) }})
                                </div>
                                @endif
                            </td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->unit_cost, 2) }}</td>
                            <td class="py-1 px-2 text-right">
                                @if ($item->selling_price)
                                {{ number_format($item->selling_price, 2) }}
                                @else
                                <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-emerald-600">{{
                                number_format($item->total_value_with_taxes ?? $item->total_cost, 2) }}</td>
                        </tr>
                        @endforeach

                        <x-slot name="footer">
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="14" class="py-1 px-2 text-right font-bold text-lg">Grand Total:</td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-emerald-600">
                                    ₨ {{ number_format($grn->items->sum('total_value_with_taxes') ?: $grn->grand_total,
                                    2) }}
                                </td>
                            </tr>
                        </x-slot>
                    </x-detail-table>

                    @if ($grn->notes)
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Notes</h3>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $grn->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Password Modal -->
    <x-password-confirm-modal id="reverseGrnModal" title="Confirm GRN Reversal"
        message="⚠️ WARNING: This will reverse all stock entries and draft payments. This action cannot be undone."
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
        document.addEventListener('passwordConfirmed', function(event) {
            const { modalId, password } = event.detail;
            
            if (modalId === 'reverseGrnModal') {
                document.getElementById('password').value = password;
                document.getElementById('reverseGrnForm').submit();
            }
        });
    </script>
</x-app-layout>