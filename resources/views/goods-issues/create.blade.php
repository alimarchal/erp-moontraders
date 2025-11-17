<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Create Goods Issue
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            <a href="{{ route('goods-issues.index') }}"
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

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('goods-issues.store') }}" id="goodsIssueForm">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <x-label for="issue_date" value="Issue Date" class="required" />
                                <x-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full"
                                    :value="old('issue_date', date('Y-m-d'))" required />
                                <x-input-error for="issue_date" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="warehouse_id" value="Warehouse" class="required" />
                                <select id="warehouse_id" name="warehouse_id"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    required>
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('warehouse_id')==$warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                                    @endforeach
                                </select>
                                <x-input-error for="warehouse_id" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="vehicle_id" value="Vehicle" class="required" />
                                <select id="vehicle_id" name="vehicle_id"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    required>
                                    <option value="">Select Vehicle</option>
                                    @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}" {{ old('vehicle_id')==$vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->vehicle_number }} ({{ $vehicle->vehicle_type }})
                                    </option>
                                    @endforeach
                                </select>
                                <x-input-error for="vehicle_id" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="employee_id" value="Salesman" class="required" />
                                <select id="employee_id" name="employee_id"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    required>
                                    <option value="">Select Salesman</option>
                                    @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id')==$employee->id ? 'selected' : '' }}>
                                        {{ $employee->full_name }} ({{ $employee->employee_code }})
                                    </option>
                                    @endforeach
                                </select>
                                <x-input-error for="employee_id" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-label for="notes" value="Notes" />
                                <textarea id="notes" name="notes" rows="2"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('notes') }}</textarea>
                                <x-input-error for="notes" class="mt-2" />
                            </div>
                        </div>

                        <hr class="my-6 border-gray-200">

                        <h3 class="text-lg font-semibold mb-4">Products to Issue</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="itemsTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">UOM</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="itemsBody">
                                    <!-- Items will be added here -->
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            <button type="button" onclick="addItem()"
                                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                Add Product
                            </button>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button class="ml-4">
                                Create Goods Issue
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let itemIndex = 0;
        const products = @json($products);
        const uoms = @json($uoms);

        function addItem() {
            const tbody = document.getElementById('itemsBody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-3 py-2">
                    <select name="items[${itemIndex}][product_id]" class="border-gray-300 rounded-md shadow-sm w-full text-sm" required>
                        <option value="">Select Product</option>
                        ${products.map(p => `<option value="${p.id}">${p.product_code} - ${p.product_name}</option>`).join('')}
                    </select>
                </td>
                <td class="px-3 py-2">
                    <input type="number" name="items[${itemIndex}][quantity_issued]" step="0.001" min="0.001"
                        class="border-gray-300 rounded-md shadow-sm w-full text-sm" required />
                </td>
                <td class="px-3 py-2">
                    <select name="items[${itemIndex}][uom_id]" class="border-gray-300 rounded-md shadow-sm w-full text-sm" required>
                        <option value="">Select UOM</option>
                        ${uoms.map(u => `<option value="${u.id}">${u.uom_name}</option>`).join('')}
                    </select>
                </td>
                <td class="px-3 py-2">
                    <input type="number" name="items[${itemIndex}][unit_cost]" step="0.01" min="0"
                        class="border-gray-300 rounded-md shadow-sm w-full text-sm" required />
                </td>
                <td class="px-3 py-2">
                    <input type="text" readonly class="border-gray-300 rounded-md shadow-sm w-full text-sm bg-gray-100" />
                </td>
                <td class="px-3 py-2 text-center">
                    <button type="button" onclick="removeItem(this)" class="text-red-600 hover:text-red-900">Remove</button>
                </td>
            `;
            tbody.appendChild(row);
            itemIndex++;
        }

        function removeItem(button) {
            button.closest('tr').remove();
        }

        // Add first item on page load
        document.addEventListener('DOMContentLoaded', function() {
            addItem();
        });
    </script>
</x-app-layout>
