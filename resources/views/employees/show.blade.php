<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Employee: {{ $employee->employee_code }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('employees.index') }}"
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
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label value="Employee Code" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->employee_code" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Name" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->name" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Company / Principal" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->company_name ?: '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Supplier" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->supplier?->supplier_name ?: '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Designation" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->designation ?: '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Phone" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->phone ?: '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Email" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->email ?: '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Assigned Warehouse" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->warehouse?->warehouse_name ?: '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Company Entity" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->company?->company_name ?: '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Linked User" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->user?->name ?: '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Hire Date" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->hire_date?->format('d-m-Y') ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Status" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->is_active ? 'Active' : 'Inactive'" disabled readonly />
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-label value="Address / Notes" />
                            <textarea rows="3"
                                class="mt-1 block w-full border-gray-300 cursor-not-allowed bg-gray-100 rounded-md shadow-sm"
                                disabled readonly>{{ $employee->address ?? '—' }}</textarea>
                        </div>

                        @if ($employee->salaries->isNotEmpty())
                        <div class="mt-6">
                            <x-label value="Salary Records" />
                            <textarea rows="4"
                                class="mt-1 block w-full border-gray-300 cursor-not-allowed bg-gray-100 rounded-md shadow-sm"
                                disabled
                                readonly>{{ $employee->salaries->map(fn ($salary) => ($salary->month ?? 'N/A') . ' · ' . number_format((float) ($salary->net_salary ?? 0), 2))->implode(PHP_EOL) }}</textarea>
                        </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label value="Created At" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->created_at?->format('d-m-Y H:i:s') ?? '—'" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Last Updated" />
                                <x-input type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$employee->updated_at?->format('d-m-Y H:i:s') ?? '—'" disabled readonly />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-2">
                            <a href="{{ route('employees.edit', $employee->id) }}"
                                class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit Employee
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        input:disabled,
        textarea:disabled {
            cursor: not-allowed !important;
        }
    </style>
</x-app-layout>