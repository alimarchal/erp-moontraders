<x-app-layout>
    <x-slot name="header">
        <div class="ak-head no-print">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('payroll.index') }}">Payroll</a><span aria-hidden="true">›</span><a href="{{ route('payroll.show', $run) }}">{{ $run->run_number }}</a><span aria-hidden="true">›</span><span>{{ $slip->employee?->name }}</span></nav>
                <h1 class="ak-title" style="margin:0">Salary slip</h1>
            </div>
            <div class="ak-head-actions"><a href="{{ route('payroll.show', $run) }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to payroll</a><button type="button" class="ak-btn ak-btn-primary" onclick="window.print()">Print slip</button></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('payroll._slip-styles')
    <div class="ak-page">@include('payroll._slip')</div>
</x-app-layout>
