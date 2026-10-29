<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('expense-vouchers.index') }}">Expense Vouchers</a><span aria-hidden="true">›</span><span>{{ $voucher->voucher_number }}</span>
                </nav>
                <h1 class="ak-title">Edit {{ $voucher->voucher_number }}</h1>
                <p class="ak-sub">A draft or rejected voucher can be changed and sent again.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('expense-vouchers.help') }}" class="ak-btn ak-btn-outline">How it works</a>
                <a href="{{ route('expense-vouchers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to vouchers</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')

    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('expense-vouchers.update', $voucher) }}" class="ak-card ev-form">
            @csrf
            @method("PUT")
            @include('expense-vouchers._form')
            <div class="ev-form-foot">
                <a href="{{ route('expense-vouchers.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
                <button type="submit" class="ak-btn ak-btn-primary">Update voucher</button>
            </div>
        </form>
    </div>

    @include('expense-vouchers._form-scripts')
</x-app-layout>
