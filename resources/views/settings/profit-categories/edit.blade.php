<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Profit Category" backRoute="profit-categories.index" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />
                    <form action="{{ route('profit-categories.update', $category) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('settings.profit-categories.form')
                        <div class="flex items-center justify-end mt-4">
                            <x-button>Update Profit Category</x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
