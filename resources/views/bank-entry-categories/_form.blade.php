@php($category = $category ?? null)

<div class="uf-fields">
    <div class="uf-field">
        <label for="name">Name <span class="uf-req">*</span></label>
        <x-input id="name" name="name" type="text" maxlength="191" class="mt-1 block w-full" required :value="old('name', $category?->name)" placeholder="Customer Receipt" />
    </div>
    <div class="uf-field">
        <label for="type">Used for <span class="uf-req">*</span></label>
        <select data-allow-clear="false" id="type" name="type" class="select2 bank-select">
            @foreach (\App\Models\BankEntryCategory::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $category?->type ?? 'both') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="uf-field">
    <label for="default_chart_of_account_id">Own ledger account (optional)</label>
    <select data-placeholder="Use the register's own account (recommended)" id="default_chart_of_account_id" name="default_chart_of_account_id" class="select2 bank-select">
        <option value="">Use the register's own account (recommended)</option>
        @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" @selected((int) old('default_chart_of_account_id', $category?->default_chart_of_account_id) === $account->id)>{{ $account->account_code }} — {{ $account->account_name }}</option>
        @endforeach
    </select>
    <p class="ak-muted" style="margin:4px 0 0">Leave empty: the register posts to its own accounts (6000 onwards), one per company where the entry has a company, and never to live accounts such as Debtors or Creditors. Pick an account only if this category must go to a specific one.</p>
</div>

<div class="uf-fields">
    <div class="uf-field">
        <label for="sort_order">Sort order</label>
        <x-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', $category?->sort_order ?? 100)" />
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center gap-2 mt-1">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"  @checked(old('is_active', $category?->is_active ?? true))>
            <span>Active</span>
        </label>
    </div>
</div>
