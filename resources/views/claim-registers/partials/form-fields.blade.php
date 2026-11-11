@php
    /** @var \App\Models\ClaimRegister|null $claimRegister */
    $claimRegister = $claimRegister ?? null;
    $type = old('transaction_type', optional($claimRegister)->transaction_type ?? 'claim');
    $amount = old('amount', $claimRegister ? max((float) $claimRegister->debit, (float) $claimRegister->credit) : null);
@endphp

<div x-data="{ type: @js($type) }" class="space-y-5">
    {{-- What kind of entry: a claim raised, or a claim recovered --}}
    <fieldset>
        <legend class="rg-field-label">Entry type <span class="rg-req" aria-hidden="true">*</span></legend>
        <div class="rg-seg" role="radiogroup" aria-label="Entry type">
            @foreach (\App\Models\ClaimRegister::transactionTypeOptions() as $value => $label)
                <label class="rg-seg-option" :class="type === @js($value) && 'is-on'">
                    <input type="radio" name="transaction_type" value="{{ $value }}" x-model="type" class="sr-only" {{ $type === $value ? 'checked' : '' }} required>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <p class="rg-hint" x-show="type === 'claim'">The supplier owes us this amount. Posting books it to Pending Claims against claim income.</p>
        <p class="rg-hint" x-show="type === 'recovery'" x-cloak>The supplier settled the claim in its ledger. It is recorded here only; the Supplier Ledger Register's claim adjustment books it.</p>
    </fieldset>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <x-label for="supplier_id" value="Supplier"  class="rg-required" />
            <select id="supplier_id" name="supplier_id" data-placeholder="Choose a supplier" class="select2 rg-supplier-select block mt-1 w-full" required>
                <option value=""></option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{ (string) old('supplier_id', optional($claimRegister)->supplier_id) === (string) $supplier->id ? 'selected' : '' }}>
                        {{ $supplier->supplier_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <x-label for="transaction_date" value="Date"  class="rg-required" />
            <x-input id="transaction_date" type="date" name="transaction_date" class="mt-1 block w-full"
                :value="old('transaction_date', optional($claimRegister?->transaction_date)->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
        </div>

        <div>
            <x-label for="amount" value="Amount (Rs)"  class="rg-required" />
            <x-input id="amount" type="number" name="amount" class="mt-1 block w-full rg-amount" step="0.01" min="0.01" inputmode="decimal"
                :value="$amount" placeholder="0.00" required />
        </div>

        <div>
            <x-label for="reference_number" value="Reference" />
            <x-input id="reference_number" type="text" name="reference_number" class="mt-1 block w-full"
                :value="old('reference_number', optional($claimRegister)->reference_number)" placeholder="e.g. ST-26-014" />
        </div>

        <div>
            <x-label for="claim_month" value="Claim month" />
            <x-input id="claim_month" type="text" name="claim_month" class="mt-1 block w-full"
                :value="old('claim_month', optional($claimRegister)->claim_month)" placeholder="e.g. October 2026" />
        </div>

        <div class="md:col-span-2">
            <x-label for="description" value="Description" />
            <x-input id="description" type="text" name="description" class="mt-1 block w-full"
                :value="old('description', optional($claimRegister)->description)" placeholder="e.g. Trade offer, October" />
        </div>

        <div>
            <x-label for="date_of_dispatch" value="Date of dispatch" />
            <x-input id="date_of_dispatch" type="date" name="date_of_dispatch" class="mt-1 block w-full"
                :value="old('date_of_dispatch', optional($claimRegister?->date_of_dispatch)->format('Y-m-d'))" />
        </div>

        <div class="md:col-span-2">
            <x-label for="notes" value="Notes" />
            <textarea id="notes" name="notes" rows="3" class="block mt-1 w-full"
                placeholder="Anything the next reader should know">{{ old('notes', optional($claimRegister)->notes) }}</textarea>
        </div>
    </div>
</div>
