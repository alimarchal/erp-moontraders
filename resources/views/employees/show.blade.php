{{--
    Settings -> Employees -> View (/settings/employees/{employee}).
    Profile, assignment and linked login. Read only; changes happen on Edit.
--}}
@php
    $initials = collect(explode(' ', trim((string) $employee->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('employees.index') }}">Employees</a><span aria-hidden="true">›</span>
                    <span>{{ $employee->name }}</span>
                </nav>
                <div class="ak-person" style="align-items:center; margin-top:6px">
                    <span class="ak-avatar" style="width:48px; height:48px; font-size:16px" aria-hidden="true">{{ $initials ?: '?' }}</span>
                    <div>
                        <h1 class="ak-title" style="margin:0">{{ $employee->name }}</h1>
                        <p class="ak-sub" style="margin-top:2px">
                            {{ $employee->employee_code }} &middot; {{ $employee->designation ?: 'No designation' }}
                            &middot;
                            @if ($employee->is_active)
                                <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                            @else
                                <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Inactive</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('employees.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Employees</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @can('employee-edit')
                    <a href="{{ route('employees.edit', $employee) }}" class="ak-btn ak-btn-primary">Edit employee</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>
        .us-grid { display: grid; gap: 20px; grid-template-columns: 1fr; }
        @media (min-width: 1024px) { .us-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
        .us-list { margin: 0; display: flex; flex-direction: column; font-size: 14px; }
        .us-list > div { display: flex; justify-content: space-between; gap: 16px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .us-list > div:last-child { border-bottom: 0; }
        .us-list dt { color: var(--ak-muted); }
        .us-list dd { margin: 0; font-weight: 600; color: var(--ak-text); text-align: right; word-break: break-word; }
        @media print { .us-grid { display: block; } .uf-card { break-inside: avoid; box-shadow: none; margin-bottom: 12px; } }
    </style>

    <div class="ak-page">
        <x-status-message />

        {{-- KPI cards --}}
        <section class="ak-kpis" aria-label="Employee summary">
            <div class="ak-kpi">
                <span class="ak-kpi-icon {{ $employee->is_active ? 'ak-tone-green' : 'ak-tone-amber' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Status</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $employee->is_active ? 'Active' : 'Inactive' }}</span>
                    <span class="ak-kpi-hint">since {{ $employee->created_at?->format('d M Y') ?? '—' }}</span>
                </span>
            </div>
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.35m-16.5 11.65V9.35m0 0a3 3 0 0 0 3.75-.62 3 3 0 0 0 4.5 0 3 3 0 0 0 4.5 0 3 3 0 0 0 3.75.62m-16.5 0a3 3 0 0 1-.62-4.72L4.2 3.44A1.5 1.5 0 0 1 5.26 3h13.48a1.5 1.5 0 0 1 1.06.44l1.19 1.19a3 3 0 0 1-.62 4.72" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Supplier</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $employee->supplier?->supplier_name ?? 'None' }}</span>
                    <span class="ak-kpi-hint">{{ $employee->company_name ?: 'no principal recorded' }}</span>
                </span>
            </div>
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 21V8.25L12 3l8.25 5.25V21M9 21v-6h6v6" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Warehouse</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $employee->warehouse?->warehouse_name ?? 'Not assigned' }}</span>
                    <span class="ak-kpi-hint">primary warehouse</span>
                </span>
            </div>
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0A17.93 17.93 0 0 1 12 21.75c-2.68 0-5.22-.58-7.5-1.63Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Linked user</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $employee->user?->name ?? 'None' }}</span>
                    <span class="ak-kpi-hint">{{ $employee->user ? $employee->user->email : 'no login linked' }}</span>
                </span>
            </div>
        </section>

        <div class="us-grid">
            <section class="uf-card" aria-labelledby="us-profile">
                <header class="uf-card-head"><h2 class="uf-card-title" id="us-profile">Profile</h2></header>
                <div class="uf-body" style="padding-top:6px; padding-bottom:6px">
                    <dl class="us-list">
                        <div><dt>Employee code</dt><dd>{{ $employee->employee_code }}</dd></div>
                        <div><dt>Full name</dt><dd>{{ $employee->name }}</dd></div>
                        <div><dt>Designation</dt><dd>{{ $employee->designation ?: '—' }}</dd></div>
                        <div><dt>Company / Principal</dt><dd>{{ $employee->company_name ?: '—' }}</dd></div>
                        <div><dt>Phone</dt><dd>{{ $employee->phone ?: '—' }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $employee->email ?: '—' }}</dd></div>
                        <div><dt>Hire date</dt><dd>{{ $employee->hire_date?->format('d M Y') ?? '—' }}</dd></div>
                        <div><dt>Status</dt><dd>{{ $employee->is_active ? 'Active' : 'Inactive' }}</dd></div>
                    </dl>
                </div>
            </section>

            <div style="display:flex; flex-direction:column; gap:20px">
                <section class="uf-card" aria-labelledby="us-assign">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="us-assign">Assignment</h2></header>
                    <div class="uf-body" style="padding-top:6px; padding-bottom:6px">
                        <dl class="us-list">
                            <div><dt>Supplier</dt><dd>{{ $employee->supplier?->supplier_name ?? '—' }}</dd></div>
                            <div><dt>Company entity</dt><dd>{{ $employee->company?->company_name ?? '—' }}</dd></div>
                            <div><dt>Cost center</dt><dd>{{ $employee->costCenter?->name ?? '—' }}</dd></div>
                            <div><dt>Warehouse</dt><dd>{{ $employee->warehouse?->warehouse_name ?? '—' }}</dd></div>
                            <div><dt>Linked user</dt><dd>{{ $employee->user?->name ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </section>

                <section class="uf-card" aria-labelledby="us-notes">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="us-notes">Address / notes</h2></header>
                    <div class="uf-body" style="padding-top:6px; padding-bottom:6px">
                        <p style="margin:8px 0; font-size:14px; white-space:pre-line">{{ $employee->address ?: '—' }}</p>
                        <dl class="us-list" style="margin-top:8px">
                            <div><dt>Added</dt><dd>{{ $employee->created_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                            <div><dt>Last updated</dt><dd>{{ $employee->updated_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
