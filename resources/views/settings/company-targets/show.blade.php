{{-- Settings -> Company Targets -> View (/settings/company-targets/{uuid}). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('company-targets.index') }}">Company Targets</a><span aria-hidden="true">›</span><span>View</span>
                </nav>
                <h1 class="ak-title">{{ $target->supplier?->supplier_name }}</h1>
                <p class="ak-sub">{{ $target->period_start->format('d M Y') }} – {{ $target->period_end->format('d M Y') }}</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('company-targets.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                @can('report-audit-company-target-summary')
                    <a href="{{ route('reports.company-target-summary.index', ['supplier_id' => $target->supplier_id, 'from' => $target->period_start->toDateString(), 'to' => min(now()->toDateString(), $target->period_end->toDateString())]) }}" class="ak-btn ak-btn-outline">Open report</a>
                @endcan
                @can('company-target-edit')
                    <a href="{{ route('company-targets.edit', $target) }}" class="ak-btn ak-btn-primary">Edit</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />
        <section class="ak-card">
            <div class="ak-dt-toolbar">
                <p>Created by <b>{{ $target->creator?->name ?? '—' }}</b> on {{ $target->created_at->format('d M Y H:i') }}@if ($target->notes) &middot; {{ $target->notes }}@endif</p>
            </div>
            <div class="ak-dt-scroll">
                <table class="ak-dt" style="min-width:600px;">
                    <thead><tr><th style="width:50px;">#</th><th>Salesman</th><th>Target</th><th style="text-align:right;">Total</th></tr></thead>
                    <tbody>
                        @foreach ($target->lines as $line)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="ak-strong">{{ $line->employee?->name }}</td>
                                <td>{{ $line->target_label }}</td>
                                <td style="text-align:right;">{{ number_format((float) $line->target_amount, 0) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="3" class="ak-strong">Total</td>
                            <td class="ak-strong" style="text-align:right;">{{ number_format((float) $target->lines->sum('target_amount'), 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
