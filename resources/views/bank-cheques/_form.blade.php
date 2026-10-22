@php($cheque = $cheque ?? null)

<div class="uf-fields">
    <div class="uf-field">
        <label for="bank_account_id">Bank account <span class="uf-req">*</span></label>
        <select data-allow-clear="false" id="bank_account_id" name="bank_account_id" class="select2 bank-select" required>
            @foreach ($bankAccounts as $account)
                <option value="{{ $account->id }}" @selected((int) old('bank_account_id', $cheque?->bank_account_id) === $account->id)>{{ $account->account_name }} ({{ $account->account_number }})</option>
            @endforeach
        </select>
    </div>
    <div class="uf-field">
        <label for="direction">Direction <span class="uf-req">*</span></label>
        <select data-allow-clear="false" id="direction" name="direction" class="select2 bank-select">
            @foreach (\App\Models\BankCheque::DIRECTIONS as $value => $label)
                <option value="{{ $value }}" @selected(old('direction', $cheque?->direction ?? 'issued') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="uf-field">
        <label for="cheque_no">Cheque number <span class="uf-req">*</span></label>
        <x-input id="cheque_no" name="cheque_no" type="text" maxlength="50" class="mt-1 block w-full" required :value="old('cheque_no', $cheque?->cheque_no)" placeholder="00000264" />
    </div>
</div>

<div class="uf-fields">
    <div class="uf-field">
        <label for="cheque_date">Cheque date <span class="uf-req">*</span></label>
        <x-input id="cheque_date" name="cheque_date" type="date" class="mt-1 block w-full" required :value="old('cheque_date', $cheque?->cheque_date?->format('Y-m-d'))" />
    </div>
    <div class="uf-field">
        <label for="amount">Amount (PKR) <span class="uf-req">*</span></label>
        <x-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required :value="old('amount', $cheque?->amount)" />
    </div>
    <div class="uf-field">
        <label for="status">Status <span class="uf-req">*</span></label>
        <select data-allow-clear="false" id="status" name="status" class="select2 bank-select">
            @foreach (\App\Models\BankCheque::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $cheque?->status ?? 'pending') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="uf-fields">
    <div class="uf-field">
        <label for="company_id">Company / principal</label>
        <select data-placeholder="—" id="company_id" name="company_id" class="select2 bank-select">
            <option value="">—</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((int) old('company_id', $cheque?->company_id) === $company->id)>{{ $company->supplier_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="uf-field">
        <label for="payee_name">Payee / drawer</label>
        <x-input id="payee_name" name="payee_name" type="text" dir="auto" maxlength="191" class="mt-1 block w-full" :value="old('payee_name', $cheque?->payee_name)" />
    </div>
    <div class="uf-field">
        <label for="cleared_date">Cleared on</label>
        <x-input id="cleared_date" name="cleared_date" type="date" class="mt-1 block w-full" :value="old('cleared_date', $cheque?->cleared_date?->format('Y-m-d'))" />
    </div>
</div>

<div class="uf-field">
    <label for="notes">Notes</label>
    <textarea id="notes" name="notes" rows="2" dir="auto" style="width:100%; padding:10px 12px; border:1px solid var(--ak-border-strong); border-radius:8px; font-size:14px">{{ old('notes', $cheque?->notes) }}</textarea>
</div>
<p class="mt-3 text-xs text-gray-500">A pending cheque is cleared automatically when a statement line carries its number and the same amount.</p>
