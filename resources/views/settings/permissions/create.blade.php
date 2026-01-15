<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Create New Permission
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('permissions.index') }}"
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
                    <form action="{{ route('permissions.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6">
                            <div>
                                <x-label for="name" value="Permission Name" />
                                <x-input id="name" name="name" type="text" class="mt-1 block w-full"
                                    :value="old('name')" required autofocus placeholder="e.g. order-edit" />
                                <p class="mt-2 text-[10px] text-gray-500 italic">
                                    Recommended format: <code class="bg-gray-100 px-1 rounded">module-action</code>
                                    (e.g., <code class="bg-gray-100 px-1 rounded">user-list</code>)
                                </p>
                            </div>

                            <div>
                                <x-label for="guard_name" value="Guard Name" />
                                <x-input id="guard_name" name="guard_name" type="text"
                                    class="mt-1 block w-full bg-gray-50 text-gray-500 cursor-not-allowed" value="web"
                                    readonly />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-100">
                            <x-button class="ml-4 bg-indigo-600 hover:bg-indigo-700">
                                Save Permission
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>