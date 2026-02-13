<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Employee Salary Transactions" :createRoute="route('employee-salary-transactions.create')" createLabel="Add Transaction"
            createPermission="employee-salary-transaction-create" :showSearch="true" :showRefresh="true" backRoute="dashboard" />
    </x-slot>

    <x-filter-section :action="route('employee-salary-transactions.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Employee --}}
            <div>
                <x-label for="filter_employee_id" value="Employee" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}"
                            {{ request('filter.employee_id') == $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Supplier --}}
            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ request('filter.supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Transaction Type --}}
            <div>
                <x-label for="filter_transaction_type" value="Transaction Type" />
                <select id="filter_transaction_type" name="filter[transaction_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach ($transactionTypeOptions as $value => $label)
                        <option value="{{ $value }}"
                            {{ request('filter.transaction_type') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Status --}}
            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}"
                            {{ request('filter.status') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Payment Method --}}
            <div>
                <x-label for="filter_payment_method" value="Payment Method" />
                <select id="filter_payment_method" name="filter[payment_method]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Methods</option>
                    @foreach ($paymentMethodOptions as $value => $label)
                        <option value="{{ $value }}"
                            {{ request('filter.payment_method') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Reference Number --}}
            <div>
                <x-label for="filter_reference_number" value="Reference Number" />
                <x-input id="filter_reference_number" name="filter[reference_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.reference_number')" placeholder="SAL-25-001" />
            </div>

            {{-- Salary Month --}}
            <div>
                <x-label for="filter_salary_month" value="Salary Month" />
                <x-input id="filter_salary_month" name="filter[salary_month]" type="text" class="mt-1 block w-full"
                    :value="request('filter.salary_month')" placeholder="January, Feb-Mar" />
            </div>

            {{-- Transaction Date From --}}
            <div>
                <x-label for="filter_transaction_date_from" value="Txn Date (From)" />
                <x-input id="filter_transaction_date_from" name="filter[transaction_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.transaction_date_from')" />
            </div>

            {{-- Transaction Date To --}}
            <div>
                <x-label for="filter_transaction_date_to" value="Txn Date (To)" />
                <x-input id="filter_transaction_date_to" name="filter[transaction_date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.transaction_date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$items" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Employee'],
        ['label' => 'Supplier'],
        ['label' => 'Txn Date', 'align' => 'text-center'],
        ['label' => 'Reference'],
        ['label' => 'Type', 'align' => 'text-center'],
        ['label' => 'Salary Month'],
        ['label' => 'Debit', 'align' => 'text-right'],
        ['label' => 'Credit', 'align' => 'text-right'],
        ['label' => 'Balance', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No salary transactions found." :emptyRoute="route('employee-salary-transactions.create')"
        emptyLinkText="Add a transaction">
        @foreach ($items as $index => $txn)
            @php
                $balance = (float) $txn->debit - (float) $txn->credit;
                $statusLabel = $statusOptions[$txn->status] ?? ucfirst($txn->status);
                $typeLabel = $transactionTypeOptions[$txn->transaction_type] ?? ucfirst($txn->transaction_type);
                $deleteDisabled = $txn->status === 'Paid';
                $canPost = in_array($txn->status, ['Pending', 'Approved']);
            @endphp
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $items->firstItem() + $index }}
                </td>
                <td class="py-1 px-2 font-semibold whitespace-nowrap">
                    {{ $txn->employee?->name ?? '-' }}
                </td>
                <td class="py-1 px-2 whitespace-nowrap">
                    {{ $txn->supplier?->supplier_name ?? '-' }}
                </td>
                <td class="py-1 px-2 text-center whitespace-nowrap">
                    {{ $txn->transaction_date?->format('d-m-Y') ?? '-' }}
                </td>
                <td class="py-1 px-2">
                    {{ $txn->reference_number ?? '-' }}
                </td>
                <td class="py-1 px-2 text-center whitespace-nowrap">
                    {{ $typeLabel }}
                </td>
                <td class="py-1 px-2 whitespace-nowrap">
                    {{ $txn->salary_month ?? '-' }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap">
                    {{ $txn->debit > 0 ? number_format($txn->debit, 2) : '-' }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap">
                    {{ $txn->credit > 0 ? number_format($txn->credit, 2) : '-' }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap font-bold {{ $balance > 0 ? 'text-red-600' : ($balance < 0 ? 'text-green-600' : '') }}">
                    {{ number_format($balance, 2) }}
                </td>
                <td class="py-1 px-2 text-center">
                    <span
                        class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                            @class([
                                'bg-amber-100 text-amber-700' => $txn->status === 'Pending',
                                'bg-blue-100 text-blue-700' => $txn->status === 'Approved',
                                'bg-emerald-100 text-emerald-700' => $txn->status === 'Paid',
                                'bg-red-100 text-red-700' => $txn->status === 'Cancelled',
                            ])">
                        {{ $statusLabel }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('employee-salary-transactions.show', $txn) }}"
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
                        @can('employee-salary-transaction-edit')
                            <a href="{{ route('employee-salary-transactions.edit', $txn) }}"
                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        @endcan
                        @can('employee-salary-transaction-post')
                            @if ($canPost)
                                <form method="POST" action="{{ route('employee-salary-transactions.post', $txn) }}"
                                    onsubmit="return confirm('Are you sure you want to post this transaction?');">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center justify-center w-8 h-8 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-100 rounded-md transition-colors duration-150"
                                        title="Post">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        @endcan
                        @role('super-admin')
                            <form method="POST" action="{{ route('employee-salary-transactions.destroy', $txn) }}"
                                onsubmit="return confirm('Are you sure you want to delete this transaction?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150 {{ $deleteDisabled ? 'opacity-40 cursor-not-allowed hover:bg-transparent hover:text-red-600 pointer-events-none' : '' }}"
                                    title="Delete" @if ($deleteDisabled) disabled aria-disabled="true" @endif>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </form>
                        @endrole
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
