@php($category = $category ?? null)

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="name" value="Name" :required="true" />
        <x-input id="name" name="name" type="text" maxlength="191" class="mt-1 block w-full" required :value="old('name', $category?->name)" placeholder="Customer Receipt" />
    </div>
    <div>
        <x-label for="type" value="Used for" :required="true" />
        <select id="type" name="type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            @foreach (\App\Models\BankEntryCategory::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $category?->type ?? 'both') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-4">
    <x-label for="default_chart_of_account_id" value="Default ledger account (other side of the bank)" />
    <select id="default_chart_of_account_id" name="default_chart_of_account_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
        <option value="">Use the company's receivable / payable account</option>
        @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" @selected((int) old('default_chart_of_account_id', $category?->default_chart_of_account_id) === $account->id)>{{ $account->account_code }} — {{ $account->account_name }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">When this category is posted, this account is debited or credited against the bank. Leave empty for categories that always belong to a company.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="sort_order" value="Sort order" />
        <x-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', $category?->sort_order ?? 100)" />
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center gap-2 mt-1">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('is_active', $category?->is_active ?? true))>
            <span class="text-sm text-gray-700">Active</span>
        </label>
    </div>
</div>
