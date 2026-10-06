<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Ticket" backRoute="tickets.index" :showSearch="false" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <x-validation-errors class="mb-4" />
            @include('tickets.form', ['ticket' => null, 'action' => route('tickets.store'), 'submitLabel' => 'Submit for approval'])
        </div>
    </div>
</x-app-layout>
