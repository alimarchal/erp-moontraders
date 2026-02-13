@php
    /** @var \App\Models\EmployeeSalary|null $employeeSalary */
    $employeeSalary = $employeeSalary ?? null;
@endphp

{{-- Row 1: Employee, Supplier, Effective From --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <x-label for="employee_id" value="Employee" :required="true" />
        <select id="employee_id" name="employee_id"
            class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Employee</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}"
                    data-supplier-id="{{ $employee->supplier_id }}"
                    {{ old('employee_id', optional($employeeSalary)->employee_id) == $employee->id ? 'selected' : '' }}>
                    {{ $employee->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="supplier_id" value="Supplier" />
        <select id="supplier_id" name="supplier_id"
            class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Supplier</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}"
                    {{ old('supplier_id', optional($employeeSalary)->supplier_id) == $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->supplier_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="effective_from" value="Effective From" :required="true" />
        <x-input id="effective_from" type="date" name="effective_from" class="mt-1 block w-full"
            :value="old('effective_from', optional($employeeSalary?->effective_from)->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
    </div>
</div>

{{-- Row 2: Basic Salary, Allowances, Deductions --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="basic_salary" value="Basic Salary" />
        <x-input id="basic_salary" type="number" name="basic_salary" class="mt-1 block w-full" step="0.01" min="0"
            :value="old('basic_salary', optional($employeeSalary)->basic_salary ?? '0.00')" />
    </div>

    <div>
        <x-label for="allowances" value="Allowances" />
        <x-input id="allowances" type="number" name="allowances" class="mt-1 block w-full" step="0.01" min="0"
            :value="old('allowances', optional($employeeSalary)->allowances ?? '0.00')" />
    </div>

    <div>
        <x-label for="deductions" value="Deductions" />
        <x-input id="deductions" type="number" name="deductions" class="mt-1 block w-full" step="0.01" min="0"
            :value="old('deductions', optional($employeeSalary)->deductions ?? '0.00')" />
    </div>
</div>

{{-- Row 3: Net Salary, Effective To, Active --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="net_salary" value="Net Salary" />
        <x-input id="net_salary" type="number" name="net_salary" class="mt-1 block w-full bg-gray-100 cursor-not-allowed" step="0.01" min="0"
            :value="old('net_salary', optional($employeeSalary)->net_salary ?? '0.00')" readonly />
    </div>

    <div>
        <x-label for="effective_to" value="Effective To" />
        <x-input id="effective_to" type="date" name="effective_to" class="mt-1 block w-full"
            :value="old('effective_to', optional($employeeSalary?->effective_to)->format('Y-m-d'))" />
    </div>

    <div>
        <x-label for="is_active" value="Active" />
        <select id="is_active" name="is_active"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="1" {{ old('is_active', optional($employeeSalary)->is_active ?? 1) == 1 ? 'selected' : '' }}>Yes</option>
            <option value="0" {{ old('is_active', optional($employeeSalary)->is_active ?? 1) == 0 ? 'selected' : '' }}>No</option>
        </select>
    </div>
</div>

{{-- Row 4: Notes --}}
<div class="grid grid-cols-1 gap-4 mt-4">
    <div>
        <x-label for="notes" value="Notes" />
        <textarea id="notes" name="notes" rows="2"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            placeholder="Additional remarks about this salary">{{ old('notes', optional($employeeSalary)->notes) }}</textarea>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const employeeSelect = document.getElementById('employee_id');
        const supplierSelect = document.getElementById('supplier_id');
        const basicSalaryInput = document.getElementById('basic_salary');
        const allowancesInput = document.getElementById('allowances');
        const deductionsInput = document.getElementById('deductions');
        const netSalaryInput = document.getElementById('net_salary');

        // Auto-populate supplier when employee changes
        if (employeeSelect) {
            employeeSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const supplierId = selectedOption.getAttribute('data-supplier-id');

                if (supplierId && supplierSelect) {
                    supplierSelect.value = supplierId;
                    if (typeof $(supplierSelect).val === 'function') {
                        $(supplierSelect).val(supplierId).trigger('change');
                    }
                }
            });
        }

        // Calculate net salary = basic_salary + allowances - deductions
        function calculateNetSalary() {
            const basicSalary = parseFloat(basicSalaryInput.value) || 0;
            const allowances = parseFloat(allowancesInput.value) || 0;
            const deductions = parseFloat(deductionsInput.value) || 0;
            const netSalary = basicSalary + allowances - deductions;
            netSalaryInput.value = netSalary.toFixed(2);
        }

        if (basicSalaryInput && allowancesInput && deductionsInput && netSalaryInput) {
            basicSalaryInput.addEventListener('input', calculateNetSalary);
            allowancesInput.addEventListener('input', calculateNetSalary);
            deductionsInput.addEventListener('input', calculateNetSalary);
            calculateNetSalary();
        }
    });
</script>
@endpush
