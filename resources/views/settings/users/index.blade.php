{{--
    Settings -> Users list (/settings/users). Same layout as the AKSIC list:
    KPI cards that double as filters, Active / Suspended tabs, one search box,
    removable filter chips, sortable dense table with comfortable / compact
    density, bulk actions on selected rows, and a pager with rows-per-page.
    Routes and query parameters are unchanged (filter[...], sort, per_page).
    Styles are plain CSS (settings.partials.ui-style) -- no Tailwind rebuild needed.
--}}
@php
    $authUser = auth()->user();
    $isSuperAdmin = $authUser->is_super_admin === 'Yes' || $authUser->hasRole('super-admin');
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', '-created_at');
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

    $supplierNames = $suppliers->pluck('supplier_name', 'id');
    $chipLabels = [
        'search' => 'Search', 'role' => 'Role', 'supplier_id' => 'Supplier', 'is_super_admin' => 'Super admin',
        'access' => 'Access', 'name' => 'Name', 'email' => 'Email', 'designation' => 'Designation',
    ];
    $chipValue = fn (string $key, $value) => match ($key) {
        'supplier_id' => $supplierNames[$value] ?? $value,
        'access' => $value === 'none' ? 'No role or permission' : $value,
        default => $value,
    };
    $advancedKeys = ['is_super_admin', 'access', 'designation'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $sortNames = ['name' => 'Name', 'email' => 'Email', 'designation' => 'Designation', 'created_at' => 'Date added'];
    $sortField = ltrim($sort, '-');
    $canBulk = $authUser->can('user-bulk-update');
    $pageIds = $canBulk ? $users->getCollection()->reject(fn ($u) => $u->id === $authUser->id)->pluck('id')->map(fn ($id) => (string) $id)->values() : [];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>Users</span>
                </nav>
                <h1 class="ak-title">Users</h1>
                <p class="ak-sub">Who can sign in, their roles and what they can open.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('settings.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                @can('role-list')
                    <a href="{{ route('roles.index') }}" class="ak-btn ak-btn-outline">Roles &amp; permissions</a>
                @endcan
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @can('user-create')
                    <a href="{{ route('users.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add user</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>@page { size: A4 landscape; margin: 10mm; }</style>

    <div class="ak-page" x-data="userList()">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Users</div>
            <table class="ak-print-meta">
                <tr><th>Filters</th><td>{{ $filters ? collect($filters)->map(fn ($v, $k) => ($chipLabels[$k] ?? \Illuminate\Support\Str::headline($k)).': '.$chipValue($k, $v))->implode(' · ') : 'None (all users)' }}</td>
                    <th>Printed</th><td>{{ now()->format('d.m.Y H:i') }} by {{ $authUser->name }}</td></tr>
            </table>
        </div>

        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        {{-- KPI cards (also filters) --}}
        <section class="ak-kpis" aria-label="User summary">
            <a href="{{ $tabUrl('') }}" class="ak-kpi{{ $tab === '' && ! isset($filters['is_super_admin']) && ! isset($filters['access']) ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.13a9.38 9.38 0 0 0 2.63.37 9.34 9.34 0 0 0 4.12-.95 4.13 4.13 0 0 0-7.53-2.49M15 19.13v-.01a6.37 6.37 0 0 0-.97-3.4M15 19.13v.1A12.32 12.32 0 0 1 8.62 21a12.32 12.32 0 0 1-6.37-1.77v-.11a6.38 6.38 0 0 1 11.96-3.4M12 6.38a3.38 3.38 0 1 1-6.75 0 3.38 3.38 0 0 1 6.75 0Zm8.25 2.25a2.63 2.63 0 1 1-5.25 0 2.63 2.63 0 0 1 5.25 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Users</span>
                    <span class="ak-kpi-value">{{ number_format($stats['total']) }}</span>
                    <span class="ak-kpi-hint">{{ $chips ? 'matching the filters' : 'all accounts' }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('Yes') }}" class="ak-kpi{{ $tab === 'Yes' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Active</span>
                    <span class="ak-kpi-value">{{ number_format($stats['active']) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($stats['inactive']) }} suspended</span>
                </span>
            </a>
            <a href="{{ $onlyUrl(['is_super_admin' => 'Yes']) }}" class="ak-kpi{{ ($filters['is_super_admin'] ?? '') === 'Yes' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.04A11.96 11.96 0 0 1 3.6 6 12 12 0 0 0 3 9.75c0 5.6 3.82 10.3 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.31-.21-2.57-.6-3.75h-.15c-3.2 0-6.1-1.25-8.25-3.29Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Super admins</span>
                    <span class="ak-kpi-value">{{ number_format($stats['super_admins']) }}</span>
                    <span class="ak-kpi-hint">skip every permission check</span>
                </span>
            </a>
            <a href="{{ $onlyUrl(['access' => 'none', 'is_active' => 'Yes']) }}" class="ak-kpi{{ $stats['no_access'] ? ' ak-kpi-action' : '' }}{{ ($filters['access'] ?? '') === 'none' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">No access yet</span>
                    <span class="ak-kpi-value">{{ number_format($stats['no_access']) }}</span>
                    <span class="ak-kpi-hint">{{ $stats['no_access'] ? 'Active, but no role or permission →' : 'Everyone has access set' }}</span>
                </span>
            </a>
        </section>

        <section class="ak-card" aria-label="Users">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $stats['total']], 'Yes' => ['Active', $stats['active']], 'No' => ['Suspended', $stats['inactive']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl($key) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === $key ? 'is-active' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('users.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[is_active]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="ak-filter-row ak-filter-row-users">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Name, email or designation" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_role">Role</label>
                        <select id="f_role" name="filter[role]">
                            <option value="">All roles</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ \Illuminate\Support\Str::headline($role->name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_supplier">Supplier</label>
                        <select id="f_supplier" name="filter[supplier_id]">
                            <option value="">All suppliers</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows per page</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\UserController::PER_PAGE as $n)
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
                        <label for="f_super">Super admin</label>
                        <select id="f_super" name="filter[is_super_admin]">
                            <option value="">Any</option>
                            <option value="Yes" @selected(($filters['is_super_admin'] ?? '') === 'Yes')>Yes</option>
                            <option value="No" @selected(($filters['is_super_admin'] ?? '') === 'No')>No</option>
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_access">Access</label>
                        <select id="f_access" name="filter[access]">
                            <option value="">Any</option>
                            <option value="none" @selected(($filters['access'] ?? '') === 'none')>No role or permission</option>
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_designation">Designation</label>
                        <input id="f_designation" type="text" name="filter[designation]" value="{{ $filters['designation'] ?? '' }}">
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

            @if ($users->count() > 0)
                {{-- Bulk actions: checkboxes point at this form with form="bulk-form" (no nested forms). --}}
                @if ($canBulk)
                    <form id="bulk-form" method="POST" action="{{ route('users.bulk-update') }}" class="ak-bulk" x-show="selected.length" x-cloak @submit.prevent="askBulk($el)">
                        @csrf
                        <b x-text="selected.length + ' selected'"></b>
                        <label for="bulk_action" class="sr-only">Bulk action</label>
                        <select id="bulk_action" name="action" x-model="bulkAction" required>
                            <option value="">Choose action…</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Suspend</option>
                            @if ($isSuperAdmin)<option value="delete">Delete</option>@endif
                        </select>
                        <button type="submit" class="ak-btn ak-btn-primary ak-btn-sm" :disabled="!bulkAction">Apply</button>
                        <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="clearSelection()">Clear selection</button>
                    </form>
                @endif

                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('users-density') === 'compact'; } catch (e) { return false; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('users-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($users->total()) }}</b> {{ \Illuminate\Support\Str::plural('user', $users->total()) }}
                            &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Date added' }}</b> ({{ str_starts_with($sort, '-') ? 'newest / Z–A first' : 'oldest / A–Z first' }})
                        </p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Users table">
                        <table class="ak-dt">
                            <caption class="sr-only">Users, {{ $users->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c ak-sticky-1">
                                        @if ($canBulk)
                                            <input type="checkbox" aria-label="Select all users on this page" :checked="allSelected()" @change="toggleAll($event.target.checked)">
                                        @else
                                            #
                                        @endif
                                    </th>
                                    <th scope="col" class="ak-sticky-2" aria-sort="{{ $ariaSort('name') }}"><a href="{{ $sortUrl('name') }}">User <span aria-hidden="true">{{ $sortMark('name') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('designation') }}"><a href="{{ $sortUrl('designation') }}">Designation / Supplier <span aria-hidden="true">{{ $sortMark('designation') }}</span></a></th>
                                    <th scope="col">Roles</th>
                                    <th scope="col" class="ak-c">Access</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('created_at') }}"><a href="{{ $sortUrl('created_at') }}">Added <span aria-hidden="true">{{ $sortMark('created_at') }}</span></a></th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-c ak-sticky-end"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    @php
                                        $isSelf = $user->id === $authUser->id;
                                        $userIsSuper = $user->is_super_admin === 'Yes' || $user->roles->contains('name', 'super-admin');
                                        $fromRoles = $user->roles->flatMap(fn ($r) => $r->permissions->pluck('id'))->unique();
                                        $direct = $user->permissions->pluck('id');
                                        $total = $fromRoles->merge($direct)->unique()->count();
                                        $initials = collect(explode(' ', trim((string) $user->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                                    @endphp
                                    <tr>
                                        <td class="ak-c ak-sticky-1" data-label="">
                                            @if ($canBulk && ! $isSelf)
                                                <input type="checkbox" name="ids[]" value="{{ $user->id }}" form="bulk-form" x-model="selected" aria-label="Select {{ $user->name }}">
                                            @elseif ($isSelf)
                                                <span class="ak-muted" title="Your own account">You</span>
                                            @else
                                                <span class="ak-muted">{{ $users->firstItem() + $loop->index }}</span>
                                            @endif
                                        </td>
                                        <td data-label="User" class="ak-sticky-2">
                                            <div class="ak-person">
                                                <span class="ak-avatar ak-hide-compact" aria-hidden="true">{{ $initials ?: '?' }}</span>
                                                <div style="min-width:0">
                                                    @can('user-list')
                                                        <a href="{{ route('users.show', $user) }}" class="ak-primary-link">{{ $user->name }}</a>
                                                    @else
                                                        <span class="ak-strong">{{ $user->name }}</span>
                                                    @endcan
                                                    <div class="ak-muted" style="word-break:break-all">{{ $user->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Designation / Supplier">
                                            <div>{{ $user->designation ?: '—' }}</div>
                                            <div class="ak-muted">{{ $user->supplier?->supplier_name ?? 'All suppliers' }}</div>
                                        </td>
                                        <td data-label="Roles">
                                            @if ($userIsSuper)<span class="ak-pill ak-pill-red">Super admin</span>@endif
                                            @forelse ($user->roles->where('name', '!=', 'super-admin') as $role)
                                                <span class="ak-pill">{{ \Illuminate\Support\Str::headline($role->name) }}</span>
                                            @empty
                                                @unless ($userIsSuper) <span class="ak-muted">No role</span> @endunless
                                            @endforelse
                                        </td>
                                        <td class="ak-c" data-label="Access">
                                            @if ($userIsSuper)
                                                <span class="ak-strong">Everything</span>
                                            @elseif ($total === 0)
                                                <span class="ak-status ak-status-amber"><i aria-hidden="true"></i>None</span>
                                            @else
                                                <span class="ak-strong" title="{{ $fromRoles->count() }} from roles, {{ $direct->count() }} extra">{{ number_format($total) }}</span>
                                                <div class="ak-muted ak-hide-compact">{{ $direct->count() ? $direct->count().' extra' : 'from roles' }}</div>
                                            @endif
                                        </td>
                                        <td class="ak-mono" data-label="Added">
                                            {{ $user->created_at?->format('d M Y') ?? '—' }}
                                            <div class="ak-muted ak-hide-compact">{{ $user->created_at?->diffForHumans() }}</div>
                                        </td>
                                        <td data-label="Status">
                                            @if ($user->is_active === 'Yes')
                                                <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                                            @else
                                                <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Suspended</span>
                                            @endif
                                        </td>
                                        <td class="ak-sticky-end" data-label="">
                                            <div class="ak-actions">
                                                @can('user-list')
                                                    <a href="{{ route('users.show', $user) }}" class="ak-icon ak-icon-view" title="View user" aria-label="View {{ $user->name }}">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    </a>
                                                @else
                                                    <span class="ak-slot"></span>
                                                @endcan
                                                <span class="ak-slot">
                                                    @can('user-edit')
                                                        <a href="{{ route('users.edit', $user) }}" class="ak-icon" title="Edit user & access" aria-label="Edit {{ $user->name }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                        </a>
                                                    @endcan
                                                </span>
                                                <span class="ak-slot">
                                                    @if (! $isSelf)
                                                        @can('user-delete')
                                                            <form method="POST" action="{{ route('users.destroy', $user) }}" @submit.prevent="askDelete($el, @js($user->name))">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="ak-icon ak-icon-danger" title="Delete user" aria-label="Delete {{ $user->name }}">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                                                                </button>
                                                            </form>
                                                        @endcan
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
                    <p>Showing <b>{{ number_format($users->firstItem()) }}–{{ number_format($users->lastItem()) }}</b> of <b>{{ number_format($users->total()) }}</b> users</p>
                    <div class="ak-pager-right">
                        <form method="GET" action="{{ route('users.index') }}" class="ak-perpage">
                            @foreach (request()->except(['per_page', 'page']) as $key => $value)
                                @if (is_array($value))
                                    @foreach ($value as $k => $v) <input type="hidden" name="{{ $key }}[{{ $k }}]" value="{{ $v }}"> @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <label for="per_page">Rows per page</label>
                            <select id="per_page" name="per_page" onchange="this.form.submit()">
                                @foreach (\App\Http\Controllers\UserController::PER_PAGE as $n)
                                    <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                                @endforeach
                            </select>
                        </form>
                        <div>{{ $users->onEachSide(1)->links() }}</div>
                    </div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    @if ($filters)
                        <h2>No users match these filters</h2>
                        <p>Remove a filter or search with part of a name or email.</p>
                        <a href="{{ route('users.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>
                    @else
                        <h2>No users yet</h2>
                        <p>Add the first user with “Add user”.</p>
                        @can('user-create') <a href="{{ route('users.create') }}" class="ak-btn ak-btn-primary">＋ Add user</a> @endcan
                    @endif
                </div>
            @endif
        </section>

        {{-- Confirm modal (same pattern as the Approve modal) --}}
        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="ul-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon" :class="confirm.danger ? 'ak-pill-red' : 'ak-tone-amber'" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="ul-confirm-title" x-text="confirm.title"></h3>
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
            function userList() {
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
                        this.ask(form, 'Delete ' + name + '?', 'The user will no longer be able to sign in. This cannot be undone.', 'Delete user', true);
                    },
                    askBulk(form) {
                        if (!this.bulkAction || !this.selected.length) return;
                        const words = { activate: 'Activate', deactivate: 'Suspend', delete: 'Delete' };
                        const count = this.selected.length + (this.selected.length === 1 ? ' user' : ' users');
                        const message = this.bulkAction === 'delete'
                            ? 'Selected users will be deleted (the last super admin is always kept). This cannot be undone.'
                            : (this.bulkAction === 'deactivate' ? 'Suspended users cannot sign in until activated again.' : 'Selected users will be able to sign in.');
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
