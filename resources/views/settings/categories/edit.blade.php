{{-- Settings -> Categories -> Edit (fields live in settings.categories.partials.form-fields). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('categories.index') }}">Categories</a><span aria-hidden="true">›</span><span>Edit</span>
                </nav>
                <h1 class="ak-title">Edit Category: {{ $category->name }}</h1>
                <p class="ak-sub">{{ $category->is_active ? 'Active' : 'Inactive' }}</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('categories.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('categories.show', $category) }}" class="ak-btn ak-btn-outline">View</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />
        <form method="POST" action="{{ route('categories.update', $category) }}">
            @csrf
            @method('PUT')
            @include('settings.categories.partials.form-fields', [
                'category' => $category,
                'submitLabel' => 'Save changes',
            ])
        </form>
    </div>
</x-app-layout>
