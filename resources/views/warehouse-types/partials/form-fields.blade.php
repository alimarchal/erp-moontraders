@php
/** @var \App\Models\WarehouseType|null $warehouseType */
$warehouseType = $warehouseType ?? null;
@endphp

<div class="grid grid-cols-1 gap-4">
    <div>
        <x-label for="name" value="Type Name" :required="true" />
        <x-input id="name" type="text" name="name" class="mt-1 block w-full"
            :value="old('name', optional($warehouseType)->name)" required autofocus placeholder="Distribution Center" />
    </div>
</div>

<div class="grid grid-cols-1 gap-4 mt-4">
    <div>
        <x-label for="description" value="Description" />
        <textarea id="description" name="description" rows="4"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            placeholder="Describe the warehouse type...">{{ old('description', optional($warehouseType)->description) }}</textarea>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 mt-4">
    <div class="flex items-center space-x-2 mt-2">
        <x-checkbox id="is_active" name="is_active"
            :checked="old('is_active', optional($warehouseType)->is_active ?? true)" />
        <x-label for="is_active" value="Active" />
    </div>
</div>