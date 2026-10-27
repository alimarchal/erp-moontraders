{{-- Settings -> Customers -> Edit (fields live in customers.partials.form-fields). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('customers.index') }}">Customers</a><span aria-hidden="true">›</span><span>Edit</span>
                </nav>
                <h1 class="ak-title">Edit Customer: {{ $customer->customer_name }}</h1>
                <p class="ak-sub">{{ $customer->customer_code }} · {{ $customer->is_active ? 'Active' : 'Inactive' }}</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('customers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <x-customer-help-link />
                <a href="{{ route('customers.show', $customer) }}" class="ak-btn ak-btn-outline">View</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('settings.partials.select2-init')

    <div class="ak-page">
        <x-status-message />
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @csrf
            @method('PUT')
            @include('customers.partials.form-fields', [
                'customer' => $customer,
                'channelTypes' => $channelTypes,
                'customerCategories' => $customerCategories,
                'accountOptions' => $accountOptions,
                'salesRepOptions' => $salesRepOptions,
                'submitLabel' => 'Save changes',
            ])
        </form>
    </div>
</x-app-layout>
