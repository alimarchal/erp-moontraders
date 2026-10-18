{{-- Settings -> Company Targets list (/settings/company-targets). --}}
@php
    $isAdmin = auth()->user()->hasAllSupplierAccess();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>Company Targets</span>
                </nav>
                <h1 class="ak-title">Company Targets</h1>
                <p class="ak-sub">Monthly sales target of every salesman, per company.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('settings.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('company-targets.help') }}" class="ak-btn ak-btn-outline">Help (اردو)</a>
                @can('report-audit-company-target-summary')
                    <a href="{{ route('reports.company-target-summary.index') }}" class="ak-btn ak-btn-outline">Target report</a>
                @endcan
                @can('company-target-create')
                    <a href="{{ route('company-targets.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add target</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />

        <section class="ak-card" aria-label="Company targets">
            <form method="GET" action="{{ route('company-targets.index') }}" class="ak-filters">
                <div class="ak-filter-row">
                    @if ($isAdmin)
                        <div class="ak-field">
                            <label for="supplier_id">Company</label>
                            <select id="supplier_id" name="supplier_id">
                                <option value="">All companies</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? null) == $supplier->id)>{{ $supplier->supplier_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="ak-field">
                        <label for="from">From</label>
                        <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="to">To</label>
                        <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="submit" class="ak-btn ak-btn-primary">Filter</button>
                        <a href="{{ route('company-targets.index') }}" class="ak-btn ak-btn-outline">Reset</a>
                    </div>
                </div>
            </form>

            <div class="ak-dt-scroll">
                <table class="ak-dt" style="min-width:760px;">
                    <thead>
                        <tr><th>Company</th><th>Period</th><th>Salesmen</th><th style="text-align:right;">Total target</th><th style="width:210px;">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($targets as $target)
                            <tr>
                                <td class="ak-strong">{{ $target->supplier?->supplier_name }}</td>
                                <td>{{ $target->period_start->format('d M Y') }} – {{ $target->period_end->format('d M Y') }}</td>
                                <td>{{ $target->lines_count }}</td>
                                <td style="text-align:right;">{{ number_format((float) $target->total_target, 0) }}</td>
                                <td>
                                    <a href="{{ route('company-targets.show', $target) }}" class="ak-btn ak-btn-outline ak-btn-sm">View</a>
                                    @can('company-target-edit')
                                        <a href="{{ route('company-targets.edit', $target) }}" class="ak-btn ak-btn-outline ak-btn-sm">Edit</a>
                                    @endcan
                                    @can('company-target-delete')
                                        <form method="POST" action="{{ route('company-targets.destroy', $target) }}" style="display:inline;"
                                            onsubmit="return confirm('Delete this target sheet?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="ak-btn ak-btn-danger-outline ak-btn-sm">Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ak-empty">No company targets yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="ak-pager">{{ $targets->links() }}</div>
        </section>
    </div>
</x-app-layout>
