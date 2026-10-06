<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit {{ $ticket->ticket_number }}" backRoute="tickets.index" :showSearch="false" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <x-validation-errors class="mb-4" />
            @include('tickets.form', ['action' => route('tickets.update', $ticket), 'httpMethod' => 'PUT', 'submitLabel' => 'Save changes'])
        </div>
    </div>
</x-app-layout>
