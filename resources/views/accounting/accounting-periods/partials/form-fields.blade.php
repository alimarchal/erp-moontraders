@php
    /** @var \App\Models\AccountingPeriod|null $accountingPeriod */
    $accountingPeriod = $accountingPeriod ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="name" value="Period Name" :required="true" />
        <x-input id="name" type="text" name="name" class="mt-1 block w-full"
            :value="old('name', optional($accountingPeriod)->name)" required autofocus placeholder="Fiscal Year 2025" />
    </div>

    <div>
        <x-label for="status" value="Status" :required="true" />
        <select id="status" name="status"
            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Status</option>
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" {{ old('status', optional($accountingPeriod)->status) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="start_date" value="Start Date" :required="true" />
        <x-input id="start_date" type="date" name="start_date" class="mt-1 block w-full"
            :value="old('start_date', optional($accountingPeriod?->start_date)->format('Y-m-d'))" required />
    </div>

    <div>
        <x-label for="end_date" value="End Date" :required="true" />
        <x-input id="end_date" type="date" name="end_date" class="mt-1 block w-full"
            :value="old('end_date', optional($accountingPeriod?->end_date)->format('Y-m-d'))" required />
    </div>
</div>
