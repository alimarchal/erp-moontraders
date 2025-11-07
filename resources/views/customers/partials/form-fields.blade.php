@php
/** @var \App\Models\Customer|null $customer */
$customer = $customer ?? null;
$channelTypes = $channelTypes ?? [];
$customerCategories = $customerCategories ?? [];
$accountOptions = $accountOptions ?? collect();
$salesRepOptions = $salesRepOptions ?? collect();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="customer_code" value="Customer Code" :required="true" />
        <x-input id="customer_code" type="text" name="customer_code"
            class="mt-1 block w-full uppercase" maxlength="191" required
            :value="old('customer_code', optional($customer)->customer_code)" placeholder="CUST-001" />
    </div>

    <div>
        <x-label for="customer_name" value="Customer Name" :required="true" />
        <x-input id="customer_name" type="text" name="customer_name" class="mt-1 block w-full" maxlength="191" required
            :value="old('customer_name', optional($customer)->customer_name)" placeholder="Al Rehman Store" />
    </div>

    <div>
        <x-label for="business_name" value="Business / Trade Name" />
        <x-input id="business_name" type="text" name="business_name" class="mt-1 block w-full" maxlength="191"
            :value="old('business_name', optional($customer)->business_name)" placeholder="Registered name" />
    </div>

    <div>
        <x-label for="sales_rep_id" value="Assigned Sales Rep" />
        <select id="sales_rep_id" name="sales_rep_id"
            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Unassigned</option>
            @foreach ($salesRepOptions as $salesRep)
            <option value="{{ $salesRep->id }}" {{ (int) old('sales_rep_id', optional($customer)->sales_rep_id) ===
                $salesRep->id ? 'selected' : '' }}>
                {{ $salesRep->name }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="phone" value="Phone" />
        <x-input id="phone" type="text" name="phone" class="mt-1 block w-full" maxlength="50"
            :value="old('phone', optional($customer)->phone)" placeholder="03XX-XXXXXXX" />
    </div>

    <div>
        <x-label for="email" value="Email" />
        <x-input id="email" type="email" name="email" class="mt-1 block w-full" maxlength="191"
            :value="old('email', optional($customer)->email)" placeholder="store@example.com" />
    </div>
</div>

<div class="mt-4">
    <x-label for="address" value="Billing Address" />
    <textarea id="address" name="address" rows="3"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
        placeholder="Street, area, city">{{ old('address', optional($customer)->address) }}</textarea>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
    <div>
        <x-label for="sub_locality" value="Sub Locality / Area" />
        <x-input id="sub_locality" type="text" name="sub_locality" class="mt-1 block w-full" maxlength="191"
            :value="old('sub_locality', optional($customer)->sub_locality)" placeholder="Satellite Town" />
    </div>

    <div>
        <x-label for="city" value="City" />
        <x-input id="city" type="text" name="city" class="mt-1 block w-full" maxlength="191"
            :value="old('city', optional($customer)->city)" placeholder="Muzaffarabad" />
    </div>

    <div>
        <x-label for="state" value="State / Region" />
        <x-input id="state" type="text" name="state" class="mt-1 block w-full" maxlength="191"
            :value="old('state', optional($customer)->state ?? 'Azad Kashmir')" placeholder="Azad Kashmir" />
    </div>

    <div>
        <x-label for="country" value="Country" />
        <x-input id="country" type="text" name="country" class="mt-1 block w-full" maxlength="191"
            :value="old('country', optional($customer)->country ?? 'Pakistan')" placeholder="Pakistan" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
    <div>
        <x-label for="channel_type" value="Channel Type" :required="true" />
        <select id="channel_type" name="channel_type"
            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
            required>
            @foreach ($channelTypes as $type)
            <option value="{{ $type }}" {{ old('channel_type', optional($customer)->channel_type) === $type ? 'selected'
                : '' }}>
                {{ $type }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="customer_category" value="Customer Category" :required="true" />
        <select id="customer_category" name="customer_category"
            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
            required>
            @foreach ($customerCategories as $category)
            <option value="{{ $category }}" {{ old('customer_category', optional($customer)->customer_category) ===
                $category ? 'selected' : '' }}>
                Category {{ $category }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="payment_terms" value="Payment Terms (days)" />
        <x-input id="payment_terms" type="number" name="payment_terms" class="mt-1 block w-full" min="0" max="365"
            :value="old('payment_terms', optional($customer)->payment_terms ?? 30)" placeholder="30" />
    </div>

    <div>
        <x-label for="last_sale_date" value="Last Sale Date" />
        <x-input id="last_sale_date" type="date" name="last_sale_date" class="mt-1 block w-full"
            :value="old('last_sale_date', optional(optional($customer)->last_sale_date)->format('Y-m-d'))" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="credit_limit" value="Credit Limit" />
        <x-input id="credit_limit" type="number" name="credit_limit" class="mt-1 block w-full" min="0" step="0.01"
            :value="old('credit_limit', optional($customer)->credit_limit ?? 50000)" placeholder="50000" />
    </div>

    <div>
        <x-label for="credit_used" value="Credit Used" />
        <x-input id="credit_used" type="number" name="credit_used" class="mt-1 block w-full" min="0" step="0.01"
            :value="old('credit_used', optional($customer)->credit_used ?? 0)" placeholder="0" />
    </div>

    <div>
        <x-label for="lifetime_value" value="Lifetime Value" />
        <x-input id="lifetime_value" type="number" name="lifetime_value" class="mt-1 block w-full" min="0" step="0.01"
            :value="old('lifetime_value', optional($customer)->lifetime_value ?? 0)" placeholder="0" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="receivable_balance" value="Receivable Balance" />
        <x-input id="receivable_balance" type="number" name="receivable_balance" class="mt-1 block w-full" step="0.01"
            :value="old('receivable_balance', optional($customer)->receivable_balance ?? 0)" placeholder="0" />
    </div>

    <div>
        <x-label for="payable_balance" value="Payable Balance" />
        <x-input id="payable_balance" type="number" name="payable_balance" class="mt-1 block w-full" step="0.01"
            :value="old('payable_balance', optional($customer)->payable_balance ?? 0)" placeholder="0" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="receivable_account_id" value="Receivable Account (AR)" />
        <select id="receivable_account_id" name="receivable_account_id"
            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not linked</option>
            @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" {{ (int) old('receivable_account_id', optional($customer)->receivable_account_id ?? 0) === $account->id ? 'selected' : '' }}>
                {{ $account->account_code }} — {{ $account->account_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="payable_account_id" value="Payable Account (AP)" />
        <select id="payable_account_id" name="payable_account_id"
            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not linked</option>
            @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" {{ (int) old('payable_account_id', optional($customer)->payable_account_id ?? 0) === $account->id ? 'selected' : '' }}>
                {{ $account->account_code }} — {{ $account->account_name }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-4">
    <x-label for="notes" value="Internal Notes" />
    <textarea id="notes" name="notes" rows="4"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
        placeholder="Credit preference, delivery window, etc.">{{ old('notes', optional($customer)->notes) }}</textarea>
</div>

<div class="mt-4">
    <div class="flex items-center">
        <input type="hidden" name="is_active" value="0">
        <input id="is_active" type="checkbox" name="is_active" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('is_active', optional($customer)->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
            Customer is active
        </label>
    </div>
</div>
