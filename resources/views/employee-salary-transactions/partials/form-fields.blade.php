@php
    /** @var \App\Models\EmployeeSalaryTransaction|null $employeeSalaryTransaction */
    $employeeSalaryTransaction = $employeeSalaryTransaction ?? null;
@endphp

{{-- Row 1: Employee, Supplier, Transaction Type --}}
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
                    {{ old('employee_id', optional($employeeSalaryTransaction)->employee_id) == $employee->id ? 'selected' : '' }}>
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
                    {{ old('supplier_id', optional($employeeSalaryTransaction)->supplier_id) == $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->supplier_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="transaction_type" value="Transaction Type" :required="true" />
        <select id="transaction_type" name="transaction_type"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Type</option>
            @foreach ($transactionTypeOptions as $value => $label)
                <option value="{{ $value }}"
                    {{ old('transaction_type', optional($employeeSalaryTransaction)->transaction_type) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

{{-- Row 2: Transaction Date, Status, Reference Number --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="transaction_date" value="Transaction Date" :required="true" />
        <x-input id="transaction_date" type="date" name="transaction_date" class="mt-1 block w-full"
            :value="old('transaction_date', optional($employeeSalaryTransaction?->transaction_date)->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
    </div>

    <div>
        <x-label for="status" value="Status" :required="true" />
        <select id="status" name="status"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Status</option>
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}"
                    {{ old('status', optional($employeeSalaryTransaction)->status ?? 'Pending') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="reference_number" value="Reference Number" />
        <x-input id="reference_number" type="text" name="reference_number" class="mt-1 block w-full"
            :value="old('reference_number', optional($employeeSalaryTransaction)->reference_number)" placeholder="SAL-25-001" />
    </div>
</div>

{{-- Row 3: Salary Month, Period Start, Period End --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="salary_month" value="Salary Month" />
        <x-input id="salary_month" type="text" name="salary_month" class="mt-1 block w-full"
            :value="old('salary_month', optional($employeeSalaryTransaction)->salary_month)" placeholder="January, Feb-Mar 2025" />
    </div>

    <div>
        <x-label for="period_start" value="Period Start" />
        <x-input id="period_start" type="date" name="period_start" class="mt-1 block w-full"
            :value="old('period_start', optional($employeeSalaryTransaction?->period_start)->format('Y-m-d'))" />
    </div>

    <div>
        <x-label for="period_end" value="Period End" />
        <x-input id="period_end" type="date" name="period_end" class="mt-1 block w-full"
            :value="old('period_end', optional($employeeSalaryTransaction?->period_end)->format('Y-m-d'))" />
    </div>
</div>

{{-- Row 4: Debit, Credit, Description --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="debit" value="Debit" :required="true" />
        <x-input id="debit" type="number" name="debit" class="mt-1 block w-full" step="0.01" min="0"
            :value="old('debit', optional($employeeSalaryTransaction)->debit ?? '0.00')" required />
    </div>

    <div>
        <x-label for="credit" value="Credit" :required="true" />
        <x-input id="credit" type="number" name="credit" class="mt-1 block w-full" step="0.01" min="0"
            :value="old('credit', optional($employeeSalaryTransaction)->credit ?? '0.00')" required />
    </div>

    <div>
        <x-label for="description" value="Description" />
        <textarea id="description" name="description" rows="1"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            placeholder="Salary payment for January 2025">{{ old('description', optional($employeeSalaryTransaction)->description) }}</textarea>
    </div>
</div>

{{-- Row 5: Debit Account, Credit Account --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="debit_account_id" value="Debit Account" />
        <select id="debit_account_id" name="debit_account_id"
            class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Account</option>
            @foreach ($chartOfAccounts as $account)
                <option value="{{ $account->id }}"
                    {{ old('debit_account_id', optional($employeeSalaryTransaction)->debit_account_id) == $account->id ? 'selected' : '' }}>
                    {{ $account->account_code }} - {{ $account->account_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="credit_account_id" value="Credit Account" />
        <select id="credit_account_id" name="credit_account_id"
            class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Account</option>
            @foreach ($chartOfAccounts as $account)
                <option value="{{ $account->id }}"
                    {{ old('credit_account_id', optional($employeeSalaryTransaction)->credit_account_id) == $account->id ? 'selected' : '' }}>
                    {{ $account->account_code }} - {{ $account->account_name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

{{-- Row 6: Payment Method, Cheque Number, Cheque Date --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="payment_method" value="Payment Method" />
        <select id="payment_method" name="payment_method"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Method</option>
            @foreach ($paymentMethodOptions as $value => $label)
                <option value="{{ $value }}"
                    {{ old('payment_method', optional($employeeSalaryTransaction)->payment_method) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="cheque_number" value="Cheque Number" />
        <x-input id="cheque_number" type="text" name="cheque_number" class="mt-1 block w-full"
            :value="old('cheque_number', optional($employeeSalaryTransaction)->cheque_number)" />
    </div>

    <div>
        <x-label for="cheque_date" value="Cheque Date" />
        <x-input id="cheque_date" type="date" name="cheque_date" class="mt-1 block w-full"
            :value="old('cheque_date', optional($employeeSalaryTransaction?->cheque_date)->format('Y-m-d'))" />
    </div>
</div>

{{-- Row 7: Bank Account --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="bank_account_id" value="Bank Account" />
        <select id="bank_account_id" name="bank_account_id"
            class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Bank Account</option>
            @foreach ($bankAccounts as $bank)
                <option value="{{ $bank->id }}"
                    {{ old('bank_account_id', optional($employeeSalaryTransaction)->bank_account_id) == $bank->id ? 'selected' : '' }}>
                    {{ $bank->account_name }} ({{ $bank->bank_name }})
                </option>
            @endforeach
        </select>
    </div>
</div>

{{-- Row 8: Notes --}}
<div class="grid grid-cols-1 gap-4 mt-4">
    <div>
        <x-label for="notes" value="Notes" />
        <textarea id="notes" name="notes" rows="2"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            placeholder="Additional remarks about this transaction">{{ old('notes', optional($employeeSalaryTransaction)->notes) }}</textarea>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const employeeSelect = document.getElementById('employee_id');
        const supplierSelect = document.getElementById('supplier_id');

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
    });
</script>
@endpush
