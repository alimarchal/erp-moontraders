@php($isOwnCompanyOnly = $suppliers->count() === 1)
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <x-label for="voucher_date" value="Voucher date" />
        <x-input id="voucher_date" class="block mt-1 w-full" type="date" name="voucher_date"
            :value="old('voucher_date', optional($voucher->voucher_date)->format('Y-m-d'))" required />
    </div>
    <div>
        <x-label for="supplier_id" value="Company (distribution)" />
        <select id="supplier_id" name="supplier_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" @required(! auth()->user()->hasAllSupplierAccess())>
            @if(auth()->user()->hasAllSupplierAccess())
                <option value="">Moon Traders (general expense)</option>
            @elseunless($isOwnCompanyOnly)
                <option value="">Select company</option>
            @endif
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $voucher->supplier_id) === $supplier->id)>{{ $supplier->supplier_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-label for="amount" value="Amount (Rs.)" />
        <x-input id="amount" class="block mt-1 w-full" type="number" step="0.01" min="0.01" name="amount"
            :value="old('amount', $voucher->amount)" required />
    </div>
    <div class="md:col-span-2">
        <x-label for="party_name" value="Party name (paid to)" />
        <x-input id="party_name" class="block mt-1 w-full" type="text" name="party_name" maxlength="255"
            :value="old('party_name', $voucher->party_name)" required />
    </div>
    <div>
        <x-label for="expense_account_id" value="Expense account" />
        <select id="expense_account_id" name="expense_account_id" required
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select account</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected((int) old('expense_account_id', $voucher->expense_account_id) === $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-3">
        <x-label for="narration" value="Narration" />
        <textarea id="narration" name="narration" rows="3" maxlength="1000"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('narration', $voucher->narration) }}</textarea>
    </div>
</div>
