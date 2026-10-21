@php($rule = $rule ?? null)

<div>
    <x-label for="name" value="Rule name" :required="true" />
    <x-input id="name" name="name" type="text" maxlength="191" class="mt-1 block w-full" required :value="old('name', $rule?->name)" placeholder="Bank charges" />
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
    <div>
        <x-label for="match_field" value="Look in" :required="true" />
        <select data-allow-clear="false" id="match_field" name="match_field" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
            @foreach (\App\Models\BankEntryRule::FIELDS as $value => $label)
                <option value="{{ $value }}" @selected(old('match_field', $rule?->match_field ?? 'narration') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-label for="match_type" value="Match" :required="true" />
        <select data-allow-clear="false" id="match_type" name="match_type" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
            @foreach (\App\Models\BankEntryRule::MATCH_TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('match_type', $rule?->match_type ?? 'contains') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <x-label for="match_value" value="Text" :required="true" />
        <x-input id="match_value" name="match_value" type="text" dir="auto" maxlength="191" class="mt-1 block w-full" required :value="old('match_value', $rule?->match_value)" placeholder="Service Charges" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
    <div>
        <x-label for="direction" value="Applies to" :required="true" />
        <select data-allow-clear="false" id="direction" name="direction" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
            @foreach (\App\Models\BankEntryRule::DIRECTIONS as $value => $label)
                <option value="{{ $value }}" @selected(old('direction', $rule?->direction ?? 'any') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <x-label for="bank_entry_category_id" value="Then set category" :required="true" />
        <select id="bank_entry_category_id" name="bank_entry_category_id" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full" required>
            <option value="">Choose…</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('bank_entry_category_id', $rule?->bank_entry_category_id) === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-label for="priority" value="Priority (1 first)" :required="true" />
        <x-input id="priority" name="priority" type="number" min="1" class="mt-1 block w-full" required :value="old('priority', $rule?->priority ?? 100)" />
    </div>
</div>

<div class="mt-4">
    <x-label for="company_id" value="And set company (optional)" />
    <select id="company_id" name="company_id" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
        <option value="">Leave company as it is</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected((int) old('company_id', $rule?->company_id) === $company->id)>{{ $company->supplier_name }}</option>
        @endforeach
    </select>
</div>

<label class="inline-flex items-center gap-2 mt-4">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('is_active', $rule?->is_active ?? true))>
    <span class="text-sm text-gray-700">Active</span>
</label>
