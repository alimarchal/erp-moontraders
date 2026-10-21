{{-- Settings -> Units of measure -> Edit (fields live in uoms.partials.form-fields). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('uoms.index') }}">Units of measure</a><span aria-hidden="true">›</span><span>Edit</span>
                </nav>
                <h1 class="ak-title">Edit Unit: {{ $uom->uom_name }}</h1>
                <p class="ak-sub">{{ $uom->enabled ? 'Enabled' : 'Disabled' }}</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('uoms.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('uoms.show', $uom) }}" class="ak-btn ak-btn-outline">View</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />
        <form method="POST" action="{{ route('uoms.update', $uom) }}">
            @csrf
            @method('PUT')
            @include('uoms.partials.form-fields', [
                'uom' => $uom,
                'submitLabel' => 'Save changes',
            ])
        </form>
    </div>
</x-app-layout>
