<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
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

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">GRN Number</h3>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $grn->grn_number }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Receipt Date
                            </h3>
                            <p class="text-lg text-gray-900 dark:text-gray-100">
                                {{ \Carbon\Carbon::parse($grn->receipt_date)->format('d M Y') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</h3>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full 
                                {{ $grn->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                {{ $grn->status === 'received' ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $grn->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                {{ ucfirst($grn->status) }}
                            </span>
                        </div>
                    </div>

                    <hr class="my-6 border-gray-200 dark:border-gray-700">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Supplier
                            </h3>
                            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ $grn->supplier->supplier_name }}</p>
                            @if ($grn->supplier_invoice_number)
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Invoice: {{ $grn->supplier_invoice_number }}
                                @if ($grn->supplier_invoice_date)
                                ({{ \Carbon\Carbon::parse($grn->supplier_invoice_date)->format('d M Y') }})
                                @endif
                            </p>
                            @endif
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Warehouse
                            </h3>
                            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ $grn->warehouse->warehouse_name }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Received
                                By
                            </h3>
                            <p class="text-base text-gray-900 dark:text-gray-100">{{ $grn->receivedBy->name ?? 'N/A' }}
                            </p>
                        </div>
                        @if ($grn->verified_by)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Verified
                                By</h3>
                            <p class="text-base text-gray-900 dark:text-gray-100">{{ $grn->verifiedBy->name ?? 'N/A' }}
                            </p>
                        </div>
                        @endif
                    </div>

                    <hr class="my-6 border-gray-200 dark:border-gray-700">

                    @if ($grn->status === 'posted')
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Payment
                                Status</h3>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full 
                                {{ $grn->payment_status === 'unpaid' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $grn->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                {{ $grn->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                {{ ucfirst($grn->payment_status) }}
                            </span>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Total
                                Amount</h3>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">₨ {{
                                number_format($grn->grand_total, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Total Paid
                            </h3>
                            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">₨ {{
                                number_format($grn->total_paid, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Balance
                                Due</h3>
                            <p
                                class="text-lg font-bold {{ $grn->balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                                ₨ {{ number_format($grn->balance, 2) }}
                            </p>
                        </div>
                    </div>

                    @if ($grn->payments()->count() > 0)
                    <div class="mb-6">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Payment
                            History</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th
                                            class="py-1 px-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">
                                            Payment #</th>
                                        <th
                                            class="py-1 px-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">
                                            Date</th>
                                        <th
                                            class="py-1 px-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">
                                            Method</th>
                                        <th
                                            class="py-1 px-2 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">
                                            Status</th>
                                        <th
                                            class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">
                                            Allocated</th>
                                        <th
                                            class="py-1 px-2 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800">
                                    @foreach ($grn->payments()->orderBy('payment_date')->get() as $payment)
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <td class="py-1 px-2">{{ $payment->payment_number }}</td>
                                        <td class="py-1 px-2">{{
                                            \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                                        <td class="py-1 px-2">{{ ucwords(str_replace('_', ' ',
                                            $payment->payment_method)) }}</td>
                                        <td class="py-1 px-2 text-center">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full 
                                                {{ $payment->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                                {{ $payment->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                                {{ ucfirst($payment->status) }}
                                            </span>
                                        </td>
                                        <td class="py-1 px-2 text-right font-semibold">₨ {{
                                            number_format($payment->pivot->allocated_amount, 2) }}</td>
                                        <td class="py-1 px-2 text-center">
                                            <a href="{{ route('supplier-payments.show', $payment->id) }}"
                                                class="text-blue-600 hover:text-blue-800 text-xs">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                    @endif

                    <hr class="my-6 border-gray-200 dark:border-gray-700">

                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Line Items</h3>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th
                                        class="py-1 px-2 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        #</th>
                                    <th
                                        class="py-1 px-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Product</th>
                                    <th
                                        class="py-1 px-2 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        UOM</th>
                                    <th
                                        class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Qty Received</th>
                                    <th
                                        class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Qty Accepted</th>
                                    <th
                                        class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Unit Cost</th>
                                    <th
                                        class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Selling Price</th>
                                    <th
                                        class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grn->items as $item)
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <td class="py-1 px-2 text-center">{{ $item->line_no }}</td>
                                    <td class="py-1 px-2">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $item->product->product_code }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{
                                            $item->product->product_name }}</div>
                                        @if ($item->is_promotional)
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 mt-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-700">
                                            Promotional
                                        </span>
                                        @endif
                                    </td>
                                    <td class="py-1 px-2 text-center">{{ $item->uom->symbol ?? $item->uom->uom_name }}
                                    </td>
                                    <td class="py-1 px-2 text-right">
                                        {{ number_format($item->quantity_received, 2) }}</td>
                                    <td class="py-1 px-2 text-right font-semibold">
                                        {{ number_format($item->quantity_accepted, 2) }}
                                        @if ($item->quantity_rejected > 0)
                                        <div class="text-xs text-red-600">
                                            (Rejected: {{ number_format($item->quantity_rejected, 2) }})
                                        </div>
                                        @endif
                                    </td>
                                    <td class="py-1 px-2 text-right">
                                        ₨ {{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="py-1 px-2 text-right">
                                        @if ($item->selling_price)
                                        ₨ {{ number_format($item->selling_price, 2) }}
                                        @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-1 px-2 text-right font-semibold">
                                        ₨ {{ number_format($item->total_cost, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-900">
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td colspan="7" class="py-1 px-2 text-right font-semibold">Subtotal:</td>
                                    <td class="py-1 px-2 text-right font-semibold">
                                        ₨ {{ number_format($grn->total_amount, 2) }}</td>
                                </tr>
                                @if ($grn->tax_amount > 0)
                                <tr>
                                    <td colspan="7" class="py-1 px-2 text-right">Tax:</td>
                                    <td class="py-1 px-2 text-right">₨ {{ number_format($grn->tax_amount, 2) }}</td>
                                </tr>
                                @endif
                                @if ($grn->freight_charges > 0)
                                <tr>
                                    <td colspan="7" class="py-1 px-2 text-right">Freight:</td>
                                    <td class="py-1 px-2 text-right">₨ {{ number_format($grn->freight_charges, 2) }}
                                    </td>
                                </tr>
                                @endif
                                @if ($grn->other_charges > 0)
                                <tr>
                                    <td colspan="7" class="py-1 px-2 text-right">Other Charges:</td>
                                    <td class="py-1 px-2 text-right">₨ {{ number_format($grn->other_charges, 2) }}
                                    </td>
                                </tr>
                                @endif
                                <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                                    <td colspan="7" class="py-1 px-2 text-right font-bold">Grand Total:</td>
                                    <td class="py-1 px-2 text-right font-bold">
                                        ₨ {{ number_format($grn->grand_total, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if ($grn->notes)
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Notes</h3>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $grn->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmReverseGrn() {
            if (!confirm('Are you sure you want to REVERSE this GRN? All stock entries and draft payments will be reversed. This action cannot be undone.')) {
                return false;
            }

            // Use a simple password prompt (note: browser prompt doesn't support masking)
            // For better security, user should ensure no one is watching their screen
            const password = prompt('🔒 Enter your password to confirm GRN reversal:\n\n(Note: Ensure no one is watching your screen)');
            
            if (!password || password.trim() === '') {
                alert('Password is required to reverse GRN.');
                return false;
            }

            document.getElementById('password').value = password;
            return true;
        }
    </script>
</x-app-layout>