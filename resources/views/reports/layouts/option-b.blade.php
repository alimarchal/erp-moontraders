{{--
    Reports, Option B (issue #197): two panes like Mail. Groups on the left, the chosen group's reports on
    the right with a star to pin each one. On a phone, tapping a group opens its list with a back button.
    While searching, the right pane lists the matches of every group. Links and permissions come from
    App\Support\Menus\ReportsMenu.

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

    <div class="menu-page bg-[#f5f5f7] min-h-full"
        x-data="menuPage({ storageKey: 'moontraders.reports.pins.{{ auth()->id() }}', ids: @js($itemIds), groups: @js(array_column($groups, 'key')) })"
        x-on:keydown.window="focusSearch($event)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-5">
            <div class="max-w-2xl">
                <x-menu-search id="reports-search" label="Search reports" placeholder="Search all reports…" />
            </div>

            @include('reports.layouts.partials.pinned', ['groups' => $groups])

            <div class="lg:grid lg:grid-cols-[300px_minmax(0,1fr)] lg:gap-6 lg:items-start">
                <nav x-ref="groups" aria-label="Report groups" x-bind:class="{ 'hidden lg:block': mobileOpen || query.trim() !== '' }"
                    class="scroll-mt-4 space-y-1 lg:sticky lg:top-4 bg-white lg:bg-transparent rounded-2xl ring-1 ring-slate-200/70 lg:ring-0 p-1.5 lg:p-0">
                    @foreach ($groups as $group)
                        <button type="button" x-on:click="open(@js($group['key']))" x-show="groupHasMatches(@js($group['key']))"
                            x-bind:class="selected === @js($group['key']) ? 'lg:bg-white lg:shadow-sm lg:ring-1 lg:ring-slate-200' : 'lg:hover:bg-white/70'"
                            class="w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                            <span class="w-8 h-8 shrink-0 rounded-lg {{ $group['color'] }} text-white grid place-items-center">
                                <x-menu-icon :name="$group['icon']" class="w-4 h-4" />
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-[14px] truncate"
                                    x-bind:class="selected === @js($group['key']) ? 'lg:font-semibold text-slate-900' : 'text-slate-700'">{{ $group['name'] }}</span>
                                <span class="block menu-ur text-[11px] leading-tight text-slate-400 text-left" lang="ur">{{ $group['urdu'] }}</span>
                            </span>
                            <span class="text-[12px] text-slate-400 tabular-nums">{{ count($group['items']) }}</span>
                            <span class="text-slate-300 lg:hidden"><x-menu-icon name="chev" class="w-4 h-4" /></span>
                        </button>
                    @endforeach
                </nav>

                <div x-ref="pane" x-bind:class="{ 'hidden lg:block': ! mobileOpen && query.trim() === '' }" class="hidden lg:block scroll-mt-4 space-y-4">
                    <button type="button" x-on:click="back()" x-show="query.trim() === ''"
                        class="lg:hidden inline-flex items-center gap-1.5 h-9 px-3 rounded-xl bg-white ring-1 ring-slate-200 text-[13px] text-slate-700">
                        <x-menu-icon name="arrowleft" class="w-4 h-4" /> All groups
                    </button>

                    @foreach ($groups as $group)
                        <section data-menu-section="{{ $group['key'] }}"
                            x-show="query.trim() !== '' ? groupVisible($el) : selected === @js($group['key'])"
                            @if (! $loop->first) style="display: none" @endif
                            class="bg-white rounded-2xl ring-1 ring-slate-200/70 shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 px-4 sm:px-5 py-4">
                                <span class="w-10 h-10 shrink-0 rounded-xl {{ $group['color'] }} text-white grid place-items-center">
                                    <x-menu-icon :name="$group['icon']" class="w-5 h-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-[17px] font-semibold text-slate-900">{{ $group['name'] }}</h3>
                                    <p class="text-[12px] text-slate-500">{{ count($group['items']) }} {{ Str::plural('report', count($group['items'])) }}</p>
                                </div>
                                <span class="ml-auto menu-ur text-[13px] text-slate-400" lang="ur">{{ $group['urdu'] }}</span>
                            </div>
                            <ul>
                                @foreach ($group['items'] as $item)
                                    <li data-menu-item data-search="{{ $item['search'] }}" x-show="matches($el)"
                                        class="flex items-center gap-1 pl-2 sm:pl-3 border-t border-slate-100 hover:bg-slate-50 transition-colors">
                                        @include('reports.layouts.partials.pin-button', ['item' => $item])
                                        <a href="{{ $item['url'] }}" data-menu-link class="group min-w-0 flex-1 flex items-center gap-3 py-3 pr-4 sm:pr-5">
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-[14px] font-medium text-slate-900">{{ $item['label'] }}</span>
                                                <span class="block text-[12px] text-slate-500">{{ $item['description'] }}</span>
                                            </span>
                                            <span class="text-slate-300 group-hover:text-slate-400"><x-menu-icon name="chev" class="w-4 h-4" /></span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach

                    <div x-show="query.trim() !== '' && visibleCount() === 0" x-cloak class="py-16 text-center text-sm text-slate-500" data-menu-empty>
                        No reports match "<span x-text="query"></span>".
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
