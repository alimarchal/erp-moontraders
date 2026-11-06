<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('payroll.index') }}">Payroll</a><span aria-hidden="true">›</span><span>Run payroll</span></nav>
                <h1 class="ak-title">Run payroll <span class="ev-ur" lang="ur" dir="rtl">تنخواہ تیار کریں</span></h1>
                <p class="ak-sub">One click builds every employee's slip from the salary set-up, absent days and advances.</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('payroll.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')
    @php $isAdmin = auth()->user()->hasAllSupplierAccess(); @endphp
    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('payroll.store') }}" class="ak-card ev-form">
            @csrf
            <div class="ev-form-grid">
                <div class="ak-field"><label for="month">Month to pay</label><input id="month" type="month" name="month" required max="{{ now()->format('Y-m') }}" value="{{ old('month', $month) }}"></div>
                <div class="ak-field ev-span-2">
                    <label for="owner">Company</label>
                    <select id="owner" class="ev-select" name="owner" @if($isAdmin) data-clearable="1" data-placeholder="{{ $companies->first()?->company_name }} (own staff)" @else required @endif>
                        @if($isAdmin)<option value=""></option>@foreach($companies as $company)<option value="company:{{ $company->id }}" @selected(old('owner') === 'company:'.$company->id)>{{ $company->company_name }} (own staff)</option>@endforeach @endif
                        @foreach($suppliers as $supplier)<option value="supplier:{{ $supplier->id }}" @selected(old('owner') === 'supplier:'.$supplier->id || (! $isAdmin && $suppliers->count() === 1))>{{ $supplier->supplier_name }}</option>@endforeach
                    </select>
                </div>
                <div class="ev-span-3 ev-note" style="margin:0">Every active employee who has a salary set gets a slip. Employees without a salary are listed on the next page so you can set them up. Advances taken earlier are recovered automatically.</div>
            </div>
            <div class="ev-form-foot">
                <a href="{{ route('payroll.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
                <button type="submit" class="ak-btn ak-btn-primary">Build payroll</button>
            </div>
        </form>
    </div>
    @push('scripts')<script>$(function () { $('.ev-select').each(function () { const $s = $(this); $s.select2({ width: '100%', allowClear: !!$s.data('clearable'), placeholder: $s.data('placeholder') || 'Select' }); }); });</script>@endpush
</x-app-layout>
