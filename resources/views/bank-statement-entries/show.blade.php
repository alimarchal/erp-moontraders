<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bank Entry #{{ $entry->id }}" :showSearch="false" :showRefresh="true" backRoute="bank-statement-entries.index" />
    </x-slot>
    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <div class="bg-white shadow-xl sm:rounded-lg">
                @include('bank-statement-entries.partials.detail')
            </div>
        </div>
    </div>
</x-app-layout>
