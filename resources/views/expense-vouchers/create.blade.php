<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New expense voucher" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="{{ route('expense-vouchers.index') }}">
            <x-slot name="actions">
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: 'method' })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How vouchers work, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="expense-vouchers" />
    @include('goods-issues.partials.form-style')
    <x-apple-fields-style />
    @include('expense-vouchers._form-styles')

    <div class="ak-page gf-page">
        <p class="gf-lede">Saved as a draft or sent straight to a checker. Nothing is posted until it is approved.</p>
        <x-status-message />
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('expense-vouchers.store') }}" class="ev-form apl-fields" enctype="multipart/form-data" data-once-form>
            @csrf
            @include('expense-vouchers._form')
        </form>
    </div>

    @include('expense-vouchers._form-scripts')
</x-app-layout>
