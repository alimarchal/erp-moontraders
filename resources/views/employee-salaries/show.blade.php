@php
    $netSalary = (float) $employeeSalary->basic_salary + (float) $employeeSalary->allowances - (float) $employeeSalary->deductions;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Salary: {{ $employeeSalary->employee?->name ?? '#' . $employeeSalary->id }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('employee-salaries.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
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
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <x-status-message class="mb-4 mt-4" />
                <div class="p-6">
                    <form>
                        {{-- Row 1: Employee, Supplier, Effective From --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-label for="employee" value="Employee" />
                                <x-input id="employee" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employeeSalary->employee?->name ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="supplier" value="Supplier" />
                                <x-input id="supplier" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employeeSalary->supplier?->supplier_name ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="effective_from" value="Effective From" />
                                <x-input id="effective_from" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employeeSalary->effective_from?->format('d-m-Y') ?? '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 2: Basic Salary, Allowances, Deductions --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="basic_salary" value="Basic Salary" />
                                <x-input id="basic_salary" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="number_format($employeeSalary->basic_salary, 2)" disabled readonly />
                            </div>
                            <div>
                                <x-label for="allowances" value="Allowances" />
                                <x-input id="allowances" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="number_format($employeeSalary->allowances, 2)" disabled readonly />
                            </div>
                            <div>
                                <x-label for="deductions" value="Deductions" />
                                <x-input id="deductions" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="number_format($employeeSalary->deductions, 2)" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 3: Net Salary, Effective To, Active --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="net_salary" value="Net Salary" />
                                <x-input id="net_salary" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100 font-bold"
                                    :value="number_format($netSalary, 2)" disabled readonly />
                            </div>
                            <div>
                                <x-label for="effective_to" value="Effective To" />
                                <x-input id="effective_to" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employeeSalary->effective_to?->format('d-m-Y') ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="is_active" value="Active" />
                                <x-input id="is_active" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employeeSalary->is_active ? 'Yes' : 'No'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 4: Notes --}}
                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <x-label for="notes" value="Notes" />
                                <x-input id="notes" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employeeSalary->notes ?? '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 5: Timestamps --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="created_at" value="Created At" />
                                <x-input id="created_at" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employeeSalary->created_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="updated_at" value="Last Updated" />
                                <x-input id="updated_at" type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employeeSalary->updated_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-2">
                            <a href="{{ route('employee-salaries.edit', $employeeSalary) }}"
                                class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit Salary
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        input:disabled {
            cursor: not-allowed !important;
        }
    </style>
</x-app-layout>
