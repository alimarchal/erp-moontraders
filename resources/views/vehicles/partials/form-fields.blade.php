@php
    $vehicle = $vehicle ?? null;
    $employeeOptions = $employeeOptions ?? collect();
    $companyOptions = $companyOptions ?? collect();
    $supplierOptions = $supplierOptions ?? collect();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="vehicle_number" value="Vehicle Number" :required="true" />
        <x-input id="vehicle_number" type="text" name="vehicle_number"
            class="mt-1 block w-full uppercase" required maxlength="191"
            :value="old('vehicle_number', optional($vehicle)->vehicle_number)" placeholder="e.g., RLF-4328" />
    </div>

    <div>
        <x-label for="registration_number" value="Registration Number" :required="true" />
        <x-input id="registration_number" type="text" name="registration_number"
            class="mt-1 block w-full uppercase" required maxlength="191"
            :value="old('registration_number', optional($vehicle)->registration_number)" placeholder="e.g., RLF-4328" />
    </div>

    <div>
        <x-label for="vehicle_type" value="Vehicle Type" />
        <x-input id="vehicle_type" type="text" name="vehicle_type" class="mt-1 block w-full" maxlength="100"
            :value="old('vehicle_type', optional($vehicle)->vehicle_type)" placeholder="Truck, Van, Pickup" />
    </div>

    <div>
        <x-label for="make_model" value="Make / Model" />
        <x-input id="make_model" type="text" name="make_model" class="mt-1 block w-full" maxlength="191"
            :value="old('make_model', optional($vehicle)->make_model)" placeholder="e.g., Hino 300" />
    </div>

    <div>
        <x-label for="year" value="Model Year" />
        <x-input id="year" type="text" name="year" class="mt-1 block w-full" maxlength="4" inputmode="numeric"
            :value="old('year', optional($vehicle)->year)" placeholder="2024" />
    </div>

    <div>
        <x-label for="company_id" value="Company" />
        <select id="company_id" name="company_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not linked</option>
            @foreach ($companyOptions as $company)
                <option value="{{ $company->id }}"
                    {{ (int) old('company_id', optional($vehicle)->company_id) === $company->id ? 'selected' : '' }}>
                    {{ $company->company_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="supplier_id" value="Transporter / Supplier" />
        <select id="supplier_id" name="supplier_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not linked</option>
            @foreach ($supplierOptions as $supplier)
                <option value="{{ $supplier->id }}"
                    {{ (int) old('supplier_id', optional($vehicle)->supplier_id) === $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->supplier_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="employee_id" value="Assigned Driver" />
        <select id="employee_id" name="employee_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Unassigned</option>
            @foreach ($employeeOptions as $employee)
                <option value="{{ $employee->id }}"
                    {{ (int) old('employee_id', optional($vehicle)->employee_id) === $employee->id ? 'selected' : '' }}>
                    {{ $employee->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="driver_name" value="Driver Name (if not an employee)" />
        <x-input id="driver_name" type="text" name="driver_name" class="mt-1 block w-full" maxlength="191"
            :value="old('driver_name', optional($vehicle)->driver_name)" placeholder="External driver" />
    </div>

    <div>
        <x-label for="driver_phone" value="Driver Phone" />
        <x-input id="driver_phone" type="text" name="driver_phone" class="mt-1 block w-full" maxlength="50"
            :value="old('driver_phone', optional($vehicle)->driver_phone)" placeholder="03XX-XXXXXXX" />
    </div>

    <div class="md:col-span-2">
        <div class="flex items-center">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" type="checkbox" name="is_active" value="1"
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                {{ old('is_active', optional($vehicle)->is_active ?? true) ? 'checked' : '' }}>
            <label for="is_active" class="ml-2 text-sm text-gray-700">
                Vehicle is active
            </label>
        </div>
    </div>
</div>
