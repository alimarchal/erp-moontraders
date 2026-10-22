@php($rule = $rule ?? null)

<div class="uf-field">
    <label for="name">Rule name <span class="uf-req">*</span></label>
    <x-input id="name" name="name" type="text" maxlength="191" class="mt-1 block w-full" required :value="old('name', $rule?->name)" placeholder="Bank charges" />
</div>

<div class="uf-fields">
    <div class="uf-field">
        <label for="match_field">Look in <span class="uf-req">*</span></label>
        <select data-allow-clear="false" id="match_field" name="match_field" class="select2 bank-select">
            @foreach (\App\Models\BankEntryRule::FIELDS as $value => $label)
                <option value="{{ $value }}" @selected(old('match_field', $rule?->match_field ?? 'narration') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="uf-field">
        <label for="match_type">Match <span class="uf-req">*</span></label>
        <select data-allow-clear="false" id="match_type" name="match_type" class="select2 bank-select">
            @foreach (\App\Models\BankEntryRule::MATCH_TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('match_type', $rule?->match_type ?? 'contains') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="uf-field">
        <label for="match_value">Text <span class="uf-req">*</span></label>
        <x-input id="match_value" name="match_value" type="text" dir="auto" maxlength="191" class="mt-1 block w-full" required :value="old('match_value', $rule?->match_value)" placeholder="Service Charges" />
    </div>
</div>

<div class="uf-fields">
    <div class="uf-field">
        <label for="direction">Applies to <span class="uf-req">*</span></label>
        <select data-allow-clear="false" id="direction" name="direction" class="select2 bank-select">
            @foreach (\App\Models\BankEntryRule::DIRECTIONS as $value => $label)
                <option value="{{ $value }}" @selected(old('direction', $rule?->direction ?? 'any') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="uf-field">
        <label for="bank_entry_category_id">Then set category <span class="uf-req">*</span></label>
        <select data-placeholder="Choose…" id="bank_entry_category_id" name="bank_entry_category_id" class="select2 bank-select" required>
            <option value="">Choose…</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('bank_entry_category_id', $rule?->bank_entry_category_id) === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="uf-field">
        <label for="priority">Priority (1 first) <span class="uf-req">*</span></label>
        <x-input id="priority" name="priority" type="number" min="1" class="mt-1 block w-full" required :value="old('priority', $rule?->priority ?? 100)" />
    </div>
</div>

<div class="uf-field">
    <label for="company_id">And set company (optional)</label>
    <select data-placeholder="Leave company as it is" id="company_id" name="company_id" class="select2 bank-select">
        <option value="">Leave company as it is</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected((int) old('company_id', $rule?->company_id) === $company->id)>{{ $company->supplier_name }}</option>
        @endforeach
    </select>
</div>

<label style="display:inline-flex; gap:8px; align-items:center; margin-top:16px">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1"  @checked(old('is_active', $rule?->is_active ?? true))>
    <span>Active</span>
</label>
