<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Expense Vouchers" :createRoute="auth()->user()->can('expense-voucher-create') ? route('expense-vouchers.create') : null"
            createLabel="New Voucher" :showSearch="true" :showRefresh="true" backRoute="dashboard" />
    </x-slot>

    <x-filter-section :action="route('expense-vouchers.index')">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <x-label for="date_from" value="From" />
                <x-input id="date_from" class="block mt-1 w-full" type="date" name="filter[date_from]" :value="$filters['date_from'] ?? ''" />
            </div>
            <div>
                <x-label for="date_to" value="To" />
                <x-input id="date_to" class="block mt-1 w-full" type="date" name="filter[date_to]" :value="$filters['date_to'] ?? ''" />
            </div>
            <div>
                <x-label for="supplier" value="Company" />
                <select id="supplier" name="filter[supplier_id]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->supplier_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="status" value="Status" />
                <select id="status" name="filter[status]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="search" value="Party or voucher no." />
                <x-input id="search" class="block mt-1 w-full" type="text" name="filter[search]" :value="$filters['search'] ?? ''" />
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <x-status-message class="mb-4 shadow-md" />
        <div class="bg-white shadow-xl sm:rounded-lg p-4 overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-left text-xs uppercase text-gray-700">
                        <th class="px-2 py-2">Voucher</th><th class="px-2 py-2">Date</th><th class="px-2 py-2">Company</th>
                        <th class="px-2 py-2">Party</th><th class="px-2 py-2">Expense account</th>
                        <th class="px-2 py-2 text-right">Amount</th><th class="px-2 py-2">Status</th><th class="px-2 py-2">Maker</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vouchers as $voucher)
                        @php($tone = ['draft' => 'bg-gray-100 text-gray-700', 'pending' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$voucher->status] ?? 'bg-gray-100')
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-2 py-1 font-mono"><a class="text-indigo-600 hover:underline" href="{{ route('expense-vouchers.show', $voucher) }}">{{ $voucher->voucher_number }}</a></td>
                            <td class="px-2 py-1 whitespace-nowrap">{{ $voucher->voucher_date->format('d-M-Y') }}</td>
                            <td class="px-2 py-1">{{ $voucher->supplier?->short_name ?? $voucher->supplier?->supplier_name ?? 'Moon Traders' }}</td>
                            <td class="px-2 py-1">{{ $voucher->party_name }}</td>
                            <td class="px-2 py-1">{{ $voucher->expenseAccount?->account_name }}</td>
                            <td class="px-2 py-1 text-right font-mono">{{ number_format($voucher->amount, 2) }}</td>
                            <td class="px-2 py-1"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $tone }}">{{ $voucher->statusLabel() }}</span></td>
                            <td class="px-2 py-1">{{ $voucher->creator?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-8 text-gray-500">No vouchers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $vouchers->links() }}</div>
        </div>
    </div>
</x-app-layout>
