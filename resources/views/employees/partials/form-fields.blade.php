@php
/** @var \App\Models\Employee|null $employee */
$employee = $employee ?? null;
$warehouseOptions = $warehouseOptions ?? collect();
$userOptions = $userOptions ?? collect();
$supplierOptions = $supplierOptions ?? collect();
$companyOptions = $companyOptions ?? collect();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="employee_code" value="Employee Code" :required="true" />
        <x-input id="employee_code" type="text" name="employee_code" class="mt-1 block w-full uppercase" maxlength="20"
            :value="old('employee_code', optional($employee)->employee_code)" required placeholder="EMP001" />
        <p class="text-xs text-gray-500 mt-1">Use letters, numbers, dashes, underscores or dots. Code must be unique.
        </p>
    </div>

    <div>
        <x-label for="name" value="Employee Name" :required="true" />
        <x-input id="name" type="text" name="name" class="mt-1 block w-full"
            :value="old('name', optional($employee)->name)" required placeholder="John Doe" />
    </div>

    <div>
        <x-label for="company_name" value="Company / Principal" />
        <x-input id="company_name" type="text" name="company_name" class="mt-1 block w-full"
            :value="old('company_name', optional($employee)->company_name)" placeholder="Nestle Pakistan" />
    </div>

    <div>
        <x-label for="designation" value="Designation" />
        <x-input id="designation" type="text" name="designation" class="mt-1 block w-full"
            :value="old('designation', optional($employee)->designation)" placeholder="Warehouse Manager" />
    </div>

    <div>
        <x-label for="supplier_id" value="Supplier" />
        <select id="supplier_id" name="supplier_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">No supplier</option>
            @foreach ($supplierOptions as $supplier)
            <option value="{{ $supplier->id }}" {{ (int) old('supplier_id', optional($employee)->supplier_id) ===
                $supplier->id ? 'selected' : '' }}>
                {{ $supplier->supplier_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="company_id" value="Company Entity" />
        <select id="company_id" name="company_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not assigned</option>
            @foreach ($companyOptions as $company)
            <option value="{{ $company->id }}" {{ (int) old('company_id', optional($employee)->company_id) ===
                $company->id ? 'selected' : '' }}>
                {{ $company->company_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="phone" value="Phone" />
        <x-input id="phone" type="text" name="phone" class="mt-1 block w-full"
            :value="old('phone', optional($employee)->phone)" placeholder="03XX-1234567" />
    </div>

    <div>
        <x-label for="email" value="Email" />
        <x-input id="email" type="email" name="email" class="mt-1 block w-full"
            :value="old('email', optional($employee)->email)" placeholder="john.doe@example.com" />
    </div>

    <div>
        <x-label for="warehouse_id" value="Primary Warehouse" />
        <select id="warehouse_id" name="warehouse_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not assigned</option>
            @foreach ($warehouseOptions as $warehouse)
            <option value="{{ $warehouse->id }}" {{ (int) old('warehouse_id', optional($employee)->warehouse_id) ===
                $warehouse->id ? 'selected' : '' }}>
                {{ $warehouse->warehouse_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="user_id" value="Linked User" />
        <select id="user_id" name="user_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">No linked user</option>
            @foreach ($userOptions as $user)
            <option value="{{ $user->id }}" {{ (int) old('user_id', optional($employee)->user_id) === $user->id ?
                'selected' : '' }}>
                {{ $user->name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="hire_date" value="Hire Date" />
        <x-input id="hire_date" type="date" name="hire_date" class="mt-1 block w-full"
            :value="old('hire_date', optional($employee?->hire_date)->format('Y-m-d'))" />
    </div>
</div>

<div class="mt-4">
    <x-label for="address" value="Address / Notes" />
    <textarea id="address" name="address"
        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
        rows="3"
        placeholder="Office address, remarks, etc.">{{ old('address', optional($employee)->address) }}</textarea>
</div>

<div class="mt-4 flex items-center">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" type="checkbox" name="is_active" value="1"
        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" {{
        old('is_active', optional($employee)->is_active ?? true) ? 'checked' : '' }}>
    <label for="is_active" class="ml-2 text-sm text-gray-700">
        Employee is active
    </label>
</div>