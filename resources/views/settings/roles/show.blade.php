@php
    $permissions_count = $role->permissions->count();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Role: {{ $role->name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('roles.index') }}"
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
                            <x-label for="name" value="Role Name" />
                            <x-input id="name" type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$role->name" disabled readonly />
                        </div>

                        <div>
                            <x-label for="guard_name" value="Guard Name" />
                            <x-input id="guard_name" type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$role->guard_name" disabled readonly />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <x-label for="created_at" value="Created At" />
                            <x-input id="created_at" type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$role->created_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                        </div>

                        <div>
                            <x-label for="updated_at" value="Last Updated" />
                            <x-input id="updated_at" type="text"
                                class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$role->updated_at?->format('d-m-Y H:i:s') ?? '-'" disabled readonly />
                        </div>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Assigned Permissions ({{ $permissions_count }})</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-2">
                            @forelse($role->permissions as $permission)
                                <div class="flex items-center space-x-2 p-2 bg-gray-50 rounded border border-gray-100 italic text-sm text-gray-700">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>{{ $permission->name }}</span>
                                </div>
                            @empty
                                <p class="col-span-full text-gray-500 italic">No permissions assigned to this role.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-8 pt-6 border-t">
                        <a href="{{ route('roles.edit', $role) }}"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                            Edit Role
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
