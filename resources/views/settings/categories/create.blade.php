<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Create Category" backRoute="categories.index" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8 bg-white border-b border-gray-200">
                    <form action="{{ route('categories.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="name" value="Category Name" :required="true" />
                                <x-input id="name" type="text" name="name" class="mt-1 block w-full"
                                    :value="old('name')" required autofocus />
                                <x-input-error for="name" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="slug" value="Slug (Optional)" />
                                <x-input id="slug" type="text" name="slug" class="mt-1 block w-full"
                                    :value="old('slug')" />
                                <x-input-error for="slug" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="is_active" class="inline-flex items-center">
                                <input id="is_active" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                <span class="ml-2 text-sm text-gray-600">Active</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-button class="ml-4">
                                {{ __('Create Category') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>