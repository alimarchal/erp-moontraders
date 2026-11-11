{{-- Header buttons of the Claim Register and the Supplier Ledger Register: download the period as an A4 PDF
     (portrait by default, landscape on request, like the sales settlement) and open the Urdu help. --}}
@props(['page'])

@php
    $headerButton = 'inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150';
    $pdfUrl = fn (string $orientation) => request()->fullUrlWithQuery(['format' => 'pdf', 'orientation' => $orientation, 'page' => null]);
@endphp

<div class="relative no-print" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    <button type="button" class="{{ $headerButton }}" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" title="Download PDF">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
        <span class="sr-only">Download PDF</span>
    </button>
    <div x-show="open" x-cloak role="menu" class="rg-menu"
        x-transition:enter="duration-150 [transition-timing-function:cubic-bezier(.23,1,.32,1)]" x-transition:enter-start="opacity-0 scale-[.97] -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="duration-100 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <p class="rg-menu-label">Download as PDF (A4)</p>
        <a role="menuitem" href="{{ $pdfUrl('portrait') }}" @click="open = false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="6" y="3" width="12" height="18" rx="2" /></svg>
            Portrait <span>recommended</span>
        </a>
        <a role="menuitem" href="{{ $pdfUrl('landscape') }}" @click="open = false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" /></svg>
            Landscape
        </a>
    </div>
</div>

<button type="button" class="{{ $headerButton }} no-print" x-data @click="$dispatch('rg-help', { topic: null })" aria-haspopup="dialog" title="How this register works, in Urdu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
    <span class="sr-only">Help</span>
</button>
