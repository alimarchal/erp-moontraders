<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('stock-adjustments.index') }}">Stock Adjustments</a><span aria-hidden="true">›</span>
                    <a href="{{ route('stock-adjustments.show', $adjustment) }}">{{ $adjustment->adjustment_number }}</a><span aria-hidden="true">›</span><span>Edit</span>
                </nav>
                <h1 class="ak-title">Edit Stock Adjustment {{ $adjustment->adjustment_number }}</h1>
                <p class="ak-sub">Only drafts can be edited. Nothing has moved yet.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('stock-adjustments.help') }}" class="ak-btn ak-btn-outline">How it works <span lang="ur" dir="rtl" style="margin-inline-start:6px">طریقہ کار</span></a>
                <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to adjustment</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('tickets.partials.style')

    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        @include('stock-adjustments._form', ['adjustment' => $adjustment, 'action' => route('stock-adjustments.update', $adjustment), 'submitLabel' => 'Save changes'])
    </div>
</x-app-layout>
