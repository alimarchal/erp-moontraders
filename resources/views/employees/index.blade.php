{{--
    Settings -> Employees list (/settings/employees). Same layout as the Users list:
    KPI cards that double as filters, Active / Inactive tabs, one search box,
    removable filter chips, sortable dense table with comfortable / compact
    density, bulk actions on selected rows, and a pager with rows-per-page.
    Routes and query parameters are unchanged (filter[...], sort, per_page).
    Styles are plain CSS (settings.partials.ui-style) -- no Tailwind rebuild needed.
--}}
@php
    $authUser = auth()->user();
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', 'employee_code');
    $tab = $filters['is_active'] ?? '';
    $chips = \Illuminate\Support\Arr::except($filters, 'is_active');

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $key) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, 'is_active');
        if ($key !== '') {
            $f['is_active'] = $key;
        }

        return $withFilters($f);
    };
    $onlyUrl = fn (array $f) => $withFilters($f);
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === $field ? '-'.$field : $field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');

    $supplierNames = $supplierOptions->pluck('supplier_name', 'id');
    $warehouseNames = $warehouseOptions->pluck('warehouse_name', 'id');
    $companyNames = $companyOptions->pluck('company_name', 'id');
    $costCenterNames = $costCenterOptions->pluck('name', 'id');
    $userNames = $userOptions->pluck('name', 'id');
    $chipLabels = [
        'search' => 'Search', 'supplier_id' => 'Supplier', 'warehouse_id' => 'Warehouse', 'company_id' => 'Company',
        'cost_center_id' => 'Cost center', 'user_id' => 'Linked user', 'has_login' => 'Linked to a user', 'no_supplier' => 'No supplier', 'no_warehouse' => 'No warehouse',
        'employee_code' => 'Code', 'name' => 'Name', 'company_name' => 'Principal', 'designation' => 'Designation',
        'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address', 'hire_date' => 'Hire date',
    ];
    $chipValue = fn (string $key, $value) => match ($key) {
        'supplier_id' => $supplierNames[$value] ?? $value,
        'warehouse_id' => $warehouseNames[$value] ?? $value,
        'company_id' => $companyNames[$value] ?? $value,
        'cost_center_id' => $costCenterNames[$value] ?? $value,
        'user_id' => $userNames[$value] ?? $value,
        'no_supplier', 'no_warehouse' => 'Active, none assigned',
        default => $value,
    };
    $advancedKeys = ['company_id', 'cost_center_id', 'user_id', 'has_login', 'designation', 'hire_date'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $sortNames = ['employee_code' => 'Code', 'name' => 'Name', 'designation' => 'Designation', 'hire_date' => 'Hire date', 'created_at' => 'Date added'];
    $sortField = ltrim($sort, '-');
    $canEdit = $authUser->can('employee-edit');
    $canBulk = $canEdit;
    $pageIds = $canBulk ? $employees->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->values() : [];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>Employees</span>
                </nav>
                <h1 class="ak-title">Employees</h1>
                <p class="ak-sub">Salesmen, drivers and staff, with their supplier, warehouse and linked login.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('settings.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('employees.export.excel', request()->query()) }}" class="ak-btn ak-btn-outline">Export to Excel</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @can('employee-create')
                    <a href="{{ route('employees.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add employee</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        /* Searchable dropdowns (Select2) sized like the other filter fields */
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
        .ak-filters .select2-container--focus .select2-selection--single, .ak-filters .select2-container--open .select2-selection--single { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
        .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
        .select2-search--dropdown .select2-search__field { border-radius: 6px; padding: 6px 8px; }
    </style>

    <div class="ak-page" x-data="employeeList()">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Employees</div>
            <table class="ak-print-meta">
                <tr><th>Filters</th><td>{{ $filters ? collect($filters)->map(fn ($v, $k) => ($chipLabels[$k] ?? \Illuminate\Support\Str::headline($k)).': '.$chipValue($k, $v))->implode(' · ') : 'None (all employees)' }}</td>
                    <th>Printed</th><td>{{ now()->format('d.m.Y H:i') }} by {{ $authUser->name }}</td></tr>
            </table>
        </div>

        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        {{-- KPI cards (also filters) --}}
        <section class="ak-kpis" aria-label="Employee summary">
            <a href="{{ $tabUrl('') }}" class="ak-kpi{{ $tab === '' && ! isset($filters['no_supplier']) && ! isset($filters['no_warehouse']) ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.13a9.38 9.38 0 0 0 2.63.37 9.34 9.34 0 0 0 4.12-.95 4.13 4.13 0 0 0-7.53-2.49M15 19.13v-.01a6.37 6.37 0 0 0-.97-3.4M15 19.13v.1A12.32 12.32 0 0 1 8.62 21a12.32 12.32 0 0 1-6.37-1.77v-.11a6.38 6.38 0 0 1 11.96-3.4M12 6.38a3.38 3.38 0 1 1-6.75 0 3.38 3.38 0 0 1 6.75 0Zm8.25 2.25a2.63 2.63 0 1 1-5.25 0 2.63 2.63 0 0 1 5.25 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Employees</span>
                    <span class="ak-kpi-value">{{ number_format($stats['total']) }}</span>
                    <span class="ak-kpi-hint">{{ $chips ? 'matching the filters' : 'all employees' }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('1') }}" class="ak-kpi{{ $tab === '1' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Active</span>
                    <span class="ak-kpi-value">{{ number_format($stats['active']) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($stats['inactive']) }} inactive</span>
                </span>
            </a>
            <a href="{{ $onlyUrl(['no_supplier' => 'Yes', 'is_active' => '1']) }}" class="ak-kpi{{ $stats['no_supplier'] ? ' ak-kpi-action' : '' }}{{ ($filters['no_supplier'] ?? '') === 'Yes' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.35m-16.5 11.65V9.35m0 0a3 3 0 0 0 3.75-.62 3 3 0 0 0 4.5 0 3 3 0 0 0 4.5 0 3 3 0 0 0 3.75.62m-16.5 0a3 3 0 0 1-.62-4.72L4.2 3.44A1.5 1.5 0 0 1 5.26 3h13.48a1.5 1.5 0 0 1 1.06.44l1.19 1.19a3 3 0 0 1-.62 4.72" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">No supplier yet</span>
                    <span class="ak-kpi-value">{{ number_format($stats['no_supplier']) }}</span>
                    <span class="ak-kpi-hint">{{ $stats['no_supplier'] ? 'Active, but no supplier assigned →' : 'Everyone has a supplier' }}</span>
                </span>
            </a>
            <a href="{{ $onlyUrl(['no_warehouse' => 'Yes', 'is_active' => '1']) }}" class="ak-kpi{{ $stats['no_warehouse'] ? ' ak-kpi-action' : '' }}{{ ($filters['no_warehouse'] ?? '') === 'Yes' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">No warehouse yet</span>
                    <span class="ak-kpi-value">{{ number_format($stats['no_warehouse']) }}</span>
                    <span class="ak-kpi-hint">{{ $stats['no_warehouse'] ? 'Active, but no warehouse assigned →' : 'Everyone has a warehouse' }}</span>
                </span>
            </a>
        </section>

        <section class="ak-card" aria-label="Employees">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $stats['total']], '1' => ['Active', $stats['active']], '0' => ['Inactive', $stats['inactive']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl((string) $key) }}" role="tab" aria-selected="{{ $tab === (string) $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === (string) $key ? 'is-active' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('employees.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[is_active]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="ak-filter-row ak-filter-row-users">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Code, name, phone or email" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_supplier">Supplier</label>
                        <select id="f_supplier" class="gl-select" name="filter[supplier_id]">
                            <option value="">All suppliers</option>
                            @foreach ($supplierOptions as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_warehouse">Warehouse</label>
                        <select id="f_warehouse" class="gl-select" name="filter[warehouse_id]">
                            <option value="">All warehouses</option>
                            @foreach ($warehouseOptions as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows per page</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\EmployeeController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="ak-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>

                <div id="ak-advanced" class="ak-filter-advanced ak-filter-advanced-users" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_company">Company</label>
                        <select id="f_company" class="gl-select" name="filter[company_id]">
                            <option value="">All companies</option>
                            @foreach ($companyOptions as $company)
                                <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_cost_center">Cost center</label>
                        <select id="f_cost_center" class="gl-select" name="filter[cost_center_id]">
                            <option value="">All cost centers</option>
                            @foreach ($costCenterOptions as $costCenter)
                                <option value="{{ $costCenter->id }}" @selected((string) ($filters['cost_center_id'] ?? '') === (string) $costCenter->id)>{{ $costCenter->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_user">Linked user</label>
                        <select id="f_user" class="gl-select" name="filter[user_id]">
                            <option value="">All users</option>
                            @foreach ($userOptions as $user)
                                <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_has_login">Linked to a user</label>
                        <select id="f_has_login" name="filter[has_login]">
                            <option value="">Any</option>
                            <option value="Yes" @selected(($filters['has_login'] ?? '') === 'Yes')>Yes</option>
                            <option value="No" @selected(($filters['has_login'] ?? '') === 'No')>No</option>
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_designation">Designation</label>
                        <input id="f_designation" type="text" name="filter[designation]" value="{{ $filters['designation'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_hire_date">Hire date</label>
                        <input id="f_hire_date" type="date" name="filter[hire_date]" value="{{ $filters['hire_date'] ?? '' }}">
                    </div>
                </div>
            </form>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters($tab !== '' ? ['is_active' => $tab] : []) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($employees->count() > 0)
                {{-- Bulk actions: checkboxes point at this form with form="bulk-form" (no nested forms). --}}
                @if ($canBulk)
                    <form id="bulk-form" method="POST" action="{{ route('employees.bulk-update') }}" class="ak-bulk" x-show="selected.length" x-cloak @submit.prevent="askBulk($el)">
                        @csrf
                        <b x-text="selected.length + ' selected'"></b>
                        <label for="bulk_action" class="sr-only">Bulk action</label>
                        <select id="bulk_action" name="action" x-model="bulkAction" required>
                            <option value="">Choose action…</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                        </select>
                        <button type="submit" class="ak-btn ak-btn-primary ak-btn-sm" :disabled="!bulkAction">Apply</button>
                        <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="clearSelection()">Clear selection</button>
                    </form>
                @endif

                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('employees-density') === 'compact'; } catch (e) { return false; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('employees-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($employees->total()) }}</b> {{ \Illuminate\Support\Str::plural('employee', $employees->total()) }}
                            &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Code' }}</b> ({{ str_starts_with($sort, '-') ? 'newest / Z–A first' : 'oldest / A–Z first' }})
                        </p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Employees table">
                        <table class="ak-dt">
                            <caption class="sr-only">Employees, {{ $employees->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c ak-sticky-1">
                                        @if ($canBulk)
                                            <input type="checkbox" aria-label="Select all employees on this page" :checked="allSelected()" @change="toggleAll($event.target.checked)">
                                        @else
                                            #
                                        @endif
                                    </th>
                                    <th scope="col" class="ak-sticky-2" aria-sort="{{ $ariaSort('name') }}"><a href="{{ $sortUrl('name') }}">Employee <span aria-hidden="true">{{ $sortMark('name') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('employee_code') }}"><a href="{{ $sortUrl('employee_code') }}">Code <span aria-hidden="true">{{ $sortMark('employee_code') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('designation') }}"><a href="{{ $sortUrl('designation') }}">Designation <span aria-hidden="true">{{ $sortMark('designation') }}</span></a></th>
                                    <th scope="col">Supplier</th>
                                    <th scope="col">Warehouse</th>
                                    <th scope="col" class="ak-num" title="Sales settlements made by this employee">Settlements</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-c ak-sticky-end"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employees as $employee)
                                    @php
                                    @endphp
                                    <tr>
                                        <td class="ak-c ak-sticky-1" data-label="">
                                            @if ($canBulk)
                                                <input type="checkbox" name="ids[]" value="{{ $employee->id }}" form="bulk-form" x-model="selected" aria-label="Select {{ $employee->name }}">
                                            @else
                                                <span class="ak-muted">{{ $employees->firstItem() + $loop->index }}</span>
                                            @endif
                                        </td>
                                        <td data-label="Employee" class="ak-sticky-2">
                                            @can('employee-list')
                                                <a href="{{ route('employees.show', $employee) }}" class="ak-primary-link">{{ $employee->name }}</a>
                                            @else
                                                <span class="ak-strong">{{ $employee->name }}</span>
                                            @endcan
                                            <div class="ak-muted">{{ $employee->phone ?: '—' }}</div>
                                        </td>
                                        <td class="ak-mono" data-label="Code">{{ $employee->employee_code }}</td>
                                        <td data-label="Designation">{{ $employee->designation ?: '—' }}</td>
                                        <td data-label="Supplier">
                                            @if ($employee->supplier)
                                                {{ $employee->supplier->supplier_name }}
                                            @else
                                                <span class="ak-muted">Not assigned</span>
                                            @endif
                                        </td>
                                        <td data-label="Warehouse">
                                            @if ($employee->warehouse)
                                                {{ $employee->warehouse->warehouse_name }}
                                            @else
                                                <span class="ak-muted">Not assigned</span>
                                            @endif
                                        </td>
                                        <td class="ak-num" data-label="Settlements">
                                            {{ number_format($employee->sales_settlements_count) }}
                                            <div class="ak-muted ak-hide-compact">{{ $employee->sales_settlements_max_settlement_date ? 'last '.\Illuminate\Support\Carbon::parse($employee->sales_settlements_max_settlement_date)->format('d M Y') : 'none yet' }}</div>
                                        </td>
                                        <td data-label="Status">
                                            @if ($employee->is_active)
                                                <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                                            @else
                                                <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Inactive</span>
                                            @endif
                                        </td>
                                        <td class="ak-sticky-end" data-label="">
                                            <div class="ak-actions">
                                                @can('employee-list')
                                                    <a href="{{ route('employees.show', $employee) }}" class="ak-icon ak-icon-view" title="View employee" aria-label="View {{ $employee->name }}">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    </a>
                                                @else
                                                    <span class="ak-slot"></span>
                                                @endcan
                                                <span class="ak-slot">
                                                    @can('employee-edit')
                                                        <a href="{{ route('employees.edit', $employee) }}" class="ak-icon" title="Edit employee" aria-label="Edit {{ $employee->name }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                        </a>
                                                    @endcan
                                                </span>
                                                <span class="ak-slot">
                                                    @can('employee-edit')
                                                        <form method="POST" action="{{ route('employees.bulk-update') }}" @submit.prevent="askToggle($el, @js($employee->name), {{ $employee->is_active ? 'true' : 'false' }})">
                                                            @csrf
                                                            <input type="hidden" name="ids[]" value="{{ $employee->id }}">
                                                            <input type="hidden" name="action" value="{{ $employee->is_active ? 'deactivate' : 'activate' }}">
                                                            <button type="submit" class="ak-icon {{ $employee->is_active ? 'ak-icon-danger' : '' }}" title="{{ $employee->is_active ? 'Deactivate employee' : 'Activate employee' }}" aria-label="{{ $employee->is_active ? 'Deactivate' : 'Activate' }} {{ $employee->name }}">
                                                                @if ($employee->is_active)
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.36 6.64a9 9 0 1 1-12.73 0M12 3v9" /></svg>
                                                                @else
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                                                @endif
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($employees->firstItem()) }}–{{ number_format($employees->lastItem()) }}</b> of <b>{{ number_format($employees->total()) }}</b> employees</p>
                    <div class="ak-pager-right">
                        <form method="GET" action="{{ route('employees.index') }}" class="ak-perpage">
                            @foreach (request()->except(['per_page', 'page']) as $key => $value)
                                @if (is_array($value))
                                    @foreach ($value as $k => $v) <input type="hidden" name="{{ $key }}[{{ $k }}]" value="{{ $v }}"> @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <label for="per_page">Rows per page</label>
                            <select id="per_page" name="per_page" onchange="this.form.submit()">
                                @foreach (\App\Http\Controllers\EmployeeController::PER_PAGE as $n)
                                    <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                                @endforeach
                            </select>
                        </form>
                        <div>{{ $employees->onEachSide(1)->links() }}</div>
                    </div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    @if ($filters)
                        <h2>No employees match these filters</h2>
                        <p>Remove a filter or search with part of a name, code or phone.</p>
                        <a href="{{ route('employees.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>
                    @else
                        <h2>No employees yet</h2>
                        <p>Add the first employee with “Add employee”.</p>
                        @can('employee-create') <a href="{{ route('employees.create') }}" class="ak-btn ak-btn-primary">＋ Add employee</a> @endcan
                    @endif
                </div>
            @endif
        </section>

        {{-- Confirm modal (same pattern as the Users list) --}}
        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="el-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon" :class="confirm.danger ? 'ak-pill-red' : 'ak-tone-amber'" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="el-confirm-title" x-text="confirm.title"></h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155" x-text="confirm.message"></p>
                    </div>
                </div>
                <div class="uf-modal-foot">
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="button" class="ak-btn" :class="confirm.danger ? 'ak-btn-danger-outline' : 'ak-btn-primary'" @click="runConfirm()" :disabled="confirm.busy" x-text="confirm.busy ? 'Working…' : confirm.button"></button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Searchable dropdowns: type to find a supplier, warehouse, company, cost center or user.
            $(function () {
                $('.gl-select').each(function () {
                    const $select = $(this);
                    $select.select2({
                        width: '100%',
                        placeholder: $select.find('option[value=""]').text() || 'Select',
                        allowClear: $select.find('option[value=""]').length > 0,
                    });
                });
            });

            function employeeList() {
                return {
                    selected: [],
                    pageIds: {{ \Illuminate\Support\Js::from($pageIds) }},
                    bulkAction: '',
                    confirm: { open: false, title: '', message: '', button: '', danger: false, busy: false, form: null },
                    allSelected() { return this.pageIds.length > 0 && this.pageIds.every((id) => this.selected.includes(id)); },
                    toggleAll(on) { this.selected = on ? [...this.pageIds] : []; },
                    clearSelection() { this.selected = []; this.bulkAction = ''; },
                    ask(form, title, message, button, danger) {
                        this.confirm = { open: true, title, message, button, danger, busy: false, form };
                    },
                    askToggle(form, name, isActive) {
                        this.ask(form,
                            (isActive ? 'Deactivate ' : 'Activate ') + name + '?',
                            isActive ? 'Inactive employees are hidden from the pickers on new documents. Their history is kept and you can activate them again.' : 'The employee will be available in the pickers again.',
                            isActive ? 'Deactivate' : 'Activate',
                            isActive);
                    },
                    askBulk(form) {
                        if (!this.bulkAction || !this.selected.length) return;
                        const words = { activate: 'Activate', deactivate: 'Deactivate' };
                        const count = this.selected.length + (this.selected.length === 1 ? ' employee' : ' employees');
                        const message = this.bulkAction === 'deactivate' ? 'Inactive employees are hidden from the pickers until activated again.' : 'Selected employees will be active again.';
                        this.ask(form, words[this.bulkAction] + ' ' + count + '?', message, words[this.bulkAction] + ' ' + count, this.bulkAction === 'deactivate');
                    },
                    runConfirm() {
                        if (this.confirm.busy || !this.confirm.form) return;
                        this.confirm.busy = true;
                        this.confirm.form.submit();
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
