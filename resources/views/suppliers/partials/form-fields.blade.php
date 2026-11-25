{{--
    Supplier create / edit fields as compact Apple-style cards (styles: suppliers.partials.apple-style).
    Left: company, defaults and tax, contact. Right: GRN settings, GRN column order (edit only), switches.
    Field names and ids are unchanged.
--}}
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
    $value = fn (string $field) => old($field, optional($supplier)->{$field});
    $checked = fn (string $field) => (bool) old($field, optional($supplier)->{$field} ?? false);
    $textFields = [
        'company' => [
            ['supplier_name', 'Supplier name', 'Pakistan Tobacco Company', 'sp-span-2', ['required' => true, 'maxlength' => 255]],
            ['short_name', 'Short name', 'PTC', '', ['maxlength' => 100, 'style' => 'text-transform:uppercase']],
            ['country', 'Country', 'Pakistan', '', []],
            ['supplier_group', 'Group', 'Local', '', []],
            ['supplier_type', 'Type', 'Tobacco', '', []],
        ],
        'tax' => [
            ['tax_id', 'Tax ID / NTN', 'NTN-1234567', '', []],
            ['pan_number', 'PAN number', 'PAN-987654', '', []],
            ['default_price_list', 'Default price list', 'STANDARD_PRICES', '', []],
            ['print_language', 'Print language', 'English', '', []],
            ['website', 'Website', 'https://example.com', 'sp-span-2', ['type' => 'url']],
        ],
    ];
    $switches = [
        ['is_transporter', 'Also provides transport', 'Can be picked as a transporter.', ''],
        ['is_internal_supplier', 'Internal supplier', 'Inter-company supplier.', ''],
        ['is_advance_tax_income', 'Advance tax income', 'Advance tax is treated as income.', ''],
        ['disabled', 'Disabled', 'Hidden from new GRNs and dropdowns.', 'sp-switch-red'],
    ];
@endphp

