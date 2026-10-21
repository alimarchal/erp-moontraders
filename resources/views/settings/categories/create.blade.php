{{-- Settings -> Categories -> Add (fields live in settings.categories.partials.form-fields). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('categories.index') }}">Categories</a><span aria-hidden="true">›</span><span>Add</span>
                </nav>
                <h1 class="ak-title">Add Category</h1>
                <p class="ak-sub">Product groups used for reporting and filtering.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('categories.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />
        <form method="POST" action="{{ route('categories.store') }}">
            @csrf
            @include('settings.categories.partials.form-fields', [
                'submitLabel' => 'Create category',
            ])
        </form>
    </div>
</x-app-layout>
