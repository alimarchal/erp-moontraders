<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Opening Customer Balances" :createRoute="route('opening-customer-balances.create')"
            createLabel="Add Balance" createPermission="opening-customer-balance-create" :showSearch="true"
            :showRefresh="true" backRoute="dashboard" />
    </x-slot>

    <x-filter-section :action="route('opening-customer-balances.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('filter.supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_employee_id" value="Employee / Salesman" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" {{ request('filter.employee_id') == $employee->id ? 'selected' : '' }}>
                            {{ $employee->employee_code }} — {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_customer_id" value="Customer" />
                <select id="filter_customer_id" name="filter[customer_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Customers</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" {{ request('filter.customer_id') == $customer->id ? 'selected' : '' }}>
                            {{ $customer->customer_code }} — {{ $customer->customer_name }}@if($customer->address) —
                            {{ $customer->address }}@endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_reference_number" value="Reference Number" />
                <x-input id="filter_reference_number" name="filter[reference_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.reference_number')" placeholder="OCB-M-..." />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$transactions" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Date / Ref', 'align' => 'text-center'],
        ['label' => 'Supplier'],
        ['label' => 'Employee / Salesman'],
        ['label' => 'Account #'],
        ['label' => 'Customer'],
        ['label' => 'Balance', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No opening customer balances found." :emptyRoute="route('opening-customer-balances.create')"
        emptyLinkText="Add a balance">

        @foreach ($transactions as $transaction)
            <tr class="border-b border-gray-200 hover:bg-gray-100 {{ $loop->even ? 'bg-gray-50' : '' }}">
                <td class="py-2 px-2 text-center">
                    {{ $loop->iteration + ($transactions->currentPage() - 1) * $transactions->perPage() }}
                </td>
                <td class="py-2 px-2 text-center">
                    {{ $transaction->transaction_date->format('d-M-Y') }}
                    <br><span class="text-xs text-gray-500">{{ $transaction->reference_number ?? '' }}</span>
                </td>
                <td class="py-2 px-2">{{ Str::limit($transaction->account->employee->supplier->supplier_name ?? '—', 20) }}
                </td>
                <td class="py-2 px-2">
                    <span class="text-xs text-gray-500">{{ $transaction->account->employee->employee_code ?? '' }}</span>
                    {{ $transaction->account->employee->name ?? '—' }}
                </td>
                <td class="py-2 px-2 text-xs font-mono">{{ $transaction->account->account_number ?? '—' }}</td>
                <td class="py-2 px-2">
                    <span class="text-xs text-gray-500">{{ $transaction->account->customer->customer_code ?? '' }}</span>
                    {{ $transaction->account->customer->customer_name ?? '—' }}
                    @if($transaction->account->customer->address)
                        <br><span class="text-xs text-gray-400">{{ $transaction->account->customer->address }}</span>
                    @endif
                </td>
                <td class="py-2 px-2 text-right font-mono">{{ number_format($transaction->debit, 2) }}</td>
                <td class="py-2 px-2 text-center">
                    @if($transaction->isPosted())
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"
                            title="Posted {{ $transaction->posted_at->format('d-M-Y H:i') }}">
                            Posted
                        </span>
                    @else
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                            Draft
                        </span>
                    @endif
                </td>
                <td class="py-2 px-2 text-center">
                    <div class="flex items-center justify-center gap-1">
                        @can('opening-customer-balance-list')
                            <a href="{{ route('opening-customer-balances.show', $transaction) }}"
                                class="text-blue-600 hover:text-blue-800" title="View">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </a>
                        @endcan
                        @if(!$transaction->isPosted())
                            @can('opening-customer-balance-edit')
                                <a href="{{ route('opening-customer-balances.edit', $transaction) }}"
                                    class="text-green-600 hover:text-green-800" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                            @endcan
                            @can('opening-customer-balance-delete')
                                <form method="POST" action="{{ route('opening-customer-balances.destroy', $transaction) }}"
                                    onsubmit="return confirm('Are you sure you want to delete this opening balance?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot name="footer">
            <tr>
                <td colspan="7" class="py-2 px-2 text-right">Total:</td>
                <td class="py-2 px-2 text-right font-mono">{{ number_format($transactions->sum('debit'), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </x-slot>
    </x-data-table>
</x-app-layout>