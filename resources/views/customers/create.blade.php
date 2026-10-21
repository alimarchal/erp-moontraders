{{-- Settings -> Customers -> Add (fields live in customers.partials.form-fields). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('customers.index') }}">Customers</a><span aria-hidden="true">›</span><span>Add</span>
                </nav>
                <h1 class="ak-title">Add New Customer</h1>
                <p class="ak-sub">Add one shop. To add many at once, use Import Excel.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('customers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('customers.import.form') }}" class="ak-btn ak-btn-outline">Import Excel</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />
        <form method="POST" action="{{ route('customers.store') }}">
            @csrf
            @include('customers.partials.form-fields', [
                'channelTypes' => $channelTypes,
                'customerCategories' => $customerCategories,
                'accountOptions' => $accountOptions,
                'salesRepOptions' => $salesRepOptions,
                'submitLabel' => 'Create customer',
            ])
        </form>
    </div>
</x-app-layout>
