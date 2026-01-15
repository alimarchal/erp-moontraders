<x-app-layout title="Create User">
    <x-page-header title="Add New User" :breadcrumbs="[
        ['label' => 'Settings', 'url' => '#'],
        ['label' => 'Users', 'url' => route('users.index')],
        ['label' => 'Create', 'url' => '#'],
    ]">
        <div class="flex items-center space-x-2">
            <a href="{{ route('users.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to List
            </a>
        </div>
    </x-page-header>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <x-validation-errors class="mb-4" />

            <form action="{{ route('users.store') }}" method="POST">
                @csrf

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-b border-gray-100 dark:border-gray-700 pb-6">
                            <div>
                                <x-label for="name" value="Full Name" />
                                <x-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                            </div>

                            <div>
                                <x-label for="email" value="Email Address" />
                                <x-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                            </div>

                            <div>
                                <x-label for="designation" value="Designation" />
                                <x-input id="designation" name="designation" type="text" class="mt-1 block w-full" :value="old('designation')" />
                            </div>

                            <div>
                                <x-label for="password" value="Password" />
                                <x-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                            </div>

                            @if(auth()->user()->is_super_admin === 'Yes' || auth()->user()->hasRole('super-admin'))
                                <div>
                                    <x-label for="is_super_admin" value="Super Admin Access" />
                                    <select id="is_super_admin" name="is_super_admin" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                        <option value="No" {{ old('is_super_admin') === 'No' ? 'selected' : '' }}>Disabled</option>
                                        <option value="Yes" {{ old('is_super_admin') === 'Yes' ? 'selected' : '' }}>Enabled</option>
                                    </select>
                                </div>
                            @else
                                <input type="hidden" name="is_super_admin" value="No">
                            @endif

                            <div>
                                <x-label for="is_active" value="Account Status" />
                                <select id="is_active" name="is_active" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="Yes" {{ old('is_active') === 'Yes' ? 'selected' : '' }}>Active</option>
                                    <option value="No" {{ old('is_active') === 'No' ? 'selected' : '' }}>Suspended</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-md font-bold text-gray-900 dark:text-gray-100 uppercase italic underline tracking-wider mb-4">Roles Assignment</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                @forelse($roles as $role)
                                    <label class="flex items-center bg-gray-50 dark:bg-gray-700 p-3 rounded-md border border-gray-100 dark:border-gray-600 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-650 transition duration-150">
                                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" 
                                               {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300 uppercase italic underline">{{ $role->name }}</span>
                                    </label>
                                @empty
                                    <p class="text-sm text-gray-500 italic">No roles available. Create roles first.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="border-t border-gray-100 dark:border-gray-700 pt-6">
                            @include('settings.roles.partials.permission-selector')
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end space-x-3">
                        <x-button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            Save User & Permissions
                        </x-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
