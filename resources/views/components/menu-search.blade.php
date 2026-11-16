{{--
    The search box of the Settings and Reports layouts. Works inside an x-data="menuPage(...)" block:
    "/" focuses it, Esc clears it, and "N results" shows while typing.
--}}
@props(['id', 'label', 'placeholder'])

<div>
    <label for="{{ $id }}" class="sr-only">{{ $label }}</label>
    <div class="relative">
        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
            <x-menu-icon name="search" class="w-4 h-4" />
        </span>
        <input id="{{ $id }}" x-ref="search" x-model="query" type="search" autocomplete="off"
            placeholder="{{ $placeholder }}" x-on:keydown.escape="clearSearch()"
            class="w-full h-11 rounded-xl border-0 bg-white ring-1 ring-slate-200 shadow-sm pl-10 pr-12 text-[14px] text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500" />
        <kbd x-show="query === ''"
            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 font-sans text-[11px] text-slate-400 ring-1 ring-slate-200 rounded px-1.5 py-0.5">/</kbd>
    </div>
    <p x-show="query.trim() !== ''" x-cloak class="mt-1.5 px-1 text-xs text-slate-500" data-menu-results
        x-text="visibleCount() + ' result' + (visibleCount() === 1 ? '' : 's')"></p>
</div>
