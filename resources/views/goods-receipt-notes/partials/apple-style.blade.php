{{--
    The Apple look for the GRN form (Option I): grey canvas, white cards with soft depth, filled fields with a
    blue focus ring, Select2 to match, pill buttons, a sheet-style line popup and a light translucent totals bar.
    Every rule is scoped to .grn-apl (or the .grn-head-btn header pills), so the classic and other layouts are
    unchanged. Styling only: no field, name or script is touched.
--}}
@once
<style>
    /* Header pills (the page header sits outside the form) */
    .grn-head-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; }
    .grn-head-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 16px; border: 0; border-radius: 980px; font-size: 14px; font-weight: 500; letter-spacing: -.01em;
        text-transform: none; text-decoration: none; white-space: nowrap; cursor: pointer; background: rgba(118,118,128,.12); color: #1d1d1f; transition: transform 100ms ease-out, background-color .15s ease; }
    .grn-head-btn:hover { background: rgba(118,118,128,.18); color: #1d1d1f; }
    .grn-head-btn svg { width: 17px; height: 17px; flex: none; }
    .grn-head-btn.is-primary { background: #0071e3; color: #fff; }
    .grn-head-btn.is-primary:hover { background: #0077ed; color: #fff; }
    .grn-head-btn.is-icon { width: 36px; padding: 0; justify-content: center; }
    .grn-head-btn:active { transform: scale(.97); }
    .grn-head-title { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -.022em; line-height: 1.2; color: #1d1d1f; }
    @media (max-width: 639px) { .grn-head-btn .grn-head-label { display: none; } .grn-head-btn:not(.is-icon) { padding: 0 12px; } }

    .grn-apl { color: #1d1d1f; }
    .grn-apl, .grn-apl * { min-width: 0; }

    /* Cards: white, rounded, soft depth instead of a grey ring */
    .grn-apl .grn-card { border-radius: 18px !important; background: #fff !important; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.05), 0 8px 24px -10px rgba(0,0,0,.14) !important; }
    .grn-apl .grn-card > .border-b, .grn-apl .grn-card > .border-t { border-color: rgba(60,60,67,.12) !important; }
    .grn-apl .grn-card > .bg-gray-50 { background: #fff !important; }

    /* Labels and fields: filled, rounded, blue focus ring */
    .grn-apl label { letter-spacing: .01em; }
    .grn-apl label.text-xs, .grn-apl .grn-sec label { font-size: 12.5px !important; font-weight: 500 !important; color: #6e6e73 !important; }
    .grn-apl input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]),
    .grn-apl select:not(.select2-hidden-accessible), .grn-apl textarea {
        min-height: 38px; border: 0 !important; border-radius: 10px !important; background-color: rgba(118,118,128,.12) !important; box-shadow: none !important;
        font-size: 14.5px; color: #1d1d1f; transition: background-color .15s ease, box-shadow .15s ease; }
    .grn-apl textarea { padding: 9px 12px; }
    .grn-apl input:focus, .grn-apl select:focus, .grn-apl textarea:focus { outline: none; background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.2), 0 0 0 1px #0071e3 !important; }
    .grn-apl input[readonly], .grn-apl input.bg-gray-100 { background-color: rgba(118,118,128,.06) !important; color: #6e6e73 !important; }
    .grn-apl input[readonly]:focus { box-shadow: none !important; background-color: rgba(118,118,128,.06) !important; }
    .grn-apl input::placeholder, .grn-apl textarea::placeholder { color: #aeaeb2; }
    .grn-apl select:not(.select2-hidden-accessible) { -webkit-appearance: none; appearance: none; padding-right: 32px !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important; background-position: right 12px center !important; background-size: 12px !important; }
    .grn-apl input[type=checkbox] { border-radius: 6px; border-color: #c7c7cc; color: #0071e3; }

    /* Select2 to match the filled fields */
    .grn-apl .select2-container .select2-selection--single .select2-selection__clear { position: absolute; right: 30px; top: 50%; transform: translateY(-50%); margin: 0 !important; color: #8e8e93; font-size: 16px; }
    .grn-apl .select2-container .select2-selection--single { position: relative; inset: auto !important; padding: 0 !important; height: 38px !important; display: flex; align-items: center; border: 0 !important; border-radius: 10px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; }
    .grn-apl .select2-container .select2-selection--single .select2-selection__rendered { padding-left: 12px !important; padding-right: 32px !important; line-height: 38px !important; font-size: 14.5px; color: #1d1d1f; }
    .grn-apl .select2-container .select2-selection__placeholder { color: #aeaeb2 !important; }
    .grn-apl .select2-container .select2-selection__arrow { top: 50% !important; right: 8px !important; transform: translateY(-50%); height: 24px !important; }
    .grn-apl .select2-container--focus .select2-selection--single, .grn-apl .select2-container--open .select2-selection--single { background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.2), 0 0 0 1px #0071e3 !important; }
    body .select2-dropdown { border: 0 !important; border-radius: 12px !important; overflow: hidden; box-shadow: 0 0 0 1px rgba(0,0,0,.06), 0 12px 32px -8px rgba(0,0,0,.24) !important; }
    body .select2-results__option--highlighted[aria-selected], body .select2-results__option--highlighted { background: #0071e3 !important; color: #fff !important; }
    body .select2-search--dropdown .select2-search__field { border: 0 !important; border-radius: 8px !important; background: rgba(118,118,128,.12) !important; padding: 6px 10px !important; }

    /* Status chips under the details */
    .grn-apl .grn-card .rounded-full.ring-1 { box-shadow: none !important; }

    /* Lines list */
    .grn-apl .grn-lines li > button { transition: background-color .15s ease; }
    .grn-apl .grn-lines li > button:hover { background: rgba(118,118,128,.06); }
    .grn-apl .grn-lines li > button:active { background: rgba(118,118,128,.12); }
    .grn-apl .grn-lines > li + li { border-color: rgba(60,60,67,.1) !important; }
    .grn-apl [role=group] button.rounded-full { box-shadow: none !important; }
    .grn-apl [role=group] button.rounded-full[aria-pressed=true].bg-gray-900 { background: #1d1d1f !important; }

    /* Pill buttons */
    .grn-apl .grn-pill { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 18px; border: 0; border-radius: 980px !important; font-size: 14.5px !important; font-weight: 500 !important; letter-spacing: -.01em;
        box-shadow: none !important; cursor: pointer; transition: transform 100ms ease-out, background-color .15s ease, opacity .15s ease; }
    .grn-apl .grn-pill:active:not(:disabled) { transform: scale(.97); }
    .grn-apl .grn-pill.is-primary { background: #0071e3 !important; color: #fff !important; }
    .grn-apl .grn-pill.is-primary:hover { background: #0077ed !important; }
    .grn-apl .grn-pill.is-plain { background: rgba(118,118,128,.12) !important; color: #1d1d1f !important; }
    .grn-apl .grn-pill.is-plain:hover:not(:disabled) { background: rgba(118,118,128,.18) !important; }
    .grn-apl .grn-pill.is-danger { background: rgba(255,59,48,.1) !important; color: #d70015 !important; }
    .grn-apl .grn-pill.is-danger:hover { background: rgba(255,59,48,.16) !important; }
    .grn-apl .grn-pill:disabled { opacity: .4; cursor: default; }
    .grn-apl .grn-pill kbd { border-color: rgba(255,255,255,.45) !important; color: rgba(255,255,255,.85) !important; border-radius: 6px; }

    /* Line popup: a sheet on the grey system background */
    .grn-apl .grn-sheet { background: #f2f2f7 !important; border-radius: 22px !important; box-shadow: 0 24px 64px -16px rgba(0,0,0,.35), 0 0 0 1px rgba(0,0,0,.05) !important; }
    @media (max-width: 639px) { .grn-apl .grn-sheet { border-radius: 22px 22px 0 0 !important; } }
    .grn-apl .grn-sheet-bar { background: rgba(255,255,255,.82) !important; -webkit-backdrop-filter: blur(20px) saturate(180%); backdrop-filter: blur(20px) saturate(180%); border-color: rgba(60,60,67,.12) !important; }
    .grn-apl .grn-sheet .grn-sec { border-radius: 14px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.04) !important; }
    .grn-apl .grn-sheet .grn-sec h4 { font-size: 13px !important; font-weight: 600 !important; letter-spacing: -.01em !important; text-transform: none !important; color: #1d1d1f !important; }
    .grn-apl .grn-sheet .grn-sec h4 > span { color: #6e6e73 !important; font-weight: 500; }
    .grn-apl .grn-sheet .grn-close { background: rgba(118,118,128,.12) !important; color: #3a3a3c !important; }
    .grn-apl .grn-scrim { background: rgba(0,0,0,.28) !important; -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); }

    /* Totals bar: a light translucent material that the lines scroll under */
    .grn-apl .grn-totals { background: rgba(255,255,255,.74) !important; color: #1d1d1f !important; -webkit-backdrop-filter: blur(20px) saturate(180%); backdrop-filter: blur(20px) saturate(180%);
        border-radius: 18px !important; box-shadow: inset 0 1px 0 rgba(255,255,255,.6), 0 0 0 1px rgba(0,0,0,.06), 0 12px 32px -12px rgba(0,0,0,.28) !important; }
    .grn-apl .grn-totals dt, .grn-apl .grn-totals .text-slate-400 { color: #6e6e73 !important; font-weight: 500 !important; }
    .grn-apl .grn-totals dd { font-weight: 600 !important; color: #1d1d1f; }
    .grn-apl .grn-totals .text-red-300 { color: #d70015 !important; }
    .grn-apl .grn-totals .text-amber-200 { color: #8a4b00 !important; }
    .grn-apl .grn-totals .text-emerald-300 { color: #1b7f3b !important; }
    .grn-apl .grn-totals button.hover\:bg-slate-800:hover { background: rgba(118,118,128,.12) !important; }
    .grn-apl .grn-totals .text-xl { font-weight: 600 !important; letter-spacing: -.02em; }
    .grn-apl .grn-totals button[type=submit] { height: 40px; padding: 0 22px; border-radius: 980px !important; font-size: 15px !important; font-weight: 500 !important; letter-spacing: -.01em; box-shadow: none !important; }
    .grn-apl .grn-totals button[type=submit].bg-blue-500 { background: #0071e3 !important; color: #fff !important; }
    .grn-apl .grn-totals button[type=submit].bg-blue-500:hover { background: #0077ed !important; }
    .grn-apl .grn-totals button[type=submit].bg-slate-700 { background: rgba(118,118,128,.16) !important; color: #8e8e93 !important; }

    @media (prefers-reduced-motion: reduce) { .grn-apl .grn-pill, .grn-head-btn { transition: none; } .grn-apl .grn-pill:active, .grn-head-btn:active { transform: none; } }
    @media (prefers-reduced-transparency: reduce) { .grn-apl .grn-totals, .grn-apl .grn-sheet-bar { background: #fff !important; -webkit-backdrop-filter: none; backdrop-filter: none; } }
    @media (prefers-contrast: more) { .grn-apl .grn-card, .grn-apl .grn-totals { box-shadow: 0 0 0 1px #6e6e73 !important; } .grn-apl input, .grn-apl select, .grn-apl textarea { box-shadow: inset 0 0 0 1px #6e6e73 !important; } }
</style>
@endonce