<div class="sp-cols-2">
    <div class="sp-stack">
        {{-- Company --}}
        <section class="sp-card" aria-labelledby="sp-company">
            <header class="sp-card-head"><h2 class="sp-card-title" id="sp-company">Company</h2></header>
            <div class="sp-card-body sp-grid">
                @foreach ($textFields['company'] as [$field, $label, $placeholder, $span, $attributes])
                    <div class="sp-field {{ $span }}">
                        <label for="{{ $field }}"><span>{{ $label }}</span></label>
                        <input id="{{ $field }}" type="{{ $attributes['type'] ?? 'text' }}" name="{{ $field }}" value="{{ $value($field) }}" placeholder="{{ $placeholder }}"
                            @if ($attributes['required'] ?? false) required @endif
                            @isset($attributes['maxlength']) maxlength="{{ $attributes['maxlength'] }}" @endisset
                            @isset($attributes['style']) style="{{ $attributes['style'] }}" @endisset>
                        @error($field) <p class="sp-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Defaults and tax --}}
        <section class="sp-card" aria-labelledby="sp-defaults">
            <header class="sp-card-head"><h2 class="sp-card-title" id="sp-defaults">Defaults &amp; tax</h2></header>
            <div class="sp-card-body sp-grid">
                <div class="sp-field">
                    <label for="default_currency_id"><span>Default currency</span></label>
                    <select id="default_currency_id" name="default_currency_id">
                        <option value="">Select currency</option>
                        @foreach ($currencyOptions as $currency)
                            <option value="{{ $currency->id }}" @selected((int) $value('default_currency_id') === $currency->id)>{{ $currency->currency_code }} · {{ $currency->currency_name }}</option>
                        @endforeach
                    </select>
                    @error('default_currency_id') <p class="sp-error">{{ $message }}</p> @enderror
                </div>
                <div class="sp-field">
                    <label for="default_bank_account_id"><span>Default bank account</span></label>
                    <select id="default_bank_account_id" name="default_bank_account_id">
                        <option value="">Select account</option>
                        @foreach ($accountOptions as $account)
                            <option value="{{ $account->id }}" @selected((int) $value('default_bank_account_id') === $account->id)>{{ $account->account_code }} · {{ $account->account_name }}</option>
                        @endforeach
                    </select>
                    @error('default_bank_account_id') <p class="sp-error">{{ $message }}</p> @enderror
                </div>
                @foreach ($textFields['tax'] as [$field, $label, $placeholder, $span, $attributes])
                    <div class="sp-field {{ $span }}">
                        <label for="{{ $field }}"><span>{{ $label }}</span></label>
                        <input id="{{ $field }}" type="{{ $attributes['type'] ?? 'text' }}" name="{{ $field }}" value="{{ $value($field) }}" placeholder="{{ $placeholder }}">
                        @error($field) <p class="sp-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Contact and notes --}}
        <section class="sp-card" aria-labelledby="sp-contact">
            <header class="sp-card-head"><h2 class="sp-card-title" id="sp-contact">Contact &amp; notes</h2></header>
            <div class="sp-card-body sp-grid">
                <div class="sp-field">
                    <label for="supplier_primary_address"><span>Primary address</span></label>
                    <textarea id="supplier_primary_address" name="supplier_primary_address" rows="2" placeholder="Street, city, postal code">{{ $value('supplier_primary_address') }}</textarea>
                </div>
                <div class="sp-field">
                    <label for="supplier_primary_contact"><span>Primary contact</span></label>
                    <textarea id="supplier_primary_contact" name="supplier_primary_contact" rows="2" placeholder="Name, phone, email">{{ $value('supplier_primary_contact') }}</textarea>
                </div>
                <div class="sp-field sp-span-2">
                    <label for="supplier_details"><span>Additional details</span></label>
                    <textarea id="supplier_details" name="supplier_details" rows="2" placeholder="Terms, payment notes">{{ $value('supplier_details') }}</textarea>
                </div>
            </div>
        </section>

        {{-- Switches --}}
        <section class="sp-card" aria-labelledby="sp-flags">
            <header class="sp-card-head"><h2 class="sp-card-title" id="sp-flags">Options</h2></header>
            <div class="sp-card-body">
                <ul class="sp-list">
                    @foreach ($switches as [$field, $label, $help, $tone])
                        <li>
                            <label class="sp-row" for="{{ $field }}">
                                <span class="sp-row-text"><span class="sp-row-title">{{ $label }}</span><span class="sp-row-sub">{{ $help }}</span></span>
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input id="{{ $field }}" class="sp-switch {{ $tone }}" type="checkbox" role="switch" name="{{ $field }}" value="1" @checked($checked($field))>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    </div>

    <div class="sp-stack">
        {{-- GRN settings --}}
        <section class="sp-card" aria-labelledby="sp-grn">
            <header class="sp-card-head">
                <div>
                    <h2 class="sp-card-title" id="sp-grn">GRN settings</h2>
                    <p class="sp-card-sub">How this company's GRNs are entered. The GRN maths stays the same.</p>
                </div>
            </header>
            <div class="sp-card-body sp-stack" style="gap:12px">
                <div class="sp-field">
                    <label for="qty_entry_mode"><span>Quantity entry on GRN</span></label>
                    <select id="qty_entry_mode" name="qty_entry_mode">
                        <option value="pieces" @selected($entryMode === 'pieces')>Pieces (normal entry)</option>
                        <option value="carton" @selected($entryMode === 'carton')>Carton + loose pieces, rate per TH (PTC)</option>
                    </select>
                    <p class="sp-hint">Carton mode is only an input helper: stock is always kept in pieces, and old GRNs are not affected. Products need “Pieces per carton” and “Pouches per piece”.</p>
                    @error('qty_entry_mode') <p class="sp-error">{{ $message }}</p> @enderror
                </div>
                <div class="sp-field">
                    <label><span>Sales tax rate</span></label>
                    <div class="sp-readout">{{ $salesTaxRate }}%</div>
                    <p class="sp-hint">Filled in as sales tax on each GRN line and can be typed over there. Shown for reference only.</p>
                </div>
                <ul class="sp-list">
                    <li>
                        <label class="sp-row" for="is_fmr_allowed">
                            <span class="sp-row-text"><span class="sp-row-title">FMR allowed</span>
                                <span class="sp-row-sub">Shows an “FMR allowed” tag on this company's GRNs (for example Engro).</span></span>
                            <input type="hidden" name="is_fmr_allowed" value="0">
                            <input id="is_fmr_allowed" class="sp-switch" type="checkbox" role="switch" name="is_fmr_allowed" value="1" @checked($checked('is_fmr_allowed'))>
                        </label>
                    </li>
                </ul>
                <details class="sp-details">
                    <summary>How FMR works on every GRN</summary>
                    <p>The FMR allowance is typed per line, taken off the value before tax, and credited to <b>4210 / 4220 FMR Allowance</b> when the GRN is posted. Stock is carried at its cost before FMR, so unit cost = (total with taxes + FMR) ÷ pieces.</p>
                </details>
            </div>
        </section>

        {{-- GRN line column order --}}
        @if ($grnColumns)
            @php
                $columnGroups = collect(\App\Models\Supplier::GRN_LINE_COLUMN_GROUPS)->map(fn ($section, $group) => [
                    'label' => $section['label'],
                    'cols' => collect($grnColumns)->where('group', $group)->values()->all(),
                    'default' => collect($section['keys'])->map(fn ($key) => ['key' => $key, 'label' => \App\Models\Supplier::GRN_LINE_COLUMNS[$key], 'group' => $group])->all(),
                ])->all();
            @endphp
            <section class="sp-card" aria-labelledby="sp-order" x-data="{ groups: {{ Js::from($columnGroups) }},
                    move(group, i, step) { const cols = this.groups[group].cols; const j = i + step; if (j < 0 || j >= cols.length) { return; } const c = cols.splice(i, 1)[0]; cols.splice(j, 0, c); },
                    reset() { Object.values(this.groups).forEach((section) => { section.cols = section.default.map((col) => ({ ...col })); }); } }">
                <header class="sp-card-head">
                    <div>
                        <h2 class="sp-card-title" id="sp-order">GRN line columns: order</h2>
                        <p class="sp-card-sub">The order of the boxes inside each section of the GRN line popup and view, as on the supplier's invoice.</p>
                    </div>
                    <button type="button" class="sp-link-btn" @click="reset()">Reset</button>
                </header>
                <div class="sp-card-body sp-order">
                    <template x-for="(section, group) in groups" :key="group">
                        <div>
                            <h3 class="sp-order-title" x-text="section.label"></h3>
                            <ol class="sp-list">
                                <template x-for="(col, i) in section.cols" :key="col.key">
                                    <li class="sp-row">
                                        <span class="sp-order-no" x-text="i + 1"></span>
                                        <span class="sp-order-name" x-text="col.label"></span>
                                        <span class="sp-order-note" x-show="col.key === 'withholding_tax'">Unilever only</span>
                                        <input type="hidden" :name="`grn_line_columns[${group}_${i}][key]`" :value="col.key">
                                        <span class="sp-move">
                                            <button type="button" @click="move(group, i, -1)" :disabled="i === 0" :aria-label="'Move ' + col.label + ' up'">↑</button>
                                            <button type="button" @click="move(group, i, 1)" :disabled="i === section.cols.length - 1" :aria-label="'Move ' + col.label + ' down'">↓</button>
                                        </span>
                                    </li>
                                </template>
                            </ol>
                        </div>
                    </template>
                    @error('grn_line_columns') <p class="sp-error">{{ $message }}</p> @enderror
                    @error('grn_line_columns.*') <p class="sp-error">{{ $message }}</p> @enderror
                </div>
            </section>
        @else
            <p class="sp-hint" style="padding:0 4px">GRN line columns start in the default order (Price &amp; discount: Price, Discount, FMR · Taxes &amp; charges: Sales tax, Excise duty, Advance income tax, Other charges, WHT). Change it from <b>Edit</b> after the supplier is saved.</p>
        @endif

    </div>
</div>
