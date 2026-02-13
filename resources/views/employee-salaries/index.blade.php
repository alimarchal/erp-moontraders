<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Employee Salaries" :createRoute="route('employee-salaries.create')" createLabel="Add Salary"
            createPermission="employee-salary-create" :showSearch="true" :showRefresh="true" backRoute="dashboard" />
    </x-slot>

    <x-filter-section :action="route('employee-salaries.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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

            {{-- Active Status --}}
            <div>
                <x-label for="filter_is_active" value="Active Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_active') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('filter.is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$items" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Employee'],
        ['label' => 'Supplier'],
        ['label' => 'Basic Salary', 'align' => 'text-right'],
        ['label' => 'Allowances', 'align' => 'text-right'],
        ['label' => 'Deductions', 'align' => 'text-right'],
        ['label' => 'Net Salary', 'align' => 'text-right'],
        ['label' => 'Effective From', 'align' => 'text-center'],
        ['label' => 'Active', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No employee salaries found." :emptyRoute="route('employee-salaries.create')"
        emptyLinkText="Add a salary">
        @foreach ($items as $index => $salary)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $items->firstItem() + $index }}
                </td>
                <td class="py-1 px-2 font-semibold whitespace-nowrap">
                    {{ $salary->employee?->name ?? '-' }}
                </td>
                <td class="py-1 px-2 whitespace-nowrap">
                    {{ $salary->supplier?->supplier_name ?? '-' }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap">
                    {{ number_format($salary->basic_salary, 2) }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap">
                    {{ $salary->allowances > 0 ? number_format($salary->allowances, 2) : '-' }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap">
                    {{ $salary->deductions > 0 ? number_format($salary->deductions, 2) : '-' }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap font-bold">
                    {{ number_format($salary->net_salary, 2) }}
                </td>
                <td class="py-1 px-2 text-center whitespace-nowrap">
                    {{ $salary->effective_from?->format('d-m-Y') ?? '-' }}
                </td>
                <td class="py-1 px-2 text-center">
                    <span
                        class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                            {{ $salary->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $salary->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('employee-salaries.show', $salary) }}"
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
                        @can('employee-salary-edit')
                            <a href="{{ route('employee-salaries.edit', $salary) }}"
                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        @endcan
                        @role('super-admin')
                            <form method="POST" action="{{ route('employee-salaries.destroy', $salary) }}"
                                onsubmit="return confirm('Are you sure you want to delete this salary?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                    title="Delete">
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
