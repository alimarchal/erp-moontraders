@php
    $uom = $uom ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="uom_name" value="Unit Name" :required="true" />
        <x-input id="uom_name" type="text" name="uom_name" class="mt-1 block w-full" maxlength="191" required
            :value="old('uom_name', optional($uom)->uom_name)" placeholder="e.g., Kilogram" />
    </div>

    <div>
        <x-label for="symbol" value="Symbol" />
        <x-input id="symbol" type="text" name="symbol" class="mt-1 block w-full" maxlength="50"
            :value="old('symbol', optional($uom)->symbol)" placeholder="e.g., kg" />
    </div>

    <div class="md:col-span-2">
        <x-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3"
            class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', optional($uom)->description) }}</textarea>
    </div>

    <div class="flex items-center space-x-6 md:col-span-2">
        <div class="flex items-center">
            <input type="hidden" name="must_be_whole_number" value="0">
            <input id="must_be_whole_number" type="checkbox" name="must_be_whole_number" value="1"
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                {{ old('must_be_whole_number', optional($uom)->must_be_whole_number ?? false) ? 'checked' : '' }}>
            <label for="must_be_whole_number" class="ml-2 text-sm text-gray-700">
                Quantity must be an integer
            </label>
        </div>

        <div class="flex items-center">
            <input type="hidden" name="enabled" value="0">
            <input id="enabled" type="checkbox" name="enabled" value="1"
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                {{ old('enabled', optional($uom)->enabled ?? true) ? 'checked' : '' }}>
            <label for="enabled" class="ml-2 text-sm text-gray-700">
                Unit is enabled
            </label>
        </div>
    </div>
</div>
