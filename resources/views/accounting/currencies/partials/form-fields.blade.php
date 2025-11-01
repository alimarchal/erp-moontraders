@php
    $currency = $currency ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="currency_code" value="Currency Code" :required="true" />
        <x-input id="currency_code" type="text" name="currency_code" class="mt-1 block w-full uppercase"
            maxlength="3" required
            :value="old('currency_code', optional($currency)->currency_code)" placeholder="e.g., USD" />
        <p class="mt-1 text-xs text-gray-500">Enter the 3-letter ISO code.</p>
    </div>

    <div>
        <x-label for="currency_name" value="Currency Name" :required="true" />
        <x-input id="currency_name" type="text" name="currency_name" class="mt-1 block w-full" required
            :value="old('currency_name', optional($currency)->currency_name)" placeholder="US Dollar" />
    </div>

    <div>
        <x-label for="currency_symbol" value="Currency Symbol" :required="true" />
        <x-input id="currency_symbol" type="text" name="currency_symbol" class="mt-1 block w-full" required
            :value="old('currency_symbol', optional($currency)->currency_symbol)" placeholder="$" />
    </div>

    <div>
        <x-label for="exchange_rate" value="Exchange Rate" :required="true" />
        <x-input id="exchange_rate" type="number" name="exchange_rate" class="mt-1 block w-full" required step="0.000001"
            min="0" :value="old('exchange_rate', optional($currency)->exchange_rate ?? 1)" />
        <p class="mt-1 text-xs text-gray-500">Relative to base currency (defaults to 1.000000).</p>
    </div>

    <div class="md:col-span-2 flex flex-col sm:flex-row sm:items-center sm:space-x-6 space-y-3 sm:space-y-0">
        <div class="flex items-center">
            <input type="hidden" name="is_base_currency" value="0">
            <input id="is_base_currency" type="checkbox" name="is_base_currency" value="1"
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                {{ old('is_base_currency', optional($currency)->is_base_currency ?? false) ? 'checked' : '' }}>
            <label for="is_base_currency" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                Set as base currency
            </label>
        </div>

        <div class="flex items-center">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" type="checkbox" name="is_active" value="1"
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                {{ old('is_active', optional($currency)->is_active ?? true) ? 'checked' : '' }}>
            <label for="is_active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                Currency is active
            </label>
        </div>
    </div>
</div>
