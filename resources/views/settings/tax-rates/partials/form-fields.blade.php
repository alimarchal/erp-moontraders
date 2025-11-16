@php
    /** @var \App\Models\TaxRate|null $taxRate */
    $taxRate = $taxRate ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="tax_code_id" value="Tax Code" :required="true" />
        <select id="tax_code_id" name="tax_code_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Tax Code</option>
            @foreach ($taxCodes as $taxCode)
                <option value="{{ $taxCode->id }}" {{ old('tax_code_id', optional($taxRate)->tax_code_id) == $taxCode->id ? 'selected' : '' }}>
                    {{ $taxCode->tax_code }} - {{ $taxCode->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="rate" value="Tax Rate (%)" :required="true" />
        <x-input id="rate" type="number" step="0.01" name="rate" class="mt-1 block w-full"
            :value="old('rate', optional($taxRate)->rate)" required autofocus placeholder="18.00" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="effective_from" value="Effective From" :required="true" />
        <x-input id="effective_from" type="date" name="effective_from" class="mt-1 block w-full"
            :value="old('effective_from', optional($taxRate)->effective_from?->format('Y-m-d'))" required />
    </div>

    <div>
        <x-label for="effective_to" value="Effective To" />
        <x-input id="effective_to" type="date" name="effective_to" class="mt-1 block w-full"
            :value="old('effective_to', optional($taxRate)->effective_to?->format('Y-m-d'))" />
    </div>
</div>

<div class="grid grid-cols-1 gap-4 mt-4">
    <div>
        <x-label for="region" value="Region" />
        <x-input id="region" type="text" name="region" class="mt-1 block w-full"
            :value="old('region', optional($taxRate)->region)" placeholder="Leave blank for all regions" />
    </div>
</div>

<div class="grid grid-cols-1 gap-4 mt-6">
    <div class="flex items-center">
        <input id="is_active" type="checkbox" name="is_active" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('is_active', optional($taxRate)->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="ml-2 text-sm text-gray-700">
            Active
        </label>
    </div>
</div>
