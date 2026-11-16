{{--
    The "Pinned" row of the Reports layouts: the reports this user starred, in the order they were
    pinned (kept in this browser). Shows nothing until something is pinned, and hides while searching.

    @var list<array{key: string, name: string, color: string, icon: string, items: list<array{id: string, label: string, url: string, icon: string}>}> $groups
--}}
<div x-show="saved.length > 0 && query.trim() === ''" x-cloak data-menu-pinned>
    <p class="mb-2 px-1 flex items-center gap-1.5 text-[12px] font-medium text-slate-500">
        <x-menu-icon name="star" class="w-3.5 h-3.5 text-amber-500" fill="currentColor" /> Pinned
    </p>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
        @foreach ($groups as $group)
            @foreach ($group['items'] as $item)
                <a href="{{ $item['url'] }}" x-show="isSaved(@js($item['id']))" x-bind:style="{ order: rank(@js($item['id'])) }"
                    class="min-w-0 bg-white rounded-xl ring-1 ring-slate-200/70 p-3 flex items-center gap-2.5 hover:ring-slate-300 hover:shadow-sm transition">
                    <span class="w-8 h-8 shrink-0 rounded-lg {{ $group['color'] }} text-white grid place-items-center">
                        <x-menu-icon :name="$item['icon']" class="w-4 h-4" />
                    </span>
                    <span class="min-w-0 text-[13px] font-medium leading-tight text-slate-800 break-words">{{ $item['label'] }}</span>
                </a>
            @endforeach
        @endforeach
    </div>
</div>
