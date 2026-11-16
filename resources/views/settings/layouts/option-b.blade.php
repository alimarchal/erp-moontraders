{{--
    Settings, Option B (issue #196): one card per group with its pages inside, and a "Recently used" row
    (kept per user in this browser) at the top. Links and permissions come from App\Support\Menus\SettingsMenu.

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

    <div class="menu-page bg-[#f5f5f7] min-h-full"
        x-data="menuPage({ storageKey: 'moontraders.settings.recent.{{ auth()->id() }}', limit: 12, ids: @js($itemIds), groups: @js(array_column($groups, 'key')) })"
        x-on:keydown.window="focusSearch($event)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-5">
            <div class="max-w-2xl">
                <x-menu-search id="settings-search" label="Search settings" placeholder="Search settings — products, users, tax…" />
            </div>

            <div x-show="saved.length > 0 && query.trim() === ''" x-cloak data-menu-recent
                class="flex gap-2 overflow-x-auto menu-noscroll -mx-4 px-4 sm:mx-0 sm:px-0">
                <span class="shrink-0 self-center mr-1 text-[12px] text-slate-500" style="order: -1">Recently used</span>
                @foreach ($groups as $group)
                    @foreach ($group['items'] as $item)
                        <a href="{{ $item['url'] }}" x-show="isSaved(@js($item['id'])) && rank(@js($item['id'])) < 6"
                            x-bind:style="{ order: rank(@js($item['id'])) }" x-on:click="remember(@js($item['id']))"
                            class="shrink-0 h-8 px-3 rounded-full bg-white ring-1 ring-slate-200 text-[12px] text-slate-700 inline-flex items-center gap-1.5 whitespace-nowrap hover:ring-slate-300">
                            <x-menu-icon name="clock" class="w-3.5 h-3.5 text-slate-400" />{{ $item['label'] }}
                        </a>
                    @endforeach
                @endforeach
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 items-start">
                @foreach ($groups as $group)
                    <section data-menu-section="{{ $group['key'] }}" x-show="groupVisible($el)"
                        class="bg-white rounded-2xl ring-1 ring-slate-200/70 shadow-sm overflow-hidden">
                        <header class="flex items-center gap-3 px-4 pt-4 pb-3">
                            <span class="w-10 h-10 shrink-0 rounded-xl {{ $group['color'] }} text-white grid place-items-center">
                                <x-menu-icon :name="$group['icon']" class="w-5 h-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-[15px] font-semibold text-slate-900">{{ $group['name'] }}</h3>
                                <p class="menu-ur text-[12px] text-slate-400 text-left" lang="ur">{{ $group['urdu'] }}</p>
                            </div>
                            <span class="ml-auto text-[11px] font-medium text-slate-500 bg-slate-100 rounded-full px-2 py-0.5 tabular-nums">{{ count($group['items']) }}</span>
                        </header>
                        <ul>
                            @foreach ($group['items'] as $item)
                                <li data-menu-item data-search="{{ $item['search'] }}" x-show="matches($el)" class="border-t border-slate-100">
                                    <a href="{{ $item['url'] }}" data-menu-link x-on:click="remember(@js($item['id']))" title="{{ $item['description'] }}"
                                        class="group flex items-center gap-2 px-4 py-2.5 text-[13.5px] hover:bg-slate-50 transition-colors">
                                        <span class="min-w-0 truncate text-slate-800">{{ $item['label'] }}</span>
                                        @if (! is_null($item['count']))
                                            <span class="ml-auto text-[12px] tabular-nums text-slate-400">{{ number_format($item['count']) }}</span>
                                        @else
                                            <span class="ml-auto"></span>
                                        @endif
                                        <span class="text-slate-300 group-hover:text-slate-400"><x-menu-icon name="chev" class="w-3.5 h-3.5" /></span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>

            <div x-show="query.trim() !== '' && visibleCount() === 0" x-cloak class="py-16 text-center text-sm text-slate-500" data-menu-empty>
                No settings match "<span x-text="query"></span>".
            </div>
        </div>
    </div>
</x-app-layout>
