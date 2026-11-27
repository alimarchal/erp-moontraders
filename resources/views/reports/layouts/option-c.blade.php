{{--
    Reports, Option C (Apple): macOS System Settings on a desktop, iOS Settings on a phone. Categories by task
    (App\Support\Menus\ReportsMenu::APPLE_CATEGORIES) in a sidebar, the chosen category as an inset grouped list,
    one search over every report, and a star to pin a report to the top. English only. The last category opened is
    remembered in this browser. On a phone the category list comes first; a category opens with a "Reports" back button.

    @var list<array{key: string, name: string, blurb: string, color: string, icon: string, items: list<array{id: string, label: string, description: string, url: string, icon: string, count: int|null, search: string}>}> $categories
--}}
@php
    $itemIds = collect($categories)->flatMap(fn (array $category) => array_column($category['items'], 'id'))->values();
@endphp
<x-app-layout>
    <x-menu-page-head />

    @push('header')
        <style>
            .rp { --rp-bg: #f5f5f7; --rp-label: #1d1d1f; --rp-label-2: #6e6e73; --rp-label-3: #8e8e93; --rp-sep: rgba(60,60,67,.12); --rp-fill: rgba(118,118,128,.12); --rp-blue: #0071e3;
                font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Inter", "Segoe UI", system-ui, sans-serif; -webkit-font-smoothing: antialiased; color: var(--rp-label); background: var(--rp-bg); }
            .rp-wrap { max-width: 80rem; margin: 0 auto; padding: 20px 16px 48px; }
            @media (min-width: 640px) { .rp-wrap { padding: 24px 24px 56px; } }
            @media (min-width: 1024px) { .rp-wrap { padding: 28px 32px 64px; } }
            .rp-title { margin: 0; font-size: 34px; font-weight: 700; letter-spacing: -.025em; line-height: 1.1; }
            .rp-sub { margin: 4px 0 0; font-size: 15px; color: var(--rp-label-2); }

            /* Search: a filled field, as in Settings on a Mac */
            .rp-search { position: relative; margin-top: 18px; max-width: 40rem; }
            .rp-search svg { position: absolute; left: 12px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: var(--rp-label-3); pointer-events: none; }
            .rp-search input { width: 100%; height: 44px; padding: 0 44px 0 38px; border: 0; border-radius: 12px; background: var(--rp-fill); font: inherit; font-size: 16px; color: var(--rp-label); box-shadow: none; transition: background-color .15s ease, box-shadow .15s ease; }
            .rp-search input::placeholder { color: var(--rp-label-3); }
            .rp-search input:focus { outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px var(--rp-blue); }
            .rp-search kbd { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); padding: 1px 7px; border-radius: 6px; background: #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.08); font: 12px ui-monospace, Menlo, monospace; color: var(--rp-label-3); pointer-events: none; }
            .rp-results { margin: 6px 4px 0; font-size: 13px; color: var(--rp-label-2); }

            /* Pinned reports */
            .rp-pinned { margin-top: 22px; }
            .rp-caption { margin: 0 0 8px 16px; font-size: 13px; font-weight: 400; letter-spacing: .01em; text-transform: uppercase; color: var(--rp-label-2); }
            .rp-pins { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); }
            .rp-pin { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 12px 14px; border-radius: 14px; background: #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.04); color: var(--rp-label); text-decoration: none; font-size: 14px; font-weight: 500; line-height: 1.25; transition: box-shadow .15s ease, transform .1s ease-out; }
            .rp-pin:hover { box-shadow: 0 0 0 1px rgba(0,0,0,.06), 0 6px 16px rgba(0,0,0,.08); }
            .rp-pin:active { transform: scale(.98); }

            /* Icons: a coloured rounded square, like Settings */
            .rp-icon { display: grid; place-items: center; flex: none; width: 30px; height: 30px; border-radius: 8px; color: #fff; }
            .rp-icon svg { width: 17px; height: 17px; }
            .rp-icon-lg { width: 44px; height: 44px; border-radius: 11px; }
            .rp-icon-lg svg { width: 24px; height: 24px; }

            /* Two panes on a desktop */
            .rp-body { margin-top: 22px; display: grid; gap: 24px; grid-template-columns: minmax(0, 1fr); align-items: start; }
            @media (min-width: 1024px) { .rp-body { grid-template-columns: 290px minmax(0, 1fr); } .rp-side { position: sticky; top: 16px; } }

            /* Inset grouped list: white card, hairlines inset past the icon */
            .rp-list { margin: 0; padding: 0; list-style: none; overflow: hidden; border-radius: 14px; background: #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.04); }
            .rp-list > li { position: relative; }
            .rp-list > li + li::before { content: ''; position: absolute; top: 0; right: 0; left: 56px; height: 1px; background: var(--rp-sep); }
            .rp-list-plain > li + li::before { left: 52px; }

            /* Sidebar rows */
            .rp-cat { display: flex; align-items: center; gap: 12px; width: 100%; min-height: 48px; padding: 8px 14px; border: 0; background: transparent; font: inherit; text-align: left; color: var(--rp-label); cursor: pointer; transition: background-color .15s ease; }
            .rp-cat:hover { background: rgba(0,0,0,.035); }
            .rp-cat:active { background: rgba(0,0,0,.06); }
            .rp-cat:focus-visible { outline: 2px solid var(--rp-blue); outline-offset: -2px; border-radius: 10px; }
            .rp-cat-name { flex: 1; min-width: 0; font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .rp-cat-count { font-size: 14px; color: var(--rp-label-3); font-variant-numeric: tabular-nums; }
            .rp-chev { width: 14px; height: 14px; flex: none; color: #c7c7cc; }
            @media (min-width: 1024px) {
                .rp-cat .rp-chev { display: none; }
                .rp-cat.is-on { background: var(--rp-blue); color: #fff; }
                .rp-cat.is-on .rp-cat-count { color: rgba(255,255,255,.8); }
                .rp-cat.is-on .rp-icon { box-shadow: 0 0 0 1.5px rgba(255,255,255,.7); }
            }

            /* The chosen category */
            .rp-head { display: flex; align-items: center; gap: 14px; margin: 0 0 14px; }
            .rp-head h3 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -.02em; }
            .rp-head p { margin: 2px 0 0; font-size: 14px; color: var(--rp-label-2); }
            .rp-section + .rp-section { margin-top: 26px; }
            .rp-back { display: inline-flex; align-items: center; gap: 2px; margin: 0 0 12px -6px; padding: 6px; border: 0; background: none; font: inherit; font-size: 17px; color: var(--rp-blue); cursor: pointer; }
            .rp-back svg { width: 20px; height: 20px; }
            @media (min-width: 1024px) { .rp-back { display: none; } }

            /* Report rows */
            .rp-row { display: flex; align-items: center; gap: 4px; padding-left: 10px; transition: background-color .15s ease; }
            .rp-row:hover { background: rgba(0,0,0,.025); }
            .rp-row:has(.rp-link:active) { background: rgba(0,0,0,.05); }
            .rp-star { display: grid; place-items: center; flex: none; width: 36px; height: 36px; border: 0; border-radius: 10px; background: none; color: #c7c7cc; cursor: pointer; transition: color .15s ease, background-color .15s ease; }
            .rp-star:hover { background: var(--rp-fill); color: var(--rp-label-2); }
            .rp-star.is-on { color: #ff9f0a; }
            .rp-star svg { width: 17px; height: 17px; }
            .rp-link { display: flex; flex: 1; align-items: center; gap: 12px; min-width: 0; min-height: 56px; padding: 9px 16px 9px 2px; color: var(--rp-label); text-decoration: none; }
            .rp-link:focus-visible { outline: 2px solid var(--rp-blue); outline-offset: -2px; border-radius: 10px; }
            .rp-link-text { flex: 1; min-width: 0; }
            .rp-link-text b { display: block; font-size: 15px; font-weight: 500; letter-spacing: -.01em; }
            .rp-link-text span { display: block; margin-top: 1px; font-size: 13px; line-height: 1.35; color: var(--rp-label-2); }
            .rp-empty { padding: 48px 16px; text-align: center; font-size: 15px; color: var(--rp-label-2); }

            @media (max-width: 1023px) { .rp-title { font-size: 30px; } }
            @media (prefers-reduced-motion: reduce) { .rp-pin, .rp-cat, .rp-row, .rp-star, .rp-search input { transition: none; } .rp-pin:active { transform: none; } }
            @media (prefers-contrast: more) { .rp-list, .rp-pin { box-shadow: 0 0 0 1px #000; } .rp-cat.is-on { outline: 2px solid #000; } }
        </style>
        <script>
            /** menuPage (search, pins, panes) plus the category last opened, kept in this browser. */
            window.reportsApple = function (config) {
                const base = window.menuPage(config);
                const baseInit = base.init;
                const key = 'moontraders.reports.category.{{ auth()->id() }}';

                return Object.assign(base, {
                    init() {
                        baseInit.call(this);
                        try {
                            const last = window.localStorage.getItem(key);
                            if (last && config.groups.includes(last)) { this.selected = last; }
                        } catch (error) { /* storage blocked: start on the first category */ }
                        this.$watch('selected', (value) => { try { window.localStorage.setItem(key, value); } catch (error) {} });
                    },
                });
            };
        </script>
    @endpush

    <div class="rp min-h-full"
        x-data="reportsApple({ storageKey: 'moontraders.reports.pins.{{ auth()->id() }}', ids: @js($itemIds), groups: @js(array_column($categories, 'key')) })"
        x-on:keydown.window="focusSearch($event)">
        <div class="rp-wrap">
            <h1 class="rp-title">Reports</h1>
            <p class="rp-sub">{{ $itemIds->count() }} reports in {{ count($categories) }} categories · only the ones you can open</p>

            <div class="rp-search" role="search">
                <label for="rp-search" class="sr-only">Search reports</label>
                <x-menu-icon name="search" />
                <input id="rp-search" x-ref="search" x-model="query" type="search" autocomplete="off" placeholder="Search reports" x-on:keydown.escape="clearSearch()">
                <kbd x-show="query === ''" aria-hidden="true">/</kbd>
            </div>
            <p class="rp-results" x-show="query.trim() !== ''" x-cloak aria-live="polite" x-text="visibleCount() + (visibleCount() === 1 ? ' report' : ' reports')"></p>

            {{-- Pinned --}}
            <div class="rp-pinned" x-show="saved.length > 0 && query.trim() === ''" x-cloak>
                <p class="rp-caption">Pinned</p>
                <div class="rp-pins">
                    @foreach ($categories as $category)
                        @foreach ($category['items'] as $item)
                            <a href="{{ $item['url'] }}" class="rp-pin" x-show="isSaved(@js($item['id']))" x-bind:style="{ order: rank(@js($item['id'])) }">
                                <span class="rp-icon" style="background: {{ $category['color'] }}"><x-menu-icon :name="$item['icon']" /></span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="rp-body">
                {{-- Categories: the sidebar on a desktop, the first screen on a phone --}}
                <nav class="rp-side" x-ref="groups" aria-label="Report categories" x-bind:class="{ 'hidden lg:block': mobileOpen || query.trim() !== '' }">
                    <p class="rp-caption lg:hidden">Categories</p>
                    <ul class="rp-list">
                        @foreach ($categories as $category)
                            <li x-show="groupHasMatches(@js($category['key']))">
                                <button type="button" class="rp-cat" x-on:click="open(@js($category['key']))"
                                    x-bind:class="selected === @js($category['key']) && 'is-on'" x-bind:aria-current="selected === @js($category['key']) ? 'true' : 'false'">
                                    <span class="rp-icon" style="background: {{ $category['color'] }}"><x-menu-icon :name="$category['icon']" /></span>
                                    <span class="rp-cat-name">{{ $category['name'] }}</span>
                                    <span class="rp-cat-count">{{ count($category['items']) }}</span>
                                    <x-menu-icon name="chev" class="rp-chev" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                {{-- The chosen category, or every match while searching --}}
                <div x-ref="pane" class="hidden lg:block" x-bind:class="{ 'hidden lg:block': ! mobileOpen && query.trim() === '' }">
                    <button type="button" class="rp-back" x-on:click="back()" x-show="query.trim() === ''">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18 9 12l6-6" /></svg>
                        Reports
                    </button>

                    @foreach ($categories as $category)
                        <section class="rp-section" data-menu-section="{{ $category['key'] }}" aria-labelledby="rp-cat-{{ $category['key'] }}"
                            x-show="query.trim() !== '' ? groupVisible($el) : selected === @js($category['key'])"
                            @if (! $loop->first) style="display: none" @endif>
                            <div class="rp-head" x-show="query.trim() === ''">
                                <span class="rp-icon rp-icon-lg" style="background: {{ $category['color'] }}"><x-menu-icon :name="$category['icon']" /></span>
                                <div>
                                    <h3 id="rp-cat-{{ $category['key'] }}">{{ $category['name'] }}</h3>
                                    <p>{{ $category['blurb'] }}</p>
                                </div>
                            </div>
                            <p class="rp-caption" x-show="query.trim() !== ''" x-cloak>{{ $category['name'] }}</p>
                            <ul class="rp-list">
                                @foreach ($category['items'] as $item)
                                    <li class="rp-row" data-menu-item data-search="{{ $item['search'] }}" x-show="matches($el)">
                                        <button type="button" class="rp-star" x-on:click="toggle(@js($item['id']))"
                                            x-bind:class="isSaved(@js($item['id'])) && 'is-on'" x-bind:aria-pressed="isSaved(@js($item['id'])).toString()"
                                            x-bind:title="isSaved(@js($item['id'])) ? 'Unpin' : 'Pin to the top'" aria-label="Pin {{ $item['label'] }}">
                                            <x-menu-icon name="star" x-bind:fill="isSaved({{ \Illuminate\Support\Js::from($item['id']) }}) ? 'currentColor' : 'none'" />
                                        </button>
                                        <a href="{{ $item['url'] }}" class="rp-link" data-menu-link>
                                            <span class="rp-link-text"><b>{{ $item['label'] }}</b><span>{{ $item['description'] }}</span></span>
                                            <x-menu-icon name="chev" class="rp-chev" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach

                    <div class="rp-empty" x-show="query.trim() !== '' && visibleCount() === 0" x-cloak data-menu-empty>
                        No reports match “<span x-text="query"></span>”.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
