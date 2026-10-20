<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Auto-classify Rule" :showSearch="false" :showRefresh="false" backRoute="bank-entry-rules.index" />
    </x-slot>
    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <x-validation-errors class="mb-4" />
                <form method="POST" action="{{ route('bank-entry-rules.store') }}">
                    @csrf
                    @include('bank-entry-rules._form')
                    <div class="flex items-center justify-between mt-6">
                        <a href="{{ route('bank-entry-rules.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                        <x-button>Save</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
