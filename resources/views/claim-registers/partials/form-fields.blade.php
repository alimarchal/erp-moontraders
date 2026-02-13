@php
    /** @var \App\Models\ClaimRegister|null $claimRegister */
    $claimRegister = $claimRegister ?? null;
@endphp

{{-- Row 1: Supplier, Transaction Date, Reference Number --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <x-label for="supplier_id" value="Supplier" :required="true" />
        <select id="supplier_id" name="supplier_id"
            class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Supplier</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}"
                    {{ old('supplier_id', optional($claimRegister)->supplier_id) == $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->supplier_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="transaction_date" value="Transaction Date" :required="true" />
        <x-input id="transaction_date" type="date" name="transaction_date" class="mt-1 block w-full"
            :value="old('transaction_date', optional($claimRegister?->transaction_date)->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
    </div>

    <div>
        <x-label for="reference_number" value="Reference Number" />
        <x-input id="reference_number" type="text" name="reference_number" class="mt-1 block w-full"
            :value="old('reference_number', optional($claimRegister)->reference_number)" placeholder="ST-25-28" />
    </div>
</div>

{{-- Row 2: Description, Claim Month, Date of Dispatch --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="description" value="Description" />
        <x-input id="description" type="text" name="description" class="mt-1 block w-full"
            :value="old('description', optional($claimRegister)->description)" placeholder="TED June-August" />
    </div>

    <div>
        <x-label for="claim_month" value="Claim Month" />
        <x-input id="claim_month" type="text" name="claim_month" class="mt-1 block w-full"
            :value="old('claim_month', optional($claimRegister)->claim_month)" placeholder="June-Aug 2024" />
    </div>

    <div>
        <x-label for="date_of_dispatch" value="Date of Dispatch" />
        <x-input id="date_of_dispatch" type="date" name="date_of_dispatch" class="mt-1 block w-full"
            :value="old('date_of_dispatch', optional($claimRegister?->date_of_dispatch)->format('Y-m-d'))" />
    </div>
</div>

{{-- Row 3: Transaction Type, Amount, Status --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="transaction_type" value="Transaction Type" :required="true" />
        <select id="transaction_type" name="transaction_type"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            @foreach (\App\Models\ClaimRegister::transactionTypeOptions() as $value => $label)
                <option value="{{ $value }}"
                    {{ old('transaction_type', optional($claimRegister)->transaction_type ?? 'claim') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="amount" value="Amount" :required="true" />
        <x-input id="amount" type="number" name="amount" class="mt-1 block w-full" step="0.01" min="0"
            :value="old('amount', optional($claimRegister)->amount ?? '0.00')" required />
    </div>

    <div>
        <x-label for="status" value="Status" :required="true" />
        <select id="status" name="status"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}"
                    {{ old('status', optional($claimRegister)->status ?? 'Pending') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

{{-- Row 4: Notes --}}
<div class="grid grid-cols-1 gap-4 mt-4">
    <div>
        <x-label for="notes" value="Notes" />
        <textarea id="notes" name="notes" rows="2"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            placeholder="Additional remarks">{{ old('notes', optional($claimRegister)->notes) }}</textarea>
    </div>
</div>
