<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Cheque" :showSearch="false" :showRefresh="false" backRoute="bank-cheques.index" />
    </x-slot>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <x-validation-errors class="mb-4" />
                <form method="POST" action="{{ route('bank-cheques.store') }}">
                    @csrf
                    @include('bank-cheques._form')
                    <div class="flex items-center justify-between mt-6">
                        <a href="{{ route('bank-cheques.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                        <x-button>Save</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
