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
            <form action="{{ route('goods-receipt-notes.reverse', $grn->id) }}" method="POST"
                onsubmit="return confirm('Are you sure you want to REVERSE this GRN? All stock entries will be reversed. This action cannot be undone.');"
                class="inline-block">
                @csrf
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

                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Line Items</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product
                                    </th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">UOM
                                    </th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty
                                        Received</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty
                                        Accepted</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit
                                        Cost
                                    </th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Selling
                                        Price</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($grn->items as $item)
                                <tr>
                                    <td class="px-3 py-2 text-sm">{{ $item->line_no }}</td>
                                    <td class="px-3 py-2 text-sm">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $item->product->product_code }}</div>
                                        <div class="text-xs text-gray-500">{{ $item->product->product_name }}</div>
                                        @if ($item->is_promotional)
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 mt-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-700">
                                            Promotional
                                        </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center text-sm">{{ $item->uom->symbol ??
                                        $item->uom->uom_name
                                        }}</td>
                                    <td class="px-3 py-2 text-right text-sm">
                                        {{ number_format($item->quantity_received, 2) }}</td>
                                    <td class="px-3 py-2 text-right text-sm font-semibold">
                                        {{ number_format($item->quantity_accepted, 2) }}
                                        @if ($item->quantity_rejected > 0)
                                        <div class="text-xs text-red-600">
                                            (Rejected: {{ number_format($item->quantity_rejected, 2) }})
                                        </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm">
                                        ₨ {{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="px-3 py-2 text-right text-sm">
                                        @if ($item->selling_price)
                                        ₨ {{ number_format($item->selling_price, 2) }}
                                        @else
                                        <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm font-semibold">
                                        ₨ {{ number_format($item->total_cost, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <td colspan="7" class="px-3 py-2 text-right font-semibold">Subtotal:</td>
                                    <td class="px-3 py-2 text-right font-semibold">
                                        ₨ {{ number_format($grn->total_amount, 2) }}</td>
                                </tr>
                                @if ($grn->tax_amount > 0)
                                <tr>
                                    <td colspan="7" class="px-3 py-2 text-right">Tax:</td>
                                    <td class="px-3 py-2 text-right">₨ {{ number_format($grn->tax_amount, 2) }}</td>
                                </tr>
                                @endif
                                @if ($grn->freight_charges > 0)
                                <tr>
                                    <td colspan="7" class="px-3 py-2 text-right">Freight:</td>
                                    <td class="px-3 py-2 text-right">₨ {{ number_format($grn->freight_charges, 2) }}
                                    </td>
                                </tr>
                                @endif
                                @if ($grn->other_charges > 0)
                                <tr>
                                    <td colspan="7" class="px-3 py-2 text-right">Other Charges:</td>
                                    <td class="px-3 py-2 text-right">₨ {{ number_format($grn->other_charges, 2) }}
                                    </td>
                                </tr>
                                @endif
                                <tr class="bg-gray-100 dark:bg-gray-800">
                                    <td colspan="7" class="px-3 py-2 text-right font-bold text-lg">Grand Total:</td>
                                    <td class="px-3 py-2 text-right font-bold text-lg">
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
</x-app-layout>