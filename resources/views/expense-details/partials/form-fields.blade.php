@php
    /** @var \App\Models\ExpenseDetail|null $expense */
    $expense = $expense ?? null;
@endphp

{{-- Row 1: Category, Transaction Date, Amount --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <x-label for="category" value="Category" :required="true" />
        <select id="category" name="category"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Category</option>
            @foreach (\App\Models\ExpenseDetail::categoryOptions() as $value => $label)
                <option value="{{ $value }}"
                    {{ old('category', optional($expense)->category) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="transaction_date" value="Transaction Date" :required="true" />
        <x-input id="transaction_date" type="date" name="transaction_date" class="mt-1 block w-full"
            :value="old('transaction_date', optional($expense?->transaction_date)->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
    </div>

    <div>
        <x-label for="amount" value="Amount" :required="true" />
        <x-input id="amount" type="number" name="amount" class="mt-1 block w-full" step="0.01" min="0"
            :value="old('amount', optional($expense)->amount ?: '0.00')" required />
    </div>
</div>

{{-- Fuel-specific fields --}}
<div id="fuel-fields" class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4" style="display: none;">
    <div>
        <x-label for="vehicle_id" value="VAN #" :required="true" />
        <select id="vehicle_id" name="vehicle_id"
            class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Vehicle</option>
            @foreach ($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}"
                    data-type="{{ $vehicle->vehicle_type }}"
                    data-driver-id="{{ $vehicle->employee_id }}"
                    {{ old('vehicle_id', optional($expense)->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                    {{ $vehicle->vehicle_number }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="vehicle_type_display" value="Vehicle Type" />
        <x-input id="vehicle_type_display" type="text" class="mt-1 block w-full bg-gray-100 cursor-not-allowed"
            :value="old('vehicle_type', optional($expense)->vehicle_type)" disabled readonly />
    </div>

    <div>
        <x-label for="driver_display" value="Driver Name" />
        <x-input id="driver_display" type="text" class="mt-1 block w-full bg-gray-100 cursor-not-allowed"
            :value="optional($expense?->driverEmployee)->name" disabled readonly />
    </div>

    <div>
        <x-label for="liters" value="Liters" :required="true" />
        <x-input id="liters" type="number" name="liters" class="mt-1 block w-full" step="0.01" min="0"
            :value="old('liters', optional($expense)->liters)" />
    </div>
</div>

{{-- Salaries-specific fields --}}
<div id="salaries-fields" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4" style="display: none;">
    <div>
        <x-label for="employee_id" value="Employee" :required="true" />
        <select id="employee_id" name="employee_id"
            class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Employee</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}"
                    data-code="{{ $employee->employee_code }}"
                    {{ old('employee_id', optional($expense)->employee_id) == $employee->id ? 'selected' : '' }}>
                    {{ $employee->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="employee_no_display" value="Employee No" />
        <x-input id="employee_no_display" type="text" class="mt-1 block w-full bg-gray-100 cursor-not-allowed"
            :value="old('employee_no', optional($expense)->employee_no)" disabled readonly />
    </div>
</div>

{{-- Row 3: Description, Notes --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="description" value="Description" />
        <x-input id="description" type="text" name="description" class="mt-1 block w-full"
            :value="old('description', optional($expense)->description)" placeholder="Description" />
    </div>
    <div>
        <x-label for="notes" value="Notes" />
        <textarea id="notes" name="notes" rows="1"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            placeholder="Additional remarks">{{ old('notes', optional($expense)->notes) }}</textarea>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function () {
        const driversData = @json($drivers->keyBy('id'));

        // Initialize select2
        $('#vehicle_id').select2({ width: '100%', placeholder: 'Select Vehicle', allowClear: true });
        $('#employee_id').select2({ width: '100%', placeholder: 'Select Employee', allowClear: true });

        function toggleCategoryFields() {
            const category = $('#category').val();
            $('#fuel-fields').toggle(category === 'fuel');
            $('#salaries-fields').toggle(category === 'salaries');
        }

        // Toggle on change
        $('#category').on('change', function () {
            toggleCategoryFields();
        });

        // Auto-fill vehicle info
        $('#vehicle_id').on('change', function () {
            const selected = $(this).find(':selected');
            const vehicleType = selected.data('type') || '';
            const driverId = selected.data('driver-id') || '';

            $('#vehicle_type_display').val(vehicleType);

            if (driverId && driversData[driverId]) {
                $('#driver_display').val(driversData[driverId].name);
            } else {
                $('#driver_display').val('');
            }
        });

        // Auto-fill employee info
        $('#employee_id').on('change', function () {
            const selected = $(this).find(':selected');
            const employeeCode = selected.data('code') || '';
            $('#employee_no_display').val(employeeCode);
        });

        // Initialize on page load
        toggleCategoryFields();

        // Trigger change to populate auto-filled fields on edit
        @if ($expense)
            $('#vehicle_id').trigger('change');
            $('#employee_id').trigger('change');
        @endif
    });
</script>
@endpush
