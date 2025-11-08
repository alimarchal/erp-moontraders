@php
/** @var \App\Models\BankAccount|null $account */
$account = $account ?? null;
$accountOptions = $accountOptions ?? collect();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="account_name" value="Account Name" :required="true" />
        <x-input id="account_name" type="text" name="account_name" maxlength="191" class="mt-1 block w-full" required
            :value="old('account_name', optional($account)->account_name)" placeholder="HBL Main Account" />
    </div>

    <div>
        <x-label for="account_number" value="Account Number" :required="true" />
        <x-input id="account_number" type="text" name="account_number" maxlength="191" class="mt-1 block w-full"
            required :value="old('account_number', optional($account)->account_number)" placeholder="24997000284199" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="bank_name" value="Bank Name" />
        <x-input id="bank_name" type="text" name="bank_name" maxlength="191" class="mt-1 block w-full"
            :value="old('bank_name', optional($account)->bank_name)" placeholder="Habib Bank Limited" />
    </div>

    <div>
        <x-label for="branch" value="Branch" />
        <x-input id="branch" type="text" name="branch" maxlength="191" class="mt-1 block w-full"
            :value="old('branch', optional($account)->branch)" placeholder="Main Branch" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="iban" value="IBAN" />
        <x-input id="iban" type="text" name="iban" maxlength="191" class="mt-1 block w-full uppercase"
            :value="old('iban', optional($account)->iban)" placeholder="PK36HABB0000000000000000" />
    </div>

    <div>
        <x-label for="swift_code" value="SWIFT Code" />
        <x-input id="swift_code" type="text" name="swift_code" maxlength="191" class="mt-1 block w-full uppercase"
            :value="old('swift_code', optional($account)->swift_code)" placeholder="HABBPKKA" />
    </div>
</div>

<div class="mt-4">
    <x-label for="chart_of_account_id" value="Chart of Account" />
    <select id="chart_of_account_id" name="chart_of_account_id"
        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
        <option value="">Not linked</option>
        @foreach ($accountOptions as $chartAccount)
        <option value="{{ $chartAccount->id }}" {{ (int) old('chart_of_account_id', optional($account)->
            chart_of_account_id) === $chartAccount->id ? 'selected' : '' }}>
            {{ $chartAccount->account_code }} — {{ $chartAccount->account_name }}
        </option>
        @endforeach
    </select>
</div>

<div class="mt-4">
    <x-label for="description" value="Description" />
    <textarea id="description" name="description" rows="3"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
        placeholder="Additional notes about this bank account">{{ old('description', optional($account)->description) }}</textarea>
</div>

<div class="mt-4">
    <div class="flex items-center">
        <input type="hidden" name="is_active" value="0">
        <input id="is_active" type="checkbox" name="is_active" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" {{
            old('is_active', optional($account)->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
            Account is active
        </label>
    </div>
</div>