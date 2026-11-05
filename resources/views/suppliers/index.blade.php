{{--
    Suppliers list (/settings/suppliers). Same layout as Expense Vouchers: status tabs, search with
    filters, removable chips, a sortable table with comfortable / compact density and a pager.
--}}
@php
    $filters = $filters ?? [];
    $tab = $filters['status'] ?? '';
    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $status) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, ['status']);
        if ($status !== '') {
            $f['status'] = $status;
        }

        return $withFilters($f);
    };
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === $field ? '-'.$field : $field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');
    $chipLabels = ['search' => 'Search', 'supplier_group' => 'Group', 'qty_entry_mode' => 'Quantity entry', 'is_transporter' => 'Transporter', 'is_internal_supplier' => 'Internal'];
    $chipValue = fn (string $key, $value) => match (true) {
        $key === 'qty_entry_mode' => $value === 'carton' ? 'Carton' : 'Pieces',
        in_array($key, ['is_transporter', 'is_internal_supplier'], true) => $value === '1' ? 'Yes' : 'No',
        default => $value,
    };
    $chips = \Illuminate\Support\Arr::except($filters, ['status']);
    $activeFilterCount = count($chips);
    $statusTotal = $statusCounts['active'] + $statusCounts['disabled'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>Suppliers</span>
                </nav>
                <h1 class="ak-title">Suppliers <span class="ev-ur" lang="ur" dir="rtl">سپلائرز</span></h1>
                <p class="ak-sub">Companies you buy stock from, their defaults and how GRN quantities are entered.</p>
            </div>
            <div class="ak-head-actions">
                <button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('ev-toggle-filters')" aria-controls="ev-filter-panel" title="Show or hide the filters">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                    Filters @if($activeFilterCount)<span class="ak-count ak-count-dark">{{ $activeFilterCount }}</span>@endif
                </button>
                <a href="{{ route('suppliers.help') }}" class="ak-btn ak-btn-outline" title="How suppliers and carton entry work">How it works <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:16px">طریقہ کار</span></a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print <span class="ev-ur" lang="ur" dir="rtl">پرنٹ</span></button>
                @can('supplier-create')
                    <a href="{{ route('suppliers.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> New supplier <span class="ev-ur" lang="ur" dir="rtl">نیا سپلائر</span></a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">
    <style>
        @page { margin: 10mm; }
        #ev-filter-panel { overflow: hidden; }
        .ev-ur { font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Geeza Pro', serif; font-weight: 600; color: var(--ak-navy); margin-inline-start: 8px; line-height: 1.5; font-size: 1.1em; }
        .ak-btn .ev-ur, .ak-tab .ev-ur, .ak-chip .ev-ur, .ak-status .ev-ur, .ak-seg .ev-ur { color: inherit; margin-inline-start: 6px; }
        .ak-btn-primary .ev-ur { color: #fff; }
        .ak-title .ev-ur { font-size: 22px; margin-inline-start: 14px; }
        .ak-tab .ev-ur { font-size: 16px; }
        .ak-seg .ev-ur { font-size: 14px; }
        .ak-dt th .ev-ur { font-size: 14px; text-transform: none; letter-spacing: 0; }
        .ak-dt th a .ev-ur { color: inherit; }
        .sp-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 900px) { .sp-filter-row { grid-template-columns: minmax(220px, 2fr) 1.2fr 1fr 1fr 1fr 90px auto; } }
        .sp-filter-row .ak-field input, .sp-filter-row .ak-field select { height: 38px; box-sizing: border-box; }
        .ak-filter-buttons { display: flex; gap: 8px; }
        .sp-table { min-width: 980px; }
        .sp-pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; margin: 1px 4px 1px 0; background: #f1f5f9; color: #475569; }
        .sp-pill-blue { background: #dbeafe; color: #1d4ed8; } .sp-pill-purple { background: #ede9fe; color: #6d28d9; } .sp-pill-amber { background: #fef3c7; color: #b45309; }
        .ak-status-slate { background: #f1f5f9; color: #475569; } .ak-status-slate i { background: #64748b; }
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
        @media print { .ak-filters, .ak-tabs, .ak-pager { display: none !important; } .sp-table { min-width: 0 !important; } }
    </style>

    <div class="ak-page" x-data="supplierList()">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Suppliers</div>
        </div>

        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section id="ev-filter-panel" class="ak-card" aria-label="Filters" x-data="{ open: {{ $activeFilterCount ? 'true' : 'false' }} }" @ev-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $activeFilterCount ? '' : 'display:none' }}">
            <form method="GET" action="{{ route('suppliers.index') }}" class="ak-filters" x-data="{ busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[status]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                <div class="sp-filter-row">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off" placeholder="Name, short name, group, type or country" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_group">Group</label>
                        <select id="f_group" class="sp-select" name="filter[supplier_group]">
                            <option value="">All groups</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group }}" @selected(($filters['supplier_group'] ?? '') === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_mode">Quantity entry</label>
                        <select id="f_mode" name="filter[qty_entry_mode]">
                            <option value="">Any</option>
                            <option value="pieces" @selected(($filters['qty_entry_mode'] ?? '') === 'pieces')>Pieces</option>
                            <option value="carton" @selected(($filters['qty_entry_mode'] ?? '') === 'carton')>Carton</option>
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_transporter">Transporter</label>
                        <select id="f_transporter" name="filter[is_transporter]">
                            <option value="">Any</option>
                            <option value="1" @selected(($filters['is_transporter'] ?? '') === '1')>Yes</option>
                            <option value="0" @selected(($filters['is_transporter'] ?? '') === '0')>No</option>
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_internal">Internal</label>
                        <select id="f_internal" name="filter[is_internal_supplier]">
                            <option value="">Any</option>
                            <option value="1" @selected(($filters['is_internal_supplier'] ?? '') === '1')>Yes</option>
                            <option value="0" @selected(($filters['is_internal_supplier'] ?? '') === '0')>No</option>
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\SupplierController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <a href="{{ route('suppliers.index') }}" class="ak-btn ak-btn-ghost" title="Remove every filter">&times; Clear</a>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy"><span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span></button>
                    </div>
                </div>
            </form>
        </section>

        <section class="ak-card" aria-label="Suppliers">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $statusTotal, 'تمام'], 'active' => ['Active', $statusCounts['active'], 'فعال'], 'disabled' => ['Disabled', $statusCounts['disabled'], 'غیر فعال']] as $key => [$label, $count, $urdu])
                    <a href="{{ $tabUrl($key) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === $key ? 'is-active' : '' }}">
                        {{ $label }} <span class="ev-ur" lang="ur" dir="rtl">{{ $urdu }}</span> <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['status'])) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($suppliers->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('sp-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('sp-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p><b>{{ number_format($suppliers->total()) }}</b> {{ \Illuminate\Support\Str::plural('supplier', $suppliers->total()) }}</p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable <span class="ev-ur" lang="ur" dir="rtl">آرام دہ</span></button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact <span class="ev-ur" lang="ur" dir="rtl">مختصر</span></button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Suppliers table">
                        <table class="ak-dt sp-table">
                            <caption class="sr-only">Suppliers, {{ $suppliers->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c" style="width:48px">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('supplier_name') }}"><a href="{{ $sortUrl('supplier_name') }}">Supplier <span class="ev-ur" lang="ur" dir="rtl">سپلائر</span> <span aria-hidden="true">{{ $sortMark('supplier_name') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('supplier_group') }}"><a href="{{ $sortUrl('supplier_group') }}">Group · type <span class="ev-ur" lang="ur" dir="rtl">گروپ / قسم</span> <span aria-hidden="true">{{ $sortMark('supplier_group') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('country') }}"><a href="{{ $sortUrl('country') }}">Country <span class="ev-ur" lang="ur" dir="rtl">ملک</span> <span aria-hidden="true">{{ $sortMark('country') }}</span></a></th>
                                    <th scope="col">Defaults <span class="ev-ur" lang="ur" dir="rtl">ڈیفالٹس</span></th>
                                    <th scope="col">GRN entry <span class="ev-ur" lang="ur" dir="rtl">اندراج</span></th>
                                    <th scope="col" class="ak-num">Products <span class="ev-ur" lang="ur" dir="rtl">مصنوعات</span></th>
                                    <th scope="col">Status <span class="ev-ur" lang="ur" dir="rtl">اسٹیٹس</span></th>
                                    <th scope="col" class="ak-c ak-sticky-end"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($suppliers as $supplier)
                                    <tr>
                                        <td class="ak-c ak-muted">{{ $suppliers->firstItem() + $loop->index }}</td>
                                        <td data-label="Supplier">
                                            <a href="{{ route('suppliers.show', $supplier) }}" class="ak-primary-link">{{ $supplier->supplier_name }}</a>
                                            <div class="ak-muted ak-hide-compact">{{ $supplier->short_name ?: '—' }}</div>
                                        </td>
                                        <td data-label="Group · type">{{ $supplier->supplier_group ?: '—' }}<div class="ak-muted">{{ $supplier->supplier_type ?: '—' }}</div></td>
                                        <td data-label="Country">{{ $supplier->country ?: '—' }}</td>
                                        <td data-label="Defaults">
                                            {{ optional($supplier->defaultCurrency)->currency_code ?: '—' }}
                                            <div class="ak-muted">{{ optional($supplier->defaultBankAccount)->account_code ? $supplier->defaultBankAccount->account_code.' · '.$supplier->defaultBankAccount->account_name : 'No bank account' }}</div>
                                        </td>
                                        <td data-label="GRN entry">
                                            <span class="sp-pill {{ $supplier->usesCartonEntry() ? 'sp-pill-amber' : '' }}">{{ $supplier->usesCartonEntry() ? 'Carton' : 'Pieces' }}</span>
                                            @if ($supplier->is_transporter)<span class="sp-pill sp-pill-blue">Transporter</span>@endif
                                            @if ($supplier->is_internal_supplier)<span class="sp-pill sp-pill-purple">Internal</span>@endif
                                        </td>
                                        <td class="ak-num" data-label="Products">{{ number_format($supplier->products_count) }}</td>
                                        <td data-label="Status">
                                            <span class="ak-status {{ $supplier->disabled ? 'ak-status-slate' : 'ak-status-green' }}"><i aria-hidden="true"></i>{{ $supplier->disabled ? 'Disabled' : 'Active' }}</span>
                                        </td>
                                        <td class="ak-sticky-end" data-label="">
                                            <div class="ak-actions">
                                                <a href="{{ route('suppliers.show', $supplier) }}" class="ak-icon ak-icon-view" title="View" aria-label="View {{ $supplier->supplier_name }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                </a>
                                                <span class="ak-slot">
                                                    @can('supplier-edit')
                                                        <a href="{{ route('suppliers.edit', $supplier) }}" class="ak-icon" title="Edit" aria-label="Edit {{ $supplier->supplier_name }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                        </a>
                                                    @endcan
                                                </span>
                                                <span class="ak-slot">
                                                    @can('supplier-edit')
                                                        <form method="POST" action="{{ route('suppliers.toggle-active', $supplier) }}" @submit.prevent="askToggle($el, @js($supplier->supplier_name), {{ $supplier->disabled ? 'false' : 'true' }})">
                                                            @csrf
                                                            @if ($supplier->disabled)
                                                                <button type="submit" class="ak-icon" style="color:#15803d" title="Activate supplier" aria-label="Activate {{ $supplier->supplier_name }}">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                                                </button>
                                                            @else
                                                                <button type="submit" class="ak-icon ak-icon-danger" title="Deactivate supplier" aria-label="Deactivate {{ $supplier->supplier_name }}">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.36 6.64A9 9 0 1 1 5.64 6.64M12 3v9" /></svg>
                                                                </button>
                                                            @endif
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
                    <p>Showing <b>{{ number_format($suppliers->firstItem()) }}–{{ number_format($suppliers->lastItem()) }}</b> of <b>{{ number_format($suppliers->total()) }}</b> suppliers</p>
                    <div class="ak-pager-right"><div>{{ $suppliers->onEachSide(1)->links() }}</div></div>
                </div>
            @else
                <div class="ak-empty">
                    <h2>No suppliers found{{ $chips || $tab ? ' with these filters' : '' }} <span class="ev-ur" lang="ur" dir="rtl">کوئی سپلائر نہیں ملا</span></h2>
                    <p>Remove a filter, or add a new supplier.</p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        <a href="{{ route('suppliers.index') }}" class="ak-btn ak-btn-outline">Clear filters</a>
                        @can('supplier-create') <a href="{{ route('suppliers.create') }}" class="ak-btn ak-btn-primary">＋ New supplier <span class="ev-ur" lang="ur" dir="rtl">نیا سپلائر</span></a> @endcan
                    </div>
                </div>
            @endif
        </section>

        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="sp-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon ak-pill-red" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="sp-confirm-title" x-text="confirm.title"></h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155" x-text="confirm.active ? 'The supplier will be marked Disabled and hidden from new GRNs and dropdowns. It can be activated again from this list. Nothing is deleted.' : 'The supplier will be marked Active again.'"></p>
                    </div>
                </div>
                <div class="uf-modal-foot">
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="button" class="ak-btn ak-btn-danger-outline" @click="runConfirm()" :disabled="confirm.busy" x-text="confirm.busy ? 'Working…' : (confirm.active ? 'Deactivate supplier' : 'Activate supplier')"></button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function supplierList() {
                return {
                    confirm: { open: false, title: '', busy: false, form: null, active: true },
                    askToggle(form, name, isActive) { this.confirm = { open: true, title: (isActive ? 'Deactivate ' : 'Activate ') + name + '?', busy: false, form, active: isActive }; },
                    runConfirm() {
                        if (this.confirm.busy || !this.confirm.form) return;
                        this.confirm.busy = true;
                        this.confirm.form.submit();
                    },
                };
            }
            $(function () {
                $('.sp-select').each(function () {
                    $(this).select2({ width: '100%', placeholder: $(this).find('option[value=""]').text() || 'Select', allowClear: true });
                });
            });
        </script>
    @endpush
</x-app-layout>
