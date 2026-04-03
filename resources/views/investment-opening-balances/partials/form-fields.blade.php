@php
    /** @var \App\Models\InvestmentOpeningBalance|null $balance */
    $balance = $balance ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="supplier_id" value="Supplier" :required="true" />
        <select id="supplier_id" name="supplier_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Supplier</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}"
                    {{ old('supplier_id', optional($balance)->supplier_id) == $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->supplier_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="date" value="Date" :required="true" />
        <x-input id="date" type="date" name="date" class="mt-1 block w-full"
            :value="old('date', optional($balance?->date)->format('Y-m-d'))" required />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="description" value="Description" :required="true" />
        <x-input id="description" type="text" name="description" class="mt-1 block w-full"
            :value="old('description', optional($balance)->description)" required
            placeholder="e.g. BANK_OPENING_AMOUNT" />
    </div>

    <div>
        <x-label for="amount" value="Amount" :required="true" />
        <x-input id="amount" type="number" name="amount" class="mt-1 block w-full"
            :value="old('amount', optional($balance)->amount ?? 0)" required step="0.01" min="0"
            placeholder="0.00" />
    </div>
</div>
