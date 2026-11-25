{{--
    Supplier create / edit / view in the Apple look: compact grouped cards on a grey canvas, filled fields,
    iOS switches, inset-grouped detail rows and a translucent sticky action bar. Every rule is scoped to
    .sp-page so nothing leaks into other screens.
--}}
@once
<style>
    .sp-page { --sp-gap: 16px; --sp-pad: 18px; max-width: 76rem; margin: 0 auto; padding: 16px 16px 24px; display: flex; flex-direction: column; gap: var(--sp-gap); color: #1d1d1f; }
    @media (min-width: 640px) { .sp-page { padding: 20px 24px 28px; } }
    .sp-page, .sp-page * { min-width: 0; box-sizing: border-box; }
    .sp-cols-2 { display: grid; gap: var(--sp-gap); grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1024px) { .sp-cols-2 { grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); } }
    .sp-stack { display: flex; flex-direction: column; gap: var(--sp-gap); }

    /* Cards */
    .sp-card { background: #fff; border-radius: 18px; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.05), 0 8px 24px -10px rgba(0,0,0,.14); }
    .sp-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; padding: 14px var(--sp-pad) 0; }
    .sp-card-title { margin: 0; font-size: 15px; font-weight: 600; letter-spacing: -.015em; line-height: 1.3; color: #1d1d1f; }
    .sp-card-sub { margin: 2px 0 0; font-size: 12.5px; line-height: 1.4; color: #6e6e73; }
    .sp-card-body { padding: 12px var(--sp-pad) var(--sp-pad); }

    /* Fields: label row (English left, Urdu right), filled input */
    .sp-grid { display: grid; gap: 12px 14px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 640px) { .sp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .sp-span-2 { grid-column: 1 / -1; } }
    .sp-field { display: flex; flex-direction: column; gap: 5px; }
    .sp-page .sp-field > label { display: flex !important; justify-content: space-between; align-items: baseline; gap: 8px; margin: 0; font-size: 12.5px !important; font-weight: 500 !important; letter-spacing: .01em; color: #6e6e73 !important; }
    .sp-page .sp-field input:not([type=checkbox]):not([type=hidden]), .sp-page .sp-field select, .sp-page .sp-field textarea {
        width: 100%; min-height: 38px; padding: 0 12px; border: 0 !important; border-radius: 10px !important; background-color: rgba(118,118,128,.12) !important;
        box-shadow: none !important; font-size: 14.5px; color: #1d1d1f; outline: none; transition: background-color .15s ease, box-shadow .15s ease; }
    .sp-page .sp-field textarea { padding: 9px 12px; min-height: 64px; line-height: 1.45; resize: vertical; }
    .sp-page .sp-field select { -webkit-appearance: none; appearance: none; padding-right: 32px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important; background-position: right 12px center !important; }
    .sp-page .sp-field input:focus, .sp-page .sp-field select:focus, .sp-page .sp-field textarea:focus { background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.2), 0 0 0 1px #0071e3 !important; }
    .sp-page .sp-field input::placeholder, .sp-page .sp-field textarea::placeholder { color: #aeaeb2; }
    .sp-hint { margin: 0; font-size: 12px; line-height: 1.45; color: #6e6e73; }
    .sp-error { margin: 0; font-size: 12px; color: #d70015; }
    .sp-readout { display: flex; align-items: center; min-height: 38px; padding: 0 12px; border-radius: 10px; background: rgba(118,118,128,.06); font-size: 14.5px; font-weight: 600; font-variant-numeric: tabular-nums; }

    /* Inset-grouped rows with iOS switches */
    .sp-list { margin: 0; padding: 0; list-style: none; border-radius: 12px; background: rgba(118,118,128,.06); overflow: hidden; }
    .sp-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; }
    .sp-row + .sp-row { box-shadow: inset 0 1px 0 rgba(60,60,67,.12); }
    .sp-row-text { display: flex; flex-direction: column; gap: 1px; }
    .sp-row-title { font-size: 14px; font-weight: 500; color: #1d1d1f; }
    .sp-row-sub { font-size: 12px; color: #6e6e73; line-height: 1.35; }
    label.sp-row { cursor: pointer; margin: 0; }
    /* iOS switch. !important beats the app's global checkbox styles (Tailwind forms and the Apple skin). */
    input.sp-switch { position: relative !important; flex: none !important; display: inline-block !important; width: 51px !important; height: 31px !important; min-width: 51px; margin: 0 !important; padding: 0 !important;
        border: 0 !important; border-radius: 999px !important; background: #e9e9eb !important; background-image: none !important; box-shadow: inset 0 0 0 1px rgba(0,0,0,.04) !important;
        color: transparent !important; -webkit-appearance: none !important; appearance: none !important; cursor: pointer; transition: background-color .2s ease; vertical-align: middle; }
    input.sp-switch::after { content: ""; position: absolute; top: 2px; left: 2px; width: 27px; height: 27px; border-radius: 50%; background: #fff; box-shadow: 0 3px 8px rgba(0,0,0,.15), 0 1px 1px rgba(0,0,0,.16), 0 0 0 .5px rgba(0,0,0,.04); transition: transform .25s cubic-bezier(.32,.72,0,1); }
    input.sp-switch:checked { background: #34c759 !important; background-image: none !important; }
    input.sp-switch:checked::after { transform: translateX(20px); }
    input.sp-switch:focus { outline: none; box-shadow: inset 0 0 0 1px rgba(0,0,0,.04) !important; }
    input.sp-switch:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; }
    input.sp-switch:active::after { width: 31px; }
    input.sp-switch:checked:active::after { transform: translateX(16px); }
    input.sp-switch.sp-switch-red:checked { background: #ff3b30 !important; }

    /* Header actions in the Apple style: sentence-case pills instead of the navy uppercase buttons */
    html.apl-on .sp-head-btn, .sp-head-btn { gap: 6px; cursor: pointer; display: inline-flex; align-items: center; height: 36px; padding: 0 16px; border: 0; border-radius: 980px; font-size: 14px; font-weight: 500; letter-spacing: -.01em;
        text-transform: none !important; text-decoration: none; background: rgba(118,118,128,.12) !important; color: #1d1d1f !important; transition: transform 100ms ease-out, background-color .15s ease; }
    .sp-head-btn:hover { background: rgba(118,118,128,.18) !important; }
    html.apl-on .sp-head-btn.sp-head-primary, .sp-head-btn.sp-head-primary { background: #0071e3 !important; color: #fff !important; }
    .sp-head-btn.sp-head-primary:hover { background: #0077ed !important; }
    .sp-head-btn:active { transform: scale(.97); }
    @media print { .sp-head-btn { display: none; } }
    .sp-head-btn svg { width: 18px; height: 18px; }
    .sp-details { margin-top: 10px; font-size: 12.5px; color: #6e6e73; }
    .sp-details summary { cursor: pointer; color: #0066cc; font-weight: 500; list-style: none; }
    .sp-details summary::-webkit-details-marker { display: none; }
    .sp-details p { margin: 6px 0 0; line-height: 1.5; }

    /* Column order */
    .sp-order { display: grid; gap: 12px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 640px) and (max-width: 1023px) { .sp-order { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .sp-order-title { margin: 0 0 6px; font-size: 11.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #6e6e73; }
    .sp-order .sp-row { padding: 6px 8px 6px 12px; }
    .sp-order-no { display: inline-flex; flex: none; width: 22px; height: 22px; align-items: center; justify-content: center; border-radius: 50%; background: #0071e3; color: #fff; font-size: 11.5px; font-weight: 600; }
    .sp-order-name { flex: 1; font-size: 14px; font-weight: 500; }
    .sp-order-note { font-size: 11.5px; color: #8e8e93; }
    .sp-move { display: inline-flex; gap: 2px; }
    .sp-move button { width: 30px; height: 28px; border: 0; border-radius: 8px; background: transparent; color: #0071e3; font-size: 15px; font-weight: 600; cursor: pointer; transition: transform 100ms ease-out, background-color .15s ease; }
    .sp-move button:hover:not(:disabled) { background: rgba(0,113,227,.1); }
    .sp-move button:disabled { color: #c7c7cc; cursor: default; }
    .sp-link-btn { border: 0; background: none; padding: 0; color: #0066cc; font-size: 13px; font-weight: 500; cursor: pointer; }

    /* Sticky action bar: a real material, content scrolls under it */
    .sp-actions { position: sticky; bottom: 12px; z-index: 20; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; padding: 10px 12px 10px 18px; border-radius: 18px;
        background: rgba(255,255,255,.72); -webkit-backdrop-filter: blur(20px) saturate(180%); backdrop-filter: blur(20px) saturate(180%);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.6), 0 0 0 1px rgba(0,0,0,.06), 0 12px 32px -12px rgba(0,0,0,.28); }
    .sp-actions-note { font-size: 13px; color: #6e6e73; }
    .sp-actions-buttons { display: flex; gap: 8px; margin-left: auto; }
    .sp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 38px; padding: 0 18px; border: 0; border-radius: 980px; font-size: 14.5px; font-weight: 500; letter-spacing: -.01em; text-decoration: none; cursor: pointer; transition: transform 100ms ease-out, background-color .15s ease; }
    .sp-btn-primary { background: #0071e3; color: #fff !important; }
    .sp-btn-primary:hover { background: #0077ed; }
    .sp-btn-plain { background: rgba(118,118,128,.12); color: #1d1d1f !important; }
    .sp-btn-plain:hover { background: rgba(118,118,128,.18); }
    .sp-page button:not(:disabled):active, .sp-page a.sp-btn:active { transform: scale(.97); }
    @media (max-width: 639px) { .sp-actions { padding: 10px; } .sp-actions-note { display: none; } .sp-actions-buttons { width: 100%; } .sp-actions-buttons .sp-btn { flex: 1; } }

    /* View: summary strip and detail rows */
    .sp-hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; padding: 16px var(--sp-pad); }
    .sp-hero-name { margin: 0; font-size: 21px; font-weight: 600; letter-spacing: -.022em; line-height: 1.2; }
    .sp-hero-meta { margin: 4px 0 0; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 13px; color: #6e6e73; }
    .sp-chip { display: inline-flex; align-items: center; gap: 5px; height: 22px; padding: 0 9px; border-radius: 999px; background: rgba(118,118,128,.12); color: #3a3a3c; font-size: 12px; font-weight: 500; }
    .sp-chip-green { background: rgba(52,199,89,.14); color: #1b7f3b; }
    .sp-chip-grey { background: rgba(118,118,128,.14); color: #6e6e73; }
    .sp-chip-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
    .sp-chip-blue { background: rgba(0,113,227,.12); color: #0058b0; }
    .sp-stats { display: flex; gap: 22px; }
    .sp-stat { text-align: right; }
    .sp-stat b { display: block; font-size: 22px; font-weight: 600; letter-spacing: -.02em; font-variant-numeric: tabular-nums; line-height: 1.1; }
    .sp-stat span { font-size: 12px; color: #6e6e73; }
    .sp-dl { margin: 0; display: grid; grid-template-columns: minmax(0, 1fr); border-radius: 12px; background: rgba(118,118,128,.06); overflow: hidden; }
    @media (min-width: 640px) { .sp-dl { grid-template-columns: repeat(2, minmax(0, 1fr)); } .sp-dl .sp-dl-wide { grid-column: 1 / -1; } }
    .sp-dl > div { padding: 9px 14px; box-shadow: inset 0 -1px 0 rgba(60,60,67,.1); }
    @media (min-width: 640px) { .sp-dl > div:not(.sp-dl-wide):nth-child(odd) { box-shadow: inset 0 -1px 0 rgba(60,60,67,.1), inset -1px 0 0 rgba(60,60,67,.1); } }
    .sp-dl dt { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; color: #6e6e73; }
    .sp-dl dd { margin: 2px 0 0; font-size: 14px; font-weight: 500; color: #1d1d1f; overflow-wrap: anywhere; white-space: pre-line; }
    .sp-dl dd.sp-empty { color: #aeaeb2; font-weight: 400; }
    .sp-mini-ol { margin: 0; padding: 0; list-style: none; counter-reset: sp; }
    .sp-mini-ol li { counter-increment: sp; display: flex; gap: 8px; align-items: center; padding: 3px 0; font-size: 13.5px; }
    .sp-mini-ol li::before { content: counter(sp); display: inline-flex; width: 18px; height: 18px; align-items: center; justify-content: center; border-radius: 50%; background: rgba(0,113,227,.12); color: #0058b0; font-size: 10.5px; font-weight: 600; }

    @media (prefers-reduced-motion: reduce) { .sp-switch, .sp-switch::after, .sp-btn, .sp-move button { transition: none; } .sp-page button:active, .sp-page a.sp-btn:active { transform: none; } }
    @media (prefers-reduced-transparency: reduce) { .sp-actions { background: #fff; -webkit-backdrop-filter: none; backdrop-filter: none; } }
    @media (prefers-contrast: more) { .sp-card, .sp-actions { box-shadow: 0 0 0 1px #6e6e73; } .sp-page .sp-field input, .sp-page .sp-field select, .sp-page .sp-field textarea { box-shadow: inset 0 0 0 1px #6e6e73 !important; } }
    @media print { .sp-actions { display: none; } .sp-card { box-shadow: 0 0 0 1px #d1d1d6; } }
</style>
@endonce
