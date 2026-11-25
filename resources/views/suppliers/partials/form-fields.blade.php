@php
    /** @var \App\Models\Supplier|null $supplier */
    $supplier = $supplier ?? null;
    $currencyOptions = $currencyOptions ?? collect();
    $accountOptions = $accountOptions ?? collect();
    $entryMode = old('qty_entry_mode', optional($supplier)->qty_entry_mode ?? 'pieces');
    $salesTaxRate = number_format((float) (optional($supplier)->sales_tax ?? 18), 2);
    // The column order is set on Edit only: a new supplier starts with the default order.
    $grnColumns = $supplier
        ? \App\Models\Supplier::resolveGrnLineColumns(old('grn_line_columns', $supplier->grn_line_columns))
        : null;
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

<fieldset class="sp-grn">
    <legend class="ev-bi"><span>GRN settings</span><span class="ev-ur" lang="ur" dir="rtl">جی آر این کی ترتیبات</span></legend>
    <p class="ak-hint" style="margin-top:0">How this company's GRNs are entered. These settings change how the GRN screen looks and fills in; the GRN maths stays the same.</p>
    <div class="sp-grn-grid">
        <div class="ak-field">
            <label for="qty_entry_mode" class="ev-bi"><span>Quantity entry on GRN</span><span class="ev-ur" lang="ur" dir="rtl">جی آر این میں مقدار کیسے درج ہو</span></label>
            <select id="qty_entry_mode" name="qty_entry_mode" class="ev-select">
                <option value="pieces" @selected($entryMode === 'pieces')>Pieces (normal entry)</option>
                <option value="carton" @selected($entryMode === 'carton')>Carton (cartons + loose pieces, rate per TH): for Pakistan Tobacco Company</option>
            </select>
            <p class="ak-hint">Carton mode is only an input helper. Stock and the inventory ledger are always kept in pieces. Old GRNs are not affected. Each product needs “Pieces per carton” and “Pouches per piece”.</p>
            @error('qty_entry_mode') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
        </div>
        <div class="ak-field">
            <label class="ev-bi"><span>Sales tax rate</span><span class="ev-ur" lang="ur" dir="rtl">سیلز ٹیکس کی شرح</span></label>
            <div class="sp-rate">{{ $salesTaxRate }}%</div>
            <p class="ak-hint">Filled in automatically as sales tax on each GRN line (value before tax × rate); it can be typed over on a line. Shown for reference: it is not changed here, so GRN amounts stay as they are.</p>
        </div>
    </div>

    <div class="sp-fmr">
        <label class="sp-check" for="is_fmr_allowed">
            <input type="hidden" name="is_fmr_allowed" value="0">
            <input id="is_fmr_allowed" type="checkbox" name="is_fmr_allowed" value="1" {{ old('is_fmr_allowed', optional($supplier)->is_fmr_allowed ?? false) ? 'checked' : '' }}>
            <span><b>FMR allowed</b> <span class="ev-ur" lang="ur" dir="rtl">ایف ایم آر کی اجازت</span>
                <small>Marks a company that gives FMR on its invoices (for example <b>Engro</b>). It shows an “FMR allowed” tag on this company's GRNs.</small>
            </span>
        </label>
        <p class="ak-hint sp-fmr-note">How FMR works on every GRN, ticked or not: the FMR allowance is typed per line, taken off the value before tax, and credited to <b>4210 / 4220 FMR Allowance</b> when the GRN is posted. Stock is carried at its cost before FMR, so unit cost = (total with taxes + FMR) ÷ pieces.</p>
    </div>

    @if ($grnColumns)
        @php
            $columnGroups = collect(\App\Models\Supplier::GRN_LINE_COLUMN_GROUPS)->map(fn ($section, $group) => [
                'label' => $section['label'],
                'cols' => collect($grnColumns)->where('group', $group)->values()->all(),
                'default' => collect($section['keys'])->map(fn ($key) => ['key' => $key, 'label' => \App\Models\Supplier::GRN_LINE_COLUMNS[$key], 'group' => $group])->all(),
            ])->all();
        @endphp
        <div class="sp-cols" x-data="{ groups: {{ Js::from($columnGroups) }},
                move(group, i, step) { const cols = this.groups[group].cols; const j = i + step; if (j < 0 || j >= cols.length) { return; } const c = cols.splice(i, 1)[0]; cols.splice(j, 0, c); },
                reset() { Object.values(this.groups).forEach((section) => { section.cols = section.default.map((col) => ({ ...col })); }); } }">
            <div class="sp-cols-head">
                <div>
                    <h3 class="ev-bi" style="margin:0"><span>GRN line columns: order</span><span class="ev-ur" lang="ur" dir="rtl">جی آر این لائن کے کالموں کی ترتیب</span></h3>
                    <p class="ak-hint" style="margin:2px 0 0">The order of the boxes inside each section of this company's GRN line popup (and the GRN view). Put them in the order of the supplier's invoice. Only the order changes: the sections, the boxes and the maths stay the same.</p>
                </div>
                <button type="button" class="ak-btn ak-btn-outline" @click="reset()">Reset to default</button>
            </div>
            <div class="sp-cols-groups">
                <template x-for="(section, group) in groups" :key="group">
                    <div class="sp-cols-group">
                        <h4 class="sp-cols-group-title" x-text="section.label"></h4>
                        <ol class="sp-cols-list">
                            <template x-for="(col, i) in section.cols" :key="col.key">
                                <li class="sp-col">
                                    <span class="sp-col-no" x-text="i + 1"></span>
                                    <span class="sp-col-name" x-text="col.label"></span>
                                    <span class="sp-col-note" x-show="col.key === 'withholding_tax'">Unilever Pakistan only</span>
                                    <input type="hidden" :name="`grn_line_columns[${group}_${i}][key]`" :value="col.key">
                                    <span class="sp-col-moves">
                                        <button type="button" class="sp-col-btn" @click="move(group, i, -1)" :disabled="i === 0" :aria-label="'Move ' + col.label + ' up'">↑</button>
                                        <button type="button" class="sp-col-btn" @click="move(group, i, 1)" :disabled="i === section.cols.length - 1" :aria-label="'Move ' + col.label + ' down'">↓</button>
                                    </span>
                                </li>
                            </template>
                        </ol>
                    </div>
                </template>
            </div>
            @error('grn_line_columns') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
            @error('grn_line_columns.*') <p class="ak-hint" style="color:#b91c1c">{{ $message }}</p> @enderror
        </div>
    @else
        <p class="ak-hint sp-cols-new">GRN line columns start in the default order (Price &amp; discount: Price, Discount, FMR · Taxes &amp; charges: Sales tax, Excise duty, Advance income tax, Other charges, WHT). Change it from <b>Edit</b> after the supplier is saved.</p>
    @endif
