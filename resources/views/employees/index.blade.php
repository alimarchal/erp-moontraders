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
        'cost_center_id' => 'Cost center', 'user_id' => 'Linked user', 'has_login' => 'Linked to a user', 'no_warehouse' => 'No warehouse',
        'employee_code' => 'Code', 'name' => 'Name', 'company_name' => 'Principal', 'designation' => 'Designation',
        'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address', 'hire_date' => 'Hire date',
    ];
    $chipValue = fn (string $key, $value) => match ($key) {
        'supplier_id' => $supplierNames[$value] ?? $value,
        'warehouse_id' => $warehouseNames[$value] ?? $value,
        'company_id' => $companyNames[$value] ?? $value,
        'cost_center_id' => $costCenterNames[$value] ?? $value,
        'user_id' => $userNames[$value] ?? $value,
        'no_warehouse' => 'Active, none assigned',
        default => $value,
    };
    $advancedKeys = ['company_id', 'cost_center_id', 'user_id', 'has_login', 'no_warehouse', 'designation', 'hire_date'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $sortNames = ['employee_code' => 'Code', 'name' => 'Name', 'designation' => 'Designation', 'hire_date' => 'Hire date', 'created_at' => 'Date added'];
    $sortField = ltrim($sort, '-');
    $canEdit = $authUser->can('employee-edit');
    $canDelete = $authUser->can('employee-delete');
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
    <style>@page { size: A4 landscape; margin: 10mm; }</style>

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
            <a href="{{ $tabUrl('') }}" class="ak-kpi{{ $tab === '' && ! isset($filters['has_login']) && ! isset($filters['no_warehouse']) ? ' is-active' : '' }}">
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
            <a href="{{ $onlyUrl(['has_login' => 'Yes']) }}" class="ak-kpi{{ ($filters['has_login'] ?? '') === 'Yes' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0A17.93 17.93 0 0 1 12 21.75c-2.68 0-5.22-.58-7.5-1.63Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Linked to a user</span>
                    <span class="ak-kpi-value">{{ number_format($stats['with_login']) }}</span>
                    <span class="ak-kpi-hint">can sign in to the system</span>
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
                        <select id="f_supplier" name="filter[supplier_id]">
                            <option value="">All suppliers</option>
                            @foreach ($supplierOptions as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_warehouse">Warehouse</label>
                        <select id="f_warehouse" name="filter[warehouse_id]">
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
                        <select id="f_company" name="filter[company_id]">
                            <option value="">All companies</option>
                            @foreach ($companyOptions as $company)
                                <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_cost_center">Cost center</label>
                        <select id="f_cost_center" name="filter[cost_center_id]">
                            <option value="">All cost centers</option>
                            @foreach ($costCenterOptions as $costCenter)
                                <option value="{{ $costCenter->id }}" @selected((string) ($filters['cost_center_id'] ?? '') === (string) $costCenter->id)>{{ $costCenter->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_user">Linked user</label>
                        <select id="f_user" name="filter[user_id]">
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
                            @if ($canDelete)<option value="delete">Delete</option>@endif
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
                                    <th scope="col" aria-sort="{{ $ariaSort('designation') }}"><a href="{{ $sortUrl('designation') }}">Designation / Supplier <span aria-hidden="true">{{ $sortMark('designation') }}</span></a></th>
                                    <th scope="col">Warehouse</th>
                                    <th scope="col">Linked user</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('hire_date') }}"><a href="{{ $sortUrl('hire_date') }}">Hired <span aria-hidden="true">{{ $sortMark('hire_date') }}</span></a></th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-c ak-sticky-end"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employees as $employee)
                                    @php
                                        $initials = collect(explode(' ', trim((string) $employee->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
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
                                            <div class="ak-person">
                                                <span class="ak-avatar ak-hide-compact" aria-hidden="true">{{ $initials ?: '?' }}</span>
                                                <div style="min-width:0">
                                                    @can('employee-list')
                                                        <a href="{{ route('employees.show', $employee) }}" class="ak-primary-link">{{ $employee->name }}</a>
                                                    @else
                                                        <span class="ak-strong">{{ $employee->name }}</span>
                                                    @endcan
                                                    <div class="ak-muted" style="word-break:break-all">{{ $employee->phone ?: ($employee->email ?: '—') }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="ak-mono" data-label="Code">{{ $employee->employee_code }}</td>
                                        <td data-label="Designation / Supplier">
                                            <div>{{ $employee->designation ?: '—' }}</div>
                                            <div class="ak-muted">{{ $employee->supplier?->supplier_name ?? ($employee->company_name ?: 'No supplier') }}</div>
                                        </td>
                                        <td data-label="Warehouse">
                                            @if ($employee->warehouse)
                                                {{ $employee->warehouse->warehouse_name }}
                                            @else
                                                <span class="ak-muted">Not assigned</span>
                                            @endif
                                        </td>
                                        <td data-label="Linked user">
                                            @if ($employee->user)
                                                {{ $employee->user->name }}
                                            @else
                                                <span class="ak-muted">None</span>
                                            @endif
                                        </td>
                                        <td class="ak-mono" data-label="Hired">
                                            {{ $employee->hire_date?->format('d M Y') ?? '—' }}
                                            @if ($employee->hire_date)
                                                <div class="ak-muted ak-hide-compact">{{ $employee->hire_date->diffForHumans() }}</div>
                                            @endif
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
                                                    @can('employee-delete')
                                                        <form method="POST" action="{{ route('employees.destroy', $employee) }}" @submit.prevent="askDelete($el, @js($employee->name))">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="ak-icon ak-icon-danger" title="Delete employee" aria-label="Delete {{ $employee->name }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
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
                    askDelete(form, name) {
                        this.ask(form, 'Delete ' + name + '?', 'The employee will be removed from the lists. This cannot be undone.', 'Delete employee', true);
                    },
                    askBulk(form) {
                        if (!this.bulkAction || !this.selected.length) return;
                        const words = { activate: 'Activate', deactivate: 'Deactivate', delete: 'Delete' };
                        const count = this.selected.length + (this.selected.length === 1 ? ' employee' : ' employees');
                        const message = this.bulkAction === 'delete'
                            ? 'Selected employees will be deleted. This cannot be undone.'
                            : (this.bulkAction === 'deactivate' ? 'Inactive employees are hidden from the pickers until activated again.' : 'Selected employees will be active again.');
                        this.ask(form, words[this.bulkAction] + ' ' + count + '?', message, words[this.bulkAction] + ' ' + count, this.bulkAction === 'delete');
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
