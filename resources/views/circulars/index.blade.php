<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Circulars
        </h2>

        <div class="flex justify-center items-center float-right">
            <button id="toggle"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Search
            </button>
            <a href="{{ route('circulars.create') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span class="hidden md:inline-block">Add Circular</span>
            </a>
            <a href="javascript:window.location.reload();"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
            </a>

            <a href="{{ route('product.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <!-- Arrow Left Icon SVG -->
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <!-- FILTER SECTION -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg" id="filters"
            style="display: none">
            <div class="p-6">
                <form method="GET" action="{{ route('circulars.index') }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Filter by Division -->
                        <div>

                            <x-division />


                        </div>

                        <!-- Filter by Circular Number -->
                        <div>
                            <x-input-filters name="circular_no" label="Circular Number" type="text" />
                        </div>

                        <!-- Filter by Date Range -->
                        <div>
                            <x-date-from />
                        </div>

                        <div>
                            <x-date-to />
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <x-submit-button />
                </form>
            </div>
        </div>
    </div>




    <!-- TABLE SECTION -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 pb-16">
        <x-status-message />
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">

            @if ($circulars->count() > 0)
            <div class="relative overflow-x-auto rounded-lg">
                <table class="min-w-max w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-green-800 text-white uppercase text-sm">
                            <th class="py-2 px-2 text-center">Reference #</th>
                            <th class="py-2 px-2 text-left">Date</th>
                            <th class="py-2 px-2 text-left">Division</th>
                            <th class="py-2 px-2 text-left">Circular No</th>
                            <th class="py-2 px-2 text-left">Title</th>
                            {{-- <th class="py-2 px-2 text-center">Description</th> --}}

                            <th class="py-2 px-2 text-center">Attachment</th>

                            <th class="py-2 px-2 text-center print:hidden">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-black text-md leading-normal font-extrabold">
                        @foreach ($circulars->sortByDesc('created_at')->values() as $index => $circular)
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="py-1 px-2 text-center">
                                {{-- {{ $index + 1 }} --}}
                                {{ $circular->circular_number }}
                            </td>
                            <td class="py-1 px-2 text-left">
                                {{ $circular->created_at->format('d-m-Y') }}
                            </td>
                            <td class="py-1 px-2 text-left">
                                <abbr title="{{ $circular->division->name ?? '-' }}">
                                    {{ $circular->division->short_name ?? '-' }}
                                </abbr>
                            </td>
                            <td class="py-1 px-2 text-left">{{ $circular->circular_no }}</td>
                            <td class="py-1 px-2 text-left">
                                <div class="w-96 break-words leading-relaxed">
                                    {{ $circular->title }}
                                </div>
                            </td>
                            {{-- <td class="py-1 px-2 text-center">
                                {{ Str::limit($circular->description, 30) }}
                            </td> --}}
                            <td class="py-1 px-2 text-center">
                                @if ($circular->attachment)
                                <div class="flex justify-center space-x-2">
                                    <!-- Download Button -->
                                    <a href="{{ route('file.download', $circular->attachment) }}"
                                        class="inline-flex items-center px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded transition-colors duration-200"
                                        title="Download attachment">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        Download
                                    </a>

                                    <!-- View Button -->
                                    <a href="{{ route('file.view', $circular->attachment) }}"
                                        class="inline-flex items-center px-2 py-1 bg-gray-600 hover:bg-gray-700 text-white text-xs font-medium rounded transition-colors duration-200"
                                        target="_blank" title="View attachment">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        View
                                    </a>
                                </div>
                                @else
                                <span class="text-gray-400 text-sm">No file</span>
                                @endif
                            </td>

                            <td class="py-1 px-2 text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="{{ route('circulars.edit', $circular) }}"
                                        class="inline-flex items-center px-3 py-1 bg-blue-800 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-2 py-2">
                {{ $circulars->links() }}
            </div>
            @else
            <p class="text-gray-700 dark:text-gray-300 text-center py-4">
                No circulars found.
                <a href="{{ route('circulars.create') }}" class="text-blue-600 hover:underline">
                    Add a new circular
                </a>.
            </p>
            @endif
        </div>
    </div>




    @push('modals')
    <script>
        const targetDiv = document.getElementById("filters");
            const btn = document.getElementById("toggle");

            function showFilters() {
                targetDiv.style.display = 'block';
                targetDiv.style.opacity = '0';
                targetDiv.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    targetDiv.style.opacity = '1';
                    targetDiv.style.transform = 'translateY(0)';
                }, 10);
            }

            function hideFilters() {
                targetDiv.style.opacity = '0';
                targetDiv.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    targetDiv.style.display = 'none';
                }, 300);
            }

            btn.onclick = function(event) {
                event.stopPropagation();
                if (targetDiv.style.display === "none") {
                    showFilters();
                } else {
                    hideFilters();
                }
            };

            // Hide filters when clicking outside
            document.addEventListener('click', function(event) {
                if (targetDiv.style.display === 'block' && !targetDiv.contains(event.target) && event.target !== btn) {
                    hideFilters();
                }
            });
            // Function to open the modal and show the full description
            function openModal(description) {
                // Set the description content in the modal
                document.getElementById('modalDescription').innerText = description;

                // Show the modal
                document.getElementById('descriptionModal').classList.remove('hidden');
            }

            // Function to close the modal
            function closeModal() {
                // Hide the modal
                document.getElementById('descriptionModal').classList.add('hidden');
            }


            // Prevent clicks inside the filter from closing it
            targetDiv.addEventListener('click', function(event) {
                event.stopPropagation();
            });

            // Add CSS for smooth transitions
            const style = document.createElement('style');
            style.textContent = `#filters {transition: opacity 0.3s ease, transform 0.3s ease;}`;
            document.head.appendChild(style);
    </script>
    <script>
        function toggleDescription(link) {
                var preview = link.previousElementSibling.previousElementSibling;
                var fullDescription = link.previousElementSibling;

                preview.style.display = 'none';
                fullDescription.style.display = 'inline';
                link.style.display = 'none';
            }
    </script>
    <script>
        function toggleDescription(link) {
                const fullText = link.previousElementSibling; // Get the full description span
                const previewText = fullText.previousElementSibling; // Get the preview text span

                // Toggle the visibility of the full text and preview text
                if (fullText.style.display !== "none") {
                    fullText.style.display = "none"; // Hide full text
                    previewText.style.display = "block"; // Show preview text
                    link.innerText = "Read more"; // Change link text
                } else {
                    fullText.style.display = "block"; // Show full text
                    previewText.style.display = "none"; // Hide preview text
                    link.innerText = "Read less"; // Change link text
                }
            }
    </script>
    @endpush
</x-app-layout>