</fieldset>
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
    /* Layout rules this form relies on (they used to come from the expense-voucher styles). */
    .ev-form { padding: 20px; }
    .ev-form-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); }
    .ev-form-grid > * { min-width: 0; }
    @media (min-width: 900px) { .ev-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .ev-form-grid .ev-span-2 { grid-column: span 2; } .ev-form-grid .ev-span-3 { grid-column: span 3; } }
    html.apl-on .ev-form label.ev-bi, .ev-form .ev-bi { display: flex !important; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 4px; }
    .ev-form .ev-ur { font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', 'Geeza Pro', serif; font-size: 15px; font-weight: 600; color: var(--ak-navy); line-height: 1.5; }
    .ev-form-foot { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--ak-line, #e5e7eb); }
    .ev-form-foot .ak-btn { display: inline-flex; align-items: center; gap: 10px; }
    @media (max-width: 640px) { .ev-form { padding: 14px; } .ev-form-foot { flex-wrap: wrap; } .ev-form-foot .ak-btn { flex: 1 1 100%; justify-content: center; } }
    .sp-cols-head h3.ev-bi { justify-content: flex-start; font-size: 14.5px; font-weight: 700; }
    .sp-grn legend.ev-bi { display: inline-flex; gap: 10px; }
    .sp-checks { display: grid; gap: 10px; grid-template-columns: 1fr; margin-top: 18px; }
    @media (min-width: 900px) { .sp-checks { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .sp-check { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--ak-border); border-radius: 8px; cursor: pointer; background: #fff; }
    .sp-check:hover { background: var(--ak-soft); }
    .sp-check input[type=checkbox] { margin-top: 3px; height: 16px; width: 16px; }
    .sp-check small { display: block; color: var(--ak-muted); font-size: 12px; font-weight: 400; margin-top: 2px; }
    .sp-grn { margin-top: 18px; padding: 16px; border: 1px solid #c7d2fe; border-radius: 10px; background: #f5f7ff; }
    .sp-grn legend { padding: 0 6px; font-weight: 700; font-size: 15px; }
    .sp-grn-grid { display: grid; gap: 14px; grid-template-columns: 1fr; }
    @media (min-width: 900px) { .sp-grn-grid { grid-template-columns: 2fr 1fr; } }
    .sp-fmr { margin-top: 14px; display: grid; gap: 10px; }
    .sp-rate { height: 40px; display: flex; align-items: center; padding: 0 12px; border: 1px solid var(--ak-border); border-radius: 8px; background: #fff; font-weight: 700; font-variant-numeric: tabular-nums; }
    .sp-fmr-note { margin: 0; padding: 8px 12px; background: #fff; border: 1px solid var(--ak-border); border-radius: 8px; }
    .sp-fmr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; background: #fff; border: 1px solid var(--ak-border); border-radius: 8px; overflow: hidden; }
    .sp-fmr-table th, .sp-fmr-table td { padding: 6px 10px; border-bottom: 1px solid var(--ak-border); text-align: left; vertical-align: top; }
    .sp-fmr-table thead th { background: var(--ak-soft); font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--ak-muted); }
    .sp-cols { margin-top: 16px; padding: 14px; background: #fff; border: 1px solid var(--ak-border); border-radius: 10px; }
    .sp-cols-head { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-start; justify-content: space-between; }
    .sp-cols-groups { display: grid; gap: 14px; grid-template-columns: 1fr; margin-top: 12px; }
    @media (min-width: 900px) { .sp-cols-groups { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .sp-cols-group-title { margin: 0 0 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #1e40af; }
    .sp-cols-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
    .sp-col { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid var(--ak-border); border-radius: 8px; background: #fff; }
    .sp-col-no { display: inline-flex; width: 26px; height: 26px; align-items: center; justify-content: center; border-radius: 999px; background: #e0e7ff; color: #3730a3; font-weight: 700; font-size: 12px; }
    .sp-col-name { font-weight: 600; flex: 1; min-width: 140px; }
    .sp-col-note { font-size: 11.5px; color: var(--ak-muted); }
    .sp-col-moves { display: inline-flex; gap: 4px; }
    .sp-col-btn { width: 34px; height: 30px; border: 1px solid var(--ak-border); border-radius: 6px; background: #fff; font-weight: 700; cursor: pointer; }
    .sp-col-btn:hover:not(:disabled) { background: #eef2ff; }
    .sp-col-btn:disabled { opacity: .35; cursor: default; }
    .sp-cols-new { margin-top: 12px; }
</style>
