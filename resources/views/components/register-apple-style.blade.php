{{-- Apple-style look for the Claim Register and Supplier Ledger Register (apple-design skill): grey canvas, quiet
     KPI cards, a hairline table under a frosted sticky header, glyph buttons that answer on press, and frosted sheets.
     Screen only: the printed register keeps its black grid. Needs x-apple-skin (html.apl-on and the --apl tokens). --}}
@once
<style>
    @media screen {
        html.apl-on body { background: var(--apl-bg, #f5f5f7); }

        /* KPI cards: no coloured edge bar, a small dot carries the tone; numbers are tabular */
        html.apl-on .rg-kpi { position: relative; border: 0 !important; border-radius: 18px !important; padding: 16px 18px !important;
            box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 0 0 1px rgba(0,0,0,.04) !important; background: #fff; }
        html.apl-on .rg-kpi > .text-sm:first-child { display: flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 500; color: var(--apl-label-2, #6e6e73) !important; letter-spacing: -.005em; }
        html.apl-on .rg-kpi > .text-sm:first-child::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; opacity: .35; }
        html.apl-on .rg-kpi.border-green-500 > .text-sm:first-child::before, html.apl-on .rg-kpi.border-emerald-500 > .text-sm:first-child::before { background: #34c759; opacity: 1; }
        html.apl-on .rg-kpi.border-red-500 > .text-sm:first-child::before { background: #ff3b30; opacity: 1; }
        html.apl-on .rg-kpi.border-blue-500 > .text-sm:first-child::before { background: #0071e3; opacity: 1; }
        html.apl-on .rg-kpi.border-orange-500 > .text-sm:first-child::before, html.apl-on .rg-kpi.border-amber-500 > .text-sm:first-child::before { background: #ff9f0a; opacity: 1; }
        html.apl-on .rg-kpi .text-2xl { margin-top: 4px; font-size: 26px !important; font-weight: 600 !important; letter-spacing: -.022em; font-variant-numeric: tabular-nums; line-height: 1.15; }
        html.apl-on .rg-match { border-radius: 14px !important; padding: 12px 18px !important; }
        html.apl-on .rg-match.border-amber-500 { box-shadow: 0 0 0 1px rgba(255,159,10,.35), 0 1px 2px rgba(0,0,0,.04) !important; background: #fffaf2; }

        /* the register card */
        html.apl-on .rg-card { border-radius: 20px !important; box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 0 0 1px rgba(0,0,0,.04) !important; padding: 20px !important; }

        /* hairline table under a frosted sticky header */
        html.apl-on .rg-table { border: 0 !important; border-collapse: separate !important; border-spacing: 0; font-size: 13.5px !important; line-height: 1.35 !important; }
        html.apl-on .rg-table th, html.apl-on .rg-table td { border: 0 !important; border-bottom: 1px solid var(--apl-sep, rgba(60,60,67,.12)) !important; padding: 9px 8px !important; }
        html.apl-on .rg-table thead th { position: sticky; top: 0; z-index: 2; background: rgba(255,255,255,.86) !important; -webkit-backdrop-filter: saturate(180%) blur(14px); backdrop-filter: saturate(180%) blur(14px);
            font-size: 12px; font-weight: 600; color: var(--apl-label-2, #6e6e73); text-transform: none; letter-spacing: 0; border-bottom: 1px solid rgba(60,60,67,.18) !important; }
        html.apl-on .rg-table tbody tr { transition: background-color .15s ease; }
        html.apl-on .rg-table tbody tr:hover td { background: #fafafc; }
        html.apl-on .rg-table tbody tr.bg-yellow-50 td { background: #fffbeb; }
        html.apl-on .rg-table .amount-cell { font-family: inherit !important; font-variant-numeric: tabular-nums; }
        html.apl-on .rg-table tfoot td { border-bottom: 0 !important; border-top: 1px solid rgba(60,60,67,.18) !important; font-weight: 600; background: transparent !important; }
        html.apl-on .rg-table td .rounded, html.apl-on .rg-table td .rounded-full { border-radius: 980px !important; }

        /* row actions: quiet tinted glyph buttons that answer on press */
        html.apl-on .rg-icon { width: 28px; height: 28px; justify-content: center; padding: 0 !important; border-radius: 9px !important; border: 0; cursor: pointer;
            transition: transform .1s ease-out, background-color .15s ease; }
        html.apl-on .rg-icon svg { width: 14px; height: 14px; }
        html.apl-on .rg-icon.rg-green { background: rgba(52,199,89,.14) !important; color: #248a3d !important; }
        html.apl-on .rg-icon.rg-blue { background: rgba(0,113,227,.12) !important; color: #0071e3 !important; }
        html.apl-on .rg-icon.rg-red { background: rgba(255,59,48,.12) !important; color: #d70015 !important; }
        html.apl-on .rg-icon.rg-green:hover { background: rgba(52,199,89,.24) !important; }
        html.apl-on .rg-icon.rg-blue:hover { background: rgba(0,113,227,.2) !important; }
        html.apl-on .rg-icon.rg-red:hover { background: rgba(255,59,48,.2) !important; }

        /* pill buttons */
        html.apl-on .rg-pill { height: 36px; padding: 0 16px !important; border-radius: 980px !important; font-size: 14px !important; font-weight: 500 !important; letter-spacing: -.01em;
            text-transform: none !important; border: 0; cursor: pointer; transition: transform .1s ease-out, background-color .15s ease, opacity .15s ease; }
        html.apl-on .rg-pill.rg-pill-sm { height: 30px; padding: 0 12px !important; font-size: 13px !important; }
        html.apl-on .rg-pill.bg-indigo-600 { background: #0071e3 !important; }
        html.apl-on .rg-pill.bg-indigo-600:hover { background: #0077ed !important; }
        html.apl-on .rg-pill.rg-pill-green { background: #34c759 !important; color: #fff !important; }
        html.apl-on .rg-pill.rg-pill-grey, html.apl-on .rg-pill.bg-gray-200, html.apl-on .rg-pill.bg-gray-500 { background: rgba(120,120,128,.14) !important; color: #1d1d1f !important; }
        html.apl-on .rg-pill:disabled { opacity: .4; }
        html.apl-on .rg-icon:active, html.apl-on .rg-pill:active:not(:disabled), html.apl-on .rg-close:active { transform: scale(.97); }

        /* inline add / edit rows */
        html.apl-on .rg-card .inline-input, html.apl-on .rg-card .inline-select, html.apl-on .rg-sheet input:not([type=checkbox]):not([type=radio]), html.apl-on .rg-sheet select, html.apl-on .rg-sheet textarea {
            border: 0 !important; border-radius: 10px !important; background: rgba(118,118,128,.12) !important; padding: 7px 10px !important; font-size: 14px !important; font-family: inherit !important; box-shadow: none !important; transition: box-shadow .15s ease, background-color .15s ease; }
        html.apl-on .rg-card .inline-input:focus, html.apl-on .rg-card .inline-select:focus, html.apl-on .rg-sheet input:focus, html.apl-on .rg-sheet select:focus, html.apl-on .rg-sheet textarea:focus {
            outline: none; background: #fff !important; box-shadow: 0 0 0 3px rgba(0,113,227,.28) !important; }

        /* summary box */
        html.apl-on .rg-summary { border: 0 !important; border-radius: 16px !important; background: #f5f5f7; box-shadow: 0 0 0 1px rgba(0,0,0,.05); }
        html.apl-on .rg-summary-head { background: transparent !important; color: #1d1d1f !important; text-align: left !important; padding: 12px 16px 4px !important; font-size: 13px !important; font-weight: 600 !important; }
        html.apl-on .rg-summary tr { border-color: rgba(60,60,67,.12) !important; }
        html.apl-on .rg-summary td { font-family: inherit !important; font-variant-numeric: tabular-nums; }
        html.apl-on .rg-summary tr.bg-green-50, html.apl-on .rg-summary tr.bg-red-50 { background: transparent !important; }

        /* sheets: frosted head, soft depth, scrim dims to focus */
        html.apl-on .rg-scrim { background: rgba(0,0,0,.3) !important; -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); }
        html.apl-on .rg-sheet { border-radius: 22px !important; background: #fff !important; box-shadow: 0 24px 64px rgba(0,0,0,.22), 0 0 0 1px rgba(0,0,0,.05) !important; }
        html.apl-on .rg-sheet-head { background: rgba(255,255,255,.88) !important; color: #1d1d1f !important; border-bottom: 1px solid rgba(60,60,67,.12); -webkit-backdrop-filter: saturate(180%) blur(16px); backdrop-filter: saturate(180%) blur(16px); border-radius: 22px 22px 0 0 !important; padding: 16px 18px 14px 24px !important; }
        html.apl-on .rg-sheet-head h3 { font-size: 19px !important; font-weight: 600 !important; letter-spacing: -.02em; }
        html.apl-on .rg-close { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; background: rgba(118,118,128,.14); color: #1d1d1f !important; transition: transform .1s ease-out, background-color .15s ease; }
        html.apl-on .rg-close:hover { background: rgba(118,118,128,.22); }
        html.apl-on .rg-close svg { width: 14px; height: 14px; }
        html.apl-on .rg-sheet label { font-size: 13px !important; font-weight: 500 !important; color: var(--apl-label-2, #6e6e73) !important; }
        html.apl-on .rg-sheet form > div:last-child { border-top: 1px solid rgba(60,60,67,.12); background: #f5f5f7; margin: 20px -24px -24px; padding: 14px 20px; }

        /* soft depth on every card (the shadow the eye reads as "lifted", not a hard outline) */
        html.apl-on .rg-kpi, html.apl-on .rg-card, html.apl-on .rg-empty, html.apl-on #filters {
            box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08) !important; }
        html.apl-on .rg-kpi { transition: box-shadow .2s ease, transform .2s cubic-bezier(.23,1,.32,1); }
        @media (hover: hover) { html.apl-on .rg-kpi:hover { transform: translateY(-1px); box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 4px 10px rgba(0,0,0,.05), 0 16px 36px -8px rgba(0,0,0,.12) !important; } }

        /* filter panel: a sheet with labelled, filled fields */
        html.apl-on #filters { border-radius: 20px !important; background: #fff; }
        html.apl-on #filters > .p-6 { padding: 22px 24px !important; }
        html.apl-on #filters label { font-size: 13px !important; font-weight: 500 !important; color: var(--apl-label-2, #6e6e73) !important; letter-spacing: -.005em; margin-bottom: 2px; }
        html.apl-on #filters input[type=text], html.apl-on #filters input[type=search], html.apl-on #filters input[type=date], html.apl-on #filters input[type=number], html.apl-on #filters select:not(.select2-hidden-accessible) {
            height: 40px; border: 0 !important; border-radius: 11px !important; background-color: rgba(118,118,128,.12) !important; box-shadow: none !important;
            padding: 0 12px !important; font-size: 15px !important; color: #1d1d1f; transition: box-shadow .15s ease, background-color .15s ease; }
        html.apl-on #filters select:not(.select2-hidden-accessible) { -webkit-appearance: none; appearance: none; padding-right: 36px !important; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important; background-position: right 13px center !important; }
        html.apl-on #filters input::placeholder { color: #8e8e93; }
        html.apl-on #filters input:focus, html.apl-on #filters select:focus { outline: none; background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        html.apl-on #filters form > .mt-4 { margin-top: 20px; padding-top: 16px; border-top: 1px solid rgba(60,60,67,.1); gap: 10px; }
        html.apl-on #filters form > .mt-4 > * { margin: 0 !important; }
        html.apl-on #filters button[type=submit], html.apl-on #filters input[type=reset] {
            height: 38px; padding: 0 20px !important; border-radius: 980px !important; border: 0 !important; text-transform: none !important; letter-spacing: -.01em !important;
            font-size: 14px !important; font-weight: 500 !important; cursor: pointer; transition: transform .1s ease-out, background-color .15s ease; box-shadow: none !important; }
        html.apl-on #filters button[type=submit] { background: #0071e3 !important; color: #fff !important; }
        html.apl-on #filters button[type=submit]:hover { background: #0077ed !important; }
        html.apl-on #filters input[type=reset] { background: rgba(120,120,128,.14) !important; color: #1d1d1f !important; }
        html.apl-on #filters input[type=reset]:hover { background: rgba(120,120,128,.22) !important; }
        html.apl-on #filters button[type=submit]:active, html.apl-on #filters input[type=reset]:active { transform: scale(.97); }

        /* Select2: filled field like the rest, a floating list with depth, chips for several choices */
        html.apl-on #filters .select2-container { margin-top: 4px; }
        html.apl-on #filters .select2-container--default .select2-selection--single,
        html.apl-on #filters .select2-container--default .select2-selection--multiple {
            min-height: 40px; height: auto; padding: 0 !important; border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; transition: box-shadow .15s ease, background-color .15s ease; }
        html.apl-on #filters .select2-container--default .select2-selection--single { height: 40px !important; }
        html.apl-on #filters .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px !important; padding-left: 12px !important; padding-right: 52px !important; font-size: 15px; color: #1d1d1f; }
        html.apl-on #filters .select2-container--default .select2-selection--single .select2-selection__placeholder,
        html.apl-on #filters .select2-container--default .select2-search--inline .select2-search__field::placeholder { color: #8e8e93 !important; }
        /* chevron like every other field, and a small round clear button just before it */
        html.apl-on #filters .select2-container--default .select2-selection--single .select2-selection__arrow { top: 0 !important; right: 10px !important; width: 14px !important; height: 40px !important;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat center / 12px 12px;
            transition: transform .2s cubic-bezier(.23,1,.32,1); }
        html.apl-on #filters .select2-container--default .select2-selection--single .select2-selection__arrow b { display: none !important; }
        html.apl-on #filters .select2-container--open .select2-selection--single .select2-selection__arrow { transform: rotate(180deg); }
        html.apl-on #filters .select2-container--default .select2-selection__clear {
            position: absolute !important; top: 50% !important; right: 32px !important; float: none !important; transform: translateY(-50%); z-index: 2;
            display: grid !important; place-items: center; width: 18px; height: 18px; margin: 0 !important; padding: 0 !important; border: 0 !important; border-radius: 50%;
            background: rgba(118,118,128,.38) !important; color: #fff !important; font-size: 0 !important; line-height: 0 !important; cursor: pointer; transition: background-color .15s ease, transform .1s ease-out; }
        html.apl-on #filters .select2-container--default .select2-selection__clear::before {
            content: ''; width: 8px; height: 8px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='4' stroke-linecap='round'%3E%3Cpath d='M6 6l12 12M18 6 6 18'/%3E%3C/svg%3E") no-repeat center / contain; }
        html.apl-on #filters .select2-container--default .select2-selection__clear:hover { background: rgba(118,118,128,.6) !important; }
        html.apl-on #filters .select2-container--default .select2-selection__clear:active { transform: translateY(-50%) scale(.9); }
        html.apl-on #filters .select2-container--default .select2-selection--multiple .select2-selection__clear { right: 10px !important; }
        html.apl-on #filters .select2-container--default .select2-selection--multiple { padding: 2px 8px !important; }
        html.apl-on #filters .select2-container--default .select2-selection--multiple .select2-selection__choice {
            position: relative; margin: 5px 4px 3px 0 !important; padding: 2px 10px 2px 24px !important; border: 0 !important; border-radius: 980px !important; background: #fff !important; color: #1d1d1f; font-size: 13px; line-height: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
        html.apl-on #filters .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            position: absolute; left: 6px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; display: grid; place-items: center; margin: 0 !important; padding: 0 !important;
            border: 0 !important; border-radius: 50%; background: rgba(118,118,128,.16) !important; color: #6e6e73 !important; font-size: 11px; line-height: 1; }
        html.apl-on #filters .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover { background: rgba(118,118,128,.3) !important; color: #1d1d1f !important; }
        html.apl-on #filters .select2-container--default .select2-selection--multiple .select2-selection__choice__display { padding: 0 !important; }
        html.apl-on #filters .select2-container--default .select2-search--inline .select2-search__field { margin-top: 6px; font-size: 15px; font-family: inherit; }
        html.apl-on #filters .select2-container--focus .select2-selection, html.apl-on #filters .select2-container--open .select2-selection {
            background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        html.apl-on #filters { overflow: visible !important; position: relative; }
        html.apl-on .select2-dropdown { border: 0 !important; border-radius: 14px !important; overflow: hidden; margin-top: 6px;
            box-shadow: 0 0 0 1px rgba(0,0,0,.06), 0 12px 32px rgba(0,0,0,.16) !important; background: rgba(255,255,255,.96); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px);
            animation: rg-pop .18s cubic-bezier(.23,1,.32,1); transform-origin: top center; }
        html.apl-on .select2-container--above .select2-dropdown { margin-top: -6px; transform-origin: bottom center; }
        @keyframes rg-pop { from { opacity: 0; transform: scale(.97) translateY(-4px); } to { opacity: 1; transform: none; } }
        html.apl-on .select2-search--dropdown { padding: 8px !important; }
        html.apl-on .select2-search--dropdown .select2-search__field { height: 34px; border: 0 !important; border-radius: 9px !important; background: rgba(118,118,128,.12); padding: 0 10px !important; font-size: 14px; outline: none; }
        html.apl-on .select2-results__options { padding: 4px 6px 6px !important; }
        html.apl-on .select2-results__option { border-radius: 8px; padding: 8px 10px !important; font-size: 14px; color: #1d1d1f; }
        html.apl-on .select2-container--default .select2-results__option--highlighted[aria-selected], html.apl-on .select2-container--default .select2-results__option--highlighted { background: #0071e3 !important; color: #fff !important; }
        html.apl-on .select2-container--default .select2-results__option[aria-selected=true]:not(.select2-results__option--highlighted) { background: rgba(0,113,227,.1) !important; color: #0071e3; font-weight: 500; }


        /* messages: a quiet banner with a coloured dot, not a bordered box */
        html.apl-on .rg-flash { display: flex; align-items: flex-start; gap: 10px; border: 0 !important; border-radius: 14px !important; padding: 12px 16px !important; font-size: 14px; background: #fff !important; color: #1d1d1f !important;
            box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 6px 18px -6px rgba(0,0,0,.1); animation: rg-pop .25s cubic-bezier(.23,1,.32,1); }
        html.apl-on .rg-flash::before { content: ''; flex: none; width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; }
        html.apl-on .rg-flash-ok::before { background: #34c759; }
        html.apl-on .rg-flash-err::before { background: #ff3b30; }
        html.apl-on .rg-flash ul { margin: 0; padding-left: 16px; }

        /* the company's paper header: same facts, light hairlines on screen */
        html.apl-on .rg-paper { border: 0 !important; border-collapse: separate !important; border-spacing: 0; border-radius: 14px; overflow: hidden; background: #f5f5f7; box-shadow: inset 0 0 0 1px rgba(60,60,67,.1); }
        html.apl-on .rg-paper td { border: 0 !important; border-bottom: 1px solid rgba(60,60,67,.1) !important; border-right: 1px solid rgba(60,60,67,.1) !important; padding: 8px 12px !important; font-variant-numeric: tabular-nums; }
        html.apl-on .rg-paper tr:last-child td { border-bottom: 0 !important; }
        html.apl-on .rg-paper td:last-child { border-right: 0 !important; }
        html.apl-on .rg-paper .font-mono { font-family: inherit !important; }
        html.apl-on .rg-paper .font-extrabold { font-weight: 600 !important; letter-spacing: -.01em; }

        /* fields inside cards (opening balance form) look like the filter fields */
        html.apl-on .rg-card select:not(.inline-select):not(.select2-hidden-accessible), html.apl-on .rg-card input[type=number]:not(.inline-input), html.apl-on .rg-card input[type=date]:not(.inline-input), html.apl-on .rg-card input[type=text]:not(.inline-input) {
            height: 40px; border: 0 !important; border-radius: 11px !important; background-color: rgba(118,118,128,.12) !important; box-shadow: none !important; padding: 0 12px !important; font-size: 15px !important; }
        html.apl-on .rg-card select:not(.inline-select):not(.select2-hidden-accessible) { -webkit-appearance: none; appearance: none; padding-right: 34px !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important; background-repeat: no-repeat !important; background-position: right 12px center !important; }
        html.apl-on .rg-card select:focus, html.apl-on .rg-card input:focus { outline: none; background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        html.apl-on .rg-card label { font-size: 13px !important; font-weight: 500 !important; color: var(--apl-label-2, #6e6e73) !important; }
        html.apl-on .rg-card .border-t { border-color: rgba(60,60,67,.1) !important; }

        /* document type legend (the claim register shares it) */
        .doc-badge { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        html.apl-on .rg-legend .doc-badge { border-radius: 980px !important; padding: 3px 10px !important; font-size: 12px; font-weight: 500; }
        html.apl-on .rg-legend .doc-badge b { font-weight: 700; margin-right: 3px; }

        /* the post confirmation: an Apple alert */
        html.apl-on .rg-confirm .bg-gray-900\/40 { background: rgba(0,0,0,.3) !important; }
        html.apl-on .rg-confirm .rounded-lg.bg-white { border-radius: 22px !important; box-shadow: 0 24px 64px rgba(0,0,0,.22), 0 0 0 1px rgba(0,0,0,.05) !important; }
        html.apl-on .rg-confirm h3 { font-size: 18px !important; font-weight: 600 !important; letter-spacing: -.02em; color: #1d1d1f !important; }
        html.apl-on .rg-confirm p { font-size: 14px !important; line-height: 1.45; color: #424245 !important; }
        html.apl-on .rg-confirm .bg-gray-100 { background: #f5f5f7 !important; border-top: 1px solid rgba(60,60,67,.1); }
        html.apl-on .rg-confirm button { height: 36px; padding: 0 18px !important; border-radius: 980px !important; text-transform: none !important; letter-spacing: -.01em !important; font-size: 14px !important; font-weight: 500 !important; box-shadow: none !important; transition: transform .1s ease-out; }
        html.apl-on .rg-confirm button.border-gray-300 { border: 0 !important; background: rgba(120,120,128,.14) !important; color: #1d1d1f !important; }
        html.apl-on .rg-confirm button.bg-green-600 { background: #34c759 !important; }
        html.apl-on .rg-confirm button:active { transform: scale(.97); }
        html.apl-on .rg-confirm input[type=password] { height: 40px; border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; padding: 0 12px !important; }
        html.apl-on .rg-confirm input[type=password]:focus { outline: none; background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        html.apl-on .rg-confirm button.bg-emerald-600 { background: #34c759 !important; color: #fff !important; }

        /* the page header (and its download menu) stays above the cards below it */
        header.bg-white { position: relative; z-index: 30; }
        /* download menu under the header button */
        .rg-menu { position: absolute; right: 0; top: calc(100% + 8px); z-index: 40; min-width: 230px; padding: 6px; border-radius: 14px; background: rgba(255,255,255,.96);
            -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); box-shadow: 0 0 0 1px rgba(0,0,0,.06), 0 12px 32px rgba(0,0,0,.16); transform-origin: top right; }
        .rg-menu-label { margin: 4px 10px 6px; font-size: 12px; font-weight: 500; color: #6e6e73; }
        .rg-menu a { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 9px; font-size: 14px; color: #1d1d1f; text-decoration: none; }
        .rg-menu a span { margin-left: auto; font-size: 11px; color: #6e6e73; }
        .rg-menu a:hover, .rg-menu a:focus-visible { background: #0071e3; color: #fff; outline: none; }
        .rg-menu a:hover span, .rg-menu a:focus-visible span { color: rgba(255,255,255,.8); }

        /* small help buttons next to a part of the page */
        .rg-info { display: inline-grid; place-items: center; width: 18px; height: 18px; margin-left: 6px; vertical-align: 1px; border: 0; border-radius: 50%; background: rgba(0,113,227,.12);
            color: #0071e3; font: 700 11px/1 Georgia, 'Times New Roman', serif; font-style: italic; cursor: pointer; transition: background-color .15s ease, transform .1s ease-out; }
        .rg-info:hover { background: rgba(0,113,227,.22); }
        .rg-info:active { transform: scale(.92); }

        /* Urdu help window */
        .rgh-scrim { position: fixed; inset: 0; z-index: 60; background: rgba(0,0,0,.32); -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px); }
        .rgh-wrap { position: fixed; inset: 0; z-index: 61; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .rgh-box { width: 100%; max-width: 900px; max-height: calc(100vh - 32px); display: flex; flex-direction: column; overflow: hidden; border-radius: 20px; background: #f5f5f7; box-shadow: 0 24px 64px rgba(0,0,0,.22); }
        .rgh-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 16px 18px 12px 22px; background: rgba(255,255,255,.88); border-bottom: 1px solid #e5e5ea;
            -webkit-backdrop-filter: saturate(180%) blur(16px); backdrop-filter: saturate(180%) blur(16px); }
        .rgh-head h3 { margin: 0; font-size: 19px; font-weight: 600; letter-spacing: -.02em; color: #1d1d1f; }
        .rgh-head p { margin: 2px 0 0; font-size: 13px; color: #6e6e73; }
        .rgh-close { display: inline-grid; place-items: center; width: 30px; height: 30px; border: 0; border-radius: 50%; background: #e8e8ed; color: #1d1d1f; cursor: pointer; }
        .rgh-close:hover { background: #dcdce1; }
        .rgh-close:active { transform: scale(.94); }
        .rgh-body { position: relative; overflow-y: auto; padding: 16px 18px 20px; scroll-behavior: smooth; }
        .rgh-body .bh-main { gap: 14px; }
        .rgh-body .bh section { padding: 14px 20px; border-radius: 14px; box-shadow: none; transition: box-shadow .4s ease, background-color .4s ease; }
        .rgh-body .bh h2 { font-size: 20px; }
        .rgh-body .bh section.rgh-hit { box-shadow: 0 0 0 2px #0071e3, 0 8px 24px -8px rgba(0,113,227,.35); background: #f7fbff; }
        @media (max-width: 640px) { .rgh-wrap { padding: 0; align-items: flex-end; } .rgh-box { max-height: 92vh; border-radius: 20px 20px 0 0; } }
        @media (prefers-reduced-motion: reduce) { .rgh-body { scroll-behavior: auto; } .rgh-body .bh section { transition: none; } }

        /* Select2 everywhere: same filled field in sheets and cards, a compact one inside table rows */
        html.apl-on .rg-sheet { overflow: visible !important; }
        html.apl-on .rg-sheet .select2-container, html.apl-on .rg-card .select2-container { margin-top: 4px; }
        html.apl-on .rg-sheet .select2-selection--single, html.apl-on .rg-card .select2-selection--single {
            height: 40px !important; padding: 0 !important; border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; }
        html.apl-on .rg-sheet .select2-selection--single .select2-selection__rendered, html.apl-on .rg-card .select2-selection--single .select2-selection__rendered { line-height: 40px !important; padding-left: 12px !important; padding-right: 34px !important; font-size: 15px; color: #1d1d1f; }
        html.apl-on #filters .select2-selection--single .select2-selection__arrow, html.apl-on .rg-sheet .select2-selection--single .select2-selection__arrow, html.apl-on .rg-card .select2-selection--single .select2-selection__arrow {
            top: 0 !important; right: 10px !important; width: 14px !important; height: 100% !important;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat center / 12px 12px; transition: transform .2s cubic-bezier(.23,1,.32,1); }
        html.apl-on .rg-sheet .select2-selection__arrow b, html.apl-on .rg-card .select2-selection__arrow b { display: none !important; }
        html.apl-on .select2-container--open .select2-selection--single .select2-selection__arrow { transform: rotate(180deg); }
        html.apl-on .rg-sheet .select2-container--focus .select2-selection, html.apl-on .rg-sheet .select2-container--open .select2-selection,
        html.apl-on .rg-card .select2-container--focus .select2-selection, html.apl-on .rg-card .select2-container--open .select2-selection { background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        html.apl-on .rg-card .select2-container.rg-inline { margin-top: 0; }
        html.apl-on .rg-card .select2-container.rg-inline .select2-selection--single { height: 32px !important; border-radius: 9px !important; }
        html.apl-on .rg-card .select2-container.rg-inline .select2-selection__rendered { line-height: 32px !important; font-size: 13px; padding-left: 9px !important; padding-right: 26px !important; }
        html.apl-on .rg-card .select2-container.rg-inline .select2-selection__arrow { right: 7px !important; }

        /* column and document type explanations inside the help window */
        .rgh-body .rgh-col, .rgh-body .rgh-type { margin: 10px 0; padding: 10px 14px 6px; border-radius: 12px; background: #fbfbfd; border: 1px solid #ececf0; scroll-margin-top: 12px; transition: box-shadow .4s ease, background-color .4s ease; }
        .rgh-body .rgh-col-head { display: flex; align-items: center; gap: 10px; }
        .rgh-body .rgh-col-head b { font-size: 16px; }
        .rgh-body .rgh-col p { margin: 2px 0 4px; font-size: 15px; }
        .rgh-body .rgh-col dl { display: grid; grid-template-columns: max-content 1fr; gap: 0 14px; margin: 0; font-size: 14.5px; }
        .rgh-body .rgh-col dt { color: #6e6e73; }
        .rgh-body .rgh-col dd { margin: 0; }
        .rgh-body .rgh-types { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 8px; }
        .rgh-body .rgh-type { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 0 10px; align-items: center; }
        .rgh-body .rgh-type small { grid-column: 1 / -1; font-size: 14px; color: #55555b; line-height: 2; }
        .rgh-body .rgh-hit { box-shadow: 0 0 0 2px #0071e3, 0 8px 24px -8px rgba(0,113,227,.35) !important; background: #f7fbff !important; }
        .rg-table th .rg-info { margin-left: 4px; width: 16px; height: 16px; font-size: 10px; }
        .rg-type-btn { border: 0; cursor: pointer; transition: transform .1s ease-out, box-shadow .15s ease; }
        .rg-type-btn:hover { box-shadow: 0 0 0 1px currentColor; }
        .rg-type-btn:active { transform: scale(.96); }

        /* text that fits: words left-aligned, at most two lines then "…" (full text on hover); dates and amounts never break */
        html.apl-on .rg-table th.rg-text, html.apl-on .rg-table td.rg-text { text-align: left !important; }
        html.apl-on .rg-table .rg-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: normal; overflow-wrap: break-word; hyphens: none; line-height: 1.35; }
        html.apl-on .rg-table th.rg-text, html.apl-on .rg-table td.rg-text { min-width: 110px; }
        html.apl-on .rg-table .rg-nowrap, html.apl-on .rg-table .amount-cell, html.apl-on .rg-table thead th { white-space: nowrap; }
        html.apl-on .rg-table thead th { font-size: 11.5px; }

        /* smaller screens: cards reflow, the table scrolls sideways with its number column kept in view */
        @media (max-width: 1023px) {
            html.apl-on .rg-kpi .text-2xl { font-size: 22px !important; }
            html.apl-on .rg-card { padding: 14px !important; border-radius: 16px !important; }
            html.apl-on .rg-table tbody td:first-child, html.apl-on .rg-table thead th:first-child { position: sticky; left: 0; z-index: 1; background: #fff; }
            html.apl-on .rg-table thead th:first-child { z-index: 3; }
        }
        @media (max-width: 640px) {
            /* a 16px gutter, KPI cards two by two, wrapping rows instead of squeezed text */
            html.apl-on .max-w-7xl.mx-auto, html.apl-on .max-w-3xl.mx-auto { padding-left: 16px !important; padding-right: 16px !important; }
            html.apl-on .grid:has(> .rg-kpi) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 10px !important; }
            html.apl-on .rg-kpi { padding: 12px 14px !important; border-radius: 16px !important; }
            html.apl-on .rg-kpi .text-2xl { font-size: 18px !important; }
            html.apl-on .rg-kpi > .text-sm:first-child { font-size: 12px; }
            html.apl-on .rg-card .flex.items-center.justify-between { flex-wrap: wrap; gap: 10px; }
            html.apl-on .rg-card .flex.items-center.gap-3 { flex-wrap: wrap; gap: 6px; }
            html.apl-on .rg-form-body, html.apl-on .rg-form-head { padding-left: 16px; padding-right: 16px; }
            html.apl-on .rg-paper { min-width: 560px; }
            html.apl-on header.bg-white .flex.justify-between { flex-wrap: wrap; gap: 10px; }
            html.apl-on header.bg-white .space-x-2 { flex-wrap: wrap; gap: 6px; }
            html.apl-on header.bg-white .space-x-2 > * { margin: 0 !important; }
            html.apl-on .rg-match { flex-direction: column; align-items: flex-start !important; }
            html.apl-on .rg-empty { padding: 40px 18px 34px; }
            html.apl-on .rg-menu { right: auto; left: 0; }
        }

        /* header create button: words, not shouting capitals */
        header.bg-white a.bg-blue-950 { text-transform: none !important; letter-spacing: -.01em !important; font-size: 14px !important; font-weight: 500 !important; gap: 4px; }
        header.bg-white a.bg-blue-950 svg.size-6 { width: 18px; height: 18px; }
        /* required fields */
        html.apl-on .rg-card label.rg-required::after { content: ' *'; color: #ff3b30; }

        /* pagination: rounded steps, the current page in blue */
        html.apl-on .rg-card nav[role="navigation"] .shadow-sm { box-shadow: none !important; display: inline-flex; gap: 4px; }
        html.apl-on .rg-card nav[role="navigation"] a, html.apl-on .rg-card nav[role="navigation"] span[aria-disabled] > span, html.apl-on .rg-card nav[role="navigation"] span[aria-current] > span, html.apl-on .rg-card nav[role="navigation"] .relative.inline-flex {
            min-width: 34px; height: 34px; justify-content: center; margin: 0 !important; border: 0 !important; border-radius: 10px !important; background: rgba(118,118,128,.1) !important; color: #1d1d1f !important; font-size: 13.5px; transition: background-color .15s ease, transform .1s ease-out; }
        html.apl-on .rg-card nav[role="navigation"] a:hover { background: rgba(118,118,128,.2) !important; }
        html.apl-on .rg-card nav[role="navigation"] a:active { transform: scale(.95); }
        html.apl-on .rg-card nav[role="navigation"] span[aria-current] > span { background: #0071e3 !important; color: #fff !important; font-weight: 600; }
        html.apl-on .rg-card nav[role="navigation"] p { color: #6e6e73; font-size: 13.5px; }

        /* claim CRUD: form sheet */
        html.apl-on .rg-form { padding: 0 !important; overflow: visible !important; }
        html.apl-on .rg-form-head { padding: 18px 24px 0; }
        html.apl-on .rg-form-head h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -.022em; color: #1d1d1f; }
        html.apl-on .rg-form-head p { margin: 4px 0 0; font-size: 14px; color: #6e6e73; }
        html.apl-on .rg-form-body { padding: 18px 24px 22px; }
        html.apl-on .rg-form-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; border-top: 1px solid rgba(60,60,67,.1); background: #f5f5f7; border-radius: 0 0 20px 20px; }
        html.apl-on .rg-card textarea { border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; padding: 10px 12px !important; font-size: 15px !important; }
        html.apl-on .rg-card textarea:focus { outline: none; background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        html.apl-on .rg-field-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500; color: #6e6e73; }
        html.apl-on .rg-req { color: #ff3b30; }
        html.apl-on .rg-seg { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: 2px; padding: 3px; border-radius: 12px; background: rgba(118,118,128,.12); }
        html.apl-on .rg-seg-option { display: block; padding: 8px 10px; border-radius: 9px; text-align: center; font-size: 14px; font-weight: 500; color: #1d1d1f; cursor: pointer; transition: background-color .2s ease, box-shadow .2s ease, transform .1s ease-out; }
        html.apl-on .rg-seg-option:hover { background: rgba(255,255,255,.5); }
        html.apl-on .rg-seg-option:active { transform: scale(.97); }
        html.apl-on .rg-seg-option.is-on { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.12), 0 0 0 .5px rgba(0,0,0,.04); }
        html.apl-on .rg-seg-option:has(input:focus-visible) { box-shadow: 0 0 0 3px rgba(0,113,227,.35); }
        html.apl-on .rg-hint { margin: 8px 2px 0; font-size: 13px; line-height: 1.45; color: #6e6e73; }
        html.apl-on .rg-amount { font-variant-numeric: tabular-nums; font-weight: 600 !important; }

        /* claim CRUD: view page */
        html.apl-on .rg-hero { padding: 24px !important; }
        html.apl-on .rg-hero-top { display: flex; flex-wrap: wrap; gap: 8px; }
        html.apl-on .rg-capsule { display: inline-flex; align-items: center; padding: 3px 11px; border-radius: 980px; font-size: 12.5px; font-weight: 600; }
        html.apl-on .rg-capsule-blue { background: rgba(0,113,227,.12); color: #0058b0; }
        html.apl-on .rg-capsule-green { background: rgba(52,199,89,.16); color: #1f7a35; }
        html.apl-on .rg-capsule-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
        html.apl-on .rg-hero-amount { margin: 14px 0 0; font-size: 38px; font-weight: 600; letter-spacing: -.03em; line-height: 1.1; color: #1d1d1f; font-variant-numeric: tabular-nums; }
        html.apl-on .rg-hero-sub { margin: 6px 0 0; font-size: 15px; color: #424245; }
        html.apl-on .rg-hero-note { margin: 12px 0 0; padding: 10px 14px; border-radius: 12px; background: #f5f5f7; font-size: 13.5px; line-height: 1.45; color: #55555b; }
        html.apl-on .rg-hero-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        html.apl-on .rg-hero-posted { margin: 14px 0 0; font-size: 13px; color: #6e6e73; }
        html.apl-on .rg-details { display: grid; grid-template-columns: minmax(130px, 34%) 1fr; margin: 0; }
        html.apl-on .rg-details dt, html.apl-on .rg-details dd { margin: 0; padding: 11px 4px; border-bottom: 1px solid rgba(60,60,67,.1); font-size: 14.5px; }
        html.apl-on .rg-details dt { color: #6e6e73; }
        html.apl-on .rg-details dd { color: #1d1d1f; overflow-wrap: anywhere; }
        html.apl-on .rg-details dt:nth-last-child(2), html.apl-on .rg-details dd:last-child { border-bottom: 0; }
        html.apl-on .rg-pill.rg-pill-danger { background: rgba(255,59,48,.12) !important; color: #d70015 !important; }
        html.apl-on .rg-pill.rg-pill-danger:hover { background: rgba(255,59,48,.2) !important; }
        html.apl-on .rg-posted { display: inline-flex; align-items: center; height: 24px; padding: 0 9px; border-radius: 980px; background: rgba(52,199,89,.14); color: #1f7a35; font-size: 11.5px; font-weight: 600; }
        @media (max-width: 640px) { html.apl-on .rg-details { grid-template-columns: 1fr; } html.apl-on .rg-details dt { padding-bottom: 0; border-bottom: 0; } html.apl-on .rg-hero-amount { font-size: 30px; } }

        /* add-entry sheet: 7xl wide, every field on one line on a wide screen, wrapping on smaller ones */
        html.apl-on .rg-add-sheet { margin: auto; }
        html.apl-on .rg-add-sub { margin: 2px 0 0; font-size: 13px; color: #6e6e73; font-weight: 400; }
        html.apl-on .rg-add-row { display: grid; gap: 12px; padding: 20px 24px 6px; align-items: end; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
        @media (min-width: 1100px) {
            html.apl-on .rg-add-claim { grid-template-columns: 145px 170px 160px 140px minmax(160px, 1fr) 130px 150px; }
            html.apl-on .rg-add-ledger { grid-template-columns: 140px 170px 130px repeat(5, minmax(92px, 1fr)) 170px; }
        }
        html.apl-on .rg-add-row label { display: block; margin-bottom: 5px; font-size: 12.5px !important; font-weight: 500 !important; color: #6e6e73 !important; white-space: nowrap; }
        html.apl-on .rg-add-row input { width: 100%; height: 40px; }
        html.apl-on .rg-add-row .select2-container { margin-top: 0 !important; }
        html.apl-on .rg-add-effect { display: flex; flex-direction: column; justify-content: flex-end; min-height: 40px; padding: 6px 12px; border-radius: 11px; background: #f5f5f7; line-height: 1.2; }
        html.apl-on .rg-add-effect span { font-size: 11.5px; color: #6e6e73; }
        html.apl-on .rg-add-effect b { font-size: 15px; font-variant-numeric: tabular-nums; }
        html.apl-on .rg-add-effect small { font-size: 11.5px; color: #6e6e73; font-variant-numeric: tabular-nums; }
        html.apl-on .rg-add-note { margin: 10px 24px 0; font-size: 12.5px; color: #6e6e73; }
        html.apl-on .rg-add-foot { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; padding: 14px 20px; border-top: 1px solid rgba(60,60,67,.1); background: #f5f5f7; border-radius: 0 0 22px 22px; }
        @media (max-width: 640px) { html.apl-on .rg-add-row { padding: 16px 16px 4px; } html.apl-on .rg-add-note { margin: 10px 16px 0; } }

        /* quick date shortcuts inside a filter panel */
        html.apl-on .rg-quick-dates { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 16px; }
        html.apl-on .rg-quick-dates span { margin-right: 4px; font-size: 13px; color: #6e6e73; }
        html.apl-on .rg-quick-dates a { padding: 5px 12px; border-radius: 980px; background: rgba(118,118,128,.12); color: #1d1d1f; font-size: 13px; text-decoration: none; transition: background-color .15s ease, transform .1s ease-out; }
        html.apl-on .rg-quick-dates a:hover { background: rgba(118,118,128,.22); }
        html.apl-on .rg-quick-dates a:active { transform: scale(.96); }
        html.apl-on .rg-quick-dates a[aria-current] { background: #0071e3; color: #fff; }
        /* empty state until a supplier is chosen */
        html.apl-on .rg-empty { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 56px 24px 48px; border-radius: 22px; background: #fff; }
        html.apl-on .rg-empty-icon { display: grid; place-items: center; width: 60px; height: 60px; margin-bottom: 14px; border-radius: 18px; background: linear-gradient(180deg, #e8f2ff, #d6e8ff); color: #0071e3; }
        html.apl-on .rg-empty h2 { margin: 0; font-size: 21px; font-weight: 600; letter-spacing: -.022em; color: #1d1d1f; }
        html.apl-on .rg-empty p { margin: 6px 0 20px; max-width: 460px; font-size: 15px; line-height: 1.45; color: #6e6e73; }
    }

    @media (prefers-reduced-motion: reduce) {
        html.apl-on .rg-icon, html.apl-on .rg-pill, html.apl-on .rg-close, html.apl-on .rg-table tbody tr { transition: none !important; }
        html.apl-on .rg-icon:active, html.apl-on .rg-pill:active, html.apl-on .rg-close:active { transform: none; }
        html.apl-on .select2-dropdown { animation: none; }
        html.apl-on .rg-kpi { transition: none; } html.apl-on .rg-kpi:hover { transform: none; }
    }

    @media (prefers-reduced-transparency: reduce) {
        html.apl-on .rg-table thead th, html.apl-on .rg-sheet-head { background: #fff !important; -webkit-backdrop-filter: none; backdrop-filter: none; }
        html.apl-on .rg-scrim { -webkit-backdrop-filter: none; backdrop-filter: none; background: rgba(0,0,0,.45) !important; }
        html.apl-on .select2-dropdown { background: #fff; -webkit-backdrop-filter: none; backdrop-filter: none; }
    }
</style>
<script>
    // "Choose supplier" in the empty state: open the filter panel, bring it into view and open the supplier list.
    window.rgOpenFilters = function () {
        var filters = document.getElementById('filters');
        var toggle = document.getElementById('toggle');
        if (!filters || !toggle) { return; }
        if (filters.style.display === 'none') { toggle.click(); }
        filters.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        setTimeout(function () { if (window.jQuery) { jQuery('select.rg-supplier-select').select2('open'); } }, 320);
    };

    // Every other dropdown of these registers is a Select2 too: search only on long lists, no clear button, the list
    // opens inside the filter panel or the sheet it belongs to (so choosing never "clicks outside" and closes them),
    // and a choice is echoed as a native change so Alpine's x-model sees it.
    window.rgSyncSelects = function (root) {
        if (window.jQuery) { jQuery(root || document).find('select.rg-select.select2-hidden-accessible').trigger('change.select2'); }
    };
    window.addEventListener('load', function () {
        if (!window.jQuery || !jQuery.fn.select2) { return; }
        jQuery('select.rg-select').each(function () {
            var $select = jQuery(this);
            if ($select.hasClass('select2-hidden-accessible')) { return; }
            var $parent = $select.closest('#filters, .rg-sheet');
            $select.select2({
                width: '100%',
                minimumResultsForSearch: this.options.length > 8 ? 0 : Infinity,
                dropdownParent: $parent.length ? $parent : jQuery(document.body),
            }).on('select2:select', function () {
                this.dispatchEvent(new Event('change', { bubbles: true }));
            });
            if ($select.hasClass('inline-select')) { $select.next('.select2').addClass('rg-inline'); }
        });
    });

    // The layout starts every Select2 with one generic placeholder; these registers name what to choose.
    window.addEventListener('load', function () {
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('select.rg-supplier-select, select.rg-multi-select').each(function () {
                var $select = jQuery(this);
                if ($select.hasClass('select2-hidden-accessible')) { $select.select2('destroy'); }
                // the list opens inside the filter panel, so choosing from it is not a click "outside" that closes the panel
                var $panel = $select.closest('#filters');
                $select.select2({ placeholder: $select.data('placeholder'), allowClear: true, width: '100%', dropdownParent: $panel.length ? $panel : jQuery(document.body) });
            });
        }
    });
</script>
@endonce
