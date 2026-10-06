<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('tickets.index') }}">Tickets</a><span aria-hidden="true">›</span><span>{{ $ticket->ticket_number }}</span>
                </nav>
                <h1 class="ak-title">Edit {{ $ticket->ticket_number }}</h1>
                <p class="ak-sub">Only pending tickets can be changed.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('tickets.show', $ticket) }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to ticket</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('tickets.partials.style')

    <div class="ak-page">
        @include('tickets.partials.flash')
        <x-validation-errors class="mb-4" />
        @include('tickets.form', ['action' => route('tickets.update', $ticket), 'httpMethod' => 'PUT', 'submitLabel' => 'Save changes'])
    </div>
</x-app-layout>
