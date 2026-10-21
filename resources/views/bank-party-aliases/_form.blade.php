@php($alias = $alias ?? null)

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="alias_name" value="Name as it appears on the statement" :required="true" />
        <x-input id="alias_name" name="alias_name" type="text" dir="auto" maxlength="191" class="mt-1 block w-full uppercase" required :value="old('alias_name', $alias?->alias_name)" placeholder="JAMEEL AHM" />
        <p class="mt-1 text-xs text-gray-500">Capital letters and spaces are ignored when matching.</p>
    </div>
    <div>
        <x-label for="canonical_name" value="Correct name" :required="true" />
        <x-input id="canonical_name" name="canonical_name" type="text" dir="auto" maxlength="191" class="mt-1 block w-full" required :value="old('canonical_name', $alias?->canonical_name)" placeholder="Jameel Ahmed" />
    </div>
</div>

<div class="mt-4">
    <x-label for="company_id" value="Company / principal" />
    <select id="company_id" name="company_id" class="select2 bank-select border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
        <option value="">Not linked to a company</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected((int) old('company_id', $alias?->company_id) === $company->id)>{{ $company->supplier_name }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">Entries with this name get this company automatically on import or "Auto-classify".</p>
</div>

<label class="inline-flex items-center gap-2 mt-4">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('is_active', $alias?->is_active ?? true))>
    <span class="text-sm text-gray-700">Active</span>
</label>
