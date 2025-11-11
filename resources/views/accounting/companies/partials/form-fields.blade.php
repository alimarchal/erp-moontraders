@php
    /** @var \App\Models\Company|null $company */
    $company = $company ?? null;
    $currencyOptions = $currencyOptions ?? collect();
    $costCenterOptions = $costCenterOptions ?? collect();
    $accountOptions = $accountOptions ?? collect();
    $parentOptions = $parentOptions ?? collect();

    $accountFieldLabels = [
        'default_bank_account_id' => 'Default Bank Account',
        'default_cash_account_id' => 'Default Cash Account',
        'default_receivable_account_id' => 'Default Receivable Account',
        'default_payable_account_id' => 'Default Payable Account',
        'default_expense_account_id' => 'Default Expense Account',
        'default_income_account_id' => 'Default Income Account',
        'write_off_account_id' => 'Write-off Account',
        'round_off_account_id' => 'Round-off Account',
        'default_inventory_account_id' => 'Default Inventory Account',
        'stock_adjustment_account_id' => 'Stock Adjustment Account',
    ];
@endphp

<input type="hidden" name="lft" value="{{ old('lft', optional($company)->lft) }}">
<input type="hidden" name="rgt" value="{{ old('rgt', optional($company)->rgt) }}">

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="company_name" value="Company Name" :required="true" />
        <x-input id="company_name" type="text" name="company_name" class="mt-1 block w-full"
            :value="old('company_name', optional($company)->company_name)" required autofocus />
    </div>

    <div>
        <x-label for="abbr" value="Abbreviation" />
        <x-input id="abbr" type="text" name="abbr" class="mt-1 block w-full"
            :value="old('abbr', optional($company)->abbr)" placeholder="MT" />
    </div>

    <div>
        <x-label for="country" value="Country" />
        <x-input id="country" type="text" name="country" class="mt-1 block w-full"
            :value="old('country', optional($company)->country)" placeholder="Pakistan" />
    </div>

    <div>
        <x-label for="tax_id" value="Tax ID" />
        <x-input id="tax_id" type="text" name="tax_id" class="mt-1 block w-full"
            :value="old('tax_id', optional($company)->tax_id)" />
    </div>

    <div>
        <x-label for="domain" value="Domain" />
        <x-input id="domain" type="text" name="domain" class="mt-1 block w-full"
            :value="old('domain', optional($company)->domain)" placeholder="moontraders.com" />
    </div>

    <div>
        <x-label for="parent_company_id" value="Parent Company" />
        <select id="parent_company_id" name="parent_company_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">No Parent (top level)</option>
            @foreach ($parentOptions as $parent)
                <option value="{{ $parent->id }}" {{ (int) old('parent_company_id', optional($company)->parent_company_id) === $parent->id ? 'selected' : '' }}>
                    {{ $parent->company_name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="phone_no" value="Phone Number" />
        <x-input id="phone_no" type="text" name="phone_no" class="mt-1 block w-full"
            :value="old('phone_no', optional($company)->phone_no)" />
    </div>
    <div>
        <x-label for="email" value="Email" />
        <x-input id="email" type="email" name="email" class="mt-1 block w-full"
            :value="old('email', optional($company)->email)" />
    </div>
    <div>
        <x-label for="fax" value="Fax" />
        <x-input id="fax" type="text" name="fax" class="mt-1 block w-full"
            :value="old('fax', optional($company)->fax)" />
    </div>
    <div>
        <x-label for="website" value="Website" />
        <x-input id="website" type="url" name="website" class="mt-1 block w-full"
            :value="old('website', optional($company)->website)" placeholder="https://example.com" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="date_of_establishment" value="Date of Establishment" />
        <x-input id="date_of_establishment" type="date" name="date_of_establishment" class="mt-1 block w-full"
            :value="old('date_of_establishment', optional($company?->date_of_establishment)->format('Y-m-d'))" />
    </div>
    <div>
        <x-label for="date_of_incorporation" value="Date of Incorporation" />
        <x-input id="date_of_incorporation" type="date" name="date_of_incorporation" class="mt-1 block w-full"
            :value="old('date_of_incorporation', optional($company?->date_of_incorporation)->format('Y-m-d'))" />
    </div>
    <div>
        <x-label for="date_of_commencement" value="Date of Commencement" />
        <x-input id="date_of_commencement" type="date" name="date_of_commencement" class="mt-1 block w-full"
            :value="old('date_of_commencement', optional($company?->date_of_commencement)->format('Y-m-d'))" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="default_currency_id" value="Default Currency" />
        <select id="default_currency_id" name="default_currency_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Currency</option>
            @foreach ($currencyOptions as $currency)
                <option value="{{ $currency->id }}" {{ (int) old('default_currency_id', optional($company)->default_currency_id) === $currency->id ? 'selected' : '' }}>
                    {{ $currency->currency_code }} · {{ $currency->currency_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <x-label for="cost_center_id" value="Default Cost Center" />
        <select id="cost_center_id" name="cost_center_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Cost Center</option>
            @foreach ($costCenterOptions as $costCenter)
                <option value="{{ $costCenter->id }}" {{ (int) old('cost_center_id', optional($company)->cost_center_id) === $costCenter->id ? 'selected' : '' }}>
                    {{ $costCenter->code }} · {{ $costCenter->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-4">
    <x-label for="company_description" value="Company Description" />
    <textarea id="company_description" name="company_description" rows="3"
        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
        placeholder="Brief description">{{ old('company_description', optional($company)->company_description) }}</textarea>
</div>

<div class="mt-4">
    <x-label for="registration_details" value="Registration Details" />
    <textarea id="registration_details" name="registration_details" rows="3"
        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('registration_details', optional($company)->registration_details) }}</textarea>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="credit_limit" value="Credit Limit" />
        <x-input id="credit_limit" type="number" name="credit_limit" step="0.01" min="0"
            class="mt-1 block w-full" :value="old('credit_limit', optional($company)->credit_limit ?? 0)" />
    </div>
    <div>
        <x-label for="monthly_sales_target" value="Monthly Sales Target" />
        <x-input id="monthly_sales_target" type="number" name="monthly_sales_target" step="0.01" min="0"
            class="mt-1 block w-full" :value="old('monthly_sales_target', optional($company)->monthly_sales_target ?? 0)" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    @foreach ($accountFieldLabels as $field => $label)
        <div>
            <x-label :for="$field" :value="$label" />
            <select id="{{ $field }}" name="{{ $field }}"
                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                <option value="">Select Account</option>
                @foreach ($accountOptions as $account)
                    <option value="{{ $account->id }}" {{ (int) old($field, optional($company)->{$field}) === $account->id ? 'selected' : '' }}>
                        {{ $account->account_code }} · {{ $account->account_name }}
                    </option>
                @endforeach
            </select>
        </div>
    @endforeach
</div>

<div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="flex items-center">
        <input type="hidden" name="is_group" value="0">
        <input id="is_group" type="checkbox" name="is_group" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('is_group', optional($company)->is_group) ? 'checked' : '' }}>
        <label for="is_group" class="ml-2 text-sm text-gray-700">
            This is a group company
        </label>
    </div>

    <div class="flex items-center">
        <input type="hidden" name="enable_perpetual_inventory" value="0">
        <input id="enable_perpetual_inventory" type="checkbox" name="enable_perpetual_inventory" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('enable_perpetual_inventory', optional($company)->enable_perpetual_inventory ?? true) ? 'checked' : '' }}>
        <label for="enable_perpetual_inventory" class="ml-2 text-sm text-gray-700">
            Enable perpetual inventory
        </label>
    </div>

    <div class="flex items-center">
        <input type="hidden" name="allow_account_creation_against_child_company" value="0">
        <input id="allow_account_creation_against_child_company" type="checkbox" name="allow_account_creation_against_child_company" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('allow_account_creation_against_child_company', optional($company)->allow_account_creation_against_child_company) ? 'checked' : '' }}>
        <label for="allow_account_creation_against_child_company" class="ml-2 text-sm text-gray-700">
            Allow account creation against child companies
        </label>
    </div>
</div>
