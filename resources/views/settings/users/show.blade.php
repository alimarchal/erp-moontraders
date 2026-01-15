<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View User: {{ $user->name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('users.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <x-status-message class="mb-4 mt-4 shadow-md" />
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label for="name" value="Full Name" />
                            <x-input id="name" type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$user->name" disabled readonly />
                        </div>

                        <div>
                            <x-label for="email" value="Email Address" />
                            <x-input id="email" type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$user->email" disabled readonly />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <x-label for="designation" value="Designation" />
                            <x-input id="designation" type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$user->designation ?? '-'" disabled readonly />
                        </div>

                        <div>
                            <x-label for="is_active" value="Accounting Status" />
                            <div class="mt-2">
                                <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $user->is_active === 'Yes' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $user->is_active === 'Yes' ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 text-sm text-gray-500 italic">
                        <div>
                            <span>Super Admin Status: </span>
                            <span class="font-bold text-gray-700">{{ $user->is_super_admin }}</span>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Assigned Roles</h3>
                        <div class="flex flex-wrap gap-2">
                            @forelse($user->roles as $role)
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                    {{ $role->name }}
                                </span>
                            @empty
                                <p class="text-gray-500 italic">No direct roles assigned.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Assigned Direct Permissions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-2">
                            @forelse($user->permissions as $permission)
                                <div class="flex items-center space-x-2 p-2 bg-gray-50 rounded border border-gray-100 italic text-sm text-gray-700">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>{{ $permission->name }}</span>
                                </div>
                            @empty
                                <p class="col-span-full text-gray-500 italic text-sm">No direct permissions assigned.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-8 pt-6 border-t">
                        <a href="{{ route('users.edit', $user) }}"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                            Edit User
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
