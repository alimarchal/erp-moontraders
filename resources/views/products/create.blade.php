{{-- Settings -> Products -> Add (fields live in products.partials.form-fields). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('products.index') }}">Products</a><span aria-hidden="true">›</span><span>Add</span>
                </nav>
                <h1 class="ak-title">Add New Product</h1>
                <p class="ak-sub">Add one product. To add many at once, use Import Excel.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('products.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('products.import.form') }}" class="ak-btn ak-btn-outline">Import Excel</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />
        <form method="POST" action="{{ route('products.store') }}">
            @csrf
            @include('products.partials.form-fields', [
                'supplierOptions' => $supplierOptions,
                'categoryOptions' => $categoryOptions,
                'uomOptions' => $uomOptions,
                'valuationMethods' => $valuationMethods,
                'submitLabel' => 'Create product',
            ])
        </form>
    </div>
</x-app-layout>
