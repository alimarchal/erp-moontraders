<x-app-layout title="System Settings">
    <x-page-header title="Settings" :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Settings', 'url' => '#'],
    ]" />

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            @foreach($categories as $categoryName => $items)
                @php
                    $visibleItems = array_filter($items, fn($item) => auth()->user()->can($item['permission']));
                @endphp

                @if(count($visibleItems) > 0)
                    <section>
                        <div class="flex items-center space-x-2 mb-6 border-b border-gray-100 dark:border-gray-700 pb-2">
                            <h2
                                class="text-xl font-bold text-gray-900 dark:text-gray-100 uppercase tracking-widest italic underline">
                                {{ $categoryName }}</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($visibleItems as $setting)
                                <a href="{{ route($setting['route']) }}"
                                    class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                                    <div class="relative flex items-center space-x-4">
                                        <div
                                            class="flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-xl bg-gray-50 dark:bg-gray-700/50 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 transition-colors duration-300">
                                            {!! $setting['icon'] !!}
                                        </div>
                                        <div>
                                            <h3
                                                class="text-lg font-bold text-gray-800 dark:text-gray-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                                {{ $setting['title'] }}
                                            </h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                                {{ $setting['description'] }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-6 flex items-center text-[10px] font-bold uppercase tracking-widest text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                        <span>Manage Module</span>
                                        <svg class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                        </svg>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if(count(array_filter(array_merge(...array_values($categories)), fn($s) => auth()->user()->can($s['permission']))) === 0)
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center border-2 border-dashed border-gray-200 dark:border-gray-700">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No settings available</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You don't have permission to access any
                        settings modules.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>