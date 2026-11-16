{{--
    Reports, Option A (issue #197): one page with the search and group chips on top, a "Pinned" row of the
    reports this user starred, and tiles per group. Links and permissions come from App\Support\Menus\ReportsMenu.

    @var list<array{key: string, name: string, urdu: string, color: string, icon: string, items: list<array{id: string, label: string, description: string, url: string, icon: string, count: int|null, search: string}>}> $groups
--}}
@php
    $itemIds = collect($groups)->flatMap(fn (array $group) => array_column($group['items'], 'id'))->values();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reports</h2>
        <p class="mt-0.5 text-[13px] text-slate-500">{{ count($groups) }} groups · {{ $itemIds->count() }} reports · only the ones you have access to</p>
    </x-slot>

    <x-menu-page-head />

    <div class="menu-page bg-[#f5f5f7] min-h-full" data-menu-spy
        x-data="menuPage({ storageKey: 'moontraders.reports.pins.{{ auth()->id() }}', ids: @js($itemIds), groups: @js(array_column($groups, 'key')), allChip: true })"
        x-on:keydown.window="focusSearch($event)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-6">
            <div class="sticky top-0 z-10 -mx-4 px-4 sm:mx-0 sm:px-0 pt-1 pb-2 bg-[#f5f5f7]/90 backdrop-blur space-y-2.5">
                <x-menu-search id="reports-search" label="Search reports" placeholder="Search reports — cash, cheque, aging, stock…" />
                <div x-ref="chips" class="relative flex gap-2 overflow-x-auto menu-noscroll -mx-4 px-4 sm:mx-0 sm:px-0">
                    <button type="button" data-chip="all" x-on:click="toTop()"
                        x-bind:class="active === null ? 'bg-slate-900 text-white' : 'bg-white ring-1 ring-slate-200 text-slate-600'"
                        class="shrink-0 h-8 px-3 rounded-full text-[12px] inline-flex items-center whitespace-nowrap">All</button>
                    @foreach ($groups as $group)
                        <button type="button" data-chip="{{ $group['key'] }}" x-on:click="jump(@js($group['key']))"
                            x-show="groupHasMatches(@js($group['key']))"
                            x-bind:class="active === @js($group['key']) ? 'bg-slate-900 text-white' : 'bg-white ring-1 ring-slate-200 text-slate-600'"
                            class="shrink-0 h-8 px-3 rounded-full text-[12px] inline-flex items-center gap-1.5 whitespace-nowrap">
                            {{ $group['name'] }}
                            <span class="text-[11px] tabular-nums opacity-60">{{ count($group['items']) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @include('reports.layouts.partials.pinned', ['groups' => $groups])

            @foreach ($groups as $group)
                <section id="group-{{ $group['key'] }}" data-menu-section="{{ $group['key'] }}" x-show="groupVisible($el)" class="scroll-mt-32">
                    <div class="flex items-baseline gap-3 mb-2.5 px-1">
                        <h3 class="text-[15px] font-semibold text-slate-900">{{ $group['name'] }}</h3>
                        <span class="menu-ur text-[12px] text-slate-400" lang="ur">{{ $group['urdu'] }}</span>
                        <span class="ml-auto text-[12px] text-slate-400 tabular-nums">{{ count($group['items']) }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2.5">
                        @foreach ($group['items'] as $item)
                            <div data-menu-item data-search="{{ $item['search'] }}" x-show="matches($el)"
                                class="flex items-center bg-white rounded-xl ring-1 ring-slate-200/70 hover:ring-slate-300 hover:shadow-sm transition">
                                <a href="{{ $item['url'] }}" data-menu-link title="{{ $item['description'] }}"
                                    class="min-w-0 flex-1 flex items-center gap-3 pl-3.5 py-3">
                                    <span class="w-9 h-9 shrink-0 rounded-[10px] {{ $group['color'] }} text-white grid place-items-center">
                                        <x-menu-icon :name="$item['icon']" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[14px] font-medium text-slate-900 truncate">{{ $item['label'] }}</span>
                                        <span class="block text-[12px] text-slate-500 truncate">{{ $item['description'] }}</span>
                                    </span>
                                </a>
                                @include('reports.layouts.partials.pin-button', ['item' => $item, 'class' => 'mx-1.5'])
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div x-show="query.trim() !== '' && visibleCount() === 0" x-cloak class="py-16 text-center text-sm text-slate-500" data-menu-empty>
                No reports match "<span x-text="query"></span>".
            </div>
        </div>
    </div>
</x-app-layout>
