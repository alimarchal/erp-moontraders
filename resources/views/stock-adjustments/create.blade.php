{{-- New Stock Adjustment, in the Goods Issue form layout (page header, numbered cards, sticky totals bar). --}}
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Stock Adjustment" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="stock-adjustments.index">
            <x-slot name="actions">
                <a href="{{ route('stock-adjustments.help') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition ease-in-out duration-150 no-print"
                    title="How it works, in English and Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                    <span class="sr-only">How it works</span>
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    @include('tickets.partials.style')
    @include('goods-issues.partials.form-style')
    @include('stock-adjustments.partials.form-style')

    <div class="ak-page gf-page">
        <p class="gf-lede">Damage, theft, expiry, count differences and cost corrections. It is saved as a draft; stock, valuation and the journal change only when you post it.</p>
        <x-status-message class="mb-4 mt-4 shadow-md" />
        <x-validation-errors class="mb-4" />
        @include('stock-adjustments._form', ['adjustment' => null, 'action' => route('stock-adjustments.store'), 'submitLabel' => 'Save draft'])
    </div>
</x-app-layout>
