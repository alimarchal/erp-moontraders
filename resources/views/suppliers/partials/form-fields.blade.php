@php
    /** @var \App\Models\Supplier|null $supplier */
    $supplier = $supplier ?? null;
    $currencyOptions = $currencyOptions ?? collect();
    $accountOptions = $accountOptions ?? collect();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="supplier_name" value="Supplier Name" :required="true" />
        <x-input id="supplier_name" type="text" name="supplier_name" class="mt-1 block w-full"
            :value="old('supplier_name', optional($supplier)->supplier_name)" required placeholder="Nestlé Pakistan" />
    </div>

    <div>
        <x-label for="short_name" value="Short Name" />
        <x-input id="short_name" type="text" name="short_name" class="mt-1 block w-full uppercase"
            maxlength="100" :value="old('short_name', optional($supplier)->short_name)" placeholder="NESTLE" />
    </div>

    <div>
        <x-label for="supplier_group" value="Supplier Group" />
        <x-input id="supplier_group" type="text" name="supplier_group" class="mt-1 block w-full"
            :value="old('supplier_group', optional($supplier)->supplier_group)" placeholder="Local" />
    </div>

    <div>
        <x-label for="supplier_type" value="Supplier Type" />
        <x-input id="supplier_type" type="text" name="supplier_type" class="mt-1 block w-full"
            :value="old('supplier_type', optional($supplier)->supplier_type)" placeholder="Food & Beverage" />
    </div>

    <div>
        <x-label for="country" value="Country" />
        <x-input id="country" type="text" name="country" class="mt-1 block w-full"
            :value="old('country', optional($supplier)->country)" placeholder="Pakistan" />
    </div>

    <div>
        <x-label for="default_price_list" value="Default Price List" />
        <x-input id="default_price_list" type="text" name="default_price_list" class="mt-1 block w-full"
            :value="old('default_price_list', optional($supplier)->default_price_list)" placeholder="STANDARD_PRICES" />
    </div>

    <div>
        <x-label for="default_currency_id" value="Default Currency" />
        <select id="default_currency_id" name="default_currency_id"
            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select currency</option>
            @foreach ($currencyOptions as $currency)
                <option value="{{ $currency->id }}"
                    {{ (int) old('default_currency_id', optional($supplier)->default_currency_id) === $currency->id ? 'selected' : '' }}>
                    {{ $currency->currency_code }} &middot; {{ $currency->currency_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="default_bank_account_id" value="Default Bank Account" />
        <select id="default_bank_account_id" name="default_bank_account_id"
            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select account</option>
            @foreach ($accountOptions as $account)
                <option value="{{ $account->id }}"
                    {{ (int) old('default_bank_account_id', optional($supplier)->default_bank_account_id) === $account->id ? 'selected' : '' }}>
                    {{ $account->account_code }} &middot; {{ $account->account_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="website" value="Website" />
        <x-input id="website" type="url" name="website" class="mt-1 block w-full"
            :value="old('website', optional($supplier)->website)" placeholder="https://example.com" />
    </div>

    <div>
        <x-label for="print_language" value="Print Language" />
        <x-input id="print_language" type="text" name="print_language" class="mt-1 block w-full"
            :value="old('print_language', optional($supplier)->print_language)" placeholder="English" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="tax_id" value="Tax ID / Registration" />
        <x-input id="tax_id" type="text" name="tax_id" class="mt-1 block w-full"
            :value="old('tax_id', optional($supplier)->tax_id)" placeholder="NTN-1234567" />
    </div>

    <div>
        <x-label for="pan_number" value="PAN Number" />
        <x-input id="pan_number" type="text" name="pan_number" class="mt-1 block w-full"
            :value="old('pan_number', optional($supplier)->pan_number)" placeholder="PAN-987654" />
    </div>
</div>

<div class="mt-4">
    <x-label for="supplier_primary_address" value="Primary Address" />
    <textarea id="supplier_primary_address" name="supplier_primary_address"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
        rows="3" placeholder="Street, City, Postal Code">{{ old('supplier_primary_address', optional($supplier)->supplier_primary_address) }}</textarea>
</div>

<div class="mt-4">
    <x-label for="supplier_primary_contact" value="Primary Contact Details" />
    <textarea id="supplier_primary_contact" name="supplier_primary_contact"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
        rows="3" placeholder="Name, phone, email">{{ old('supplier_primary_contact', optional($supplier)->supplier_primary_contact) }}</textarea>
</div>

<div class="mt-4">
    <x-label for="supplier_details" value="Additional Details" />
    <textarea id="supplier_details" name="supplier_details"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
        rows="4" placeholder="Notes about terms, payment, contacts">{{ old('supplier_details', optional($supplier)->supplier_details) }}</textarea>
</div>

<div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="flex items-center">
        <input type="hidden" name="is_transporter" value="0">
        <input id="is_transporter" type="checkbox" name="is_transporter" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('is_transporter', optional($supplier)->is_transporter ?? false) ? 'checked' : '' }}>
        <label for="is_transporter" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
            Supplier also provides transportation
        </label>
    </div>

    <div class="flex items-center">
        <input type="hidden" name="is_internal_supplier" value="0">
        <input id="is_internal_supplier" type="checkbox" name="is_internal_supplier" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('is_internal_supplier', optional($supplier)->is_internal_supplier ?? false) ? 'checked' : '' }}>
        <label for="is_internal_supplier" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
            Internal supplier (inter-company)
        </label>
    </div>

    <div class="flex items-center">
        <input type="hidden" name="disabled" value="0">
        <input id="disabled" type="checkbox" name="disabled" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('disabled', optional($supplier)->disabled ?? false) ? 'checked' : '' }}>
        <label for="disabled" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
            Supplier is disabled
        </label>
    </div>
</div>
