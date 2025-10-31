<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Edit Circular: {{ $circular->circular_no }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('circulars.index') }}"
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
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <x-status-message class="mb-4 mt-4" />
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />
                    <form method="POST" action="{{ route('circulars.update', $circular) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="flex-1">
                                    <x-label for="circular_no" value="Circular Number" :required="true" />
                                    <x-input id="circular_no" type="text" name="circular_no"
                                        class="mt-1 block w-full" :value="old('circular_no', $circular->circular_no)" required />
                                </div>

                                <div class="flex-1">
                                    <x-label for="title" value="Title" />
                                    <x-input id="title" type="text" name="title" class="mt-1 block w-full"
                                        :value="old('title', $circular->title)" />
                                </div>

                                <div class="flex-1">
                                    <x-label for="division_id" value="Division" :required="true" />
                                    <select id="division_id" name="division_id"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm select2"
                                        required>
                                        <option value="">Select Division</option>
                                        @foreach ($divisions as $division)
                                            <option value="{{ $division->id }}"
                                                {{ old('division_id', $circular->division_id) == $division->id ? 'selected' : '' }}>
                                                {{ $division->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-span-2">
                            <x-label for="description" value="Description" />
                            <textarea id="description" name="description"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                rows="3">{{ old('description', $circular->description) }}</textarea>
                        </div>

                        <div>
                            <x-label for="attachment" value="Attachment" />
                            @if ($circular->attachment)
                                <div class="mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Current file: </span>
                                    <a href="{{ Storage::url($circular->attachment) }}"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                        target="_blank">
                                        View Current Attachment
                                    </a>
                                </div>
                            @endif
                            <input type="file" id="attachment" name="attachment"
                                class="mt-1 block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-md file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-950 file:text-white
                                hover:file:bg-green-800" />
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Leave empty to keep the current file
                            </p>
                        </div>
                        <div class="flex items-center justify-end mt-4">
                            <x-button class="ml-4">
                                Create Circular
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Include Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

    <!-- Include Select2 JS -->
    <script>
        $(document).ready(function() {
            $('.select2').select2();
        });
    </script>
</x-app-layout>