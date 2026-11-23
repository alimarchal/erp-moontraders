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
                'classic' => ['Classic', 'The colourful dashboard: gradient header, six coloured tiles and a chart for every list.', 'images/layouts/dashboard-classic.jpg'],
                'a' => ['Focus', 'Calm white cards: a compact top bar, one Sales chart, one Leaders card with tabs, and what needs attention on the side.', 'images/layouts/dashboard-a.jpg'],
            ],
        ],
        'settings' => [
            'title' => 'Settings page',
            'ur' => 'سیٹنگز',
            'options' => [
                'classic' => ['Classic', 'Three columns of lists, as before.', 'images/layouts/settings-classic.jpg'],
                'a' => ['Option A: side menu + tiles', 'Groups on the left, tidy tiles on the right, search stays on top. Chips on a phone.', 'images/layouts/settings-a.jpg'],
                'b' => ['Option B: one card per group', 'Nine cards with their pages inside and a "Recently used" row. Fits about one screen.', 'images/layouts/settings-b.jpg'],
            ],
        ],
        'reports' => [
            'title' => 'Reports page',
            'ur' => 'رپورٹس',
            'options' => [
                'classic' => ['Classic', 'Three columns of lists, as before.', 'images/layouts/reports-classic.jpg'],
                'a' => ['Option A: group chips + pins', 'Search on top, chips that jump to each group, and a Pinned row for the reports each user stars.', 'images/layouts/reports-a.jpg'],
                'b' => ['Option B: two panes', 'Groups on the left, the chosen group\'s reports on the right, like Mail. One group at a time on a phone.', 'images/layouts/reports-b.jpg'],
            ],
        ],
        'goods_receipt_notes' => [
            'title' => 'Goods receipt notes (create, edit, view)',
            'ur' => 'گڈز ریسیپٹ نوٹ',
            'options' => [
                'classic' => ['Classic', 'The one wide table with every column, as before.', 'images/layouts/grn-classic.jpg'],
                'a' => ['Option A: spreadsheet grid', 'Every column in one grid, grouped, with the product and line total pinned, a status mark per line, filters and a totals bar.', 'images/layouts/grn-a.jpg'],
                'b' => ['Option B: lines list + line editor', 'Lines on the left, the chosen line with all its fields on the right. Save and next with Enter. Best for long GRNs and tablets.', 'images/layouts/grn-b.jpg'],
                'c' => ['Option C: entry bar + table', 'Every field of one line in a bar on top; Enter adds it to the table below. Edit loads a line back into the bar. Fastest for keyboard entry.', 'images/layouts/grn-c.jpg'],
                'd' => ['Option D: line cards', 'One card per line; open a card to fill all its fields, the rest stay closed. Large buttons, made for phones and tablets.', 'images/layouts/grn-d.jpg'],
                'e' => ['Option E: step by step', 'Three steps: receipt details, then the lines, then a review of every line and check before saving. Easiest for new staff.', 'images/layouts/grn-e.jpg'],
                'f' => ['Option F: invoice', 'Looks like the supplier\'s paper invoice: rows you click to open, totals bottom-right, remarks and signature lines. The view prints like a document.', 'images/layouts/grn-f.jpg'],
                'g' => ['Option G: focus mode', 'One line at a time, large fields and big Previous / Next buttons; lines as chips on top. Best on tablets.', 'images/layouts/grn-g.jpg'],
                'h' => ['Option H: enterprise', 'A professional ERP object page, like SAP or Oracle: key figures in the header, section tabs, a dense items table with an item detail panel, and a full summary.', 'images/layouts/grn-h.jpg'],
                'i' => ['Option I: lines + popup', 'A short, easy list of lines; click a line or Add line and a popup opens with every field of that line. Enter saves and opens the next line.', 'images/layouts/grn-i.jpg'],
            ],
        ],
        'goods_receipt_notes_index' => [
            'title' => 'Goods receipt notes list',
            'ur' => 'گڈز ریسیپٹ نوٹ کی فہرست',
            'options' => [
                'classic' => ['Classic', 'Filter box and the wide table, as before.', 'images/layouts/grn-index-classic.jpg'],
                'a' => ['Option A: table + status tabs', 'Tabs with the count and amount of drafts, posted and reversed GRNs, a search with folding filters, and a clean table that stacks on a phone.', 'images/layouts/grn-index-a.jpg'],
                'b' => ['Option B: cards', 'One card per GRN with the amount large, key facts and actions. Three across on a wide screen, one on a phone.', 'images/layouts/grn-index-b.jpg'],
                'c' => ['Option C: by day', 'GRNs grouped by receipt day with each day\'s count and amount, like a timeline.', 'images/layouts/grn-index-c.jpg'],
                'd' => ['Option D: enterprise grid', 'A dense, striped ERP grid with every key column, received by, and page totals in the footer.', 'images/layouts/grn-index-d.jpg'],
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
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                            @foreach ($config['options'] as $value => [$label, $description, $preview])
                                <label class="group relative flex cursor-pointer flex-col overflow-hidden rounded-xl ring-1 ring-gray-200 transition has-[:checked]:ring-2 has-[:checked]:ring-indigo-600 hover:ring-gray-300">
                                    <input type="radio" name="{{ $page }}" value="{{ $value }}" class="peer sr-only" @checked(old($page, $current[$page]) === $value)>
                                    <span class="block aspect-[18/7] overflow-hidden bg-gray-100">
                                        @if ($preview && file_exists(public_path($preview)))
                                            <img src="{{ asset($preview) }}" alt="{{ $label }} preview" class="h-full w-full object-cover object-top" loading="lazy">
                                        @else
                                            <span class="flex h-full w-full items-center justify-center text-xs text-gray-400">{{ $value === 'classic' ? 'Current layout' : 'Save to try it on the page' }}</span>
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
