<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Edit Journal Entry
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('journal-entries.show', $journalEntry->id) }}"
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
            <x-validation-errors class="mb-4 mt-4" />

            <form method="POST" action="{{ route('journal-entries.update', $journalEntry->id) }}">
                @csrf
                @method('PUT')

                @include('journal-entries.partials.form', [
                    'entry' => $journalEntry,
                    'submitLabel' => 'Update Journal Entry',
                    'defaultCurrencyId' => $defaultCurrencyId ?? null,
                ])
            </form>
        </div>
    </div>
</x-app-layout>
