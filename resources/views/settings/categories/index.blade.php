{{--
    Settings -> Categories list. Same layout as Settings -> Users: summary cards, tabs, one search box,
    filter chips and a sortable table. Query parameters (filter[...], sort, per_page) are unchanged.
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', 'name');
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
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === $field ? '-'.$field : $field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');
    $chipLabels = ['search' => 'Search'];
    $sortNames = ['name' => 'Name'];
    $sortField = ltrim($sort, '-');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>Categories</span>
                </nav>
                <h1 class="ak-title">Categories</h1>
                <p class="ak-sub">Product groups used for reporting and filtering.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('settings.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                @can('category-create')
                    <a href="{{ route('categories.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add category</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page" x-data="listPage()">
        <x-status-message />

        <section class="ak-kpis" aria-label="Summary" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
            <a href="{{ $tabUrl('') }}" class="ak-kpi{{ $tab === '' ? ' is-active' : '' }}">
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Categories</span>
                    <span class="ak-kpi-value">{{ number_format($stats['total']) }}</span>
                    <span class="ak-kpi-hint">{{ $chips ? 'matching the filters' : 'all records' }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('1') }}" class="ak-kpi{{ $tab === '1' ? ' is-active' : '' }}">
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Active</span>
                    <span class="ak-kpi-value">{{ number_format($stats['active']) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($stats['inactive']) }} inactive</span>
                </span>
            </a>
        </section>

        <section class="ak-card" aria-label="Categories">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $stats['total']], '1' => ['Active', $stats['active']], '0' => ['Inactive', $stats['inactive']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl((string) $key) }}" role="tab" aria-selected="{{ $tab === (string) $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === (string) $key ? 'is-active' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('categories.index') }}" class="ak-filters" x-data="{ busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[is_active]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                <div class="ak-filter-row" style="grid-template-columns: minmax(0, 3fr) minmax(110px, .6fr) auto">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off" placeholder="Name or slug">
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows per page</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach ($perPageOptions as $n)
                                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>
            </form>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $value }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters($tab !== '' ? ['is_active' => $tab] : []) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($categories->count() > 0)
                <div class="ak-table-wrap">
                    <div class="ak-dt-toolbar">
                        <p><b>{{ number_format($categories->total()) }}</b> {{ \Illuminate\Support\Str::plural('record', $categories->total()) }} &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Name' }}</b></p>
                    </div>
                    <div class="ak-dt-scroll" tabindex="0" aria-label="Categories table">
                        <table class="ak-dt">
                            <caption class="sr-only">Categories, {{ $categories->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('name') }}"><a href="{{ $sortUrl('name') }}">Category <span aria-hidden="true">{{ $sortMark('name') }}</span></a></th>
                                    <th scope="col">Slug</th>
                                    <th scope="col" class="ak-c">Products</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-c"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $category)
                                    <tr>
                                        <td class="ak-c" data-label=""><span class="ak-muted">{{ $categories->firstItem() + $loop->index }}</span></td>
                                        <td data-label="Category"><a href="{{ route('categories.show', $category) }}" class="ak-primary-link">{{ $category->name }}</a></td>
                                        <td class="ak-muted" data-label="Slug">{{ $category->slug ?: '—' }}</td>
                                        <td class="ak-c ak-mono" data-label="Products">{{ number_format($category->products_count) }}</td>
                                        <td data-label="Status">
                                            @if ($category->is_active)
                                                <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                                            @else
                                                <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Inactive</span>
                                            @endif
                                        </td>
                                        <td data-label="">
                                            <div class="ak-actions">
                                                <a href="{{ route('categories.show', $category) }}" class="ak-icon ak-icon-view" title="View" aria-label="View {{ $category->name }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                </a>
                                                <span class="ak-slot">
                                                    @can('category-edit')
                                                        <a href="{{ route('categories.edit', $category) }}" class="ak-icon" title="Edit" aria-label="Edit {{ $category->name }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                        </a>
                                                    @endcan
                                                </span>
                                                <span class="ak-slot">
                                                    @if (auth()->user()->can('category-delete') && $category->is_active)
                                                        <form method="POST" action="{{ route('categories.destroy', $category) }}" @submit.prevent="askDelete($el, @js($category->name))">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="ak-icon ak-icon-danger" title="Deactivate" aria-label="Deactivate {{ $category->name }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.36 6.64A9 9 0 1 1 5.64 6.64M12 3v9" /></svg>
                                                            </button>
                                                        </form>
                                                    @endif
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
                    <p>Showing <b>{{ number_format($categories->firstItem()) }}–{{ number_format($categories->lastItem()) }}</b> of <b>{{ number_format($categories->total()) }}</b></p>
                    <div class="ak-pager-right">{{ $categories->onEachSide(1)->links() }}</div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    @if ($filters)
                        <h2>Nothing matches these filters</h2>
                        <p>Remove a filter or search with part of the name.</p>
                        <a href="{{ route('categories.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>
                    @else
                        <h2>Nothing here yet</h2>
                        @can('category-create') <a href="{{ route('categories.create') }}" class="ak-btn ak-btn-primary">＋ Add category</a> @endcan
                    @endif
                </div>
            @endif
        </section>

        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="lp-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon ak-pill-red" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="lp-confirm-title" x-text="confirm.title"></h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155">It will be marked Inactive and can be turned back on from Edit. Nothing is deleted.</p>
                    </div>
                </div>
                <div class="uf-modal-foot">
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="button" class="ak-btn ak-btn-danger-outline" @click="runConfirm()" :disabled="confirm.busy" x-text="confirm.busy ? 'Working…' : 'Deactivate'"></button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function listPage() {
                return {
                    confirm: { open: false, title: '', busy: false, form: null },
                    askDelete(form, name) { this.confirm = { open: true, title: 'Deactivate ' + name + '?', busy: false, form }; },
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
