{{--
    Reports -> Company Target Summary (/reports/company-target-summary).
    Salesman target vs achieved sales from posted settlements. Excel and PDF downloads use the same filters.
--}}
@php
    $query = array_filter([
        'supplier_id' => $supplier?->id,
        'from' => $from->toDateString(),
        'to' => $to->toDateString(),
        'designation' => $designation,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('reports.index') }}">Reports</a><span aria-hidden="true">›</span><span>Company Target Summary</span>
                </nav>
                <h1 class="ak-title">Company Target Summary</h1>
                <p class="ak-sub">Salesman target against sales achieved in posted settlements.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('reports.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                @if ($summary)
                    <a href="{{ route('reports.company-target-summary.export.excel', $query) }}" class="ak-btn ak-btn-outline">Excel</a>
                    <a href="{{ route('reports.company-target-summary.export.pdf', $query) }}" class="ak-btn ak-btn-outline">PDF</a>
                    <button type="button" class="ak-btn ak-btn-primary" onclick="window.print()">Print</button>
                @endif
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>@page { size: A4 landscape; margin: 10mm; }</style>

    <div class="ak-page">
        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="ak-card no-print" aria-label="Filters">
            <form method="GET" action="{{ route('reports.company-target-summary.index') }}" class="ak-filters">
                <div class="ak-filter-row">
                    <div class="ak-field">
                        <label for="supplier_id">Company</label>
                        <select id="supplier_id" name="supplier_id" @disabled($suppliers->count() === 1 && ! auth()->user()->hasAllSupplierAccess())>
                            @if (auth()->user()->hasAllSupplierAccess())
                                <option value="">Select company</option>
                            @endif
                            @foreach ($suppliers as $option)
                                <option value="{{ $option->id }}" @selected($supplier?->id === $option->id)>{{ $option->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="from">Start date</label>
                        <input id="from" type="date" name="from" value="{{ $from->toDateString() }}">
                    </div>
                    <div class="ak-field">
                        <label for="to">End date</label>
                        <input id="to" type="date" name="to" value="{{ $to->toDateString() }}">
                    </div>
                    <div class="ak-field">
                        <label for="designation">Designation</label>
                        <select id="designation" name="designation">
                            <option value="">All</option>
                            @foreach ($designations as $option)
                                <option value="{{ $option }}" @selected($designation === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="submit" class="ak-btn ak-btn-primary">Show report</button>
                        <a href="{{ route('reports.company-target-summary.index') }}" class="ak-btn ak-btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="ak-card" aria-label="Company target summary" style="padding:18px;margin-top:14px;">
            @if ($summary)
                <div style="text-align:center;background:#a9d18e;border:2px solid #111827;padding:10px;font-size:22px;font-weight:700;letter-spacing:1px;margin-bottom:12px;">
                    {{ mb_strtoupper($supplier->supplier_name) }}
                </div>
                <p class="ak-muted" style="margin:0 0 12px;font-size:13px;">
                    {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }} &middot; {{ $summary['days_left'] }} day(s) left for Remaining/Day
                    &middot; Generated {{ now()->format('d M Y H:i') }} by {{ auth()->user()->name }} &middot; MOON TRADERS ERP
                </p>
                <div style="overflow-x:auto;">
                    @include('reports.company-target-summary._table', ['summary' => $summary])
                </div>
            @else
                <div class="ak-empty">Select a company to see its target summary.</div>
            @endif
        </section>
    </div>
    <style>@media print { .no-print, nav, header .ak-head-actions { display: none !important; } }</style>
</x-app-layout>
