@props([
'id' => 'pdf-download-btn',
'label' => 'Download PDF',
'type' => 'client', // 'client' for JavaScript generation or 'server' for backend generation
'route' => null, // For server-side PDF generation
'icon' => true,
'variant' => 'indigo', // indigo, gray, blue, green
'size' => 'md', // sm, md, lg
])

@php
$variants = [
'indigo' => 'bg-indigo-700 hover:bg-indigo-800 focus:ring-indigo-500',
'gray' => 'bg-gray-700 hover:bg-gray-800 focus:ring-gray-500',
'blue' => 'bg-blue-700 hover:bg-blue-800 focus:ring-blue-500',
'green' => 'bg-green-700 hover:bg-green-800 focus:ring-green-500',
];

$sizes = [
'sm' => 'px-3 py-1.5 text-xs',
'md' => 'px-4 py-2 text-xs',
'lg' => 'px-5 py-2.5 text-sm',
];

$variantClass = $variants[$variant] ?? $variants['indigo'];
$sizeClass = $sizes[$size] ?? $sizes['md'];

// Base classes for consistent styling
$baseClasses = "inline-flex items-center justify-center {$sizeClass} {$variantClass} border border-transparent
rounded-md font-semibold text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2
transition ease-in-out duration-150 shadow-sm whitespace-nowrap";
@endphp

@if($type === 'server' && $route)
<a href="{{ $route }}" target="_blank" {{ $attributes->merge(['class' => $baseClasses]) }}>
    @if($icon)
    <svg class="w-4 h-4 mr-2 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
        stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
    @endif
    <span class="truncate">{{ $label }}</span>
</a>
@else
<button id="{{ $id }}" type="button" {{ $attributes->merge(['class' => $baseClasses]) }}>
    @if($icon)
    <svg class="w-4 h-4 mr-2 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
        stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
    @endif
    <span class="truncate">{{ $label }}</span>
</button>
@endif