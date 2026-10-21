{{-- Settings -> Categories -> View. Read only; changes happen on Edit. --}}
@php
    $rows = [
            'Name' => $category->name,
            'Slug' => $category->slug,
            'Products in this category' => number_format($category->products_count),
            'Status' => $category->is_active ? 'Active' : 'Inactive',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('categories.index') }}">Categories</a><span aria-hidden="true">›</span><span>{{ $category->name }}</span>
                </nav>
                <h1 class="ak-title">{{ $category->name }}</h1>
                <p class="ak-sub">
                    @if ($category->is_active)
                        <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                    @else
                        <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Inactive</span>
                    @endif
                </p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('categories.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                @can('category-edit')
                    <a href="{{ route('categories.edit', $category) }}" class="ak-btn ak-btn-primary">Edit category</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />

        <section class="uf-card" style="max-width:640px">
            <div class="uf-card-head"><h2 class="uf-card-title">Category details</h2></div>
            <dl style="margin:0; padding:8px 20px 16px">
                @foreach ($rows as $label => $value)
                    <div style="display:flex; justify-content:space-between; gap:16px; padding:9px 0; border-bottom:1px solid var(--ak-line); font-size:14px">
                        <dt class="ak-muted" style="flex:none">{{ $label }}</dt>
                        <dd style="margin:0; text-align:right; word-break:break-word; color:var(--ak-text)">{{ filled($value) ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
</x-app-layout>
