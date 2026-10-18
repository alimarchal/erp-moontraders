{{--
    Settings -> Company Targets -> Add / Edit (/settings/company-targets/create).
    Picking the company and designation fills the salesman rows below before anything is saved;
    rows can be removed, more salesmen added, and the Target text and Total changed per row.
--}}
@php
    $isCreate = ! $target->exists;
    $initialRows = $rows;
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
                <a href="{{ route('company-targets.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Company Targets</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page" x-data="companyTargetForm({
            supplierId: @js($selectedSupplier ? (string) $selectedSupplier : ''),
            designation: @js(old('designation', $designation)),
            rows: @js($initialRows),
            url: @js(route('company-targets.employees')),
            isCreate: @js($isCreate),
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

            <section class="ak-card" style="padding:18px;">
                <div class="ak-filter-row">
                    <div class="ak-field">
                        <label for="supplier_id">Company</label>
                        <select id="supplier_id" name="supplier_id" x-model="supplierId" @change="reload()" @disabled($lockedSupplier) required>
                            @unless ($lockedSupplier)
                                <option value="">Select company</option>
                            @endunless
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                        @if ($lockedSupplier)
                            <input type="hidden" name="supplier_id" value="{{ $selectedSupplier }}">
                        @endif
                    </div>
                    <div class="ak-field">
                        <label for="period_start">Start date</label>
                        <input id="period_start" type="date" name="period_start" required
                            value="{{ old('period_start', $target->period_start?->toDateString()) }}">
                    </div>
                    <div class="ak-field">
                        <label for="period_end">End date</label>
                        <input id="period_end" type="date" name="period_end" required
                            value="{{ old('period_end', $target->period_end?->toDateString()) }}">
                    </div>
                    <div class="ak-field">
                        <label for="designation">Designation</label>
                        <select id="designation" name="designation" x-model="designation" @change="reload()">
                            <template x-for="option in designations" :key="option">
                                <option :value="option" x-text="option" :selected="option === designation"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div class="ak-field" style="margin-top:12px;">
                    <label for="notes">Notes</label>
                    <input id="notes" type="text" name="notes" maxlength="2000" value="{{ old('notes', $target->notes) }}" placeholder="Optional">
                </div>
            </section>

            <section class="ak-card" style="padding:0;margin-top:14px;">
                <div class="ak-dt-toolbar">
                    <p><b x-text="rows.length"></b> salesman row(s) &middot; Total target <b x-text="grandTotal()"></b></p>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <select x-model="addId" aria-label="Add salesman" style="height:34px;border:1px solid #d1d5db;border-radius:8px;padding:0 8px;">
                            <option value="">Add salesman…</option>
                            <template x-for="employee in availableEmployees()" :key="employee.id">
                                <option :value="employee.id" x-text="employee.name + (employee.code ? ' (' + employee.code + ')' : '')"></option>
                            </template>
                        </select>
                        <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" @click="addRow()" :disabled="!addId">Add row</button>
                    </div>
                </div>
                <div class="ak-dt-scroll">
                    <table class="ak-dt" style="min-width:720px;">
                        <thead>
                            <tr><th style="width:50px;">#</th><th>Salesman</th><th>Target</th><th style="width:200px;">Total</th><th style="width:70px;"></th></tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in rows" :key="row.employee_id">
                                <tr>
                                    <td x-text="index + 1"></td>
                                    <td class="ak-strong">
                                        <span x-text="row.name"></span>
                                        <input type="hidden" :name="`lines[${index}][employee_id]`" :value="row.employee_id">
                                    </td>
                                    <td class="ak-field"><input type="text" :name="`lines[${index}][target_label]`" x-model="row.target_label" maxlength="255" placeholder="Corporate"></td>
                                    <td class="ak-field"><input type="number" :name="`lines[${index}][target_amount]`" x-model="row.target_amount" min="0" step="0.01" required></td>
                                    <td><button type="button" class="ak-btn ak-btn-danger-outline ak-btn-sm" @click="rows.splice(index, 1)" aria-label="Remove row">Remove</button></td>
                                </tr>
                            </template>
                            <tr x-show="rows.length === 0"><td colspan="5" class="ak-empty">No salesman rows yet. Pick a company, or add a salesman above.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="ak-actions" style="margin-top:14px;display:flex;gap:10px;">
                <button type="submit" class="ak-btn ak-btn-primary">{{ $isCreate ? 'Save target' : 'Update target' }}</button>
                <a href="{{ route('company-targets.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        function companyTargetForm(config) {
            return {
                supplierId: config.supplierId,
                designation: config.designation,
                rows: config.rows,
                designations: [config.designation],
                allEmployees: [],
                addId: '',
                async init() {
                    if (this.supplierId) {
                        await this.load(false);
                    }
                },
                async reload() {
                    await this.load(true);
                },
                async load(replaceRows) {
                    if (!this.supplierId) {
                        this.rows = [];
                        this.allEmployees = [];
                        return;
                    }
                    const params = new URLSearchParams({ supplier_id: this.supplierId, designation: this.designation });
                    const response = await fetch(`${config.url}?${params}`, { headers: { Accept: 'application/json' } });
                    if (!response.ok) {
                        return;
                    }
                    const data = await response.json();
                    this.designations = data.designations.length ? data.designations : [this.designation];
                    if (!this.designations.includes(this.designation)) {
                        this.designations.unshift(this.designation);
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
                grandTotal() {
                    return this.rows.reduce((sum, row) => sum + (parseFloat(row.target_amount) || 0), 0).toLocaleString();
                },
            };
        }
    </script>
</x-app-layout>
