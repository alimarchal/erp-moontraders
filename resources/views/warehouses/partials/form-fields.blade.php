@php
/** @var \App\Models\Warehouse|null $warehouse */
$warehouse = $warehouse ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="warehouse_name" value="Warehouse Name" :required="true" />
        <x-input id="warehouse_name" type="text" name="warehouse_name" class="mt-1 block w-full"
            :value="old('warehouse_name', optional($warehouse)->warehouse_name)" required autofocus
            placeholder="Main Warehouse" />
    </div>

    <div>
        <x-label for="company_id" value="Company" />
        <select id="company_id" name="company_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Company</option>
            @foreach ($companies as $company)
            <option value="{{ $company->id }}" {{ (string) old('company_id', optional($warehouse)->company_id) ===
                (string) $company->id ? 'selected' : '' }}>
                {{ $company->company_name }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="warehouse_type_id" value="Warehouse Type" />
        <select id="warehouse_type_id" name="warehouse_type_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Type</option>
            @foreach ($warehouseTypes as $type)
            <option value="{{ $type->id }}" {{ (string) old('warehouse_type_id', optional($warehouse)->
                warehouse_type_id) === (string) $type->id ? 'selected' : '' }}>
                {{ $type->name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="account_id" value="Accounting Account" />
        <select id="account_id" name="account_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Account</option>
            @foreach ($chartOfAccounts as $coa)
            <option value="{{ $coa->id }}" {{ (string) old('account_id', optional($warehouse)->account_id) === (string)
                $coa->id ? 'selected' : '' }}>
                {{ $coa->account_code }} - {{ $coa->account_name }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="parent_warehouse_id" value="Parent Warehouse" />
        <select id="parent_warehouse_id" name="parent_warehouse_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">None</option>
            @foreach ($warehouses as $w)
            <option value="{{ $w->id }}" {{ (string) old('parent_warehouse_id', optional($warehouse)->
                parent_warehouse_id) === (string) $w->id ? 'selected' : '' }}>
                {{ $w->warehouse_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="default_in_transit_warehouse_id" value="Default In-Transit Warehouse" />
        <select id="default_in_transit_warehouse_id" name="default_in_transit_warehouse_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">None</option>
            @foreach ($warehouses as $w)
            <option value="{{ $w->id }}" {{ (string) old('default_in_transit_warehouse_id', optional($warehouse)->
                default_in_transit_warehouse_id) === (string) $w->id ? 'selected' : '' }}>
                {{ $w->warehouse_name }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div class="flex items-center space-x-2 mt-6">
        <x-checkbox id="is_group" name="is_group" :checked="old('is_group', optional($warehouse)->is_group)" />
        <x-label for="is_group" value="Is Group" />
    </div>
    <div class="flex items-center space-x-2 mt-6">
        <x-checkbox id="disabled" name="disabled" :checked="old('disabled', optional($warehouse)->disabled)" />
        <x-label for="disabled" value="Disabled" />
    </div>
    <div class="flex items-center space-x-2 mt-6">
        <x-checkbox id="is_rejected_warehouse" name="is_rejected_warehouse"
            :checked="old('is_rejected_warehouse', optional($warehouse)->is_rejected_warehouse)" />
        <x-label for="is_rejected_warehouse" value="Is Rejected Warehouse" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="email_id" value="Email" />
        <x-input id="email_id" type="email" name="email_id" class="mt-1 block w-full"
            :value="old('email_id', optional($warehouse)->email_id)" />
    </div>
    <div>
        <x-label for="phone_no" value="Phone" />
        <x-input id="phone_no" type="text" name="phone_no" class="mt-1 block w-full"
            :value="old('phone_no', optional($warehouse)->phone_no)" />
    </div>
    <div>
        <x-label for="mobile_no" value="Mobile" />
        <x-input id="mobile_no" type="text" name="mobile_no" class="mt-1 block w-full"
            :value="old('mobile_no', optional($warehouse)->mobile_no)" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="address_line_1" value="Address Line 1" />
        <x-input id="address_line_1" type="text" name="address_line_1" class="mt-1 block w-full"
            :value="old('address_line_1', optional($warehouse)->address_line_1)" />
    </div>
    <div>
        <x-label for="address_line_2" value="Address Line 2" />
        <x-input id="address_line_2" type="text" name="address_line_2" class="mt-1 block w-full"
            :value="old('address_line_2', optional($warehouse)->address_line_2)" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="city" value="City" />
        <x-input id="city" type="text" name="city" class="mt-1 block w-full"
            :value="old('city', optional($warehouse)->city)" />
    </div>
    <div>
        <x-label for="state" value="State" />
        <x-input id="state" type="text" name="state" class="mt-1 block w-full"
            :value="old('state', optional($warehouse)->state)" />
    </div>
    <div>
        <x-label for="pin" value="PIN/Postal Code" />
        <x-input id="pin" type="text" name="pin" class="mt-1 block w-full"
            :value="old('pin', optional($warehouse)->pin)" />
    </div>
</div>