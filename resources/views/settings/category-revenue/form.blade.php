<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="supplier_id" value="Supplier" />
        <select id="supplier_id" name="supplier_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">All Suppliers</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" {{ (string) old('supplier_id', optional($category)->supplier_id) === (string) $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->supplier_name }}
                </option>
            @endforeach
        </select>
        <x-input-error for="supplier_id" class="mt-2" />
    </div>

    <div>
        <x-label for="name" value="Category Name" :required="true" />
        <x-input id="name" type="text" name="name" class="mt-1 block w-full"
            :value="old('name', optional($category)->name)" required autofocus />
        <x-input-error for="name" class="mt-2" />
    </div>

    <div>
        <x-label for="slug" value="Slug" />
        <x-input id="slug" type="text" name="slug" class="mt-1 block w-full"
            :value="old('slug', optional($category)->slug)" />
        <x-input-error for="slug" class="mt-2" />
    </div>
</div>

<div class="mt-4">
    <label for="is_active" class="inline-flex items-center">
        <input id="is_active" type="checkbox"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            name="is_active" value="1" {{ old('is_active', optional($category)->is_active ?? true) ? 'checked' : '' }}>
        <span class="ml-2 text-sm text-gray-600">Active</span>
    </label>
</div>
