<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Supplier Payments" :createRoute="route('supplier-payments.create')"
            createLabel="New Payment" :showSearch="true" :showRefresh="true" />
    </x-slot>

    <x-filter-section :action="route('supplier-payments.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_payment_number" value="Payment Number" />
                <x-input id="filter_payment_number" name="filter[payment_number]" type="text" class="mt-1 block w-full"
                    :value="request('filter.payment_number')" placeholder="PAY-2025-000001" />
            </div>

            <div>
                <x-label for="filter_reference_number" value="Reference Number" />
                <x-input id="filter_reference_number" name="filter[reference_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.reference_number')" placeholder="TXN-12345" />
            </div>

            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{ request('filter.supplier_id')==$supplier->id ? 'selected' :
                        '' }}>
                        {{ $supplier->supplier_name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_payment_method" value="Payment Method" />
                <select id="filter_payment_method" name="filter[payment_method]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Methods</option>
                    <option value="bank_transfer" {{ request('filter.payment_method')==='bank_transfer' ? 'selected'
                        : '' }}>Bank Transfer</option>
                    <option value="cash" {{ request('filter.payment_method')==='cash' ? 'selected' : '' }}>Cash</option>
                    <option value="cheque" {{ request('filter.payment_method')==='cheque' ? 'selected' : '' }}>Cheque
                    </option>
                    <option value="online" {{ request('filter.payment_method')==='online' ? 'selected' : '' }}>Online
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('filter.status')==='draft' ? 'selected' : '' }}>Draft</option>
                    <option value="posted" {{ request('filter.status')==='posted' ? 'selected' : '' }}>Posted</option>
                    <option value="cancelled" {{ request('filter.status')==='cancelled' ? 'selected' : '' }}>Cancelled
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_payment_date_from" value="Payment Date From" />
                <x-input id="filter_payment_date_from" name="filter[payment_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.payment_date_from')" />
            </div>

            <div>
                <x-label for="filter_payment_date_to" value="Payment Date To" />
                <x-input id="filter_payment_date_to" name="filter[payment_date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.payment_date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$payments" :headers="[
            ['label' => '#', 'align' => 'text-center'],
            ['label' => 'Payment Number'],
            ['label' => 'Payment Date', 'align' => 'text-center'],
            ['label' => 'Supplier'],
            ['label' => 'Method / Reference'],
            ['label' => 'Amount', 'align' => 'text-right'],
            ['label' => 'Status', 'align' => 'text-center'],
            ['label' => 'Actions', 'align' => 'text-center'],
        ]" emptyMessage="No supplier payments found." :emptyRoute="route('supplier-payments.create')"
        emptyLinkText="Create a Payment">

        @foreach ($payments as $index => $payment)
        <tr class="border-b border-gray-200 dark:border-gray-700 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $payments->firstItem() + $index }}
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ $payment->payment_number }}
                </div>
                @if ($payment->journalEntry)
                <div class="text-xs text-gray-500">
                    JE: {{ $payment->journalEntry->entry_number }}
                </div>
                @endif
            </td>
            <td class="py-1 px-2 text-center">
                {{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}
            </td>
            <td class="py-1 px-2">
                {{ $payment->supplier->supplier_name }}
            </td>
            <td class="py-1 px-2">
                <div class="font-medium text-gray-900 dark:text-gray-100">
                    {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                </div>
                @if ($payment->reference_number)
                <div class="text-xs text-gray-500">
                    Ref: {{ $payment->reference_number }}
                </div>
                @endif
            </td>
            <td class="py-1 px-2 text-right">
                <div class="font-semibold text-gray-900 dark:text-gray-100">
                    ₨ {{ number_format($payment->amount, 2) }}
                </div>
            </td>
            <td class="py-1 px-2 text-center">
                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full 
                        {{ $payment->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                        {{ $payment->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}
                        {{ $payment->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}
                        {{ $payment->status === 'bounced' ? 'bg-orange-100 text-orange-700' : '' }}">
                    {{ ucfirst($payment->status) }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('supplier-payments.show', $payment->id) }}"
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
                    @if ($payment->status === 'draft')
                    <form action="{{ route('supplier-payments.post', $payment->id) }}" method="POST"
                        class="inline-block"
                        onsubmit="return confirm('Are you sure you want to post this payment? This will create accounting journal entries.');">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                            title="Post Payment">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    </form>
                    <form action="{{ route('supplier-payments.destroy', $payment->id) }}" method="POST"
                        class="inline-block"
                        onsubmit="return confirm('Are you sure you want to delete this payment?');">
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