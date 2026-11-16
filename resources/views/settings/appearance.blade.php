{{--
    Settings → Appearance (super admins only): which layout the dashboard, the Settings page and the Reports
    page use, for everyone. Classic is the page as it was; A and B are the redesigns from issues #196 and #197.
--}}
@php
    $pages = [
        'dashboard' => [
            'title' => 'Dashboard',
            'ur' => 'ڈیش بورڈ',
            'options' => [
                'classic' => ['Classic', 'The colourful dashboard: gradient header, six coloured tiles and a chart for every list.', null],
                'a' => ['Focus', 'Calm white cards: a compact top bar, one Sales chart, one Leaders card with tabs, and what needs attention on the side.', 'images/layouts/dashboard-a.jpg'],
            ],
        ],
        'settings' => [
            'title' => 'Settings page',
            'ur' => 'سیٹنگز',
            'options' => [
                'classic' => ['Classic', 'Three columns of lists, as before.', null],
                'a' => ['Option A: side menu + tiles', 'Groups on the left, tidy tiles on the right, search stays on top. Chips on a phone.', 'images/layouts/settings-a.jpg'],
                'b' => ['Option B: one card per group', 'Nine cards with their pages inside and a "Recently used" row. Fits about one screen.', 'images/layouts/settings-b.jpg'],
            ],
        ],
        'reports' => [
            'title' => 'Reports page',
            'ur' => 'رپورٹس',
            'options' => [
                'classic' => ['Classic', 'Three columns of lists, as before.', null],
                'a' => ['Option A: group chips + pins', 'Search on top, chips that jump to each group, and a Pinned row for the reports each user stars.', 'images/layouts/reports-a.jpg'],
                'b' => ['Option B: two panes', 'Groups on the left, the chosen group\'s reports on the right, like Mail. One group at a time on a phone.', 'images/layouts/reports-b.jpg'],
            ],
        ],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">Appearance</h2>
    </x-slot>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600&display=swap" rel="stylesheet">

    <div class="py-6 bg-[#F2F1F5] min-h-full">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-5 flex flex-wrap items-center gap-3">
                <a href="{{ route('settings.index') }}" class="text-sm text-indigo-700 hover:underline">← Settings</a>
                <p class="text-sm text-gray-600">Pick how each page looks. The choice applies to <b>every user</b> and only super admins can change it.</p>
            </div>

            @if (session('success'))
                <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200" role="status">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">Choose a layout for every page.</div>
            @endif

            <form method="POST" action="{{ route('settings.appearance.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                @foreach ($pages as $page => $config)
                    <fieldset class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-5" data-appearance-page="{{ $page }}">
                        <legend class="sr-only">{{ $config['title'] }}</legend>
                        <div class="mb-3 flex items-baseline gap-3">
                            <h3 class="text-base font-semibold text-gray-900">{{ $config['title'] }}</h3>
                            <span class="text-xs text-gray-400" style="font-family: 'Noto Nastaliq Urdu', serif">{{ $config['ur'] }}</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            @foreach ($config['options'] as $value => [$label, $description, $preview])
                                <label class="group relative flex cursor-pointer flex-col overflow-hidden rounded-xl ring-1 ring-gray-200 transition has-[:checked]:ring-2 has-[:checked]:ring-indigo-600 hover:ring-gray-300">
                                    <input type="radio" name="{{ $page }}" value="{{ $value }}" class="peer sr-only" @checked(old($page, $current[$page]) === $value)>
                                    <span class="block aspect-[16/10] bg-gray-100">
                                        @if ($preview && file_exists(public_path($preview)))
                                            <img src="{{ asset($preview) }}" alt="{{ $label }} preview" class="h-full w-full object-cover object-top" loading="lazy">
                                        @else
                                            <span class="flex h-full w-full items-center justify-center text-xs text-gray-400">{{ $value === 'classic' ? 'Current layout' : 'Preview' }}</span>
                                        @endif
                                    </span>
                                    <span class="flex items-start gap-2 p-3">
                                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full ring-1 ring-gray-300 group-has-[:checked]:bg-indigo-600 group-has-[:checked]:ring-4 group-has-[:checked]:ring-indigo-100" aria-hidden="true"></span>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-gray-900">{{ $label }}</span>
                                            <span class="block text-xs text-gray-500">{{ $description }}</span>
                                        </span>
                                    </span>
                                    <span class="pointer-events-none absolute right-2 top-2 hidden rounded-full bg-indigo-600 px-2 py-0.5 text-[11px] font-semibold text-white group-has-[:checked]:block">In use</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-blue-950 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Save layouts</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
