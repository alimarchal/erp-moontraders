@php($cheque = $cheque ?? null)

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <x-label for="bank_account_id" value="Bank account" :required="true" />
        <select data-allow-clear="false" id="bank_account_id" name="bank_account_id" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full" required>
            @foreach ($bankAccounts as $account)
                <option value="{{ $account->id }}" @selected((int) old('bank_account_id', $cheque?->bank_account_id) === $account->id)>{{ $account->account_name }} ({{ $account->account_number }})</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-label for="direction" value="Direction" :required="true" />
        <select data-allow-clear="false" id="direction" name="direction" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
            @foreach (\App\Models\BankCheque::DIRECTIONS as $value => $label)
                <option value="{{ $value }}" @selected(old('direction', $cheque?->direction ?? 'issued') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-label for="cheque_no" value="Cheque number" :required="true" />
        <x-input id="cheque_no" name="cheque_no" type="text" maxlength="50" class="mt-1 block w-full" required :value="old('cheque_no', $cheque?->cheque_no)" placeholder="00000264" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="cheque_date" value="Cheque date" :required="true" />
        <x-input id="cheque_date" name="cheque_date" type="date" class="mt-1 block w-full" required :value="old('cheque_date', $cheque?->cheque_date?->format('Y-m-d'))" />
    </div>
    <div>
        <x-label for="amount" value="Amount (PKR)" :required="true" />
        <x-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required :value="old('amount', $cheque?->amount)" />
    </div>
    <div>
        <x-label for="status" value="Status" :required="true" />
        <select data-allow-clear="false" id="status" name="status" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
            @foreach (\App\Models\BankCheque::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $cheque?->status ?? 'pending') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="company_id" value="Company / principal" />
        <select data-placeholder="—" id="company_id" name="company_id" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
            <option value="">—</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((int) old('company_id', $cheque?->company_id) === $company->id)>{{ $company->supplier_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-label for="payee_name" value="Payee / drawer" />
        <x-input id="payee_name" name="payee_name" type="text" dir="auto" maxlength="191" class="mt-1 block w-full" :value="old('payee_name', $cheque?->payee_name)" />
    </div>
    <div>
        <x-label for="cleared_date" value="Cleared on" />
        <x-input id="cleared_date" name="cleared_date" type="date" class="mt-1 block w-full" :value="old('cleared_date', $cheque?->cleared_date?->format('Y-m-d'))" />
    </div>
</div>

<div class="mt-4">
    <x-label for="notes" value="Notes" />
    <textarea id="notes" name="notes" rows="2" dir="auto" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">{{ old('notes', $cheque?->notes) }}</textarea>
</div>
<p class="mt-3 text-xs text-gray-500">A pending cheque is cleared automatically when a statement line carries its number and the same amount.</p>
