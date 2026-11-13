{{--
    One month's payroll (/payroll/{uuid}). Same shell as the Payroll hub (tab bar, Apple skin). Every payslip opens in a
    sheet with Earnings on the left and Deductions on the right; the maker adjusts it there (absent days, a line for this
    month, waive the settlement short). Approve / reject / pay / delete happen in sheets on this page.
--}}
@php
    $user = auth()->user();
    $isMaker = (int) $run->created_by === $user->id;
    $isSubmitter = (int) $run->submitted_by === $user->id;
    $editable = $run->isEditable() && $isMaker && $user->can('payroll-create');
    $statusTone = ['draft' => 'grey', 'pending' => 'amber', 'approved' => 'blue', 'paid' => 'green', 'rejected' => 'red'][$run->status] ?? 'grey';
    $statusText = $run->status === 'paid' && $run->paid_on ? 'Paid on '.$run->paid_on->format('d-M-Y') : $run->statusLabel();
    $step = ['draft' => 1, 'rejected' => 1, 'pending' => 2, 'approved' => 3, 'paid' => 4][$run->status] ?? 1;
    $slips = $run->slips->sortBy(fn ($slip) => $slip->employee?->name)->values();
    // A payroll for all of Moon Traders is shown supplier by supplier (where each employee works), with a subtotal for each.
    $groups = $run->supplier_id ? collect(['' => $slips]) : $slips->groupBy(fn ($slip) => $slip->employee?->supplier?->supplier_name ?? 'Moon Traders (head office)')->sortKeys();
    $ordered = $groups->flatten(1)->values();
    $fmt = fn ($value) => number_format((float) $value, 2);
    $fmt0 = fn ($value) => number_format((float) $value);
    $days = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.') ?: '0';
    $sourceLabels = [
        'basic' => 'Basic', 'allowance' => 'Allowance', 'absent' => 'Absent days', 'advance' => 'Advance', 'manual' => 'Added by hand',
        'adjustment' => 'Bonus/deduction', 'excess' => 'Settlement excess (old)', 'short' => 'Settlement short',
    ];
    $tabNames = ['runs' => 'Payroll runs', 'salaries' => 'Employee salaries', 'adjustments' => 'Bonus & deductions', 'categories' => 'Categories'];
    $tabs = collect(\App\Http\Controllers\PayrollHubController::TABS)->filter(fn (string $permission) => $user->can($permission))->keys();

    $slipData = $ordered->map(fn ($slip) => [
        'id' => $slip->id,
        'name' => $slip->employee?->name ?? '—',
        'code' => $slip->employee?->employee_code,
        'designation' => $slip->employee?->designation,
        'supplier' => $slip->employee?->supplier?->supplier_name,
        'salary_type' => $slip->salary_type,
        'days_in_month' => (int) $slip->days_in_month,
        'absent_days' => (float) $slip->absent_days,
        'recover_settlement_short' => (bool) $slip->recover_settlement_short,
        'has_short' => $slip->lines->contains('source', 'short'),
        'earnings' => $fmt($slip->total_earnings),
        'deductions' => $fmt($slip->total_deductions),
        'net' => $fmt($slip->net_pay),
        'negative' => (float) $slip->net_pay < 0,
        'lines' => $slip->lines->map(fn ($line) => [
            'id' => $line->id,
            'kind' => $line->kind,
            'source' => $line->source,
            'source_label' => $sourceLabels[$line->source] ?? \Illuminate\Support\Str::headline($line->source),
            'label' => $line->label,
            'amount' => $fmt($line->amount),
            'remove_url' => $line->source === 'manual' ? route('payroll.lines.destroy', [$run, $line]) : null,
        ])->values(),
        'urls' => [
            'update' => route('payroll.slips.update', [$run, $slip]),
            'lines' => route('payroll.lines.store', [$run, $slip]),
            'short' => route('payroll.slips.short', [$run, $slip]),
            'pdf' => route('payroll.slip.pdf', [$run, $slip]),
            'print' => route('payroll.slips.show', [$run, $slip]),
        ],
    ]);
    $openId = (int) (request('slip') ?: old('_slip'));
    $openIndex = $openId ? $slipData->search(fn ($slip) => $slip['id'] === $openId) : false;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div class="min-w-0">
                <a href="{{ route('payroll.index', ['tab' => 'runs']) }}" class="pr-back no-print"><span aria-hidden="true">‹</span> Payroll runs</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Payroll · {{ $run->period_month->format('F Y') }}</h2>
                <p class="pr-head-sub">{{ $run->ownerLabel() }} · <span class="pr-mono">{{ $run->run_number }}</span> · <span class="pr-cap pr-cap-{{ $statusTone }}">{{ $statusText }}</span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2 no-print">
                <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'sheet' })" aria-label="Help: downloads and the salary sheet" title="Help: downloads and the salary sheet">i</button>
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button type="button" class="pr-head-btn" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        Download
                    </button>
                    <div class="pr-row-menu pr-menu-abs" role="menu" x-show="open" x-cloak x-transition.opacity.duration.120ms>
                        <a role="menuitem" href="{{ route('payroll.sheet.pdf', $run) }}"><b>Salary sheet (PDF)</b></a>
                        <a role="menuitem" href="{{ route('payroll.sheet.excel', $run) }}">Salary sheet (Excel)</a>
                        <a role="menuitem" href="{{ route('payroll.slips.pdf', $run) }}">All payslips (PDF)</a>
                        <a role="menuitem" href="{{ route('payroll.export.pdf', $run) }}">Payroll register (PDF)</a>
                        <a role="menuitem" href="{{ route('payroll.export.excel', $run) }}">Payroll register (Excel)</a>
                        <a role="menuitem" href="{{ route('payroll.print', $run) }}" target="_blank" rel="noopener">Print all payslips</a>
                    </div>
                </div>
                <button type="button" class="pr-head-btn" x-data @click="$dispatch('rg-help', { topic: null })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How payroll works, in Urdu">Help</button>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="payroll" />
    @include('payroll.hub._styles')
    <style>
        .pr-back { display: inline-flex; align-items: center; gap: 4px; margin-bottom: 2px; font-size: 14px; color: var(--pr-blue); text-decoration: none; }
        .pr-back:hover { text-decoration: underline; }
        .pr-head-sub { margin: 4px 0 0; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 14px; color: var(--pr-muted); }
        .pr-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
        .pr-menu-abs { position: absolute; right: 0; top: calc(100% + 6px); min-width: 230px; }
        .pr-steps { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; margin: 0; padding: 14px 18px; list-style: none; }
        .pr-steps li { display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 500; color: var(--pr-muted); }
        .pr-steps li + li::before { content: ''; width: 22px; height: 2px; border-radius: 1px; background: var(--pr-sep); }
        .pr-steps .dot { width: 22px; height: 22px; border-radius: 50%; display: inline-grid; place-items: center; font-size: 12px; font-weight: 600; background: var(--pr-fill); color: var(--pr-muted); }
        .pr-steps li.is-done { color: var(--pr-green); } .pr-steps li.is-done .dot { background: #34c759; color: #fff; }
        .pr-steps li.is-now { color: var(--pr-text); font-weight: 600; } .pr-steps li.is-now .dot { background: var(--pr-blue); color: #fff; }
        .pr-banner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; border-radius: 16px; font-size: 14px; }
        .pr-banner-blue { background: rgba(0,113,227,.08); color: #0b3f7a; } .pr-banner-amber { background: var(--pr-amber-bg); color: var(--pr-amber); }
        .pr-banner-red { background: var(--pr-red-bg); color: #9b1117; }
        .pr-banner a { color: inherit; font-weight: 600; }
        .pr-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 12px 16px; }
        .pr-actions .pr-hint { margin: 0; font-size: 14px; color: var(--pr-muted); }
        .pr-actions .pr-push { margin-left: auto; }
        .pr-group td { background: #f5f5f7 !important; font-size: 13px; color: #3a3a40; }
        .pr-muted-inline { color: var(--pr-muted); }
        .pr-emp b { font-weight: 600; color: var(--pr-text); }
        .pr-emp small { display: block; color: var(--pr-muted); font-size: 12.5px; }
        .pr-flag { display: inline-flex; margin: 3px 4px 0 0; padding: 1px 8px; border-radius: 980px; font-size: 11.5px; font-weight: 600; }
        .pr-flag-red { background: var(--pr-red-bg); color: var(--pr-red); } .pr-flag-amber { background: var(--pr-amber-bg); color: var(--pr-amber); } .pr-flag-grey { background: var(--pr-grey-bg); color: #3a3a40; }
        .pr-slip-row { cursor: pointer; }
        .pr-search { height: 36px; width: 240px; max-width: 100%; padding: 0 12px; border: 0 !important; border-radius: 10px !important; background: var(--pr-fill) !important; font-size: 14px; }
        .pr-search:focus { outline: none; background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px var(--pr-blue) !important; }
        .pr-lines { margin: 0; padding: 0; list-style: none; }
        .pr-lines li { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(60,60,67,.08); font-size: 14px; }
        .pr-lines li:last-child { border-bottom: 0; }
        .pr-lines .lbl { min-width: 0; overflow-wrap: anywhere; }
        .pr-lines .src { display: block; font-size: 11.5px; color: var(--pr-muted); }
        .pr-lines .amt { flex: none; display: inline-flex; align-items: center; gap: 4px; font-variant-numeric: tabular-nums; }
        .pr-lines .none { color: var(--pr-muted); }
        .pr-rm { width: 24px; height: 24px; display: inline-grid; place-items: center; border: 0; border-radius: 50%; background: var(--pr-red-bg); color: #d70015; font-size: 15px; line-height: 1; cursor: pointer; }
        .pr-meta { display: grid; gap: 8px 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0 0 16px; }
        @media (min-width: 640px) { .pr-meta { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .pr-meta dt { font-size: 12px; color: var(--pr-muted); } .pr-meta dd { margin: 1px 0 0; font-size: 14px; font-weight: 600; }
        .pr-edit { margin-top: 16px; padding: 14px; border-radius: 16px; background: #fbfbfd; box-shadow: inset 0 0 0 1px var(--pr-sep); }
        .pr-edit h4 { margin: 0 0 10px; font-size: 14px; font-weight: 600; }
        .pr-edit-row { display: grid; gap: 10px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 640px) { .pr-edit-row { grid-template-columns: 2fr 1fr 2fr auto; } .pr-edit-row.is-small { grid-template-columns: 160px auto 1fr; } }
        .pr-switch { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; padding: 12px 14px; border-radius: 14px; background: #fff; box-shadow: inset 0 0 0 1px var(--pr-sep); }
        .pr-switch p { margin: 0; font-size: 14px; } .pr-switch small { display: block; color: var(--pr-muted); font-size: 12.5px; }
        .pr-toggle { position: relative; flex: none; width: 51px; height: 31px; border: 0; border-radius: 16px; background: rgba(120,120,128,.32); cursor: pointer; transition: background-color .2s ease; }
        .pr-toggle::after { content: ''; position: absolute; top: 2px; left: 2px; width: 27px; height: 27px; border-radius: 50%; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.2); transition: transform .2s cubic-bezier(.23,1,.32,1); }
        .pr-toggle.is-on { background: #34c759; } .pr-toggle.is-on::after { transform: translateX(20px); }
        .pr-toggle:focus-visible { outline: 2px solid var(--pr-blue); outline-offset: 2px; }
        .pr-nav { display: inline-flex; gap: 6px; }
        .pr-ledger { display: flex; flex-wrap: wrap; gap: 6px 18px; padding: 14px 18px; font-size: 13.5px; color: var(--pr-muted); }
        .pr-ledger a { color: var(--pr-blue); }
        @media screen and (max-width: 767px) {
            .pr-group td { display: block !important; }
            .pr-table.pr-cards tr.pr-group { padding: 8px 14px; }
            .pr-search { width: 100%; }
        }
    </style>

    <div class="ak-page pr-hub" x-data="prRun(@js($slipData), @js($openIndex === false ? null : $openIndex))"
        @keydown.escape.window="close()" @keydown.arrow-right.window="step($event, 1)" @keydown.arrow-left.window="step($event, -1)">
        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <nav class="pr-tabs no-print" aria-label="Payroll sections">
            @foreach ($tabs as $key)
                <a href="{{ route('payroll.index', ['tab' => $key]) }}" class="pr-tab {{ $key === 'runs' ? 'is-active' : '' }}" @if ($key === 'runs') aria-current="page" @endif>{{ $tabNames[$key] }}</a>
            @endforeach
        </nav>

        <section class="pr-card" aria-label="Progress">
            <ol class="pr-steps">
                <li class="{{ $step > 1 ? 'is-done' : 'is-now' }}"><span class="dot">{{ $step > 1 ? '✓' : '1' }}</span> Prepared by {{ $run->creator?->name ?? '—' }}</li>
                <li class="{{ $step > 2 ? 'is-done' : ($step === 2 ? 'is-now' : '') }}"><span class="dot">{{ $step > 2 ? '✓' : '2' }}</span> Checker approves</li>
                <li class="{{ $step > 3 ? 'is-done' : ($step === 3 ? 'is-now' : '') }}"><span class="dot">{{ $step > 3 ? '✓' : '3' }}</span> Posted to ledger</li>
                <li class="{{ $step >= 4 ? 'is-done' : '' }}"><span class="dot">{{ $step >= 4 ? '✓' : '4' }}</span> Paid{{ $run->paid_on ? ' on '.$run->paid_on->format('d M Y') : '' }} <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'lifecycle' })" aria-label="Help: how a payroll moves from draft to paid" title="Help: how a payroll moves from draft to paid">i</button></li>
            </ol>
        </section>

        @if ($run->status === 'rejected' && $run->rejection_reason)
            <div class="pr-banner pr-banner-red" role="alert"><span><b>Rejected by {{ $run->checker?->name ?? 'the checker' }}:</b> {{ $run->rejection_reason }}</span></div>
        @endif

        <div class="pr-stats">
            <div class="pr-card pr-stat"><small>Employees</small><b>{{ $slips->count() }}</b></div>
            <div class="pr-card pr-stat"><small>Total earnings <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'payslip' })" aria-label="Help: earnings" title="Help: earnings">i</button></small><b class="is-earn">{{ $fmt($run->total_earnings) }}</b></div>
            <div class="pr-card pr-stat"><small>Total deductions <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'deductions' })" aria-label="Help: deductions" title="Help: deductions">i</button></small><b class="is-ded">{{ $fmt($run->total_deductions) }}</b></div>
            <div class="pr-card pr-stat"><small>Net to pay <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'payslip' })" aria-label="Help: net pay" title="Help: net pay">i</button></small><b>{{ $fmt($run->total_net) }}</b></div>
        </div>

        @if ($editable && $missing->isNotEmpty())
            <div class="pr-banner pr-banner-blue">
                <span><b>{{ $missing->count() }}</b> {{ \Illuminate\Support\Str::plural('employee', $missing->count()) }} can be paid but {{ $missing->count() === 1 ? 'is' : 'are' }} not in this payroll yet: {{ $missing->take(5)->pluck('name')->join(', ') }}{{ $missing->count() > 5 ? ' and '.($missing->count() - 5).' more' : '' }}.</span>
                <form method="POST" action="{{ route('payroll.add-employees', $run) }}" x-data="{ busy: false }" @submit="busy = true">@csrf<button class="pr-btn pr-btn-primary pr-btn-sm" :disabled="busy">Add to this payroll</button></form>
            </div>
        @endif

        @if ($run->isEditable() && $withoutSalary->isNotEmpty())
            <div class="pr-banner pr-banner-amber">
                <span>{{ $withoutSalary->count() }} active {{ \Illuminate\Support\Str::plural('employee', $withoutSalary->count()) }} {{ $withoutSalary->count() === 1 ? 'has' : 'have' }} no salary set and {{ $withoutSalary->count() === 1 ? 'is' : 'are' }} not in this payroll: {{ $withoutSalary->take(6)->pluck('name')->join(', ') }}{{ $withoutSalary->count() > 6 ? ' and '.($withoutSalary->count() - 6).' more' : '' }}.</span>
                @can('payroll-salary-manage')<a href="{{ route('payroll.index', ['tab' => 'salaries', 'filter' => ['salary' => 'missing']]) }}">Set their salaries ›</a>@endcan
            </div>
        @endif

        <section class="pr-card no-print" aria-label="Actions">
            <div class="pr-actions">
                @if ($run->isEditable())
                    @if ($editable)
                        <button type="button" class="pr-btn pr-btn-primary" @click="open('variable')" title="Enter this month's achievement items, overtime, fines and absent days for everyone in one grid">Enter variable pay</button>
                        <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'grid' })" aria-label="Help: the variable pay grid" title="Help: the variable pay grid">i</button>
                        <form method="POST" action="{{ route('payroll.recalculate', $run) }}" x-data="{ busy: false }" @submit="busy = true">@csrf
                            <button class="pr-btn" :disabled="busy" title="Rebuild every payslip from the latest salary set-up, advances and settlement shorts. Lines you added by hand are kept.">Recalculate</button>
                        </form>
                        <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'recalc' })" aria-label="Help: recalculate" title="Help: recalculate">i</button>
                        @if ($missing->isNotEmpty())
                            <form method="POST" action="{{ route('payroll.add-employees', $run) }}" x-data="{ busy: false }" @submit="busy = true">@csrf
                                <button class="pr-btn" :disabled="busy">Add missing employees ({{ $missing->count() }})</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('payroll.submit', $run) }}" x-data="{ busy: false }" @submit="busy = true">@csrf
                            <button class="pr-btn pr-btn-primary" :disabled="busy">Send for approval</button><button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'approve' })" aria-label="Help: sending for approval" title="Help: sending for approval">i</button>
                        </form>
                        <button type="button" class="pr-btn pr-btn-danger pr-push" @click="open('delete')">Delete payroll</button>
                    @else
                        <p class="pr-hint">Only {{ $run->creator?->name ?? 'the user who prepared it' }}, who prepared this payroll, can change it.</p>
                    @endif
                @elseif ($run->status === 'pending')
                    @if ($user->can('payroll-approve') && ! $isSubmitter)
                        <button type="button" class="pr-btn pr-btn-green" @click="open('approve')">Approve and post</button>
                        <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'approve' })" aria-label="Help: approving" title="Help: approving">i</button>
                        <button type="button" class="pr-btn pr-btn-danger" @click="open('reject')">Reject</button>
                    @elseif ($isSubmitter)
                        <p class="pr-hint">Waiting for a checker — you sent it, so someone else must approve.</p>
                    @else
                        <p class="pr-hint">Waiting for a checker to approve.</p>
                    @endif
                @elseif ($run->status === 'approved')
                    @can('payroll-pay')
                        <button type="button" class="pr-btn pr-btn-primary" @click="open('pay')">Pay salaries</button>
                        <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'pay' })" aria-label="Help: paying salaries" title="Help: paying salaries">i</button>
                        <p class="pr-hint">Approved by {{ $run->checker?->name ?? 'the checker' }}. Pay Rs {{ $fmt($run->total_net) }} by cash or bank.</p>
                    @else
                        <p class="pr-hint">Approved. Waiting for the salaries to be paid.</p>
                    @endcan
                @elseif ($run->status === 'paid')
                    <p class="pr-hint">Paid by {{ $run->paid_method === 'bank' ? 'bank ('.trim(($run->paidBankAccount?->bank_name ?? '').' '.($run->paidBankAccount?->account_number ?? '')).')' : 'cash' }} on {{ $run->paid_on?->format('d M Y') }}.</p>
                @endif
            </div>
        </section>

        <section class="pr-card" aria-label="Payslips">
            <div class="pr-toolbar">
                <p><b>{{ $slips->count() }}</b> {{ \Illuminate\Support\Str::plural('payslip', $slips->count()) }} · tap one to open it</p>
                <span class="pr-spacer"></span>
                <input type="search" class="pr-search no-print" x-model.debounce.150ms="q" placeholder="Search name or code" aria-label="Search payslips by name or code">
            </div>
            @if ($slips->isEmpty())
                <div class="pr-empty"><b>No payslips</b>Nobody in this payroll yet.</div>
            @else
                <div class="pr-scroll">
                    <table class="pr-table pr-cards">
                        <thead>
                            <tr>
                                <th scope="col">Employee</th>
                                <th scope="col" class="pr-num">Basic<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'basic' })" aria-label="Help: basic salary" title="Help: basic salary">i</button></th>
                                <th scope="col" class="pr-num">Earnings<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'payslip' })" aria-label="Help: earnings" title="Help: earnings">i</button></th>
                                <th scope="col" class="pr-num">Deductions<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'deductions' })" aria-label="Help: deductions" title="Help: deductions">i</button></th>
                                <th scope="col" class="pr-num">Net pay<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'payslip' })" aria-label="Help: net pay" title="Help: net pay">i</button></th>
                                <th scope="col" class="no-print"><span class="sr-only">Payslip</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $index = 0; @endphp
                            @foreach ($groups as $groupName => $groupSlips)
                                @if ($groupName !== '')
                                    <tr class="pr-group" x-show="! q">
                                        <td colspan="6"><b>{{ $groupName }}</b> · {{ $groupSlips->count() }} {{ \Illuminate\Support\Str::plural('employee', $groupSlips->count()) }} · net <b>{{ $fmt0($groupSlips->sum('net_pay')) }}</b> <span class="pr-muted-inline">(earnings {{ $fmt0($groupSlips->sum('total_earnings')) }}, deductions {{ $fmt0($groupSlips->sum('total_deductions')) }})</span></td>
                                    </tr>
                                @endif
                                @foreach ($groupSlips as $slip)
                                    @php
                                        $short = (float) $slip->lines->where('source', 'short')->sum('amount');
                                        $advance = (float) $slip->lines->where('source', 'advance')->sum('amount');
                                        $search = mb_strtolower(($slip->employee?->name ?? '').' '.($slip->employee?->employee_code ?? ''));
                                    @endphp
                                    <tr class="pr-slip-row" x-show="match(@js($search))" @click="openSlip({{ $index }})">
                                        <td data-label="Employee" class="pr-emp">
                                            <b>{{ $slip->employee?->name ?? '—' }}</b>
                                            <small>{{ $slip->employee?->employee_code }}{{ $slip->employee?->designation ? ' · '.$slip->employee->designation : '' }}</small>
                                            @if ($short > 0)<span class="pr-flag pr-flag-red">Short Rs {{ $fmt0($short) }}</span>@elseif (! $slip->recover_settlement_short)<span class="pr-flag pr-flag-grey">Short waived</span>@endif
                                            @if ($advance > 0)<span class="pr-flag pr-flag-amber">Advance Rs {{ $fmt0($advance) }}</span>@endif
                                            @if ((float) $slip->absent_days > 0)<span class="pr-flag pr-flag-grey">Absent {{ $days($slip->absent_days) }}</span>@endif
                                        </td>
                                        <td data-label="Basic" class="pr-num">{{ $fmt0($slip->basic_salary) }}</td>
                                        <td data-label="Earnings" class="pr-num pr-earn">{{ $fmt0($slip->total_earnings) }}</td>
                                        <td data-label="Deductions" class="pr-num pr-ded">{{ (float) $slip->total_deductions > 0 ? $fmt0($slip->total_deductions) : '—' }}</td>
                                        <td data-label="Net pay" class="pr-num"><b @class(['pr-ded' => (float) $slip->net_pay < 0])>{{ $fmt0($slip->net_pay) }}</b></td>
                                        <td data-label="" class="pr-num no-print"><button type="button" class="pr-btn pr-btn-sm" @click.stop="openSlip({{ $index }})" aria-haspopup="dialog" aria-label="Open the payslip of {{ $slip->employee?->name }}">Payslip</button></td>
                                    </tr>
                                    @php $index++; @endphp
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td data-label="">Total</td>
                                <td data-label="Basic" class="pr-num">{{ $fmt0($slips->sum('basic_salary')) }}</td>
                                <td data-label="Earnings" class="pr-num">{{ $fmt0($run->total_earnings) }}</td>
                                <td data-label="Deductions" class="pr-num">{{ $fmt0($run->total_deductions) }}</td>
                                <td data-label="Net pay" class="pr-num">{{ $fmt0($run->total_net) }}</td>
                                <td data-label="" class="no-print"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>

        @if ($run->journalEntry || $run->paymentJournalEntry)
            <section class="pr-card pr-ledger" aria-label="Ledger">
                <span>Ledger: <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'ledger' })" aria-label="Help: ledger entries" title="Help: ledger entries">i</button></span>
                @foreach ([['Salary posted', $run->journalEntry], ['Payment', $run->paymentJournalEntry]] as [$label, $entry])
                    @if ($entry)
                        <span>{{ $label }} —
                            @can('journal-entry-list')<a href="{{ route('journal-entries.show', $entry) }}">JE #{{ $entry->id }}{{ $entry->reference ? ' ('.$entry->reference.')' : '' }}</a>@else JE #{{ $entry->id }}{{ $entry->reference ? ' ('.$entry->reference.')' : '' }}@endcan
                            · {{ $entry->entry_date?->format('d M Y') }}</span>
                    @endif
                @endforeach
            </section>
        @endif

        {{-- PAYSLIP SHEET --}}
        <template x-if="sheet === 'slip' && slip">
            <div class="pr-sheet-wrap" role="dialog" aria-modal="true" aria-labelledby="pr-slip-title">
                <div class="pr-scrim" @click="close()"></div>
                <div class="pr-sheet pr-sheet-lg" x-trap.noscroll="sheet === 'slip'">
                    <div class="pr-sheet-head">
                        <div class="min-w-0">
                            <h3 id="pr-slip-title" x-text="slip.name"></h3>
                            <p><span x-text="[slip.code, slip.designation, slip.supplier].filter(Boolean).join(' · ')"></span> · {{ $run->period_month->format('F Y') }}</p>
                            <p style="margin-top:6px"><span class="pr-cap pr-cap-{{ $statusTone }}">{{ $statusText }}</span></p>
                        </div>
                        <div class="pr-nav no-print" style="margin-left:auto">
                            <button type="button" class="pr-btn pr-btn-sm" @click="prev()" :disabled="index === 0" aria-label="Previous payslip">‹ Previous</button>
                            <button type="button" class="pr-btn pr-btn-sm" @click="next()" :disabled="index === slips.length - 1" aria-label="Next payslip">Next ›</button>
                        </div>
                        <button type="button" class="pr-x" style="margin-left:4px" @click="close()" aria-label="Close payslip">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>
                    <div class="pr-sheet-body">
                        <dl class="pr-meta">
                            <div><dt>Salary type</dt><dd x-text="slip.salary_type === 'fixed' ? 'Fixed' : 'Basic'"></dd></div>
                            <div><dt>Days in month</dt><dd x-text="slip.days_in_month"></dd></div>
                            <div><dt>Absent days</dt><dd x-text="slip.absent_days || 0"></dd></div>
                            <div><dt>Payslip</dt><dd x-text="(index + 1) + ' of ' + slips.length"></dd></div>
                        </dl>

                        <div class="pr-split">
                            <div class="pr-col pr-col-earn">
                                <div class="pr-col-head"><h4>Earnings <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'payslip' })" aria-label="Help: earnings" title="Help: earnings">i</button></h4></div>
                                <ul class="pr-lines">
                                    <template x-for="line in lines('earning')" :key="line.id">
                                        <li>
                                            <span class="lbl"><span x-text="line.label"></span><span class="src" x-text="line.source_label"></span></span>
                                            <span class="amt"><span x-text="line.amount"></span>
                                                @if ($editable)
                                                    <template x-if="line.remove_url">
                                                        <form method="POST" :action="line.remove_url" x-data="{ busy: false }" @submit="busy = true">@csrf<input type="hidden" name="_slip" :value="slip.id"> @method('DELETE')
                                                            <button class="pr-rm" :disabled="busy" title="Remove this line" :aria-label="'Remove ' + line.label">×</button>
                                                        </form>
                                                    </template>
                                                @endif
                                            </span>
                                        </li>
                                    </template>
                                    <li x-show="lines('earning').length === 0"><span class="none">No earnings</span></li>
                                </ul>
                                <div class="pr-col-total"><span>Total earnings</span><span class="pr-earn" x-text="slip.earnings"></span></div>
                            </div>
                            <div class="pr-col pr-col-ded">
                                <div class="pr-col-head"><h4>Deductions <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'deductions' })" aria-label="Help: deductions" title="Help: deductions">i</button></h4></div>
                                <ul class="pr-lines">
                                    <template x-for="line in lines('deduction')" :key="line.id">
                                        <li>
                                            <span class="lbl"><span x-text="line.label"></span><span class="src" x-text="line.source_label"></span></span>
                                            <span class="amt"><span x-text="line.amount"></span>
                                                @if ($editable)
                                                    <template x-if="line.remove_url">
                                                        <form method="POST" :action="line.remove_url" x-data="{ busy: false }" @submit="busy = true">@csrf<input type="hidden" name="_slip" :value="slip.id"> @method('DELETE')
                                                            <button class="pr-rm" :disabled="busy" title="Remove this line" :aria-label="'Remove ' + line.label">×</button>
                                                        </form>
                                                    </template>
                                                @endif
                                            </span>
                                        </li>
                                    </template>
                                    <li x-show="lines('deduction').length === 0"><span class="none">Nothing deducted</span></li>
                                </ul>
                                <div class="pr-col-total"><span>Total deductions</span><span class="pr-ded" x-text="slip.deductions"></span></div>
                            </div>
                        </div>

                        <div class="pr-net"><small>Net pay</small><b :class="slip.negative && 'pr-ded'" x-text="'Rs ' + slip.net"></b></div>

                        <template x-if="slip.has_short || ! slip.recover_settlement_short">
                            <div class="pr-switch">
                                <p>Take settlement short from salary <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'short' })" aria-label="Help: settlement short" title="Help: settlement short">i</button>
                                    <small x-show="slip.recover_settlement_short">The cash this employee was short by in this month's posted settlements is deducted.</small>
                                    <small x-show="! slip.recover_settlement_short">Waived — the shortage stays a company expense on this payslip.</small>
                                </p>
                                @if ($editable)
                                    <form method="POST" :action="slip.urls.short" x-data="{ busy: false }" @submit="busy = true">@csrf<input type="hidden" name="_slip" :value="slip.id">
                                        <input type="hidden" name="recover" :value="slip.recover_settlement_short ? 0 : 1">
                                        <button class="pr-toggle" :class="slip.recover_settlement_short && 'is-on'" :disabled="busy" role="switch" :aria-checked="slip.recover_settlement_short ? 'true' : 'false'" aria-label="Take settlement short from salary"></button>
                                    </form>
                                @else
                                    <span class="pr-cap" :class="slip.recover_settlement_short ? 'pr-cap-red' : 'pr-cap-grey'" x-text="slip.recover_settlement_short ? 'Deducted' : 'Waived'"></span>
                                @endif
                            </div>
                        </template>

                        @if ($editable)
                            <div class="pr-edit no-print">
                                <h4>Absent days <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'absent' })" aria-label="Help: absent days" title="Help: absent days">i</button></h4>
                                <template x-if="slip.salary_type === 'basic'">
                                    <form method="POST" :action="slip.urls.update" class="pr-edit-row is-small" x-data="{ busy: false }" @submit="busy = true">@csrf<input type="hidden" name="_slip" :value="slip.id"> @method('PUT')
                                        <label class="pr-field"><span>Days absent</span><input class="pr-input" type="number" step="0.5" min="0" :max="slip.days_in_month" name="absent_days" :value="slip.absent_days" required></label>
                                        <button class="pr-btn" :disabled="busy">Save</button>
                                        <span class="pr-label">Cuts basic salary by the days absent.</span>
                                    </form>
                                </template>
                                <p x-show="slip.salary_type === 'fixed'" class="pr-label" style="margin:0">Fixed salary — absent days don't cut the pay.</p>

                                <h4 style="margin-top:16px">Add for this month <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'one-off' })" aria-label="Help: adding a one-off line" title="Help: adding a one-off line">i</button></h4>
                                <form method="POST" :action="slip.urls.lines" class="pr-edit-row" x-data="{ busy: false }" @submit="busy = true">@csrf<input type="hidden" name="_slip" :value="slip.id">
                                    <label class="pr-field"><span>Category</span>
                                        <select class="pr-select" name="salary_component_id" required>
                                            <option value="">Choose…</option>
                                            <optgroup label="Earnings">
                                                @foreach ($components->where('type', 'earning') as $component)<option value="{{ $component->id }}">{{ $component->name }}</option>@endforeach
                                            </optgroup>
                                            <optgroup label="Deductions">
                                                @foreach ($components->where('type', 'deduction') as $component)<option value="{{ $component->id }}">{{ $component->name }}</option>@endforeach
                                            </optgroup>
                                        </select>
                                    </label>
                                    <label class="pr-field"><span>Amount (Rs)</span><input class="pr-input" type="number" step="0.01" min="0.01" name="amount" required inputmode="decimal"></label>
                                    <label class="pr-field"><span>Note (optional)</span><input class="pr-input" type="text" name="label" maxlength="160" placeholder="e.g. Overtime 6 hours"></label>
                                    <button class="pr-btn pr-btn-primary" :disabled="busy">Add</button>
                                </form>
                            </div>
                        @endif
                    </div>
                    <div class="pr-sheet-foot">
                        <span class="pr-foot-note">{{ $run->run_number }}</span>
                        <a class="pr-btn" :href="slip.urls.print" target="_blank" rel="noopener">Print</a>
                        <a class="pr-btn pr-btn-primary" :href="slip.urls.pdf">Download PDF</a>
                    </div>
                </div>
            </div>
        </template>

        {{-- CONFIRM SHEETS: approve, reject, delete, pay --}}
        @php
            $dialogs = [
                'approve' => ['Approve and post this payroll?', 'Salary of '.$run->period_month->format('F Y').' for '.$slips->count().' employees is posted to the ledger (net Rs '.$fmt($run->total_net).'), every advance recovery is recorded and the settlement shorts are taken back. A posted payroll cannot be changed.', route('payroll.approve', $run), 'Approve and post', 'pr-btn-green', 'POST'],
                'reject' => ['Reject this payroll?', 'It goes back to '.($run->creator?->name ?? 'the maker').' with your reason. Nothing is posted.', route('payroll.reject', $run), 'Reject payroll', 'pr-btn-danger', 'POST'],
                'delete' => ['Delete this payroll?', 'The payslips are removed. Nothing has been posted, so the month can be built again.', route('payroll.destroy', $run), 'Delete payroll', 'pr-btn-danger', 'DELETE'],
            ];
        @endphp
        @foreach ($dialogs as $key => [$title, $text, $url, $button, $class, $method])
            <template x-if="sheet === '{{ $key }}'">
                <div class="pr-sheet-wrap" role="dialog" aria-modal="true" aria-labelledby="pr-{{ $key }}-title">
                    <div class="pr-scrim" @click="close()"></div>
                    <form method="POST" action="{{ $url }}" class="pr-sheet" x-trap.noscroll="sheet === '{{ $key }}'" x-data="{ busy: false }" @submit="busy = true">@csrf @if ($method === 'DELETE') @method('DELETE') @endif
                        <div class="pr-sheet-head">
                            <div><h3 id="pr-{{ $key }}-title">{{ $title }}</h3><p>{{ $text }}</p></div>
                            <button type="button" class="pr-x" @click="close()" aria-label="Close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg></button>
                        </div>
                        @if ($key === 'reject')
                            <div class="pr-sheet-body">
                                <label class="pr-field"><span>Reason (the maker sees this)</span><textarea class="pr-textarea" name="rejection_reason" required maxlength="500" rows="3"></textarea></label>
                            </div>
                        @endif
                        <div class="pr-sheet-foot">
                            <button type="button" class="pr-btn" @click="close()">Cancel</button>
                            <button class="pr-btn {{ $class }}" :disabled="busy">{{ $button }}</button>
                        </div>
                    </form>
                </div>
            </template>
        @endforeach


        @if ($editable)
            @php
                // One row per payslip: what was typed by hand for each monthly category, and the absent days.
                $gridRows = $slips->values()->map(fn ($slip) => [
                    'id' => $slip->id,
                    'name' => $slip->employee?->name,
                    'code' => $slip->employee?->employee_code,
                    'job' => $slip->employee?->designation,
                    'basic_type' => $slip->salary_type !== 'fixed',
                    'absent' => (float) $slip->absent_days ?: '',
                    'days' => (int) $slip->days_in_month,
                    'cells' => $variableCategories->mapWithKeys(fn ($category) => [
                        $category->id => ($sum = (float) $slip->lines->where('source', 'manual')->where('salary_component_id', $category->id)->sum('amount')) > 0 ? $sum : '',
                    ])->all(),
                    'extra' => (float) $slip->lines->where('source', 'adjustment')->sum(fn ($line) => $line->kind === 'deduction' ? -(float) $line->amount : (float) $line->amount),
                ])->all();
            @endphp
            <template x-if="sheet === 'variable'">
                <div class="pr-sheet-wrap" role="dialog" aria-modal="true" aria-labelledby="pr-var-title">
                    <div class="pr-scrim" @click="close()"></div>
                    <form method="POST" action="{{ route('payroll.variable-pay', $run) }}" class="pr-sheet pr-sheet-xl" x-trap.noscroll="sheet === 'variable'"
                        x-data="prGrid(@js($gridRows), @js($variableCategories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type])->values()))" @submit="busy = true; $refs.payload.value = payload()">
                        @csrf
                        {{-- The whole grid travels as one field, so a large payroll never runs into PHP's limit on form fields. --}}
                        <input type="hidden" name="payload" x-ref="payload">
                        <div class="pr-sheet-head">
                            <div>
                                <h3><span id="pr-var-title">Variable pay · {{ $run->period_month->format('F Y') }}</span> <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'grid' })" aria-label="Help: the variable pay grid" title="Help: the variable pay grid">i</button></h3>
                                <p>Type or paste from Excel (rows and columns in this order). Empty or 0 removes an amount. Advances and settlement shorts are worked out again after saving.</p>
                            </div>
                            <button type="button" class="pr-x" @click="close()" aria-label="Close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg></button>
                        </div>
                        <div class="pr-grid-tools">
                            <input class="pr-input pr-grid-search" type="search" placeholder="Find employee" x-model="q" aria-label="Find employee in the grid">
                            <span class="pr-foot-note" x-text="rows.length + ' employees · ' + cats.length + ' columns'"></span>
                        </div>
                        <div class="pr-grid-scroll">
                            <table class="pr-grid">
                                <thead>
                                    <tr>
                                        <th class="pr-grid-sticky">Employee</th>
                                        <th class="pr-num" title="Absent days (basic salary type only)">Absent<br>days<button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'absent' })" aria-label="Help: absent days" title="Help: absent days">i</button></th>
                                        <template x-for="cat in cats" :key="cat.id">
                                            <th class="pr-num" :class="cat.type === 'deduction' ? 'pr-ded' : ''" x-text="cat.name"></th>
                                        </template>
                                        <th class="pr-num">Row total</th>
                                    </tr>
                                    <tr class="pr-fill-row">
                                        <th class="pr-grid-sticky">Same for everyone <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'incentive' })" aria-label="Help: one amount for everyone" title="Help: one amount for everyone">i</button><small>type, then Enter: fills every employee shown</small></th>
                                        <th></th>
                                        <template x-for="cat in cats" :key="cat.id">
                                            <th><input class="pr-cell pr-fill" type="number" step="0.01" min="0" placeholder="All…" x-model="fill[cat.id]"
                                                @keydown.enter.prevent="fillColumn(cat.id)" @change="fillColumn(cat.id)" :aria-label="'Same ' + cat.name + ' for every employee shown'"></th>
                                        </template>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, r) in rows" :key="row.id">
                                        <tr x-show="match(row)">
                                            <td class="pr-grid-sticky"><b x-text="row.name"></b><small x-text="[row.code, row.job].filter(Boolean).join(' · ')"></small></td>
                                            <td>
                                                <input class="pr-cell" type="number" step="0.5" min="0" :max="row.days" x-model="row.absent"
                                                    :disabled="! row.basic_type" :title="row.basic_type ? '' : 'Fixed salary: absent days do not cut pay'" @paste="paste($event, r, -1)" :aria-label="'Absent days of ' + row.name">
                                            </td>
                                            <template x-for="(cat, c) in cats" :key="cat.id">
                                                <td><input class="pr-cell" type="number" step="0.01" min="0" x-model="row.cells[cat.id]"
                                                    :class="cat.type === 'deduction' ? 'is-ded' : ''" @paste="paste($event, r, c)" :aria-label="cat.name + ' for ' + row.name"></td>
                                            </template>
                                            <td class="pr-num"><span x-text="money(rowTotal(row))"></span><small x-show="row.extra !== 0" x-text="(row.extra > 0 ? '+ ' : '− ') + money(Math.abs(row.extra)) + ' from Bonus tab'"></small></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="pr-grid-sticky">Total</td>
                                        <td></td>
                                        <template x-for="cat in cats" :key="cat.id"><td class="pr-num" x-text="money(colTotal(cat.id))"></td></template>
                                        <td class="pr-num" x-text="money(rows.reduce((s, row) => s + rowTotal(row), 0))"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="pr-sheet-foot">
                            <span class="pr-foot-note">Deduction columns are shown in red and subtract from pay.</span>
                            <button type="button" class="pr-btn" @click="close()">Cancel</button>
                            <button class="pr-btn pr-btn-primary" :disabled="busy" x-text="busy ? 'Saving…' : 'Save variable pay'"></button>
                        </div>
                    </form>
                </div>
            </template>
        @endif

        <template x-if="sheet === 'pay'">
            <div class="pr-sheet-wrap" role="dialog" aria-modal="true" aria-labelledby="pr-pay-title">
                <div class="pr-scrim" @click="close()"></div>
                <form method="POST" action="{{ route('payroll.pay', $run) }}" class="pr-sheet" x-trap.noscroll="sheet === 'pay'" x-data="{ busy: false, method: 'cash' }" @submit="busy = true">@csrf
                    <div class="pr-sheet-head">
                        <div><h3 id="pr-pay-title">Pay the salaries</h3><p>Rs {{ $fmt($run->total_net) }} for {{ $slips->count() }} {{ \Illuminate\Support\Str::plural('employee', $slips->count()) }} · {{ $run->period_month->format('F Y') }}</p></div>
                        <button type="button" class="pr-x" @click="close()" aria-label="Close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg></button>
                    </div>
                    <div class="pr-sheet-body" style="display:grid; gap:14px">
                        <div class="pr-field"><span>Paid by</span>
                            <div class="pr-seg" role="radiogroup" aria-label="Paid by">
                                <label><input type="radio" name="method" value="cash" x-model="method"><span>Cash</span></label>
                                <label><input type="radio" name="method" value="bank" x-model="method"><span>Bank</span></label>
                            </div>
                        </div>
                        <label class="pr-field" x-show="method === 'bank'" x-cloak><span>Bank account</span>
                            <select class="pr-select" name="bank_account_id" :required="method === 'bank'" :disabled="method !== 'bank'">
                                <option value="">Choose account</option>
                                @foreach ($bankAccounts as $bank)<option value="{{ $bank->id }}">{{ $bank->bank_name }} · {{ $bank->account_number }}</option>@endforeach
                            </select>
                        </label>
                        <label class="pr-field"><span>Paid on</span><input class="pr-input" type="date" name="paid_on" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}"></label>
                    </div>
                    <div class="pr-sheet-foot">
                        <button type="button" class="pr-btn" @click="close()">Cancel</button>
                        <button class="pr-btn pr-btn-primary" :disabled="busy">Mark as paid</button>
                    </div>
                </form>
            </div>
        </template>
    </div>

    <style>
        .pr-sheet-xl { max-width: min(1400px, 96vw); }
        .pr-grid-tools { display: flex; align-items: center; gap: 12px; padding: 10px 22px; border-bottom: 1px solid var(--pr-sep); }
        .pr-grid-search { max-width: 260px; height: 34px; }
        .pr-grid-scroll { flex: 1; overflow: auto; overscroll-behavior: contain; }
        .pr-grid { border-collapse: separate; border-spacing: 0; font-size: 13px; min-width: 100%; }
        .pr-grid th { position: sticky; top: 0; z-index: 2; background: #fafafc; padding: 8px 6px; border-bottom: 1px solid var(--pr-sep); font-size: 11.5px; font-weight: 600; color: var(--pr-muted); white-space: nowrap; }
        .pr-grid td { padding: 3px 4px; border-bottom: 1px solid rgba(60,60,67,.08); white-space: nowrap; }
        .pr-grid tfoot td { position: sticky; bottom: 0; background: #fafafc; font-weight: 600; padding: 8px 6px; border-top: 1px solid var(--pr-sep); }
        .pr-grid .pr-grid-sticky { position: sticky; left: 0; z-index: 1; background: #fff; min-width: 190px; max-width: 230px; padding-left: 14px; }
        .pr-grid th.pr-grid-sticky { z-index: 3; background: #fafafc; text-align: left; }
        .pr-grid tfoot .pr-grid-sticky { background: #fafafc; z-index: 3; }
        .pr-grid td.pr-grid-sticky b { display: block; font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
        .pr-grid small { display: block; font-size: 11.5px; color: var(--pr-muted); overflow: hidden; text-overflow: ellipsis; }
        .pr-cell { width: 92px; height: 32px; padding: 0 8px; border: 0; border-radius: 8px; background: var(--pr-fill); text-align: right; font-size: 13.5px; font-variant-numeric: tabular-nums; }
        .pr-cell:focus { outline: none; background: #fff; box-shadow: 0 0 0 3px rgba(0,113,227,.25), 0 0 0 1px var(--pr-blue); }
        .pr-cell.is-ded { color: var(--pr-red); }
        .pr-grid .pr-fill-row th { top: 33px; background: #f0f6ff; padding: 4px; }
        .pr-grid .pr-fill-row th.pr-grid-sticky { font-size: 12px; color: #0058b0; }
        .pr-grid .pr-fill-row small { font-weight: 400; }
        .pr-fill { background: #fff; box-shadow: inset 0 0 0 1px rgba(0,113,227,.35); }
        .pr-cell:disabled { opacity: .35; }
        @media (max-width: 640px) { .pr-grid .pr-grid-sticky { min-width: 130px; max-width: 150px; } .pr-cell { width: 78px; } }
    </style>
    <script>
        function prGrid(rows, cats) {
            return {
                rows, cats, q: '', busy: false, fill: {},
                // One amount for every employee shown (narrow the list with the search box first to fill only some).
                fillColumn(id) {
                    const value = String(this.fill[id] ?? '').trim();
                    if (value === '') { return; }
                    this.rows.filter((row) => this.match(row)).forEach((row) => { row.cells[id] = value === '0' ? '' : value; });
                    this.fill[id] = '';
                },
                match(row) { const q = this.q.trim().toLowerCase(); return ! q || (row.name + ' ' + (row.code || '')).toLowerCase().includes(q); },
                num(v) { const n = parseFloat(String(v ?? '').replace(/,/g, '')); return isNaN(n) ? 0 : n; },
                rowTotal(row) { return this.cats.reduce((s, cat) => s + (cat.type === 'deduction' ? -1 : 1) * this.num(row.cells[cat.id]), 0); },
                colTotal(id) { return this.rows.reduce((s, row) => s + this.num(row.cells[id]), 0); },
                money(v) { return Number(v).toLocaleString('en-PK', { maximumFractionDigits: 2 }); },
                payload() {
                    return JSON.stringify(this.rows.map((row) => ({ id: row.id, absent: row.basic_type ? row.absent : null, cells: row.cells })));
                },
                // Paste a block copied from Excel: rows go down, columns go right, starting at this cell (-1 = absent days).
                paste(event, r, c) {
                    const text = (event.clipboardData || window.clipboardData).getData('text');
                    if (! /[\t\n]/.test(text.trim())) { return; }
                    event.preventDefault();
                    const visible = this.rows.filter((row) => this.match(row));
                    const start = visible.indexOf(this.rows[r]);
                    text.replace(/\r/g, '').replace(/\n$/, '').split('\n').forEach((line, i) => {
                        const row = visible[start + i];
                        if (! row) { return; }
                        line.split('\t').forEach((value, j) => {
                            const col = c + j;
                            const clean = String(value).replace(/,/g, '').trim();
                            // text such as a names column copied by mistake leaves the cell as it was
                            if (clean !== '' && isNaN(parseFloat(clean))) { return; }
                            if (col === -1) { if (row.basic_type) { row.absent = clean; } }
                            else if (this.cats[col]) { row.cells[this.cats[col].id] = clean === '0' ? '' : clean; }
                        });
                    });
                },
            };
        }

        function prRun(slips, openIndex) {
            return {
                slips,
                q: '',
                sheet: openIndex === null ? null : 'slip',
                index: openIndex ?? 0,
                get slip() { return this.slips[this.index] ?? null; },
                lines(kind) { return this.slip ? this.slip.lines.filter((line) => line.kind === kind) : []; },
                match(text) { return ! this.q || text.includes(this.q.trim().toLowerCase()); },
                openSlip(i) { this.index = i; this.sheet = 'slip'; },
                open(name) { this.sheet = name; },
                close() {
                    this.sheet = null;
                    // drop ?slip= so a refresh does not reopen it
                    const url = new URL(window.location.href);
                    if (url.searchParams.has('slip')) { url.searchParams.delete('slip'); window.history.replaceState({}, '', url); }
                },
                next() { if (this.index < this.slips.length - 1) { this.index++; } },
                step(event, by) {
                    // arrow keys move between payslips, but never while typing in a field
                    if (this.sheet !== 'slip' || ['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target.tagName)) { return; }
                    by > 0 ? this.next() : this.prev();
                },
                prev() { if (this.index > 0) { this.index--; } },
            };
        }
    </script>
</x-app-layout>
