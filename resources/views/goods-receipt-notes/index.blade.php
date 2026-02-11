<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Goods Receipt Notes" :createRoute="route('goods-receipt-notes.create')" createLabel=""
            :showSearch="true" :showRefresh="true" />
    </x-slot>

    <x-filter-section :action="route('goods-receipt-notes.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_grn_number" value="GRN Number" />
                <x-input id="filter_grn_number" name="filter[grn_number]" type="text" class="mt-1 block w-full"
                    :value="request('filter.grn_number')" placeholder="GRN-2025-0001" />
            </div>

            <div>
                <x-label for="filter_supplier_invoice_number" value="Supplier Invoice" />
                <x-input id="filter_supplier_invoice_number" name="filter[supplier_invoice_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.supplier_invoice_number')"
                    placeholder="INV-12345" />
            </div>

            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ request('filter.supplier_id') == $supplier->id ? 'selected' :
                        '' }}>
                                        {{ $supplier->supplier_name }}
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ request('filter.warehouse_id') == $warehouse->id ? 'selected'
                        : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('filter.status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="received" {{ request('filter.status') === 'received' ? 'selected' : '' }}>Received
                    </option>
                    <option value="posted" {{ request('filter.status') === 'posted' ? 'selected' : '' }}>Posted</option>
                </select>
            </div>

            <div>
                <x-label for="filter_payment_status" value="Payment Status" />
                <select id="filter_payment_status" name="filter[payment_status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Payment Statuses</option>
                    <option value="unpaid" {{ request('filter.payment_status') === 'unpaid' ? 'selected' : '' }}>Unpaid
                    </option>
                    <option value="partial" {{ request('filter.payment_status') === 'partial' ? 'selected' : '' }}>Partial
                    </option>
                    <option value="paid" {{ request('filter.payment_status') === 'paid' ? 'selected' : '' }}>Paid</option>
                </select>
            </div>

            <div>
                <x-label for="filter_receipt_date_from" value="Receipt Date From" />
                <x-input id="filter_receipt_date_from" name="filter[receipt_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.receipt_date_from')" />
            </div>

            <div>
                <x-label for="filter_receipt_date_to" value="Receipt Date To" />
                <x-input id="filter_receipt_date_to" name="filter[receipt_date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.receipt_date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$grns" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'GRN Number'],
        ['label' => 'Receipt Date', 'align' => 'text-center'],
        ['label' => 'Supplier'],
        ['label' => 'Warehouse'],
        ['label' => 'Qty / Amount', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Payment', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No goods receipt notes found." :emptyRoute="route('goods-receipt-notes.create')"
        emptyLinkText="Create a GRN">

        @foreach ($grns as $index => $grn)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $grns->firstItem() + $index }}
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold text-gray-900">
                        {{ $grn->grn_number }}
                    </div>
                    @if ($grn->supplier_invoice_number)
                        <div class="text-xs text-gray-500">
                            Inv: {{ $grn->supplier_invoice_number }}
                        </div>
                    @endif
                </td>
                <td class="py-1 px-2 text-center">
                    {{ \Carbon\Carbon::parse($grn->receipt_date)->format('d M Y') }}
                </td>
                <td class="py-1 px-2">
                    {{ $grn->supplier->supplier_name }}
                </td>
                <td class="py-1 px-2">
                    {{ $grn->warehouse->warehouse_name }}
                </td>
                <td class="py-1 px-2 text-right">
                    <div class="font-semibold text-gray-900">
                        {{ number_format($grn->total_quantity, 2) }}
                    </div>
                    <div class="text-xs text-gray-500">
                        ₨ {{ number_format($grn->grand_total, 2) }}
                    </div>
                </td>
                <td class="py-1 px-2 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full 
                            {{ $grn->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                            {{ $grn->status === 'received' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $grn->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                        {{ ucfirst($grn->status) }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    @if ($grn->status === 'posted')
                        <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full 
                                {{ $grn->payment_status === 'unpaid' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $grn->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                {{ $grn->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                            {{ ucfirst($grn->payment_status) }}
                        </span>
                        @if ($grn->payment_status !== 'unpaid')
                            <div class="text-xs text-gray-500 mt-0.5">
                                ₨ {{ number_format($grn->total_paid, 2) }}
                            </div>
                        @endif
                        @if ($grn->payment_status !== 'paid')
                            <div class="text-xs text-red-600 font-semibold mt-0.5">
                                Due: ₨ {{ number_format($grn->balance, 2) }}
                            </div>
                        @endif
                    @else
                        <span class="text-xs text-gray-400">N/A</span>
                    @endif
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('goods-receipt-notes.show', $grn->id) }}"
                            class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                            title="View">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        @if ($grn->status === 'draft')
                            <a href="{{ route('goods-receipt-notes.edit', $grn->id) }}"
                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                            <form action="{{ route('goods-receipt-notes.destroy', $grn->id) }}" method="POST"
                                class="inline-block" onsubmit="return confirm('Are you sure you want to delete this GRN?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                    title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>