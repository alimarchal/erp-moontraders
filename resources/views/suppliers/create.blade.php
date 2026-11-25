<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Supplier" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="suppliers.index">
            <x-slot name="actions">
                <a href="{{ route('suppliers.help') }}" class="sp-head-btn" title="How suppliers and carton entry work">How it works</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    @include('suppliers.partials.apple-style')

    <form method="POST" action="{{ route('suppliers.store') }}" class="sp-page">
        @csrf
        <x-status-message />
        <x-validation-errors />

        @include('suppliers.partials.form-fields', ['currencyOptions' => $currencyOptions, 'accountOptions' => $accountOptions])

        <div class="sp-actions">
            <span class="sp-actions-note">Add a company you buy stock from.</span>
            <div class="sp-actions-buttons">
                <a href="{{ route('suppliers.index') }}" class="sp-btn sp-btn-plain">Cancel</a>
                <button type="submit" class="sp-btn sp-btn-primary">Create supplier</button>
            </div>
        </div>
    </form>
</x-app-layout>
