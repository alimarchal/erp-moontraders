<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('tickets.index') }}">Tickets</a><span aria-hidden="true">›</span><span>New</span>
                </nav>
                <h1 class="ak-title">New Ticket</h1>
                <p class="ak-sub">Ask an admin to change a price, add a new SKU or re-activate one. Nothing changes until it is approved.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('tickets.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Tickets</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('tickets.partials.style')

    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        @include('tickets.form', ['ticket' => null, 'action' => route('tickets.store'), 'submitLabel' => 'Submit for approval'])
    </div>
</x-app-layout>
