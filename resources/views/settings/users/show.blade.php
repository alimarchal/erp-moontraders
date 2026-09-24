{{--
    Settings -> Users -> View (/settings/users/{user}).
    Profile, access summary, what the user can actually open (arranged like the
    Settings and Reports screens, with where each permission comes from) and
    recent activity. Read only; changes happen on Edit.
--}}
@php
    $authUser = auth()->user();
    $isSuper = $user->is_super_admin === 'Yes' || $user->roles->contains('name', 'super-admin');
    $initials = collect(explode(' ', trim((string) $user->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $fromRoles = collect($grants)->filter(fn ($g) => ! empty($g['roles']))->count();
    $extra = collect($grants)->filter(fn ($g) => ! empty($g['direct']))->count();
    $onlyExtra = collect($grants)->filter(fn ($g) => ! empty($g['direct']) && empty($g['roles']))->count();

    // Keep only screens where the user has something; count per module.
    $modules = collect($catalog)->map(function ($module) use ($grants) {
        $total = 0;
        $given = 0;
        $sections = collect($module['sections'])->map(function ($section) use ($grants, &$total, &$given) {
            $groups = collect($section['groups'])->map(function ($group) use ($grants, &$total, &$given) {
                $total += count($group['permissions']);
                $perms = collect($group['permissions'])->filter(fn ($p) => isset($grants[$p['id']]))->values();
                $given += $perms->count();

                return $perms->isEmpty() ? null : ['label' => $group['label'], 'count' => count($group['permissions']), 'permissions' => $perms];
            })->filter()->values();

            return $groups->isEmpty() ? null : ['label' => $section['label'], 'groups' => $groups];
        })->filter()->values();

        return ['key' => $module['key'], 'label' => $module['label'], 'sections' => $sections, 'given' => $given, 'total' => $total];
    });
    $firstTab = $modules->firstWhere(fn ($m) => $m['given'] > 0)['key'] ?? 'settings';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('users.index') }}">Users</a><span aria-hidden="true">›</span>
                    <span>{{ $user->name }}</span>
                </nav>
                <div class="ak-person" style="align-items:center; margin-top:6px">
                    <span class="ak-avatar" style="width:48px; height:48px; font-size:16px" aria-hidden="true">{{ $initials ?: '?' }}</span>
                    <div>
                        <h1 class="ak-title" style="margin:0">{{ $user->name }}</h1>
                        <p class="ak-sub" style="margin-top:2px">
                            {{ $user->designation ?: 'No designation' }} &middot; {{ $user->email }}
                            &middot;
                            @if ($user->is_active === 'Yes')
                                <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                            @else
                                <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Suspended</span>
                            @endif
                            @if ($isSuper) <span class="ak-pill ak-pill-red" style="margin:0">Super admin</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('users.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Users</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @can('user-edit')
                    <a href="{{ route('users.edit', $user) }}" class="ak-btn ak-btn-primary">Edit user &amp; access</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>
        .us-grid { display: grid; gap: 20px; grid-template-columns: 1fr; }
        @media (min-width: 1024px) { .us-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
        .us-list { margin: 0; display: flex; flex-direction: column; font-size: 14px; }
        .us-list > div { display: flex; justify-content: space-between; gap: 16px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .us-list > div:last-child { border-bottom: 0; }
        .us-list dt { color: var(--ak-muted); }
        .us-list dd { margin: 0; font-weight: 600; color: var(--ak-text); text-align: right; word-break: break-word; }
        .us-src { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
        .us-feed { margin: 0; padding: 0; list-style: none; }
        .us-feed li { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .us-feed li:last-child { border-bottom: 0; }
        .us-feed i { flex: none; width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; background: var(--ak-navy); }
        .us-feed small { display: block; color: var(--ak-muted); font-size: 12px; }
        @media print {
            .us-grid { display: block; }
            .uf-card { break-inside: avoid; box-shadow: none; margin-bottom: 12px; }
            .pm-tabs, .pm-toolbar { display: none !important; }
            .pm-scroll { max-height: none !important; overflow: visible !important; }
            [data-module] { display: block !important; }
        }
    </style>

    <div class="ak-page">
        <x-status-message />

        {{-- KPI cards --}}
        <section class="ak-kpis" aria-label="Access summary">
            <div class="ak-kpi">
                <span class="ak-kpi-icon {{ $user->is_active === 'Yes' ? 'ak-tone-green' : 'ak-tone-amber' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0A17.93 17.93 0 0 1 12 21.75c-2.68 0-5.22-.58-7.5-1.63Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Sign in</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $user->is_active === 'Yes' ? 'Allowed' : 'Blocked (suspended)' }}</span>
                    <span class="ak-kpi-hint">since {{ $user->created_at?->format('d M Y') ?? '—' }}</span>
                </span>
            </div>
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.1 9.1 0 0 0 3.74-.48 3 3 0 0 0-4.68-2.72m.94 3.2v.03c0 .34-.02.67-.06 1A11.94 11.94 0 0 1 12 21c-2.17 0-4.2-.58-5.95-1.58a6.06 6.06 0 0 1-.06-1.02m12.01 0a5.97 5.97 0 0 0-.94-3.2m0 0A5.99 5.99 0 0 0 12 12.75a6 6 0 0 0-5.06 2.77M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Roles</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $user->roles->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::headline($n))->implode(', ') ?: 'None' }}</span>
                    <span class="ak-kpi-hint">{{ $fromRoles }} permissions from roles</span>
                </span>
            </div>
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.04A11.96 11.96 0 0 1 3.6 6 12 12 0 0 0 3 9.75c0 5.6 3.82 10.3 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.31-.21-2.57-.6-3.75h-.15c-3.2 0-6.1-1.25-8.25-3.29Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Total access</span>
                    <span class="ak-kpi-value">{{ $isSuper ? 'Everything' : number_format(count($grants)) }}</span>
                    <span class="ak-kpi-hint">{{ $isSuper ? 'super admin skips permission checks' : $extra.' extra ('.$onlyExtra.' not in any role)' }}</span>
                </span>
            </div>
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.35m-16.5 11.65V9.35m0 0a3 3 0 0 0 3.75-.62 3 3 0 0 0 4.5 0 3 3 0 0 0 4.5 0 3 3 0 0 0 3.75.62m-16.5 0a3 3 0 0 1-.62-4.72L4.2 3.44A1.5 1.5 0 0 1 5.26 3h13.48a1.5 1.5 0 0 1 1.06.44l1.19 1.19a3 3 0 0 1-.62 4.72" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Supplier</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $user->supplier?->supplier_name ?? 'All suppliers' }}</span>
                    <span class="ak-kpi-hint">{{ $user->supplier ? 'limited to this supplier' : 'not linked to one supplier' }}</span>
                </span>
            </div>
        </section>

        <div class="us-grid">
            {{-- What the user can open --}}
            <section class="uf-card" aria-labelledby="us-access" x-data="{ tab: @js($firstTab), search: '' }">
                <header class="uf-card-head">
                    <div>
                        <h2 class="uf-card-title" id="us-access">What {{ \Illuminate\Support\Str::of($user->name)->before(' ') }} can open</h2>
                        <p class="uf-card-sub" style="margin-left:0">Arranged like the Settings and Reports pages. <span class="pm-act is-role" style="height:20px; font-size:11px; cursor:default">role</span> comes from a role, <span class="pm-act is-on" style="height:20px; font-size:11px; cursor:default">extra</span> was given individually.</p>
                    </div>
                </header>

                @if ($isSuper)
                    <div class="uf-body"><p class="uf-note uf-note-warn" style="margin:0">Super admin: every screen and report is open, whatever is listed below.</p></div>
                @endif

                @if (count($grants) === 0)
                    <div class="ak-empty">
                        <h2>No access yet</h2>
                        <p>This user can sign in but cannot open any module. Give a role or extra permissions.</p>
                        @can('user-edit') <a href="{{ route('users.edit', $user) }}" class="ak-btn ak-btn-primary">Give access</a> @endcan
                    </div>
                @else
                    <div class="pm-toolbar">
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input type="search" x-model.debounce.150ms="search" placeholder="Find a screen or report" aria-label="Search access">
                        </div>
                    </div>
                    <div class="pm-tabs" role="tablist" x-show="search.trim() === ''">
                        @foreach ($modules as $module)
                            <button type="button" role="tab" @click="tab = @js($module['key'])" :class="tab === @js($module['key']) && 'is-active'">
                                {{ $module['label'] }} <span class="ak-count">{{ $module['given'] }} / {{ $module['total'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="pm-scroll">
                        @foreach ($modules as $module)
                            <div data-module="{{ $module['key'] }}" x-show="search.trim() !== '' || tab === @js($module['key'])">
                                @forelse ($module['sections'] as $section)
                                    <div class="pm-section">
                                        <div class="pm-section-head"><h4>{{ $section['label'] }}</h4></div>
                                        @foreach ($section['groups'] as $group)
                                            @php $hay = strtolower($module['label'].' '.$section['label'].' '.$group['label'].' '.$group['permissions']->pluck('name')->implode(' ')); @endphp
                                            <div class="pm-row" x-show="search.trim() === '' || @js($hay).includes(search.trim().toLowerCase())">
                                                <div class="pm-row-name">
                                                    <span>{{ $group['label'] }}</span>
                                                    <span class="ak-count">{{ $group['permissions']->count() }}/{{ $group['count'] }}</span>
                                                </div>
                                                <div class="pm-acts">
                                                    @foreach ($group['permissions'] as $permission)
                                                        @php $g = $grants[$permission['id']]; $viaRole = ! empty($g['roles']); @endphp
                                                        <span class="pm-act {{ $viaRole ? 'is-role' : 'is-on' }}" style="cursor:default"
                                                            title="{{ $permission['name'] }} — {{ $viaRole ? 'from role: '.implode(', ', array_unique($g['roles'])) : 'extra permission' }}{{ $viaRole && ! empty($g['direct']) ? ' (also given as extra)' : '' }}">
                                                            ✓ {{ $permission['label'] }}
                                                            <small>{{ $viaRole ? 'role' : 'extra' }}</small>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @empty
                                    <p class="pm-empty">Nothing in {{ strtolower($module['label']) }} is given to this user.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <div style="display:flex; flex-direction:column; gap:20px">
                <section class="uf-card" aria-labelledby="us-profile">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="us-profile">Profile</h2></header>
                    <div class="uf-body" style="padding-top:6px; padding-bottom:6px">
                        <dl class="us-list">
                            <div><dt>Full name</dt><dd>{{ $user->name }}</dd></div>
                            <div><dt>Email (login)</dt><dd>{{ $user->email }}</dd></div>
                            <div><dt>Designation</dt><dd>{{ $user->designation ?: '—' }}</dd></div>
                            <div><dt>Supplier</dt><dd>{{ $user->supplier?->supplier_name ?? 'All suppliers' }}</dd></div>
                            <div><dt>Status</dt><dd>{{ $user->is_active === 'Yes' ? 'Active' : 'Suspended' }}</dd></div>
                            <div><dt>Super admin</dt><dd>{{ $user->is_super_admin }}</dd></div>
                            <div><dt>Roles</dt><dd>
                                @forelse ($user->roles as $role)
                                    <span class="ak-pill" style="margin:0 0 4px 4px">{{ \Illuminate\Support\Str::headline($role->name) }}</span>
                                @empty
                                    —
                                @endforelse
                            </dd></div>
                            <div><dt>Added</dt><dd>{{ $user->created_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                            <div><dt>Last updated</dt><dd>{{ $user->updated_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </section>

                <section class="uf-card" aria-labelledby="us-activity">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="us-activity">Recent activity</h2></header>
                    <div class="uf-body" style="padding-top:6px; padding-bottom:6px">
                        @if ($activity->isEmpty())
                            <p class="ak-muted" style="padding:12px 0">No activity recorded yet.</p>
                        @else
                            <ul class="us-feed">
                                @foreach ($activity as $entry)
                                    <li>
                                        <i aria-hidden="true"></i>
                                        <div>
                                            {{ \Illuminate\Support\Str::ucfirst($entry->description) }}
                                            <small>
                                                {{ $entry->created_at?->format('d M Y, h:i A') }} &middot; {{ $entry->created_at?->diffForHumans() }}
                                                @if ($entry->causer && ! ($entry->causer_id === $user->id && $entry->causer_type === $user->getMorphClass())) &middot; by {{ $entry->causer->name ?? '—' }} @endif
                                            </small>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
