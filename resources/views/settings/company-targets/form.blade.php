{{--
    Settings -> Company Targets -> Add / Edit (/settings/company-targets/create).
    Step 1 picks the company, dates and designation (the dropdown lists every designation of that
    company). Step 2 shows the salesman rows before anything is saved: edit Target text and Total,
    set one Total for all rows, remove rows or add more. A sticky bar keeps the total and Save in view.
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
        .ct-tools input, .ct-tools select { height: 34px; padding: 0 10px; border: 1px solid var(--ak-border); border-radius: 8px; font-size: 13px; background: #fff; }
        .ct-tools input[type=number] { width: 150px; }
        .ct-tools select { min-width: 200px; }
        .ct-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
        .ct-table th { position: sticky; top: 0; z-index: 1; padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ak-muted); background: #f3f4f6; border-bottom: 1px solid var(--ak-line); }
        .ct-table td { padding: 10px 14px; border-bottom: 1px solid #eef0f3; vertical-align: middle; }
        .ct-table tbody tr:hover td { background: #f8faff; }
        .ct-num { text-align: right; }
        .ct-person { display: flex; align-items: center; gap: 12px; }
        .ct-avatar { display: inline-flex; align-items: center; justify-content: center; flex: none; width: 34px; height: 34px; border-radius: 999px; background: #e0e7ff; color: #3730a3; font-size: 12px; font-weight: 700; }
        .ct-person b { display: block; color: var(--ak-text); font-weight: 600; }
        .ct-person span { font-size: 12px; color: var(--ak-muted); }
        .ct-cell { width: 100%; height: 38px; padding: 0 12px; border: 1px solid var(--ak-border); border-radius: 8px; font-size: 14px; background: #fff; }
        .ct-cell:focus, .ct-tools input:focus, .ct-tools select:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
        .ct-money { position: relative; }
        .ct-money i { position: absolute; left: 12px; top: 10px; font-style: normal; font-size: 12px; font-weight: 600; color: var(--ak-muted); pointer-events: none; }
        .ct-money .ct-cell { padding-left: 42px; text-align: right; font-variant-numeric: tabular-nums; }
        .ct-words { margin-top: 2px; font-size: 11px; color: var(--ak-muted); text-align: right; min-height: 14px; }
        .ct-remove { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 1px solid transparent; border-radius: 8px; background: transparent; color: #9ca3af; cursor: pointer; }
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

        <form method="POST" action="{{ $isCreate ? route('company-targets.store') : route('company-targets.update', $target) }}">
            @csrf
            @unless ($isCreate) @method('PUT') @endunless

            {{-- Step 1 --}}
            <section class="ak-card" style="padding:20px;" aria-labelledby="step-company">
                <h2 class="ct-step" id="step-company"><span class="ct-step-no">1</span> Company &amp; period <small>who the targets are for, and which dates they cover</small></h2>
                <div class="ct-grid">
                    <div class="ak-field ct-col-5">
                        <label for="supplier_id">Company</label>
                        <select id="supplier_id" name="supplier_id" x-model="supplierId" @change="changeSupplier()" @disabled($lockedSupplier) required>
                            @unless ($lockedSupplier)
                                <option value="">Select company</option>
                            @endunless
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
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
                        <select id="designation" name="designation" x-model="designation" @change="reload()" :disabled="!supplierId">
                            <option value="" x-show="!supplierId || designations.length === 0" x-text="supplierId ? 'No designations found' : 'Select a company first'"></option>
                            <template x-for="option in designations" :key="option.name">
                                <option :value="option.name" x-text="`${option.name} (${option.count})`" :selected="option.name === designation"></option>
                            </template>
                        </select>
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
            <section class="ak-card" style="margin-top:16px;padding:0;" aria-labelledby="step-rows">
                <div style="padding:20px 20px 0;">
                    <h2 class="ct-step" id="step-rows"><span class="ct-step-no">2</span> Salesmen &amp; targets <small>review the rows, then save</small></h2>
                </div>
                <div class="ct-tools">
                    <div class="ct-tools-group">
                        <label for="bulk">Same Total for all rows</label>
                        <input id="bulk" type="number" min="0" step="0.01" x-model="bulk" placeholder="e.g. 5000000" @keydown.enter.prevent="applyBulk()">
                        <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" @click="applyBulk()" :disabled="bulk === ''">Apply</button>
                        <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="resetTotals()" :disabled="rows.length === 0">Reset to 0</button>
                    </div>
                    <div class="ct-tools-group">
                        <label for="add">Add salesman</label>
                        <select id="add" x-model="addId" @change="addRow()" :disabled="availableEmployees().length === 0">
                            <option value="" x-text="availableEmployees().length ? 'Choose to add…' : 'Everyone is already listed'"></option>
                            <template x-for="employee in availableEmployees()" :key="employee.id">
                                <option :value="employee.id" x-text="employee.name + (employee.code ? ' (' + employee.code + ')' : '')"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div class="ct-loading" x-show="loading" x-cloak role="status">Loading this company's salesmen…</div>
                <div style="overflow-x:auto;">
                    <table class="ct-table" style="min-width:760px;">
                        <thead>
                            <tr><th style="width:56px;">#</th><th>Salesman</th><th style="width:30%;">Target</th><th class="ct-num" style="width:230px;">Total</th><th style="width:60px;"><span class="sr-only">Remove</span></th></tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in rows" :key="row.employee_id">
                                <tr>
                                    <td class="ak-muted" x-text="index + 1"></td>
                                    <td>
                                        <div class="ct-person">
                                            <span class="ct-avatar" x-text="initials(row.name)" aria-hidden="true"></span>
                                            <div><b x-text="row.name"></b></div>
                                        </div>
                                        <input type="hidden" :name="`lines[${index}][employee_id]`" :value="row.employee_id">
                                    </td>
                                    <td><input class="ct-cell" type="text" :name="`lines[${index}][target_label]`" x-model="row.target_label" maxlength="255" placeholder="Corporate" :aria-label="`Target for ${row.name}`"></td>
                                    <td>
                                        <div class="ct-money"><i>PKR</i><input class="ct-cell" type="number" :name="`lines[${index}][target_amount]`" x-model="row.target_amount" min="0" step="0.01" required :aria-label="`Total for ${row.name}`"></div>
                                        <div class="ct-words" x-text="Number(row.target_amount) > 0 ? format(row.target_amount) : ''"></div>
                                    </td>
                                    <td class="ct-num"><button type="button" class="ct-remove" @click="rows.splice(index, 1)" :aria-label="`Remove ${row.name}`" title="Remove row"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5h6v2m-8 0 1 12h8l1-12"/></svg></button></td>
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
                addId: '',
                bulk: '',
                loading: false,
                async init() {
                    if (this.supplierId) {
                        await this.load(false);
                    }
                },
                async reload() {
                    await this.load(true);
                },
                async changeSupplier() {
                    this.designation = '';
                    this.designations = [];
                    await this.load(true);
                },
                async load(replaceRows) {
                    if (!this.supplierId) {
                        this.rows = [];
                        this.allEmployees = [];
                        this.designations = [];
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
                    } finally {
                        this.loading = false;
                    }
                },
                availableEmployees() {
                    return this.allEmployees.filter((employee) => !this.rows.some((row) => row.employee_id === employee.id));
                },
                addRow() {
                    const employee = this.allEmployees.find((item) => String(item.id) === String(this.addId));
                    if (employee) {
                        this.rows.push({ employee_id: employee.id, name: employee.name, target_label: 'Corporate', target_amount: '0' });
                    }
                    this.addId = '';
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
