<x-app-layout title="Edit Permission">
    <x-page-header title="Edit Permission: {{ $permission->name }}" :breadcrumbs="[
        ['label' => 'Settings', 'url' => '#'],
        ['label' => 'Permissions', 'url' => route('permissions.index')],
        ['label' => 'Edit', 'url' => '#'],
    ]">
        <div class="flex items-center space-x-2">
            <a href="{{ route('permissions.index') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to List
            </a>
        </div>
    </x-page-header>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <x-validation-errors class="mb-4" />

            <form action="{{ route('permissions.update', $permission) }}" method="POST">
                @csrf
                @method('PUT')

                <div
                    class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-label for="name" value="Permission Name" />
                                <x-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $permission->name)" required autofocus />
                                <p class="mt-2 text-sm text-gray-500">
                                    Changing a permission name will affect all roles and users that currently have it.
                                </p>
                            </div>

                            <div>
                                <x-label for="guard_name" value="Guard Name" />
                                <x-input id="guard_name" name="guard_name" type="text"
                                    class="mt-1 block w-full bg-gray-50 dark:bg-gray-700 text-gray-500"
                                    :value="$permission->guard_name" readonly />
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end">
                        <x-button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            Update Permission
                        </x-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>