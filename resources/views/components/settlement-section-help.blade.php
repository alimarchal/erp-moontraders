@props(['section', 'label' => null])
{{-- A small "?" beside a settlement section heading; opens that section's help (sales-settlements/partials/help-modal). --}}
<button type="button" {{ $attributes->merge(['class' => 'ss-help-q']) }}
    x-data @click.stop="$dispatch('ss-help-section', { section: @js($section) })"
    aria-haspopup="dialog" aria-label="Help: {{ $label ?? $section }}" title="Help (Urdu)" data-help-section="{{ $section }}">?</button>
@once
    <style>
        .ss-help-q { display: inline-grid; place-items: center; flex: none; width: 22px; height: 22px; padding: 0; border: 0; border-radius: 50%; background: rgba(118, 118, 128, .12); color: #3a3a3c; font: 600 12px/1 -apple-system, system-ui, sans-serif; cursor: pointer; transition: transform 160ms cubic-bezier(.23, 1, .32, 1), background-color 160ms ease; }
        .ss-help-q:active { transform: scale(.92); }
        .ss-help-q:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; }
        @media (hover: hover) and (pointer: fine) { .ss-help-q:hover { background: rgba(118, 118, 128, .2); } }
        @media (prefers-reduced-motion: reduce) { .ss-help-q { transition: none; } }
        @media print { .ss-help-q { display: none !important; } }
    </style>
@endonce
