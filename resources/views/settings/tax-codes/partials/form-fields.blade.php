@php
    /** @var \App\Models\TaxCode|null $taxCode */
    $taxCode = $taxCode ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="tax_code" value="Tax Code" :required="true" />
        <x-input id="tax_code" type="text" name="tax_code" class="mt-1 block w-full"
            :value="old('tax_code', optional($taxCode)->tax_code)" required autofocus placeholder="GST-18" />
    </div>

    <div>
        <x-label for="name" value="Tax Name" :required="true" />
        <x-input id="name" type="text" name="name" class="mt-1 block w-full"
            :value="old('name', optional($taxCode)->name)" required placeholder="GST @ 18%" />
    </div>
</div>

<div class="grid grid-cols-1 gap-4 mt-4">
    <div>
        <x-label for="description" value="Description" />
        <textarea id="description" name="description"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            rows="3" placeholder="Enter tax description">{{ old('description', optional($taxCode)->description) }}</textarea>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="tax_type" value="Tax Type" :required="true" />
        <select id="tax_type" name="tax_type"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Tax Type</option>
            @foreach ($taxTypeOptions as $value => $label)
                <option value="{{ $value }}" {{ old('tax_type', optional($taxCode)->tax_type) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="calculation_method" value="Calculation Method" :required="true" />
        <select id="calculation_method" name="calculation_method"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Method</option>
            @foreach ($calculationMethodOptions as $value => $label)
                <option value="{{ $value }}" {{ old('calculation_method', optional($taxCode)->calculation_method) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="tax_payable_account_id" value="Tax Payable Account" />
        <select id="tax_payable_account_id" name="tax_payable_account_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Account</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" {{ old('tax_payable_account_id', optional($taxCode)->tax_payable_account_id) == $account->id ? 'selected' : '' }}>
                    {{ $account->account_code }} - {{ $account->account_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="tax_receivable_account_id" value="Tax Receivable Account" />
        <select id="tax_receivable_account_id" name="tax_receivable_account_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Account</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" {{ old('tax_receivable_account_id', optional($taxCode)->tax_receivable_account_id) == $account->id ? 'selected' : '' }}>
                    {{ $account->account_code }} - {{ $account->account_name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
    <div class="flex items-center">
        <input id="is_active" type="checkbox" name="is_active" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('is_active', optional($taxCode)->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="ml-2 text-sm text-gray-700">
            Active
        </label>
    </div>

    <div class="flex items-center">
        <input id="is_compound" type="checkbox" name="is_compound" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('is_compound', optional($taxCode)->is_compound ?? false) ? 'checked' : '' }}>
        <label for="is_compound" class="ml-2 text-sm text-gray-700">
            Compound Tax
        </label>
    </div>

    <div class="flex items-center">
        <input id="included_in_price" type="checkbox" name="included_in_price" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('included_in_price', optional($taxCode)->included_in_price ?? false) ? 'checked' : '' }}>
        <label for="included_in_price" class="ml-2 text-sm text-gray-700">
            Included in Price
        </label>
    </div>
</div>
