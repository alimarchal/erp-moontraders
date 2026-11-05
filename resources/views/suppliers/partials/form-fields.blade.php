@php
    /** @var \App\Models\Supplier|null $supplier */
    $supplier = $supplier ?? null;
    $currencyOptions = $currencyOptions ?? collect();
    $accountOptions = $accountOptions ?? collect();
    $entryMode = old('qty_entry_mode', optional($supplier)->qty_entry_mode ?? 'pieces');
@endphp
<div class="ev-form-grid">
    <div class="ak-field ev-span-2">
        <label for="supplier_name" class="ev-bi"><span>Supplier name</span><span class="ev-ur" lang="ur" dir="rtl">سپلائر کا نام</span></label>
        <input id="supplier_name" type="text" name="supplier_name" value="{{ old('supplier_name', optional($supplier)->supplier_name) }}" placeholder="Pakistan Tobacco Company" required maxlength="255">
        @error('supplier_name') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ">
        <label for="short_name" class="ev-bi"><span>Short name</span><span class="ev-ur" lang="ur" dir="rtl">مختصر نام</span></label>
        <input id="short_name" type="text" name="short_name" value="{{ old('short_name', optional($supplier)->short_name) }}" placeholder="PTC" maxlength="100" style="text-transform:uppercase">
        @error('short_name') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ">
        <label for="supplier_group" class="ev-bi"><span>Group</span><span class="ev-ur" lang="ur" dir="rtl">گروپ</span></label>
        <input id="supplier_group" type="text" name="supplier_group" value="{{ old('supplier_group', optional($supplier)->supplier_group) }}" placeholder="Local" >
        @error('supplier_group') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ">
        <label for="supplier_type" class="ev-bi"><span>Type</span><span class="ev-ur" lang="ur" dir="rtl">قسم</span></label>
        <input id="supplier_type" type="text" name="supplier_type" value="{{ old('supplier_type', optional($supplier)->supplier_type) }}" placeholder="Tobacco" >
        @error('supplier_type') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ">
        <label for="country" class="ev-bi"><span>Country</span><span class="ev-ur" lang="ur" dir="rtl">ملک</span></label>
        <input id="country" type="text" name="country" value="{{ old('country', optional($supplier)->country) }}" placeholder="Pakistan" >
        @error('country') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ev-span-3 sp-mode">
        <label for="qty_entry_mode" class="ev-bi"><span>Quantity entry on GRN</span><span class="ev-ur" lang="ur" dir="rtl">جی آر این میں مقدار کیسے درج ہو</span></label>
        <select id="qty_entry_mode" name="qty_entry_mode" class="ev-select">
            <option value="pieces" @selected($entryMode === 'pieces')>Pieces (normal entry)</option>
            <option value="carton" @selected($entryMode === 'carton')>Carton (cartons + loose pieces, rate per TH): for Pakistan Tobacco Company</option>
        </select>
        <p class="ak-hint">Carton mode is only an input helper. Stock and the inventory ledger are always kept in pieces. Switch back to Pieces at any time; old GRNs are not affected. Each product needs “Pieces per carton” and “Pouches per piece” set.</p>
        @error('qty_entry_mode') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field">
        <label for="default_currency_id" class="ev-bi"><span>Default currency</span><span class="ev-ur" lang="ur" dir="rtl">ڈیفالٹ کرنسی</span></label>
        <select id="default_currency_id" name="default_currency_id" class="ev-select">
            <option value="">Select currency</option>
            @foreach ($currencyOptions as $currency)
                <option value="{{ $currency->id }}" @selected((int) old('default_currency_id', optional($supplier)->default_currency_id) === $currency->id)>{{ $currency->currency_code }} · {{ $currency->currency_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field ev-span-2">
        <label for="default_bank_account_id" class="ev-bi"><span>Default bank account</span><span class="ev-ur" lang="ur" dir="rtl">ڈیفالٹ بینک اکاؤنٹ</span></label>
        <select id="default_bank_account_id" name="default_bank_account_id" class="ev-select">
            <option value="">Select account</option>
            @foreach ($accountOptions as $account)
                <option value="{{ $account->id }}" @selected((int) old('default_bank_account_id', optional($supplier)->default_bank_account_id) === $account->id)>{{ $account->account_code }} · {{ $account->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field ">
        <label for="default_price_list" class="ev-bi"><span>Default price list</span><span class="ev-ur" lang="ur" dir="rtl">ڈیفالٹ پرائس لسٹ</span></label>
        <input id="default_price_list" type="text" name="default_price_list" value="{{ old('default_price_list', optional($supplier)->default_price_list) }}" placeholder="STANDARD_PRICES" >
        @error('default_price_list') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ">
        <label for="website" class="ev-bi"><span>Website</span><span class="ev-ur" lang="ur" dir="rtl">ویب سائٹ</span></label>
        <input id="website" type="url" name="website" value="{{ old('website', optional($supplier)->website) }}" placeholder="https://example.com" >
        @error('website') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ">
        <label for="print_language" class="ev-bi"><span>Print language</span><span class="ev-ur" lang="ur" dir="rtl">پرنٹ کی زبان</span></label>
        <input id="print_language" type="text" name="print_language" value="{{ old('print_language', optional($supplier)->print_language) }}" placeholder="English" >
        @error('print_language') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ">
        <label for="tax_id" class="ev-bi"><span>Tax ID / NTN</span><span class="ev-ur" lang="ur" dir="rtl">ٹیکس آئی ڈی / این ٹی این</span></label>
        <input id="tax_id" type="text" name="tax_id" value="{{ old('tax_id', optional($supplier)->tax_id) }}" placeholder="NTN-1234567" >
        @error('tax_id') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ">
        <label for="pan_number" class="ev-bi"><span>PAN number</span><span class="ev-ur" lang="ur" dir="rtl">پین نمبر</span></label>
        <input id="pan_number" type="text" name="pan_number" value="{{ old('pan_number', optional($supplier)->pan_number) }}" placeholder="PAN-987654" >
        @error('pan_number') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
    </div>
    <div class="ak-field ev-span-3">
        <label for="supplier_primary_address" class="ev-bi"><span>Primary address</span><span class="ev-ur" lang="ur" dir="rtl">بنیادی پتہ</span></label>
        <textarea id="supplier_primary_address" name="supplier_primary_address" rows="3" placeholder="Street, city, postal code">{{ old('supplier_primary_address', optional($supplier)->supplier_primary_address) }}</textarea>
    </div>
    <div class="ak-field ev-span-3">
        <label for="supplier_primary_contact" class="ev-bi"><span>Primary contact</span><span class="ev-ur" lang="ur" dir="rtl">بنیادی رابطہ</span></label>
        <textarea id="supplier_primary_contact" name="supplier_primary_contact" rows="3" placeholder="Name, phone, email">{{ old('supplier_primary_contact', optional($supplier)->supplier_primary_contact) }}</textarea>
    </div>
    <div class="ak-field ev-span-3">
        <label for="supplier_details" class="ev-bi"><span>Additional details</span><span class="ev-ur" lang="ur" dir="rtl">اضافی تفصیل</span></label>
        <textarea id="supplier_details" name="supplier_details" rows="4" placeholder="Terms, payment notes">{{ old('supplier_details', optional($supplier)->supplier_details) }}</textarea>
    </div>
</div>
<div class="sp-checks">
    <label class="sp-check" for="is_transporter">
        <input type="hidden" name="is_transporter" value="0">
        <input id="is_transporter" type="checkbox" name="is_transporter" value="1" {{ old('is_transporter', optional($supplier)->is_transporter ?? false) ? 'checked' : '' }}>
        <span><b>Also provides transport</b> <span class="ev-ur" lang="ur" dir="rtl">ٹرانسپورٹ بھی فراہم کرتا ہے</span><small>The supplier can be picked as a transporter.</small></span>
    </label>
    <label class="sp-check" for="is_internal_supplier">
        <input type="hidden" name="is_internal_supplier" value="0">
        <input id="is_internal_supplier" type="checkbox" name="is_internal_supplier" value="1" {{ old('is_internal_supplier', optional($supplier)->is_internal_supplier ?? false) ? 'checked' : '' }}>
        <span><b>Internal supplier</b> <span class="ev-ur" lang="ur" dir="rtl">اندرونی سپلائر</span><small>Inter-company supplier.</small></span>
    </label>
    <label class="sp-check" for="is_fmr_allowed">
        <input type="hidden" name="is_fmr_allowed" value="0">
        <input id="is_fmr_allowed" type="checkbox" name="is_fmr_allowed" value="1" {{ old('is_fmr_allowed', optional($supplier)->is_fmr_allowed ?? false) ? 'checked' : '' }}>
        <span><b>FMR allowed</b> <span class="ev-ur" lang="ur" dir="rtl">ایف ایم آر کی اجازت</span><small>Exclude FMR from the unit cost on GRN.</small></span>
    </label>
    <label class="sp-check" for="is_advance_tax_income">
        <input type="hidden" name="is_advance_tax_income" value="0">
        <input id="is_advance_tax_income" type="checkbox" name="is_advance_tax_income" value="1" {{ old('is_advance_tax_income', optional($supplier)->is_advance_tax_income ?? false) ? 'checked' : '' }}>
        <span><b>Advance tax income</b> <span class="ev-ur" lang="ur" dir="rtl">ایڈوانس ٹیکس آمدنی</span><small>Advance tax is treated as income.</small></span>
    </label>
    <label class="sp-check" for="disabled">
        <input type="hidden" name="disabled" value="0">
        <input id="disabled" type="checkbox" name="disabled" value="1" {{ old('disabled', optional($supplier)->disabled ?? false) ? 'checked' : '' }}>
        <span><b>Disabled</b> <span class="ev-ur" lang="ur" dir="rtl">غیر فعال</span><small>Hidden from new GRNs and dropdowns.</small></span>
    </label>
</div>
<style>
    .sp-checks { display: grid; gap: 10px; grid-template-columns: 1fr; margin-top: 18px; }
    @media (min-width: 900px) { .sp-checks { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .sp-check { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--ak-border); border-radius: 8px; cursor: pointer; background: #fff; }
    .sp-check:hover { background: var(--ak-soft); }
    .sp-check input[type=checkbox] { margin-top: 3px; height: 16px; width: 16px; }
    .sp-check small { display: block; color: var(--ak-muted); font-size: 12px; font-weight: 400; margin-top: 2px; }
    .sp-mode { padding: 12px; border: 1px dashed #a5b4fc; border-radius: 8px; background: #eef2ff; }
</style>
