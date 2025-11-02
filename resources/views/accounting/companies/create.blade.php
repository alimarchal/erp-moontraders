<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Create Company
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('companies.index') }}"
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
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />
                    <form method="POST" action="{{ route('companies.store') }}">
                        @csrf

                        @include('accounting.companies.partials.form-fields', [
                            'currencyOptions' => $currencyOptions,
                            'costCenterOptions' => $costCenterOptions,
                            'accountOptions' => $accountOptions,
                            'parentOptions' => $parentOptions,
                        ])

                        <div class="flex items-center justify-end mt-6">
                            <x-button>
                                Create Company
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
