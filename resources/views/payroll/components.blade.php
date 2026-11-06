@php $calc = ['fixed' => 'Fixed amount', 'percent_of_basic' => '% of basic', 'input' => 'Typed each month']; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('payroll.index') }}">Payroll</a><span aria-hidden="true">›</span><span>Allowances &amp; deductions</span></nav>
                <h1 class="ak-title">Allowances &amp; deductions</h1>
                <p class="ak-sub">What can appear on a salary slip. Add your own, or switch one off.</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('payroll.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Payroll</a></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        <section class="ak-card" style="padding:16px 20px">
            <h2 style="margin:0 0 10px; font-size:15px; font-weight:700">Add one</h2>
            <form method="POST" action="{{ route('payroll.components.store') }}" style="display:flex; flex-wrap:wrap; gap:10px; align-items:end">@csrf
                <div class="ak-field"><label>Name</label><input name="name" required maxlength="120" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
                <div class="ak-field"><label>Type</label><select name="type" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="earning">Earning (added)</option><option value="deduction">Deduction (taken off)</option></select></div>
                <div class="ak-field"><label>How it is worked out</label><select name="calculation" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px">@foreach($calc as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="ak-field"><label>Ledger account (optional)</label><select name="account_id" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px; max-width:260px"><option value="">Salary expense (5281)</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->account_code }} - {{ $a->account_name }}</option>@endforeach</select></div>
                <button class="ak-btn ak-btn-primary" style="height:38px">Add</button>
            </form>
        </section>
        <section class="ak-card" style="overflow-x:auto">
            <table class="ak-dt" style="min-width:760px">
                <thead><tr><th>Name</th><th>Type</th><th>Worked out</th><th>Ledger account</th><th>Active</th><th></th></tr></thead>
                <tbody>
                    @foreach($components as $component)
                        <tr>
                                <td><input form="c{{ $component->id }}" name="name" value="{{ $component->name }}" required maxlength="120" style="height:34px; border:1px solid var(--ak-border); border-radius:6px; padding:0 8px; width:100%; min-width:160px"></td>
                                <td><select form="c{{ $component->id }}" name="type" style="height:34px; border:1px solid var(--ak-border); border-radius:6px; padding:0 6px"><option value="earning" @selected($component->type === 'earning')>Earning</option><option value="deduction" @selected($component->type === 'deduction')>Deduction</option></select></td>
                                <td><select form="c{{ $component->id }}" name="calculation" style="height:34px; border:1px solid var(--ak-border); border-radius:6px; padding:0 6px">@foreach($calc as $k => $l)<option value="{{ $k }}" @selected($component->calculation === $k)>{{ $l }}</option>@endforeach</select></td>
                                <td><select form="c{{ $component->id }}" name="account_id" style="height:34px; border:1px solid var(--ak-border); border-radius:6px; padding:0 6px; max-width:230px"><option value="">Salary expense (5281)</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected($component->account_id === $a->id)>{{ $a->account_code }} - {{ $a->account_name }}</option>@endforeach</select></td>
                                <td><label class="ev-remove" style="display:inline-flex; gap:6px; align-items:center"><input type="checkbox" form="c{{ $component->id }}" name="is_active" value="1" @checked($component->is_active)> Active</label></td>
                                <td><button form="c{{ $component->id }}" class="ak-btn ak-btn-outline" style="padding:4px 12px">Save</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @foreach($components as $component)
                <form id="c{{ $component->id }}" method="POST" action="{{ route('payroll.components.update', $component) }}" hidden>@csrf @method('PUT')</form>
            @endforeach
        </section>
    </div>
</x-app-layout>
