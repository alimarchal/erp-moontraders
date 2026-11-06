<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('stock-adjustments.index') }}">Stock Adjustments</a><span aria-hidden="true">›</span><span>New</span>
                </nav>
                <h1 class="ak-title">New Stock Adjustment</h1>
                <p class="ak-sub">Damage, theft, expiry, count differences and cost corrections. Saved as a draft until you post it.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('stock-adjustments.help') }}" class="ak-btn ak-btn-outline">How it works <span lang="ur" dir="rtl" style="margin-inline-start:6px">طریقہ کار</span></a>
                <a href="{{ route('stock-adjustments.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to adjustments</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('tickets.partials.style')

    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        @include('stock-adjustments._form', ['adjustment' => null, 'action' => route('stock-adjustments.store'), 'submitLabel' => 'Save draft'])
    </div>
</x-app-layout>
