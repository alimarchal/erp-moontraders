{{--
    Settings -> Company Targets -> Add / Edit (/settings/company-targets/create).
    Pick the company, dates and designation (every dropdown is a searchable Select2; the designation
    list and the salesmen belong to the chosen company only). The salesman rows are shown before
    anything is saved: edit Target text and Total, set one Total for all rows, remove rows, or add
    several salesmen of that company at once. A sticky bar keeps the total and Save in view.
--}}
@php
    $isCreate = ! $target->exists;
    $selectedSupplier = old('supplier_id', $target->supplier_id);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('company-targets.index') }}">Company Targets</a><span aria-hidden="true">›</span>
                    <span>{{ $isCreate ? 'Add' : 'Edit' }}</span>
                </nav>
                <h1 class="ak-title">{{ $isCreate ? 'Add Company Target' : 'Edit Company Target' }}</h1>
                <p class="ak-sub">Monthly target of each salesman. Achieved sales are calculated from posted settlements.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('company-targets.help') }}" class="ak-btn ak-btn-outline">Help (اردو)</a>
                <a href="{{ route('company-targets.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Company Targets</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <style>
        [x-cloak] { display: none !important; }
        .ct-step { display: flex; align-items: center; gap: 10px; margin: 0 0 14px; font-size: 15px; font-weight: 700; color: var(--ak-text); }
        .ct-step-no { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 999px; background: #1e2a5a; color: #fff; font-size: 13px; }
        .ct-step small { font-weight: 500; color: var(--ak-muted); font-size: 13px; }
        .ct-grid { display: grid; gap: 16px; grid-template-columns: repeat(12, minmax(0, 1fr)); }
        .ct-col-5 { grid-column: span 5; } .ct-col-2 { grid-column: span 2; } .ct-col-3 { grid-column: span 3; } .ct-col-12 { grid-column: span 12; }
        @media (max-width: 900px) { .ct-col-5, .ct-col-2, .ct-col-3 { grid-column: span 12; } }
        .ct-hint { margin-top: 6px; font-size: 12px; color: var(--ak-muted); }
        .ct-period-chip { display: inline-flex; gap: 6px; align-items: center; margin-top: 6px; padding: 2px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: 600; }
        .ct-tools { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--ak-line); background: #fafbfd; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }
        .ct-tools-group { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .ct-tools label { font-size: 12px; font-weight: 600; color: var(--ak-muted); }
        .ct-tools input { height: 34px; padding: 0 10px; border: 1px solid var(--ak-border); border-radius: 8px; font-size: 13px; background: #fff; }
        .ct-tools input[type=number] { width: 150px; }
        .ct-tools .select2-container { min-width: 260px; }
        .ct-wrap { max-width: 1000px; margin-inline: auto; }
        .ct-table { width: 100%; border-collapse: collapse; font-size: 14px; table-layout: fixed; }
        .ct-table th { position: sticky; top: 0; z-index: 1; padding: 9px 12px; text-align: left; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ak-muted); background: #f3f4f6; border-bottom: 1px solid var(--ak-line); }
        .ct-table td { padding: 6px 12px; border-bottom: 1px solid #eef0f3; vertical-align: middle; }
        .ct-table tbody tr:nth-child(even) td { background: #fbfcfe; }
        .ct-table tbody tr:hover td { background: #f1f5ff; }
        .ct-num { text-align: right; }
        .ct-name { font-weight: 600; color: var(--ak-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ct-cell { width: 100%; height: 36px; padding: 0 10px; border: 1px solid var(--ak-border); border-radius: 6px; font-size: 14px; background: #fff; }
        .ct-cell.is-amount { text-align: right; font-variant-numeric: tabular-nums; }
        .ct-cell.is-zero { color: #9ca3af; }
        .ct-cell:focus, .ct-tools input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); color: var(--ak-text); }
        .ct-tools .select2-container--default .select2-selection--multiple { min-height: 34px; border: 1px solid var(--ak-border); border-radius: 8px; padding: 0 6px; }
        .ct-tools .select2-container--default .select2-selection--multiple .select2-selection__choice { margin-top: 5px; font-size: 12px; }
        .ct-tools .select2-search--inline .select2-search__field { margin-top: 6px; font-size: 13px; }
        .ct-remove { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 1px solid transparent; border-radius: 8px; background: transparent; color: #9ca3af; cursor: pointer; }
        .ct-remove:hover, .ct-remove:focus-visible { color: #b91c1c; background: #fef2f2; border-color: #fecaca; outline: none; }
        .ct-empty { padding: 44px 16px; text-align: center; color: var(--ak-muted); }
        .ct-empty b { display: block; margin-bottom: 4px; color: var(--ak-text); }
        .ct-loading { padding: 10px 18px; font-size: 13px; color: #3730a3; background: #eef2ff; }
        .ct-bar { position: sticky; bottom: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding: 12px 18px; background: rgba(255,255,255,.96); backdrop-filter: blur(6px); border: 1px solid var(--ak-border); border-radius: var(--ak-radius); box-shadow: 0 -6px 24px -12px rgba(15,23,42,.25); }
        .ct-bar-stats { display: flex; gap: 22px; flex-wrap: wrap; }
        .ct-bar-stats div span { display: block; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--ak-muted); }
        .ct-bar-stats div b { font-size: 18px; color: var(--ak-text); font-variant-numeric: tabular-nums; }
    </style>

    <div class="ak-page" x-data="companyTargetForm({
            supplierId: @js($selectedSupplier ? (string) $selectedSupplier : ''),
            designation: @js(old('designation', $designation)),
            designations: @js($designations->values()),
            rows: @js($rows),
            url: @js(route('company-targets.employees')),
        })" x-init="init()">
        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">
                <ul style="margin:0;padding-left:18px;">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="ct-wrap" method="POST" action="{{ $isCreate ? route('company-targets.store') : route('company-targets.update', $target) }}">
            @csrf
            @unless ($isCreate) @method('PUT') @endunless

            {{-- Step 1 --}}
            <section class="ak-card" style="padding:20px;" aria-labelledby="step-company">
                <h2 class="ct-step" id="step-company">Company &amp; period <small>who the targets are for, and which dates they cover</small></h2>
                <div class="ct-grid">
                    <div class="ak-field ct-col-5">
                        <label for="supplier_id">Company</label>
                        <select id="supplier_id" name="supplier_id" x-ref="company" @disabled($lockedSupplier) required>
                            @unless ($lockedSupplier)
                                <option value="">Select company</option>
                            @endunless
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) $selectedSupplier === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                        @if ($lockedSupplier)
                            <input type="hidden" name="supplier_id" value="{{ $selectedSupplier }}">
                            <p class="ct-hint">Your account is tied to this company.</p>
                        @endif
                    </div>
                    <div class="ak-field ct-col-2">
                        <label for="period_start">Start date</label>
                        <input id="period_start" type="date" name="period_start" x-model="start" required>
                    </div>
                    <div class="ak-field ct-col-2">
                        <label for="period_end">End date</label>
                        <input id="period_end" type="date" name="period_end" x-model="end" required>
                    </div>
                    <div class="ak-field ct-col-3">
                        <label for="designation">Designation</label>
                        <select id="designation" name="designation" x-ref="designation"></select>
                        <p class="ct-hint">Every designation of this company. The number is its active employees.</p>
                    </div>
                    <div class="ct-col-12" x-show="periodDays() > 0">
                        <span class="ct-period-chip" x-text="`${periodDays()} day(s) in this period`"></span>
                    </div>
                    <div class="ak-field ct-col-12">
                        <label for="notes">Notes <span class="ak-muted" style="font-weight:400;">(optional)</span></label>
                        <input id="notes" type="text" name="notes" maxlength="2000" value="{{ old('notes', $target->notes) }}" placeholder="e.g. Revised after mid-month review">
                    </div>
                </div>
            </section>

            {{-- Step 2 --}}
            <section class="ak-card" style="margin-top:16px;padding:0;" aria-label="Salesmen and targets">
                <div class="ct-tools">
                    <div class="ct-tools-group">
                        <label for="bulk">Same Total for all rows</label>
                        <input id="bulk" type="number" min="0" step="0.01" x-model="bulk" placeholder="e.g. 5000000" @keydown.enter.prevent="applyBulk()">
                        <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" @click="applyBulk()" :disabled="bulk === ''">Apply</button>
                        <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="resetTotals()" :disabled="rows.length === 0">Reset to 0</button>
                    </div>
                    <div class="ct-tools-group">
                        <label for="add">Add salesmen</label>
                        <div style="width:380px;max-width:100%;">
                            <select id="add" x-ref="add" multiple></select>
                        </div>
                        <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" @click="addSelected()" :disabled="picked.length === 0">Add selected</button>
                        <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="addAll()" :disabled="availableEmployees().length === 0">Add all</button>
                    </div>
                </div>
                <div class="ct-loading" x-show="loading" x-cloak role="status">Loading this company's salesmen…</div>
                <div style="overflow-x:auto;">
                    <table class="ct-table" style="min-width:640px;">
                        <colgroup><col style="width:52px;"><col><col style="width:210px;"><col style="width:200px;"><col style="width:52px;"></colgroup>
                        <thead>
                            <tr><th>#</th><th>Salesman</th><th>Target</th><th class="ct-num">Total (PKR)</th><th><span class="sr-only">Remove</span></th></tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in rows" :key="row.employee_id">
                                <tr>
                                    <td class="ak-muted" x-text="index + 1"></td>
                                    <td class="ct-name" :title="row.name">
                                        <span x-text="row.name"></span>
                                        <input type="hidden" :name="`lines[${index}][employee_id]`" :value="row.employee_id">
                                    </td>
                                    <td><input class="ct-cell" type="text" :name="`lines[${index}][target_label]`" x-model="row.target_label" maxlength="255" placeholder="Corporate" :aria-label="`Target for ${row.name}`"></td>
                                    <td>
                                        <input class="ct-cell is-amount" :class="Number(row.target_amount) > 0 ? '' : 'is-zero'" type="number" inputmode="decimal"
                                            :name="`lines[${index}][target_amount]`" x-model="row.target_amount" min="0" step="0.01" required
                                            :aria-label="`Total for ${row.name}`" :title="Number(row.target_amount) >= 1000 ? format(row.target_amount) : null" data-amount
                                            @focus="$event.target.select()" @keydown.enter.prevent="focusNext($event)">
                                    </td>
                                    <td class="ct-num"><button type="button" class="ct-remove" @click="removeRow(index)" :aria-label="`Remove ${row.name}`" title="Remove row"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5h6v2m-8 0 1 12h8l1-12"/></svg></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="ct-empty" x-show="rows.length === 0 && !loading" x-cloak>
                    <b x-text="supplierId ? 'No salesman rows yet' : 'Pick a company to begin'"></b>
                    <span x-text="supplierId ? 'Choose another designation, or add a salesman from the list above.' : 'Its salesmen appear here before you save.'"></span>
                </div>
            </section>

            <div class="ct-bar">
                <div class="ct-bar-stats">
                    <div><span>Salesmen</span><b x-text="rows.length"></b></div>
                    <div><span>With a target</span><b x-text="`${filledCount()} / ${rows.length}`"></b></div>
                    <div><span>Total target</span><b x-text="grandTotal()"></b></div>
                </div>
                <div style="display:flex;gap:10px;">
                    <a href="{{ route('company-targets.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
                    <button type="submit" class="ak-btn ak-btn-primary" :disabled="rows.length === 0 || !supplierId">{{ $isCreate ? 'Save target' : 'Update target' }}</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function companyTargetForm(config) {
            return {
                supplierId: config.supplierId,
                designation: config.designation,
                designations: config.designations,
                rows: config.rows,
                start: @js(old('period_start', $target->period_start?->toDateString())),
                end: @js(old('period_end', $target->period_end?->toDateString())),
                allEmployees: [],
                picked: [],
                loading: false,
                async init() {
                    await this.whenSelect2();
                    this.mountSelects();
                    if (this.supplierId) {
                        await this.load(false);
                    }
                },
                whenSelect2() {
                    return new Promise((resolve) => {
                        const check = () => (window.jQuery && jQuery.fn.select2 ? resolve() : setTimeout(check, 30));
                        check();
                    });
                },
                mountSelects() {
                    const $company = jQuery(this.$refs.company);
                    $company.select2({ width: '100%', placeholder: 'Select company' });
                    $company.on('change', () => {
                        if (this.supplierId !== $company.val()) {
                            this.supplierId = $company.val() || '';
                            this.changeSupplier();
                        }
                    });

                    const $designation = jQuery(this.$refs.designation);
                    $designation.select2({ width: '100%', placeholder: 'Select a company first' });
                    $designation.on('change', () => {
                        if ($designation.val() && this.designation !== $designation.val()) {
                            this.designation = $designation.val();
                            this.reload();
                        }
                    });
                    this.fillDesignations();

                    const $add = jQuery(this.$refs.add);
                    $add.select2({ width: '100%', placeholder: 'Search salesmen…', closeOnSelect: false });
                    $add.on('change', () => { this.picked = $add.val() || []; });
                },
                fillDesignations() {
                    const $designation = jQuery(this.$refs.designation).empty();
                    this.designations.forEach((option) => {
                        $designation.append(new Option(`${option.name} (${option.count})`, option.name, false, option.name === this.designation));
                    });
                    $designation.prop('disabled', !this.supplierId).val(this.designation || null).trigger('change.select2');
                },
                fillAddList() {
                    const $add = jQuery(this.$refs.add).empty();
                    this.availableEmployees().forEach((employee) => {
                        $add.append(new Option(employee.name + (employee.code ? ` (${employee.code})` : ''), employee.id, false, false));
                    });
                    $add.prop('disabled', !this.supplierId).val(null).trigger('change.select2');
                    this.picked = [];
                },
                async reload() {
                    await this.load(true);
                },
                async changeSupplier() {
                    this.designation = '';
                    this.designations = [];
                    this.fillDesignations();
                    await this.load(true);
                },
                async load(replaceRows) {
                    if (!this.supplierId) {
                        this.rows = [];
                        this.allEmployees = [];
                        this.designations = [];
                        this.fillDesignations();
                        this.fillAddList();
                        return;
                    }
                    this.loading = true;
                    const fetchData = async (designation) => {
                        const params = new URLSearchParams({ supplier_id: this.supplierId, designation });
                        const response = await fetch(`${config.url}?${params}`, { headers: { Accept: 'application/json' } });
                        return response.ok ? response.json() : null;
                    };
                    try {
                        let data = await fetchData(this.designation || 'Salesman');
                        if (!data) {
                            return;
                        }
                        this.designations = data.designations;
                        const names = data.designations.map((option) => option.name);
                        if (!names.includes(this.designation)) {
                            // New company: keep Salesman when it has one, otherwise its first designation.
                            this.designation = names.includes('Salesman') ? 'Salesman' : (names[0] ?? '');
                            if (this.designation && this.designation !== 'Salesman') {
                                data = (await fetchData(this.designation)) ?? data;
                            }
                        }
                        this.allEmployees = data.all_employees;
                        if (replaceRows) {
                            this.rows = data.employees.map((employee) => ({
                                employee_id: employee.id,
                                name: employee.name,
                                target_label: 'Corporate',
                                target_amount: '0',
                            }));
                        }
                        this.fillDesignations();
                        this.fillAddList();
                    } finally {
                        this.loading = false;
                    }
                },
                availableEmployees() {
                    return this.allEmployees.filter((employee) => !this.rows.some((row) => row.employee_id === employee.id));
                },
                pushEmployee(employee) {
                    this.rows.push({ employee_id: employee.id, name: employee.name, target_label: 'Corporate', target_amount: '0' });
                },
                addSelected() {
                    this.picked.forEach((id) => {
                        const employee = this.allEmployees.find((item) => String(item.id) === String(id));
                        if (employee && !this.rows.some((row) => row.employee_id === employee.id)) {
                            this.pushEmployee(employee);
                        }
                    });
                    this.fillAddList();
                },
                addAll() {
                    this.availableEmployees().forEach((employee) => this.pushEmployee(employee));
                    this.fillAddList();
                },
                removeRow(index) {
                    this.rows.splice(index, 1);
                    this.fillAddList();
                },
                bulk: '',
                focusNext(event) {
                    const inputs = Array.from(this.$root.querySelectorAll('[data-amount]'));
                    const next = inputs[inputs.indexOf(event.target) + 1];
                    if (next) {
                        next.focus();
                    }
                },
                filledCount() {
                    return this.rows.filter((row) => Number(row.target_amount) > 0).length;
                },
                applyBulk() {
                    if (this.bulk === '' || Number(this.bulk) < 0) {
                        return;
                    }
                    this.rows.forEach((row) => { row.target_amount = String(this.bulk); });
                },
                resetTotals() {
                    this.rows.forEach((row) => { row.target_amount = '0'; });
                },
                initials(name) {
                    return String(name ?? '').split(' ').filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('');
                },
                format(value) {
                    return Number(value).toLocaleString('en-PK', { maximumFractionDigits: 2 });
                },
                grandTotal() {
                    return this.format(this.rows.reduce((sum, row) => sum + (parseFloat(row.target_amount) || 0), 0));
                },
                periodDays() {
                    if (!this.start || !this.end) {
                        return 0;
                    }
                    const days = Math.round((new Date(this.end) - new Date(this.start)) / 86400000) + 1;
                    return days > 0 ? days : 0;
                },
            };
        }
    </script>
</x-app-layout>
