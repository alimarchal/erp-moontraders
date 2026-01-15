<x-app-layout title="Create Role">
    <x-page-header title="Create New Role" :breadcrumbs="[
        ['label' => 'Settings', 'url' => '#'],
        ['label' => 'Roles', 'url' => route('roles.index')],
        ['label' => 'Create', 'url' => '#'],
    ]">
        <div class="flex items-center space-x-2">
            <a href="{{ route('roles.index') }}"
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

            <form action="{{ route('roles.store') }}" method="POST">
                @csrf

                <div
                    class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6">
                            <div>
                                <x-label for="name" value="Role Name" />
                                <x-input id="name" name="name" type="text" class="mt-1 block w-full"
                                    :value="old('name')" required autofocus placeholder="e.g. Sales Manager" />
                            </div>

                            <div>
                                <x-label for="guard_name" value="Guard Name" />
                                <x-input id="guard_name" name="guard_name" type="text"
                                    class="mt-1 block w-full bg-gray-50 dark:bg-gray-700 text-gray-500" value="web"
                                    readonly />
                            </div>
                        </div>

                        <div class="border-t border-gray-100 dark:border-gray-700 pt-6">
                            @include('settings.roles.partials.permission-selector')
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end space-x-3">
                        <x-button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            Create Role & Assign Permissions
                        </x-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>