<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Custom Settlement Report" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 12px;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 4px;
                text-align: center;
            }

            .report-table th {
                background-color: #f3f4f6;
                font-weight: bold;
            }

            .text-left {
                text-align: left !important;
            }

            .text-right {
                text-align: right !important;
            }

            @media print {
                @page {
                    margin: 10mm;
                }

                body {
                    margin: 0;
                    padding: 0;
                }

                .no-print {
                    display: none !important;
                }

                .report-table {
                    font-size: 10px;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.custom-settlement.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Date --}}
            <div>
                <x-label for="date" value="Date" />
                <x-input id="date" class="block mt-1 w-full" type="date" name="date" :value="$date" required />
            </div>

            {{-- Supplier --}}
            <div>
                <x-label for="supplier_id" value="Supplier" />
                <select id="supplier_id" name="supplier_id" class="select2 border-gray-300 rounded-md w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Designation --}}
            <div>
                <x-label for="designation" value="Designation" />
                <select id="designation" name="designation" class="select2 border-gray-300 rounded-md w-full">
                    <option value="">All Designations</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig }}" {{ $designation == $desig ? 'selected' : '' }}>
                            {{ $desig }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Salesman --}}
            <div>
                <x-label for="employee_ids" value="Salesman (Multi-Select)" />
                <select id="employee_ids" name="employee_ids[]" multiple
                    class="select2 border-gray-300 rounded-md w-full">
                    @foreach($allEmployees as $employee)
                        <option value="{{ $employee->id }}" {{ in_array($employee->id, $employeeIds) ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Custom Settlement Report<br>
                    <span class="text-sm font-semibold">Date: {{ \Carbon\Carbon::parse($date)->format('d-M-Y') }}</span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Sr.#</th>
                            <th class="text-left">Salesman Name</th>
                            <th class="text-center">Sale</th>
                            <th class="text-center">%age</th>
                            <th class="text-center">Scheme</th>
                            <th class="text-center">Total Discount</th>
                            <th class="text-center">Expiry Liquid</th>
                            <th class="text-center">Expiry Powder</th>
                            <th class="text-center">Today Cash</th>
                            <th class="text-center">Total Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($settlements as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="text-left whitespace-nowrap">{{ $item->salesman_name }}</td>
                                <td class="text-center">{{ $item->sale > 0 ? number_format($item->sale, 2) : '-' }}</td>
                                <td class="text-center">
                                    {{ $item->percentage_expense > 0 ? number_format($item->percentage_expense, 2) : '-' }}
                                </td>
                                <td class="text-center">
                                    {{ $item->scheme_discount > 0 ? number_format($item->scheme_discount, 2) : '-' }}
                                </td>
                                <td class="text-center font-bold">
                                    {{ $item->total_discount > 0 ? number_format($item->total_discount, 2) : '-' }}
                                </td>
                                <td class="text-center">
                                    {{ $item->amr_liquid > 0 ? number_format($item->amr_liquid, 2) : '-' }}
                                </td>
                                <td class="text-center">
                                    {{ $item->amr_powder > 0 ? number_format($item->amr_powder, 2) : '-' }}
                                </td>
                                <td class="text-center font-bold">
                                    {{ $item->today_cash_amount > 0 ? number_format($item->today_cash_amount, 2) : '-' }}
                                </td>
                                <td class="text-center">
                                    {{ $item->total_credit > 0 ? number_format($item->total_credit, 2) : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold">
                        <tr>
                            <td colspan="2" class="text-right">Total:</td>
                            <td class="text-right">{{ number_format($totals['sale'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['percentage_expense'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['scheme_discount'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['total_discount'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['amr_liquid'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['amr_powder'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['today_cash_amount'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['total_credit'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>