<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Edit Account Type: {{ $accountType->type_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('account-types.index') }}"
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
                <x-status-message class="mb-4 mt-4" />
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />
                    <form method="POST" action="{{ route('account-types.update', $accountType) }}">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="flex-1">
                                    <x-label for="type_name" value="Type Name" :required="true" />
                                    <x-input id="type_name" type="text" name="type_name" class="mt-1 block w-full"
                                        :value="old('type_name', $accountType->type_name)" required />
                                </div>

                                <div class="flex-1">
                                    <x-label for="report_group" value="Report Group" />
                                    <x-input id="report_group" type="text" name="report_group" class="mt-1 block w-full"
                                        :value="old('report_group', $accountType->report_group)" />
                                </div>
                            </div>
                        </div>

                        <div class="col-span-2 mt-4">
                            <x-label for="description" value="Description" />
                            <textarea id="description" name="description"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                rows="3">{{ old('description', $accountType->description) }}</textarea>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-button class="ml-4">
                                Update Account Type
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>