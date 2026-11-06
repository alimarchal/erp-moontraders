<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('expense-vendors.index') }}">Shops</a><span aria-hidden="true">›</span><span>New</span></nav>
                <h1 class="ak-title">New shop</h1>
                <p class="ak-sub">Shops are shared: every voucher maker can pick them while raising a voucher.</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('expense-vendors.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to shops</a></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')
    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('expense-vendors.store') }}" class="ak-card ev-form">
            @csrf
            
            @include('expense-vendors._form')
            <div class="ev-form-foot">
                <a href="{{ route('expense-vendors.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
                <button type="submit" class="ak-btn ak-btn-primary">Save shop</button>
            </div>
        </form>
    </div>
</x-app-layout>
