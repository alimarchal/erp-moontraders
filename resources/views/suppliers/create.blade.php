<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('suppliers.index') }}">Suppliers</a><span aria-hidden="true">›</span><span>New</span>
                </nav>
                <h1 class="ak-title">New supplier <span class="ev-ur" lang="ur" dir="rtl" style="font-size:18px">نیا سپلائر</span></h1>
                <p class="ak-sub">Add a company you buy stock from.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('suppliers.help') }}" class="ak-btn ak-btn-outline">How it works <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:16px">طریقہ کار</span></a>
                <a href="{{ route('suppliers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to suppliers</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')

    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('suppliers.store') }}" class="ak-card ev-form">
            @csrf
            @include('suppliers.partials.form-fields', ['currencyOptions' => $currencyOptions, 'accountOptions' => $accountOptions])
            <div class="ev-form-foot">
                <a href="{{ route('suppliers.index') }}" class="ak-btn ak-btn-outline">Cancel <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:15px">منسوخ کریں</span></a>
                <button type="submit" class="ak-btn ak-btn-primary">Create supplier <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:15px">سپلائر بنائیں</span></button>
            </div>
        </form>
    </div>

    @include('expense-vouchers._form-scripts')
</x-app-layout>
