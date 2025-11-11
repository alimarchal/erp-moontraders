@php
    /** @var \App\Models\CostCenter|null $costCenter */
    $costCenter = $costCenter ?? null;
    $typeOptions = $typeOptions ?? [];
    $parentOptions = $parentOptions ?? collect();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="code" value="Code" :required="true" />
        <x-input id="code" type="text" name="code" class="mt-1 block w-full uppercase"
            maxlength="20" :value="old('code', optional($costCenter)->code)" required
            placeholder="CC001" />
        <p class="text-xs text-gray-500 mt-1">Use a unique alphanumeric code (letters, numbers, dashes, underscores).</p>
    </div>

    <div>
        <x-label for="name" value="Name" :required="true" />
        <x-input id="name" type="text" name="name" class="mt-1 block w-full"
            :value="old('name', optional($costCenter)->name)" required placeholder="Marketing Department" />
    </div>

    <div>
        <x-label for="type" value="Type" :required="true" />
        <select id="type" name="type"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Type</option>
            @foreach ($typeOptions as $value => $label)
                <option value="{{ $value }}" {{ old('type', optional($costCenter)->type) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="parent_id" value="Parent Cost Center" />
        <select id="parent_id" name="parent_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">No Parent (top level)</option>
            @foreach ($parentOptions as $parent)
                <option value="{{ $parent->id }}" {{ (int) old('parent_id', optional($costCenter)->parent_id) === $parent->id ? 'selected' : '' }}>
                    {{ $parent->code }} · {{ $parent->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="start_date" value="Start Date" />
        <x-input id="start_date" type="date" name="start_date" class="mt-1 block w-full"
            :value="old('start_date', optional($costCenter?->start_date)->format('Y-m-d'))" />
    </div>

    <div>
        <x-label for="end_date" value="End Date" />
        <x-input id="end_date" type="date" name="end_date" class="mt-1 block w-full"
            :value="old('end_date', optional($costCenter?->end_date)->format('Y-m-d'))" />
    </div>
</div>

<div class="mt-4">
    <x-label for="description" value="Description" />
    <textarea id="description" name="description"
        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
        rows="3" placeholder="Optional notes for this cost center">{{ old('description', optional($costCenter)->description) }}</textarea>
</div>

<div class="mt-4 flex items-center">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" type="checkbox" name="is_active" value="1"
        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        {{ old('is_active', optional($costCenter)->is_active ?? true) ? 'checked' : '' }}>
    <label for="is_active" class="ml-2 text-sm text-gray-700">
        Cost center is active
    </label>
</div>
