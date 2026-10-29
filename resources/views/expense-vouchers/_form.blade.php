@php($isOwnCompanyOnly = $suppliers->count() === 1)
<div class="ev-form-grid">
    <div class="ak-field">
        <label for="voucher_date">Voucher date</label>
        <input id="voucher_date" type="date" name="voucher_date" required value="{{ old('voucher_date', optional($voucher->voucher_date)->format('Y-m-d')) }}">
    </div>
    <div class="ak-field">
        <label for="supplier_id">Company (distribution)</label>
        <select id="supplier_id" class="ev-select" name="supplier_id" @required(! auth()->user()->hasAllSupplierAccess())>
            @if(auth()->user()->hasAllSupplierAccess())
                <option value="">Moon Traders (general expense)</option>
            @elseif(! $isOwnCompanyOnly)
                <option value="">Select company</option>
            @endif
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $voucher->supplier_id) === $supplier->id)>{{ $supplier->supplier_name }}</option>
            @endforeach
        </select>
        <p class="ak-hint">Leave as Moon Traders for an expense of the business itself.</p>
    </div>
    <div class="ak-field">
        <label for="amount">Amount (Rs.)</label>
        <input id="amount" type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount', $voucher->amount) }}">
    </div>
    <div class="ak-field ev-span-2">
        <label for="party_name">Party name (paid to)</label>
        <select id="party_name" class="ev-party" name="party_name" required>
            <option value=""></option>
            @php($currentParty = old('party_name', $voucher->party_name))
            @if($currentParty && ! $parties->contains($currentParty))
                <option value="{{ $currentParty }}" selected>{{ $currentParty }}</option>
            @endif
            @foreach($parties as $party)
                <option value="{{ $party }}" @selected($currentParty === $party)>{{ $party }}</option>
            @endforeach
        </select>
        <p class="ak-hint">Pick a party used before, or type a new name and press Enter.</p>
    </div>
    <div class="ak-field">
        <label for="expense_account_id">Expense account</label>
        <select id="expense_account_id" class="ev-select" name="expense_account_id" required>
            <option value="">Select account</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected((int) old('expense_account_id', $voucher->expense_account_id) === $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field ev-span-3">
        <label for="narration">Narration</label>
        <textarea id="narration" name="narration" rows="3" maxlength="1000">{{ old('narration', $voucher->narration) }}</textarea>
    </div>
</div>
