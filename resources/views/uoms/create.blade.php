{{-- Settings -> Units of measure -> Add (fields live in uoms.partials.form-fields). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('uoms.index') }}">Units of measure</a><span aria-hidden="true">›</span><span>Add</span>
                </nav>
                <h1 class="ak-title">Add Unit</h1>
                <p class="ak-sub">Units used to count stock and to sell products.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('uoms.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />
        <form method="POST" action="{{ route('uoms.store') }}">
            @csrf
            @include('uoms.partials.form-fields', [
                'submitLabel' => 'Create unit',
            ])
        </form>
    </div>
</x-app-layout>
