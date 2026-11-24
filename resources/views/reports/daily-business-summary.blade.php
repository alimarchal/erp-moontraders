{{--
    Daily Business Summary (/reports/daily-business-summary): the management sheet for one company or
    all companies. Empty until a company is chosen in the filters; sales by category and the TTS block
    run from the 1st of the month to the report date, the salesman table is the report date only.
    Filters follow the goods issues list (hidden panel opened by the header Filters button, searchable
    dropdowns, quick dates). Every block is a numbered table; on a phone the salesman table turns into
    one card per salesman, and print keeps the bordered A4 landscape sheet.
--}}
@php
    $money = fn ($value) => abs((float) $value) < 0.005 ? '–' : number_format((float) $value, 2);
    $signed = fn ($value) => (float) $value < 0 ? '('.number_format(abs((float) $value), 2).')' : number_format((float) $value, 2);
    $reportDate = \Carbon\Carbon::parse($date);
    $today = now()->toDateString();
    $quickDates = [
        'Today' => $today,
        'Yesterday' => now()->subDay()->toDateString(),
        'Last month end' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
    ];
    $dateUrl = fn (string $day) => request()->fullUrlWithQuery(['date' => $day]);
    $selectedEmployees = array_map('strval', $employeeIds);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Daily Business Summary" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index">
            <x-slot name="afterFilter">
                {{-- Help sits next to Filters, like the goods issues list --}}
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: @js($hasQuery ? 'what' : 'start') })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How this report works, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <x-register-help page="daily-business-summary" />

    @push('header')
        <style>
            /* ---------- Filters: the goods issues panel ---------- */
            .gl-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
            @media (min-width: 640px) { .gl-filter-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (min-width: 1024px) { .gl-filter-row { grid-template-columns: 170px minmax(190px, 1.3fr) minmax(150px, 1fr) minmax(220px, 2fr) auto; } }
            .gl-filter-row .ak-field input, .gl-filter-row .ak-field select:not([multiple]) { height: 38px; box-sizing: border-box; }
            .gl-filter-row .ak-field input[type="date"] { padding: 0 10px; font-size: 14px; }
            .gl-filter-row .ak-filter-buttons { display: flex; gap: 8px; padding-top: 0; }
            .gl-filter-row .ak-filter-buttons .ak-btn { flex: 1; justify-content: center; }
            .ak-filters .select2-container { width: 100% !important; display: block; }
            .ak-filters .select2-container .select2-selection--single { height: 38px; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
            .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
            .ak-filters .select2-container .select2-selection--multiple { min-height: 38px; border: 1px solid var(--ak-border); border-radius: 8px; }
            .gl-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
            .gl-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
            .gl-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
            .gl-ranges a:hover { background: var(--ak-soft); }
            .gl-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
            html.apl-on .rg-gi-filters { border: 0 !important; border-radius: 20px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08) !important; }
            html.apl-on .rg-gi-filters .ak-field label { font-size: 13px; font-weight: 500; color: #6e6e73; text-transform: none; letter-spacing: 0; }
            html.apl-on .rg-gi-filters .ak-btn { height: 38px; border-radius: 980px !important; text-transform: none; transition: transform .1s ease-out, background-color .15s ease; }
            html.apl-on .rg-gi-filters .ak-btn:active { transform: scale(.97); }
            html.apl-on .rg-gi-filters .ak-btn-primary { background: #0071e3 !important; border-color: #0071e3 !important; }
            html.apl-on .rg-gi-filters .gl-ranges a { border: 0 !important; background: rgba(118,118,128,.12); color: #1d1d1f; font-weight: 500; font-size: 13px; padding: 5px 12px; transition: background-color .15s ease, transform .1s ease-out; }
            html.apl-on .rg-gi-filters .gl-ranges a:hover { background: rgba(118,118,128,.22); }
            html.apl-on .rg-gi-filters .gl-ranges a:active { transform: scale(.96); }
            html.apl-on .rg-gi-filters .gl-ranges a[aria-current] { background: #0071e3 !important; color: #fff !important; }
            html.apl-on .rg-gi-filters .gl-ranges-label { font-size: 13px; font-weight: 500; color: #6e6e73; }

            /* ---------- Report ---------- */
            .dbs { --dbs-text: #1d1d1f; --dbs-muted: #6e6e73; --dbs-line: rgba(60,60,67,.12); --dbs-fill: #f5f5f7; --dbs-red: #d70015; --dbs-blue: #0071e3;
                color: var(--dbs-text); font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
            .dbs-card { background: #fff; border-radius: 18px; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08); }
            .dbs-num { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
            .dbs-neg { color: var(--dbs-red); }

            /* Title block */
            .dbs-head { padding: 18px 20px; text-align: center; }
            .dbs-head-org { font-size: 13px; font-weight: 600; letter-spacing: .02em; color: var(--dbs-muted); text-transform: uppercase; }
            .dbs-head-title { margin-top: 2px; font-size: clamp(20px, 5vw, 28px); line-height: 1.1; font-weight: 700; letter-spacing: -.02em; }
            .dbs-head-meta { margin-top: 10px; display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; }
            .dbs-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 980px; background: var(--dbs-fill); font-size: 13px; color: var(--dbs-text); }
            .dbs-pill b { font-weight: 600; }
            .dbs-pill span { color: var(--dbs-muted); }

            /* Headline figures */
            .dbs-kpis { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            @media (min-width: 900px) { .dbs-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
            .dbs-kpi { padding: 14px 16px; min-width: 0; }
            .dbs-kpi-label { font-size: 12px; font-weight: 500; color: var(--dbs-muted); line-height: 1.25; }
            .dbs-kpi-value { margin-top: 6px; font-size: clamp(15px, 4.2vw, 24px); font-weight: 700; letter-spacing: -.015em; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .dbs-kpi-sub { margin-top: 2px; font-size: 11.5px; color: var(--dbs-muted); }

            /* Numbered section tables, two per row on wide screens so the rows line up */
            .dbs-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); }
            @media (min-width: 900px) { .dbs-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            .dbs-section { overflow: hidden; display: flex; flex-direction: column; min-width: 0; }
            .dbs-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; padding: 14px 16px 8px; }
            .dbs-section-title { font-size: 15px; font-weight: 600; letter-spacing: -.01em; }
            .dbs-section-title .rg-info { margin-left: 6px; vertical-align: 1px; }
            .dbs-section-note { font-size: 12px; color: var(--dbs-muted); white-space: nowrap; }
            .dbs-table { width: 100%; border-collapse: collapse; font-size: 14px; line-height: 1.3; }
            .dbs-table th, .dbs-table td { padding: 8px 12px; border-top: 1px solid var(--dbs-line); vertical-align: middle; }
            .dbs-table thead th { border-top: 0; background: var(--dbs-fill); font-size: 12px; font-weight: 600; color: var(--dbs-muted); text-align: left; white-space: nowrap; }
            .dbs-table thead th.dbs-num { text-align: right; }
            .dbs-table .dbs-idx { width: 1%; padding-right: 4px; color: var(--dbs-muted); font-variant-numeric: tabular-nums; text-align: left; white-space: nowrap; }
            .dbs-table tbody tr:hover td { background: #f3f8fe; }
            .dbs-table .dbs-sub td { font-weight: 600; background: rgba(118,118,128,.06); }
            .dbs-table .dbs-total td { font-weight: 700; background: var(--dbs-fill); border-top: 1px solid rgba(60,60,67,.28); }
            .dbs-table .dbs-empty td { text-align: center; color: var(--dbs-muted); font-style: italic; padding: 18px 12px; }

            /* Salesman table: scrolls sideways on tablets, one card per salesman on phones */
            .dbs-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .dbs-salesmen { min-width: 920px; }
            .dbs-salesmen thead th { position: sticky; top: 0; z-index: 1; }
            .dbs-salesmen .dbs-name { font-weight: 500; white-space: nowrap; }
            /* # and the name stay in view while the figures scroll sideways */
            @media (min-width: 768px) {
                .dbs-salesmen .dbs-idx { position: sticky; left: 0; z-index: 2; width: 40px; min-width: 40px; background: #fff; }
                .dbs-salesmen .dbs-name, .dbs-salesmen thead th:nth-child(2) { position: sticky; left: 40px; z-index: 2; background: #fff; box-shadow: 1px 0 0 var(--dbs-line); }
                .dbs-salesmen thead th.dbs-idx, .dbs-salesmen thead th:nth-child(2) { z-index: 3; background: var(--dbs-fill); }
                .dbs-salesmen tfoot td.dbs-idx, .dbs-salesmen tfoot td.dbs-name { background: var(--dbs-fill); }
            }
            .dbs-company { font-size: 12px; font-weight: 400; color: var(--dbs-muted); }
            .dbs-name .dbs-company { display: none; }
            @media (max-width: 767px) {
                .dbs-salesmen { min-width: 0; font-size: 13px; }
                .dbs-salesmen thead { display: none; }
                .dbs-salesmen, .dbs-salesmen tbody, .dbs-salesmen tfoot { display: block; }
                .dbs-salesmen tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 12px; padding: 12px 14px; border-top: 1px solid var(--dbs-line); }
                .dbs-salesmen tbody tr:hover td { background: transparent; }
                .dbs-salesmen td { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; padding: 3px 0; border: 0; min-width: 0; }
                .dbs-salesmen td[data-label]::before { content: attr(data-label); font-size: 12px; font-weight: 400; color: var(--dbs-muted); white-space: nowrap; }
                .dbs-salesmen td.dbs-idx { display: none; }
                .dbs-salesmen td.dbs-name { grid-column: 1 / -1; justify-content: flex-start; font-size: 15px; font-weight: 600; padding-bottom: 6px; white-space: normal; }
                .dbs-salesmen td.dbs-name::before { content: attr(data-index); color: var(--dbs-muted); font-weight: 500; font-size: 13px; min-width: 22px; }
                .dbs-salesmen td.dbs-company-cell { display: none; }
                .dbs-salesmen .dbs-name .dbs-company { display: inline; margin-left: 6px; }
                .dbs-salesmen .dbs-empty td { grid-column: 1 / -1; justify-content: center; }
                .dbs-salesmen td[data-label="Credit"], .dbs-salesmen td.dbs-cash { margin-top: 4px; padding-top: 8px; border-top: 1px dashed var(--dbs-line) !important; font-weight: 600; }
                .dbs-salesmen tfoot tr { background: var(--dbs-fill); }
                .dbs-salesmen tfoot td { background: transparent !important; border: 0 !important; }
                .dbs-salesmen tfoot td.dbs-total-label { grid-column: 1 / -1; justify-content: flex-start; font-size: 15px; padding-bottom: 6px; }
                .dbs-salesmen tfoot td.dbs-name::before { display: none; }
                .dbs-section-head { flex-direction: column; align-items: flex-start; gap: 2px; }
                .dbs-section-note { white-space: normal; }
            }
            @media (max-width: 359px) {
                .dbs-salesmen tr { grid-template-columns: minmax(0, 1fr); }
                .dbs-table th, .dbs-table td { padding-left: 10px; padding-right: 10px; }
                .dbs-kpis { gap: 8px; }
                .dbs-kpi { padding: 12px; }
            }
            .dbs-footnote { font-size: 12px; line-height: 1.5; color: var(--dbs-muted); padding: 0 4px; }

            .print-only { display: none; }

            @media print {
                @page { margin: 8mm; size: A4 landscape; }
                .no-print, #dbs-filter-panel, .rg-gi-filters { display: none !important; }
                body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
                .max-w-7xl { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
                .print-only { display: block !important; }
                .dbs { color: #000; font-size: 10px; }
                .dbs-card { box-shadow: none !important; border-radius: 0; border: 1px solid #000; }
                .dbs-head { padding: 4px; }
                .dbs-head-title { font-size: 15px; }
                .dbs-pill { background: none; padding: 0 6px; font-size: 10px; }
                .dbs-kpis { grid-template-columns: repeat(4, 1fr) !important; gap: 0 !important; }
                .dbs-kpi { padding: 4px 6px; border-left: 0; }
                .dbs-kpi-value { font-size: 13px; }
                .dbs-grid { grid-template-columns: repeat(4, 1fr) !important; gap: 6px !important; }
                .dbs-section-head { padding: 3px 6px; background: #f3f4f6; border-bottom: 1px solid #000; }
                .dbs-section-title { font-size: 10px; font-weight: 800; text-transform: uppercase; }
                .dbs-section-note { font-size: 9px; }
                .dbs-table { font-size: 9.5px; }
                .dbs-table th, .dbs-table td { padding: 2px 4px; border: 1px solid #000 !important; color: #000 !important; background: none !important; }
                .dbs-table thead th { background: #f3f4f6 !important; }
                .dbs-scroll { overflow: visible !important; }
                .dbs-salesmen { min-width: 0 !important; }
                .dbs-gap { margin-top: 6px !important; }
                .dbs-footnote { display: none; }
            }
        </style>
    @endpush

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 no-print">
        <section id="dbs-filter-panel" class="ak-card rg-gi-filters" aria-label="Filters" x-data="{ open: {{ $hasQuery ? 'false' : 'true' }} }"
            @gl-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $hasQuery ? 'display:none' : '' }}">
            <form method="GET" action="{{ route('reports.daily-business-summary.index') }}" class="ak-filters" x-data="{ busy: false }" @submit="busy = true">
                <div class="gl-filter-row">
                    <div class="ak-field">
                        <label for="date">Date</label>
                        <input id="date" type="date" name="date" value="{{ $date }}" max="{{ $today }}" required>
                    </div>
                    <div class="ak-field">
                        <label for="supplier_id">Company</label>
                        <select id="supplier_id" name="supplier_id" class="gl-select" data-placeholder="Select company">
                            <option value=""></option>
                            @if ($canChooseCompany)
                                <option value="{{ \App\Http\Controllers\Reports\DailyBusinessSummaryController::ALL_COMPANIES }}" @selected($allCompanies)>All Companies</option>
                            @endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected($supplierId == $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="designation">Designation</label>
                        <select id="designation" name="designation" class="gl-select" data-placeholder="All designations">
                            <option value=""></option>
                            @foreach ($designations as $desig)
                                <option value="{{ $desig }}" @selected($designation === $desig)>{{ $desig }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="employee_ids">Salesmen</label>
                        <select id="employee_ids" name="employee_ids[]" multiple class="gl-select" data-placeholder="All salesmen">
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(in_array((string) $employee->id, $selectedEmployees, true))>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Loading…</span>
                        </button>
                    </div>
                </div>
                @if ($hasQuery)
                    <nav class="gl-ranges" aria-label="Quick dates">
                        <span class="gl-ranges-label">Quick dates</span>
                        @foreach ($quickDates as $label => $day)
                            <a href="{{ $dateUrl($day) }}" @if ($date === $day) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                @endif
            </form>
        </section>
    </div>

    @unless ($hasQuery)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 mt-4 no-print">
            <div class="dbs">
                <div class="dbs-card p-10 text-center">
                    <p class="text-lg font-semibold">No company selected</p>
                    <p class="mt-1 text-sm" style="color:#6e6e73">Choose a company{{ $canChooseCompany ? ' (or All Companies)' : '' }} and the date above, then click Apply to generate the Daily Business Summary.</p>
                </div>
            </div>
        </div>
    @else
        @php
            $tts = $report['tts'];
            $investment = $report['investment'];
            $totals = $report['totals'];
            $categoryTotal = $report['categories']->sum('amount');
            $groupTotal = array_sum($report['category_groups']);
            $period = \Carbon\Carbon::parse($report['month_start'])->format('d M').' – '.$reportDate->format('d M');
            $investmentRows = [
                'Powder Expiry' => $investment['powder_expiry'],
                'Liquid Expiry' => $investment['liquid_expiry'],
                'Claim Amount' => $investment['claims'],
                'Stock Amount' => $investment['stock'],
                'Credit Amount' => $investment['credit'],
                'Ledger Amount' => $investment['ledger'],
            ];
            $salesmanColumns = $allCompanies ? 11 : 10;
        @endphp

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 mt-4">
            <div class="dbs space-y-4">
                {{-- Title --}}
                <header class="dbs-card dbs-head">
                    <div class="dbs-head-org">Shahzain Traders · Muzaffarabad</div>
                    <h1 class="dbs-head-title">Daily Business Summary</h1>
                    <div class="dbs-head-meta">
                        <span class="dbs-pill"><span>Distributor</span> <b>{{ $companyLabel }}</b></span>
                        <span class="dbs-pill"><span>Date</span> <b>{{ $reportDate->format('d.m.Y') }}</b></span>
                        @if ($designation !== '')
                            <span class="dbs-pill"><span>Designation</span> <b>{{ $designation }}</b></span>
                        @endif
                    </div>
                    <p class="print-only" style="font-size:9px;margin-top:4px">Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}</p>
                </header>

                {{-- Headline figures --}}
                <section class="dbs-kpis dbs-gap" aria-label="Headline figures">
                    <div class="dbs-card dbs-kpi">
                        <div class="dbs-kpi-label">Total Sales</div>
                        <div class="dbs-kpi-value" title="{{ number_format($report['total_sales_mtd'], 2) }}">{{ number_format($report['total_sales_mtd'], 2) }}</div>
                        <div class="dbs-kpi-sub">Month to date</div>
                    </div>
                    <div class="dbs-card dbs-kpi">
                        <div class="dbs-kpi-label">Today's Cash</div>
                        <div class="dbs-kpi-value" title="{{ number_format($totals['cash'], 2) }}">{{ number_format($totals['cash'], 2) }}</div>
                        <div class="dbs-kpi-sub">Cash + bank slips</div>
                    </div>
                    <div class="dbs-card dbs-kpi">
                        <div class="dbs-kpi-label">Total Credit</div>
                        <div class="dbs-kpi-value" title="{{ number_format($totals['credit'], 2) }}">{{ number_format($totals['credit'], 2) }}</div>
                        <div class="dbs-kpi-sub">Closing market credit</div>
                    </div>
                    <div class="dbs-card dbs-kpi">
                        <div class="dbs-kpi-label">Total Discount</div>
                        <div class="dbs-kpi-value" title="{{ number_format($totals['discount'], 2) }}">{{ number_format($totals['discount'], 2) }}</div>
                        <div class="dbs-kpi-sub">Today</div>
                    </div>
                </section>

                <div class="dbs-grid dbs-gap">
                    {{-- Investment position --}}
                    <section class="dbs-card dbs-section" aria-labelledby="dbs-investment">
                        <div class="dbs-section-head">
                            <h2 id="dbs-investment" class="dbs-section-title">Investment Position <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'investment' })" aria-label="What Investment Position means" title="What Investment Position means">i</button></h2>
                            <span class="dbs-section-note">As on {{ $reportDate->format('d M') }}</span>
                        </div>
                        <table class="dbs-table">
                            <thead><tr><th class="dbs-idx">#</th><th>Head</th><th class="dbs-num">Amount (PKR)</th></tr></thead>
                            <tbody>
                                @foreach ($investmentRows as $label => $amount)
                                    <tr><td class="dbs-idx">{{ $loop->iteration }}</td><td>{{ $label }}</td><td class="dbs-num">{{ number_format($amount, 2) }}</td></tr>
                                @endforeach
                                <tr class="dbs-total"><td class="dbs-idx"></td><td>Total Investment</td><td class="dbs-num">{{ number_format($investment['total'], 2) }}</td></tr>
                            </tbody>
                        </table>
                    </section>

                    {{-- TTS, month to date --}}
                    <section class="dbs-card dbs-section" aria-labelledby="dbs-tts">
                        <div class="dbs-section-head">
                            <h2 id="dbs-tts" class="dbs-section-title">TTS Summary <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'tts' })" aria-label="How the TTS balance is worked out" title="How the TTS balance is worked out">i</button></h2>
                            <span class="dbs-section-note">Month to date · {{ $period }}</span>
                        </div>
                        <table class="dbs-table">
                            <thead><tr><th class="dbs-idx">#</th><th>Head</th><th class="dbs-num">Amount (PKR)</th></tr></thead>
                            <tbody>
                                <tr><td class="dbs-idx">1</td><td>TTS Received</td><td class="dbs-num">{{ number_format($tts['tts_received'], 2) }}</td></tr>
                                <tr><td class="dbs-idx">2</td><td>Promo Received</td><td class="dbs-num">{{ number_format($tts['promo_received'], 2) }}</td></tr>
                                <tr class="dbs-sub"><td class="dbs-idx"></td><td>Total Received</td><td class="dbs-num">{{ number_format($tts['total_received'], 2) }}</td></tr>
                                <tr><td class="dbs-idx">3</td><td>TTS Passed</td><td class="dbs-num">{{ number_format($tts['tts_passed'], 2) }}</td></tr>
                                <tr><td class="dbs-idx">4</td><td>Promo Passed</td><td class="dbs-num">{{ number_format($tts['promo_passed'], 2) }}</td></tr>
                                <tr class="dbs-sub"><td class="dbs-idx"></td><td>Total Scheme Passed</td><td class="dbs-num">{{ number_format($tts['total_passed'], 2) }}</td></tr>
                                <tr><td class="dbs-idx">5</td><td>Percentage Passed</td><td class="dbs-num">{{ number_format($tts['percentage_passed'], 2) }}</td></tr>
                                <tr class="dbs-total"><td class="dbs-idx"></td><td>Balance</td><td class="dbs-num {{ $tts['balance'] < 0 ? 'dbs-neg' : '' }}">{{ $signed($tts['balance']) }}</td></tr>
                            </tbody>
                        </table>
                    </section>

                    {{-- Category-wise sales, month to date --}}
                    <section class="dbs-card dbs-section" aria-labelledby="dbs-categories">
                        <div class="dbs-section-head">
                            <h2 id="dbs-categories" class="dbs-section-title">Category-wise Sales <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'categories' })" aria-label="What Category-wise Sales means" title="What Category-wise Sales means">i</button></h2>
                            <span class="dbs-section-note">{{ $period }}</span>
                        </div>
                        <table class="dbs-table">
                            <thead><tr><th class="dbs-idx">#</th><th>Category</th><th class="dbs-num">Sales (PKR)</th></tr></thead>
                            <tbody>
                                @forelse ($report['categories'] as $category)
                                    <tr><td class="dbs-idx">{{ $loop->iteration }}</td><td>{{ $category->name }}</td><td class="dbs-num">{{ number_format($category->amount, 2) }}</td></tr>
                                @empty
                                    <tr class="dbs-empty"><td colspan="3">No sales this month</td></tr>
                                @endforelse
                                <tr class="dbs-total"><td class="dbs-idx"></td><td>Total Sales</td><td class="dbs-num">{{ number_format($categoryTotal, 2) }}</td></tr>
                            </tbody>
                        </table>
                    </section>

                    {{-- Category groups, month to date --}}
                    <section class="dbs-card dbs-section" aria-labelledby="dbs-groups">
                        <div class="dbs-section-head">
                            <h2 id="dbs-groups" class="dbs-section-title">Category Group Summary <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'categories' })" aria-label="Which categories make each group" title="Which categories make each group">i</button></h2>
                            <span class="dbs-section-note">{{ $period }}</span>
                        </div>
                        <table class="dbs-table">
                            <thead><tr><th class="dbs-idx">#</th><th>Group</th><th class="dbs-num">Sales (PKR)</th></tr></thead>
                            <tbody>
                                @foreach ($report['category_groups'] as $group => $amount)
                                    <tr><td class="dbs-idx">{{ $loop->iteration }}</td><td>{{ $group }}</td><td class="dbs-num">{{ number_format($amount, 2) }}</td></tr>
                                @endforeach
                                <tr class="dbs-total"><td class="dbs-idx"></td><td>Total</td><td class="dbs-num">{{ number_format($groupTotal, 2) }}</td></tr>
                            </tbody>
                        </table>
                    </section>
                </div>

                {{-- Salesman-wise performance for the day --}}
                <section class="dbs-card dbs-section dbs-gap" aria-labelledby="dbs-salesmen">
                    <div class="dbs-section-head">
                        <h2 id="dbs-salesmen" class="dbs-section-title">Daily Salesman-wise Performance, Credit &amp; Cash <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'salesmen' })" aria-label="What each salesman column means" title="What each salesman column means">i</button></h2>
                        <span class="dbs-section-note">{{ $reportDate->format('d M Y') }} · {{ $report['salesmen']->count() }} {{ \Illuminate\Support\Str::plural('salesman', $report['salesmen']->count()) }}</span>
                    </div>
                    <div class="dbs-scroll">
                        <table class="dbs-table dbs-salesmen">
                            <thead>
                                <tr>
                                    <th class="dbs-idx">#</th>
                                    <th>Salesman</th>
                                    @if ($allCompanies)
                                        <th>Company</th>
                                    @endif
                                    <th class="dbs-num">Sales (PKR)</th>
                                    <th class="dbs-num">%age</th>
                                    <th class="dbs-num">Scheme</th>
                                    <th class="dbs-num">Total Discount</th>
                                    <th class="dbs-num">Expiry Liquid</th>
                                    <th class="dbs-num">Expiry Powder</th>
                                    <th class="dbs-num">Credit (PKR)</th>
                                    <th class="dbs-num">Amount (PKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($report['salesmen'] as $row)
                                    <tr>
                                        <td class="dbs-idx">{{ $loop->iteration }}</td>
                                        <td class="dbs-name" data-index="{{ $loop->iteration }}.">
                                            {{ $row->name }}
                                            @if ($allCompanies && $row->company)
                                                <span class="dbs-company">{{ $row->company }}</span>
                                            @endif
                                        </td>
                                        @if ($allCompanies)
                                            <td class="dbs-company-cell">{{ $row->company }}</td>
                                        @endif
                                        <td class="dbs-num" data-label="Sales">{{ $money($row->sales) }}</td>
                                        <td class="dbs-num" data-label="%age">{{ $money($row->percentage) }}</td>
                                        <td class="dbs-num" data-label="Scheme">{{ $money($row->scheme) }}</td>
                                        <td class="dbs-num" data-label="Discount">{{ $money($row->discount) }}</td>
                                        <td class="dbs-num" data-label="Exp. Liquid">{{ $money($row->expiry_liquid) }}</td>
                                        <td class="dbs-num" data-label="Exp. Powder">{{ $money($row->expiry_powder) }}</td>
                                        <td class="dbs-num" data-label="Credit">{{ $money($row->credit) }}</td>
                                        <td class="dbs-num dbs-cash" data-label="Amount">{{ $money($row->cash) }}</td>
                                    </tr>
                                @empty
                                    <tr class="dbs-empty"><td colspan="{{ $salesmanColumns }}">No salesman data for this date</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="dbs-total">
                                    <td class="dbs-idx"></td>
                                    <td class="dbs-name dbs-total-label">Total</td>
                                    @if ($allCompanies)
                                        <td class="dbs-company-cell"></td>
                                    @endif
                                    <td class="dbs-num" data-label="Sales">{{ number_format($totals['sales'], 2) }}</td>
                                    <td class="dbs-num" data-label="%age">{{ number_format($totals['percentage'], 2) }}</td>
                                    <td class="dbs-num" data-label="Scheme">{{ number_format($totals['scheme'], 2) }}</td>
                                    <td class="dbs-num" data-label="Discount">{{ number_format($totals['discount'], 2) }}</td>
                                    <td class="dbs-num" data-label="Exp. Liquid">{{ number_format($totals['expiry_liquid'], 2) }}</td>
                                    <td class="dbs-num" data-label="Exp. Powder">{{ number_format($totals['expiry_powder'], 2) }}</td>
                                    <td class="dbs-num" data-label="Credit">{{ number_format($totals['credit'], 2) }}</td>
                                    <td class="dbs-num dbs-cash" data-label="Amount">{{ number_format($totals['cash'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <p class="dbs-footnote no-print">
                    Sales by category and the TTS summary run from the 1st of the month to the report date. Salesman figures are for the report date; Credit is each salesman's closing market credit. %age = advance tax (1161); Scheme = scheme discount (5292) + Promotion OFF (5288); Amount = cash counted + bank slips.
                </p>
            </div>
        </div>
    @endunless

    @push('scripts')
        <script>
            // The header's filter button opens this page's own filter panel, like the goods issues list.
            document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('gl-toggle-filters')); });

            // Searchable dropdowns: type to find a company, designation or salesman.
            $(function () {
                $('.gl-select').each(function () {
                    const $select = $(this);
                    $select.select2({
                        width: '100%',
                        placeholder: $select.data('placeholder') || 'Select',
                        allowClear: true,
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
