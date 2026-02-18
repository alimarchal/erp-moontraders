<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Edit User: {{ $user->name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('users.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                title="Back to List">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 mt-4 shadow-md" />

            {{-- User Summary Card --}}
            <div class="mb-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row items-center justify-between px-6 py-4 gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center">
                            <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }} &middot; {{ $user->designation ?? 'No designation' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap justify-center sm:justify-end">
                        @foreach($user->roles as $role)
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700">{{ strtoupper($role->name) }}</span>
                        @endforeach
                        @if($user->is_super_admin === 'Yes')
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300 border border-red-200 dark:border-red-700">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"/></svg>
                                SUPER ADMIN
                            </span>
                        @endif
                        <span @class([
                            'inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full border',
                            'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 dark:border-emerald-700' => $user->is_active === 'Yes',
                            'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/40 dark:text-red-300 dark:border-red-700' => $user->is_active === 'No',
                        ])>
                            <span @class([
                                'w-1.5 h-1.5 rounded-full mr-1.5',
                                'bg-emerald-500' => $user->is_active === 'Yes',
                                'bg-red-500' => $user->is_active === 'No',
                            ])></span>
                            {{ $user->is_active === 'Yes' ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
            </div>

            <x-validation-errors class="mb-4" />

            <form action="{{ route('users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- Section 1: Account Details --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Account Details
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-label for="name" value="Full Name" />
                                <x-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus />
                            </div>

                            <div>
                                <x-label for="email" value="Email Address" />
                                <x-input id="email" name="email" type="email" class="mt-1 block w-full bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 cursor-not-allowed" :value="$user->email" readonly title="Email cannot be changed" />
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Email cannot be changed once the account is created.</p>
                            </div>

                            <div>
                                <x-label for="designation" value="Designation" />
                                <x-input id="designation" name="designation" type="text" class="mt-1 block w-full" :value="old('designation', $user->designation)" placeholder="e.g. Manager, Accountant" />
                            </div>

                            <div>
                                <x-label for="password" value="New Password" />
                                <x-input id="password" name="password" type="password" class="mt-1 block w-full" placeholder="Leave blank to keep current" autocomplete="new-password" />
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Minimum 8 characters. Only fill if changing password.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section 2: Access Control --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Access Control
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @if(Gate::allows('manage-super-admins') || auth()->user()->hasRole('super-admin'))
                                <div>
                                    <x-label for="is_super_admin" value="Super Admin Access" />
                                    <select id="is_super_admin" name="is_super_admin" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <option value="No" {{ old('is_super_admin', $user->is_super_admin) === 'No' ? 'selected' : '' }}>Disabled</option>
                                        <option value="Yes" {{ old('is_super_admin', $user->is_super_admin) === 'Yes' ? 'selected' : '' }}>Enabled</option>
                                    </select>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Super admins bypass all permission checks.</p>
                                </div>
                            @endif

                            <div>
                                <x-label for="is_active" value="Account Status" />
                                <select id="is_active" name="is_active" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="Yes" {{ old('is_active', $user->is_active) === 'Yes' ? 'selected' : '' }}>Active</option>
                                    <option value="No" {{ old('is_active', $user->is_active) === 'No' ? 'selected' : '' }}>Suspended</option>
                                </select>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Suspended users cannot log in.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section 3: Roles --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Roles
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                            @foreach($roles as $role)
                                <label class="relative flex items-center gap-2.5 px-4 py-3 rounded-lg border cursor-pointer transition-all duration-150
                                    {{ in_array($role->id, old('roles', $user->roles->pluck('id')->toArray())) ? 'bg-indigo-50 border-indigo-300 dark:bg-indigo-900/30 dark:border-indigo-600' : 'bg-gray-50 border-gray-200 hover:border-gray-300 dark:bg-gray-700/50 dark:border-gray-600 dark:hover:border-gray-500' }}">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                                           {{ in_array($role->id, old('roles', $user->roles->pluck('id')->toArray())) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-500 dark:bg-gray-900"
                                           onchange="this.closest('label').classList.toggle('bg-indigo-50', this.checked); this.closest('label').classList.toggle('border-indigo-300', this.checked); this.closest('label').classList.toggle('bg-gray-50', !this.checked); this.closest('label').classList.toggle('border-gray-200', !this.checked);">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Section 4: Permissions --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Permissions
                        </h3>
                    </div>
                    <div class="p-6">
                        @include('settings.roles.partials.permission-selector', ['user' => $user])
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('users.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <x-button class="bg-indigo-600 hover:bg-indigo-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Update User & Permissions
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
