@php
    /** @var \App\Models\CustomerEmployeeAccountTransaction|null $transaction */
    $transaction = $transaction ?? null;
    $isEdit = $transaction !== null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @unless($isEdit)
        <div>
            <x-label for="employee_id" value="Employee / Salesman" :required="true" />
            <select id="employee_id" name="employee_id"
                class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                required>
                <option value="">Select Employee</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" {{ (int) old('employee_id') === $employee->id ? 'selected' : '' }}>
                        {{ $employee->employee_code }} — {{ $employee->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <x-label for="customer_id" value="Customer" :required="true" />
            <select id="customer_id" name="customer_id"
                class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                required>
                <option value="">Select Customer</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}"
                        data-address="{{ $customer->address }}{{ $customer->city ? ', ' . $customer->city : '' }}" {{ (int) old('customer_id') === $customer->id ? 'selected' : '' }}>
                        {{ $customer->customer_code }} — {{ $customer->customer_name }}@if($customer->address) —
                        {{ $customer->address }}@endif
                    </option>
                @endforeach
            </select>
        </div>

        <div id="customer-address-display" class="md:col-span-2" style="display: none;">
            <x-label value="Customer Address" />
            <div id="customer-address-text"
                class="mt-1 p-2 bg-gray-50 rounded-md text-sm text-gray-600 border border-gray-200"></div>
        </div>
    @else
        <div>
            <x-label value="Employee / Salesman" />
            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                :value="$transaction->account->employee->employee_code . ' — ' . $transaction->account->employee->name"
                disabled readonly />
        </div>

        <div>
            <x-label value="Customer" />
            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                :value="$transaction->account->customer->customer_code . ' — ' . $transaction->account->customer->customer_name" disabled readonly />
        </div>
    @endunless

    <div>
        <x-label for="balance_date" value="Balance Date" :required="true" />
        <x-input id="balance_date" type="date" name="balance_date" class="mt-1 block w-full" :value="old('balance_date', optional($transaction?->transaction_date)->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
    </div>

    <div>
        <x-label for="opening_balance" value="Opening Balance" :required="true" />
        <x-input id="opening_balance" type="number" name="opening_balance" class="mt-1 block w-full" step="0.01"
            min="0.01" :value="old('opening_balance', optional($transaction)->debit)" required />
    </div>
</div>

<div class="mt-4">
    <x-label for="description" value="Description" />
    <textarea id="description" name="description" rows="2"
        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
        placeholder="Optional description">{{ old('description', optional($transaction)->description) }}</textarea>
</div>

@unless($isEdit)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const customerSelect = document.getElementById('customer_id');
                const addressDisplay = document.getElementById('customer-address-display');
                const addressText = document.getElementById('customer-address-text');

                if (customerSelect && addressDisplay && addressText) {
                    function updateAddress() {
                        const selected = customerSelect.options[customerSelect.selectedIndex];
                        const address = selected ? selected.getAttribute('data-address') : '';
                        if (address) {
                            addressText.textContent = address;
                            addressDisplay.style.display = '';
                        } else {
                            addressDisplay.style.display = 'none';
                        }
                    }

                    customerSelect.addEventListener('change', updateAddress);
                    updateAddress();
                }
            });
        </script>
    @endpush
@endunless