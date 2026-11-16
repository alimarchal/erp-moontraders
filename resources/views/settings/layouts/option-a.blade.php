{{--
    Settings, Option A (issue #196): a menu of groups on the left (a row of chips on a phone), tiles on
    the right, and a search that stays on top. Links and permissions come from App\Support\Menus\SettingsMenu.

    @var list<array{key: string, name: string, urdu: string, color: string, icon: string, items: list<array{id: string, label: string, description: string, url: string, icon: string, count: int|null, search: string}>}> $groups
--}}
@php
    $itemIds = collect($groups)->flatMap(fn (array $group) => array_column($group['items'], 'id'))->values();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
        <p class="mt-0.5 text-[13px] text-slate-500">Master data and setup · {{ count($groups) }} groups · {{ $itemIds->count() }} pages</p>
    </x-slot>

    <x-menu-page-head />

    <div class="menu-page bg-[#f5f5f7] min-h-full" data-menu-spy
        x-data="menuPage({ storageKey: 'moontraders.settings.recent.{{ auth()->id() }}', limit: 12, ids: @js($itemIds), groups: @js(array_column($groups, 'key')) })"
        x-on:keydown.window="focusSearch($event)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 lg:flex lg:gap-8">
            <aside class="hidden lg:block w-60 shrink-0">
                <nav class="sticky top-4 space-y-0.5" aria-label="Settings groups">
                    @foreach ($groups as $group)
                        <a href="#group-{{ $group['key'] }}" x-on:click.prevent="jump(@js($group['key']))"
                            x-show="groupHasMatches(@js($group['key']))"
                            x-bind:class="active === @js($group['key']) ? 'bg-white shadow-sm ring-1 ring-slate-200 text-slate-900 font-semibold' : 'text-slate-600 hover:bg-white/70'"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13px] transition-colors">
                            <span class="w-6 h-6 shrink-0 rounded-md {{ $group['color'] }} text-white grid place-items-center">
                                <x-menu-icon :name="$group['icon']" class="w-3.5 h-3.5" />
                            </span>
                            <span class="truncate">{{ $group['name'] }}</span>
                            <span class="ml-auto text-[11px] font-normal text-slate-400 tabular-nums">{{ count($group['items']) }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="flex-1 min-w-0 space-y-7">
                <div class="sticky top-0 z-10 -mx-4 px-4 sm:mx-0 sm:px-0 pt-1 pb-2 bg-[#f5f5f7]/90 backdrop-blur">
                    <x-menu-search id="settings-search" label="Search settings" placeholder="Search settings — products, users, tax…" />
                    <div x-ref="chips" class="lg:hidden relative mt-2.5 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-2 overflow-x-auto menu-noscroll">
                        @foreach ($groups as $group)
                            <button type="button" data-chip="{{ $group['key'] }}" x-on:click="jump(@js($group['key']))"
                                x-show="groupHasMatches(@js($group['key']))"
                                x-bind:class="active === @js($group['key']) ? 'bg-slate-900 text-white' : 'bg-white ring-1 ring-slate-200 text-slate-600'"
                                class="shrink-0 h-8 px-3 rounded-full text-[12px] inline-flex items-center whitespace-nowrap">
                                {{ $group['name'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                @foreach ($groups as $group)
                    <section id="group-{{ $group['key'] }}" data-menu-section="{{ $group['key'] }}" x-show="groupVisible($el)"
                        class="scroll-mt-32 lg:scroll-mt-20">
                        <div class="flex items-baseline gap-3 mb-2.5 px-1">
                            <h3 class="text-[15px] font-semibold text-slate-900">{{ $group['name'] }}</h3>
                            <span class="menu-ur text-[12px] text-slate-400" lang="ur">{{ $group['urdu'] }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2.5">
                            @foreach ($group['items'] as $item)
                                <a href="{{ $item['url'] }}" data-menu-item data-menu-link data-search="{{ $item['search'] }}"
                                    x-show="matches($el)" x-on:click="remember(@js($item['id']))" title="{{ $item['description'] }}"
                                    class="group flex items-center gap-3 bg-white rounded-xl ring-1 ring-slate-200/70 px-3.5 py-3 hover:ring-slate-300 hover:shadow-sm transition">
                                    <span class="w-9 h-9 shrink-0 rounded-[10px] {{ $group['color'] }} text-white grid place-items-center">
                                        <x-menu-icon :name="$item['icon']" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[14px] font-medium text-slate-900 truncate">{{ $item['label'] }}</span>
                                        <span class="block text-[12px] text-slate-500 truncate">{{ $item['description'] }}</span>
                                    </span>
                                    @if (! is_null($item['count']))
                                        <span class="text-[12px] tabular-nums text-slate-400">{{ number_format($item['count']) }}</span>
                                    @endif
                                    <span class="text-slate-300 group-hover:text-slate-400"><x-menu-icon name="chev" class="w-4 h-4" /></span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div x-show="query.trim() !== '' && visibleCount() === 0" x-cloak class="py-16 text-center text-sm text-slate-500" data-menu-empty>
                    No settings match "<span x-text="query"></span>".
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
