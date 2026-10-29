@php($isOwnCompanyOnly = $suppliers->count() === 1)
<div class="ev-form-grid">
    <div class="ak-field">
        <label for="voucher_date" class="ev-bi"><span>Voucher date</span><span class="ev-ur" lang="ur" dir="rtl">واؤچر کی تاریخ</span></label>
        <input id="voucher_date" type="date" name="voucher_date" required value="{{ old('voucher_date', optional($voucher->voucher_date)->format('Y-m-d')) }}">
    </div>
    <div class="ak-field">
        <label for="supplier_id" class="ev-bi"><span>Company (distribution)</span><span class="ev-ur" lang="ur" dir="rtl">کمپنی / ڈسٹری بیوشن</span></label>
        <select id="supplier_id" class="ev-select" name="supplier_id" @if(auth()->user()->hasAllSupplierAccess()) data-clearable="1" data-placeholder="Moon Traders (general expense)" @else required @endif>
            @if(auth()->user()->hasAllSupplierAccess())
                <option value=""></option>
                <option value="general" @selected(old('supplier_id', $voucher->exists ? ($voucher->supplier_id ?? 'general') : 'general') === 'general')>Moon Traders (general expense)</option>
            @elseif(! $isOwnCompanyOnly)
                <option value="">Select company</option>
            @endif
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $voucher->supplier_id) === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field">
        <label for="amount" class="ev-bi"><span>Amount (Rs.)</span><span class="ev-ur" lang="ur" dir="rtl">رقم (روپے)</span></label>
        <input id="amount" type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount', $voucher->amount) }}">
    </div>
    <div class="ak-field ev-span-2">
        <label for="party_name" class="ev-bi"><span>Party name (paid to)</span><span class="ev-ur" lang="ur" dir="rtl">پارٹی کا نام (جسے ادائیگی کی گئی)</span></label>
        <input id="party_name" type="text" name="party_name" required maxlength="255" list="party-suggestions" autocomplete="off"
            value="{{ old('party_name', $voucher->party_name) }}" placeholder="Person, shop or company paid  |  شخص، دکان یا کمپنی">
        <datalist id="party-suggestions">
            @foreach($parties as $party)
                <option value="{{ $party }}"></option>
            @endforeach
        </datalist>
    </div>
    <div class="ak-field">
        <label for="expense_account_id" class="ev-bi"><span>Expense account</span><span class="ev-ur" lang="ur" dir="rtl">اخراجات کا کھاتہ</span></label>
        <select id="expense_account_id" class="ev-select" name="expense_account_id" required>
            <option value="">Select account</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected((int) old('expense_account_id', $voucher->expense_account_id ?? ($defaultAccountId ?? null)) === $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field ev-span-3">
        <label for="narration" class="ev-bi"><span>Narration</span><span class="ev-ur" lang="ur" dir="rtl">تفصیل / بیان</span></label>
        <textarea id="narration" name="narration" rows="3" maxlength="1000" placeholder="What was this expense for?  |  یہ خرچ کس مد میں ہوا؟">{{ old('narration', $voucher->narration) }}</textarea>
    </div>
</div>
