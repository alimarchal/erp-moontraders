{{--
    Payroll (/payroll): the one screen for salaries. Tabs: Payroll runs · Employee salaries · Incentives & adjustments ·
    Categories. Same look as Goods Issues (Apple skin, hidden filters, sticky tabs, quick-look sheets). Each tab shows
    only to people with its permission (PayrollHubController::TABS). Every edit happens in a sheet on this page.
--}}
@php
    $tabMeta = [
        'runs' => ['Payroll runs', 'Monthly payroll: build, check, approve and pay'],
        'salaries' => ['Employee salaries', 'Basic salary, allowances and standing deductions of each employee'],
        'adjustments' => ['Incentives & adjustments', 'Extra pay or cuts for one month or every month: incentive, commission, Eid bonus, fine'],
        'categories' => ['Categories', 'The allowances and deductions a payslip can have'],
    ];
    $tabUrl = fn (string $key) => route('payroll.index', ['tab' => $key]);
    $headerCreate = match ($tab) {
        'runs' => auth()->user()->can('payroll-create') ? ['New payroll', "\$dispatch('pr-new-run')"] : null,
        'salaries' => ['Set salary', "\$dispatch('pr-salary', { ids: [] })"],
        'adjustments' => ['Add incentive / adjustment', "\$dispatch('pr-adjust')"],
        'categories' => ['New category', "\$dispatch('pr-category', null)"],
        default => null,
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Payroll</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ $tabMeta[$tab][1] }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 no-print">
                @if ($tab === 'runs')
                    <a href="{{ route('payroll.list.excel', ['filter' => $filters]) }}" class="pr-head-btn" title="Download the payroll list as Excel">Excel</a>
                    <a href="{{ route('payroll.list.pdf', ['filter' => $filters]) }}" class="pr-head-btn" title="Download the payroll list as PDF">PDF</a>
                @elseif ($tab === 'salaries')
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx', 'page' => null]) }}" class="pr-head-btn" title="Download the salary register as Excel">Excel</a>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf', 'page' => null]) }}" class="pr-head-btn" title="Download the salary register as PDF">PDF</a>
                @endif
                @if (in_array($tab, ['salaries', 'adjustments'], true) && auth()->user()->can('payroll-import-excel'))
                    <button type="button" class="pr-head-btn" x-data @click="$dispatch('pr-import', @js($tab))" title="Fill an Excel template and upload it">Import Excel</button>
                @endif
                @if (in_array($tab, ['runs', 'salaries'], true))
                    <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'exports' })" aria-label="Help: downloads" title="Help: downloads">i</button>
                @endif
                @if (in_array($tab, ['runs', 'salaries', 'adjustments'], true))
                    <button type="button" class="pr-head-btn" x-data @click="$dispatch('pr-toggle-filters')" aria-controls="pr-filter-panel">Filters @if (count($filters))<span class="pr-head-count">{{ count($filters) }}</span>@endif</button>
                @endif
                <button type="button" class="pr-head-btn" x-data @click="$dispatch('rg-help', { topic: null })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How payroll works, in Urdu">Help</button>
                @if ($headerCreate)
                    <button type="button" class="pr-head-btn pr-head-primary" x-data @click="{{ $headerCreate[1] }}">+ {{ $headerCreate[0] }}</button>
                @endif
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="payroll" />
    @include('payroll.hub._styles')

    <div class="ak-page pr-hub">
        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <nav class="pr-tabs" aria-label="Payroll sections">
            @foreach ($tabs as $key)
                <a href="{{ $tabUrl($key) }}" class="pr-tab {{ $tab === $key ? 'is-active' : '' }}" @if ($tab === $key) aria-current="page" @endif>
                    {{ $tabMeta[$key][0] }} <span class="pr-tab-count">{{ number_format($counts[$key] ?? 0) }}</span>
                </a>
            @endforeach
            <span style="align-self:center; padding:0 6px 0 2px"><button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'tabs' })" aria-label="Help: the payroll sections" title="Help: the payroll sections">i</button></span>
        </nav>

        @include('payroll.hub._'.$tab)

        @can('payroll-import-excel')
            @if (in_array($tab, ['salaries', 'adjustments'], true))
                @include('payroll.hub._import', ['importKind' => $tab])
            @endif
        @endcan
    </div>
</x-app-layout>
