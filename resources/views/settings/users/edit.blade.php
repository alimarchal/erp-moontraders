<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
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
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg border border-gray-200">
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />
                    <form action="{{ route('users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pb-6">
                            <div>
                                <x-label for="name" value="Full Name" />
                                <x-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus />
                            </div>

                            <div>
                                <x-label for="email" value="Email Address" />
                                <x-input id="email" name="email" type="email" class="mt-1 block w-full bg-gray-50 text-gray-500 cursor-not-allowed" :value="$user->email" readonly title="Email cannot be changed" />
                                <p class="text-[10px] text-gray-400 mt-1 italic">Email cannot be changed once the account is created.</p>
                            </div>

                            <div>
                                <x-label for="designation" value="Designation" />
                                <x-input id="designation" name="designation" type="text" class="mt-1 block w-full" :value="old('designation', $user->designation)" />
                            </div>

                            <div>
                                <x-label for="password" value="New Password (Optional)" />
                                <x-input id="password" name="password" type="password" class="mt-1 block w-full" placeholder="Leave blank to keep current" />
                            </div>

                            @if(Gate::allows('manage-super-admins') || auth()->user()->hasRole('super-admin'))
                                <div>
                                    <x-label for="is_super_admin" value="Super Admin Access" />
                                    <select id="is_super_admin" name="is_super_admin" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <option value="No" {{ old('is_super_admin', $user->is_super_admin) === 'No' ? 'selected' : '' }}>Disabled</option>
                                        <option value="Yes" {{ old('is_super_admin', $user->is_super_admin) === 'Yes' ? 'selected' : '' }}>Enabled</option>
                                    </select>
                                </div>
                            @endif

                            <div>
                                <x-label for="is_active" value="Account Status" />
                                <select id="is_active" name="is_active" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="Yes" {{ old('is_active', $user->is_active) === 'Yes' ? 'selected' : '' }}>Active</option>
                                    <option value="No" {{ old('is_active', $user->is_active) === 'No' ? 'selected' : '' }}>Suspended</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-6">
                            <h3 class="text-md font-bold text-gray-900 uppercase italic underline tracking-wider mb-4">Roles Assignment</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pb-6">
                                @foreach($roles as $role)
                                    <label class="flex items-center bg-gray-50 p-3 rounded-md border border-gray-100 cursor-pointer hover:bg-gray-100 transition duration-150">
                                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" 
                                               {{ in_array($role->id, old('roles', $user->roles->pluck('id')->toArray())) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="ml-2 text-sm font-medium text-gray-700 uppercase italic underline">{{ $role->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-6">
                            @include('settings.roles.partials.permission-selector', ['user' => $user])
                        </div>

                        <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-100">
                            <x-button class="ml-4 bg-indigo-600 hover:bg-indigo-700">
                                Update User & Permissions
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
