@props([
    'href',
    'label',
    'description' => '',
    'count' => null,
    'iconBg' => 'bg-gray-500',
])

<a href="{{ $href }}"
    class="flex items-center gap-3 px-4 py-3 active:bg-gray-50 transition-colors duration-100 group">
    <div class="flex-shrink-0 w-9 h-9 rounded-lg {{ $iconBg }} flex items-center justify-center shadow-sm">
        {{ $icon }}
    </div>
    <div class="flex-1 min-w-0">
        <div class="text-sm font-medium text-gray-900">{{ $label }}</div>
        @if ($description)
            <div class="text-xs text-gray-400 truncate">{{ $description }}</div>
        @endif
    </div>
    <div class="flex items-center gap-1.5 flex-shrink-0">
        @if (!is_null($count))
            <span class="text-sm text-gray-400 font-medium tabular-nums">{{ $count }}</span>
        @endif
        <svg class="w-4 h-4 text-gray-300 group-hover:text-gray-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
        </svg>
    </div>
</a>
