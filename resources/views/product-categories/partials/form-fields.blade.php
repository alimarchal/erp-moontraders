@php
/** @var \App\Models\ProductCategory|null $category */
$category = $category ?? null;
$parentOptions = $parentOptions ?? collect();
$accountOptions = $accountOptions ?? collect();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="category_code" value="Category Code" :required="true" />
        <x-input id="category_code" type="text" name="category_code" maxlength="191"
            class="mt-1 block w-full uppercase" required
            :value="old('category_code', optional($category)->category_code)" placeholder="BISCUITS" />
    </div>

    <div>
        <x-label for="category_name" value="Category Name" :required="true" />
        <x-input id="category_name" type="text" name="category_name" maxlength="191"
            class="mt-1 block w-full" required
            :value="old('category_name', optional($category)->category_name)" placeholder="Biscuits & Cookies" />
    </div>
</div>

<div class="mt-4">
    <x-label for="description" value="Description" />
    <textarea id="description" name="description" rows="3"
        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
        placeholder="Short summary for planners and sales teams">{{ old('description', optional($category)->description) }}</textarea>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="parent_id" value="Parent Category" />
        <select id="parent_id" name="parent_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Top level</option>
            @foreach ($parentOptions as $parent)
            <option value="{{ $parent->id }}" {{ (int) old('parent_id', optional($category)->parent_id) === $parent->id ? 'selected' : '' }}>
                {{ $parent->category_name }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="default_inventory_account_id" value="Default Inventory Account" />
        <select id="default_inventory_account_id" name="default_inventory_account_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not linked</option>
            @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" {{ (int) old('default_inventory_account_id', optional($category)->default_inventory_account_id) === $account->id ? 'selected' : '' }}>
                {{ $account->account_code }} — {{ $account->account_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="default_cogs_account_id" value="Default COGS Account" />
        <select id="default_cogs_account_id" name="default_cogs_account_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not linked</option>
            @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" {{ (int) old('default_cogs_account_id', optional($category)->default_cogs_account_id) === $account->id ? 'selected' : '' }}>
                {{ $account->account_code }} — {{ $account->account_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="default_sales_revenue_account_id" value="Default Sales Revenue Account" />
        <select id="default_sales_revenue_account_id" name="default_sales_revenue_account_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not linked</option>
            @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" {{ (int) old('default_sales_revenue_account_id', optional($category)->default_sales_revenue_account_id) === $account->id ? 'selected' : '' }}>
                {{ $account->account_code }} — {{ $account->account_name }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-4">
    <div class="flex items-center">
        <input type="hidden" name="is_active" value="0">
        <input id="is_active" type="checkbox" name="is_active" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('is_active', optional($category)->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="ml-2 text-sm text-gray-700">
            Category is active
        </label>
    </div>
</div>
