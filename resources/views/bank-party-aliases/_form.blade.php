@php($alias = $alias ?? null)

<div class="uf-fields">
    <div class="uf-field">
        <label for="alias_name">Name as it appears on the statement <span class="uf-req">*</span></label>
        <x-input id="alias_name" name="alias_name" type="text" dir="auto" maxlength="191" class="mt-1 block w-full uppercase" required :value="old('alias_name', $alias?->alias_name)" placeholder="JAMEEL AHM" />
        <p class="ak-muted" style="margin:4px 0 0">Capital letters and spaces are ignored when matching.</p>
    </div>
    <div class="uf-field">
        <label for="canonical_name">Correct name <span class="uf-req">*</span></label>
        <x-input id="canonical_name" name="canonical_name" type="text" dir="auto" maxlength="191" class="mt-1 block w-full" required :value="old('canonical_name', $alias?->canonical_name)" placeholder="Jameel Ahmed" />
    </div>
</div>

<div class="uf-field">
    <label for="company_id">Company / principal</label>
    <select data-placeholder="Not linked to a company" id="company_id" name="company_id" class="select2 bank-select">
        <option value="">Not linked to a company</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected((int) old('company_id', $alias?->company_id) === $company->id)>{{ $company->supplier_name }}</option>
        @endforeach
    </select>
    <p class="ak-muted" style="margin:4px 0 0">Entries with this name get this company automatically on import or "Auto-classify".</p>
</div>

<label style="display:inline-flex; gap:8px; align-items:center; margin-top:16px">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1"  @checked(old('is_active', $alias?->is_active ?? true))>
    <span>Active</span>
</label>
