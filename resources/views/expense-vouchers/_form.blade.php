@php
    $isOwnCompanyOnly = $suppliers->count() === 1;
@endphp
<div class="ev-form-grid">
    <div class="ak-field">
        <label for="voucher_date" class="ev-bi"><span>Voucher date</span><span class="ev-ur" lang="ur" dir="rtl">واؤچر کی تاریخ</span></label>
        <input id="voucher_date" type="date" name="voucher_date" required value="{{ old('voucher_date', optional($voucher->voucher_date)->format('Y-m-d')) }}">
    </div>
    <div class="ak-field">
        <label for="owner" class="ev-bi"><span>Company (distribution)</span><span class="ev-ur" lang="ur" dir="rtl">کمپنی / ڈسٹری بیوشن</span></label>
        @php
            $currentOwner = old('owner', $voucher->exists
                ? ($voucher->supplier_id ? 'supplier:'.$voucher->supplier_id : ($voucher->company_id ? 'company:'.$voucher->company_id : ''))
                : ($defaultOwner ?? ''));
        @endphp
        <select id="owner" class="ev-select" name="owner" @if(auth()->user()->hasAllSupplierAccess()) data-clearable="1" data-placeholder="{{ $companies->first()?->company_name }} (general expense)" @else required @endif>
            @if(auth()->user()->hasAllSupplierAccess())
                <option value=""></option>
                @foreach($companies as $company)
                    <option value="company:{{ $company->id }}" @selected($currentOwner === 'company:'.$company->id)>{{ $company->company_name }} (general expense)</option>
                @endforeach
            @elseif(! $isOwnCompanyOnly)
                <option value="">Select company</option>
            @endif
            @foreach($suppliers as $supplier)
                <option value="supplier:{{ $supplier->id }}" @selected($currentOwner === 'supplier:'.$supplier->id || ($isOwnCompanyOnly && ! $currentOwner))>{{ $supplier->supplier_name }}</option>
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
    <div class="ak-field ev-span-3">
        <label for="attachment" class="ev-bi"><span>Attachment (optional): receipt, bill or photo</span><span class="ev-ur" lang="ur" dir="rtl">منسلک دستاویز (اختیاری): رسید، بل یا تصویر</span></label>
        <input id="attachment" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" class="ev-file">
        <p class="ak-hint">PDF, JPG, PNG or WEBP, up to {{ round(config('settlement_attachments.max_kb') / 1024) }} MB. Kept privately under Vouchers / {{ optional($voucher->voucher_date)->format('d-m-Y') ?? 'dd-mm-yyyy of the voucher' }}.</p>
        @if($voucher->exists && $voucher->attachment)
            <div class="ev-current-file">
                <a href="{{ $voucher->attachment_url }}" target="_blank" rel="noopener">Current attachment ({{ strtoupper(pathinfo($voucher->attachment, PATHINFO_EXTENSION)) }})</a>
                <label class="ev-remove"><input type="checkbox" name="remove_attachment" value="1"> Remove it <span class="ev-ur" lang="ur" dir="rtl" style="font-size:15px">ہٹا دیں</span></label>
            </div>
        @endif
    </div>
</div>
