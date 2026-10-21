<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Party Alias" :showSearch="false" :showRefresh="false" backRoute="bank-party-aliases.index" />
    </x-slot>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <x-validation-errors class="mb-4" />
                <form method="POST" action="{{ route('bank-party-aliases.update', $alias) }}">
                    @csrf
                    @method('PUT')
                    @include('bank-party-aliases._form')
                    <div class="flex items-center justify-between mt-6">
                        <a href="{{ route('bank-party-aliases.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                        <x-button>Update</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
