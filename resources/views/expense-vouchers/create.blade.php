<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('expense-vouchers.index') }}">Expense Vouchers</a><span aria-hidden="true">›</span><span>New</span>
                </nav>
                <h1 class="ak-title">New expense voucher <span class="ev-ur" lang="ur" dir="rtl" style="font-size:22px">نیا اخراجاتی واؤچر</span></h1>
                <p class="ak-sub">Saved as a draft first; send it to the checker when it is ready.</p>
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
        <form method="POST" action="{{ route('expense-vouchers.store') }}" class="ak-card ev-form">
            @csrf
            
            @include('expense-vouchers._form')
            <div class="ev-form-foot">
                <a href="{{ route('expense-vouchers.index') }}" class="ak-btn ak-btn-outline">Cancel <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:17px">منسوخ کریں</span></a>
                <button type="submit" class="ak-btn ak-btn-primary">Save as draft <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:17px">مسودہ محفوظ کریں</span></button>
            </div>
        </form>
    </div>

    @include('expense-vouchers._form-scripts')
</x-app-layout>
