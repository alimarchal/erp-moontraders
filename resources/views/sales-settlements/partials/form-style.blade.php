{{-- Apple-style skin for the settlement create / edit forms, scoped to .ssf-page. It only restyles: every input,
     id and script hook of the form is unchanged, so what the form saves is unchanged. Builds on <x-apple-skin />
     and goods-issues/partials/form-style (fields, Select2). --}}
<style>
    .ssf-page { --ssf-orange: #ff9500; --ssf-green: #34c759; --ssf-purple: #af52de; --ssf-blue: #0071e3; --ssf-press: cubic-bezier(.23, 1, .32, 1); }
    .ssf-page .ssf-inner { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .ssf-page .ssf-shell { background: transparent; box-shadow: none; overflow: visible; }
    .ssf-page #settlementForm { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
    .ssf-page #settlementForm > * { min-width: 0; }

    /* Cards */
    .ssf-page .ssf-card { position: relative; background: #fff; border: 0; border-radius: 18px; box-shadow: 0 1px 1px rgba(0, 0, 0, .02), 0 4px 18px rgba(0, 0, 0, .05); overflow: hidden; }
    .ssf-page .ssf-card-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 48px; padding: 12px 14px 10px 18px; background: #fff; border-bottom: 1px solid rgba(60, 60, 67, .1); }
    .ssf-page .ssf-card-titles { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .ssf-page .ssf-card-title { margin: 0; display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; letter-spacing: -.01em; color: #1d1d1f; text-align: left; }
    .ssf-page .ssf-card-title::before { content: ""; flex: none; width: 8px; height: 8px; border-radius: 50%; background: var(--ssf-tone, #86868b); }
    .ssf-page .ssf-tone-orange { --ssf-tone: var(--ssf-orange); }
    .ssf-page .ssf-tone-green { --ssf-tone: var(--ssf-green); }
    .ssf-page .ssf-tone-purple { --ssf-tone: var(--ssf-purple); }
    .ssf-page .ssf-tone-blue { --ssf-tone: var(--ssf-blue); }
    .ssf-page .ssf-start { --ssf-tone: var(--ssf-blue); overflow: visible; }
    .ssf-page .ssf-start .ssf-card-head { border-radius: 18px 18px 0 0; }
    .ssf-page .ssf-start-body { padding: 16px 18px 14px; }
    .ssf-page #noItemsMessage { margin: 4px 0 0; color: #86868b; font-size: 13px; }

    /* Help "?" and the Add buttons */
    .ssf-page .ssf-add { flex: none; display: inline-flex; align-items: center; height: 30px; padding: 0 12px; border: 0; border-radius: 980px; background: rgba(0, 113, 227, .1); color: #0071e3; font-size: 13px; font-weight: 600; white-space: nowrap; cursor: pointer; transition: transform 160ms var(--ssf-press), background-color 160ms ease; }
    .ssf-page .ssf-add:active { transform: scale(.97); }
    .ssf-page .ssf-add:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; }
    @media (hover: hover) and (pointer: fine) { .ssf-page .ssf-add:hover { background: rgba(0, 113, 227, .16); } }

    /* Section heading over the money cards */
    .ssf-page .ssf-money { padding: 0; }
    .ssf-page .ssf-section-title { display: flex; align-items: center; gap: 8px; margin: 4px 0 14px; font-size: 22px; font-weight: 600; letter-spacing: -.02em; color: #1d1d1f; }
    .ssf-page .ssf-section-title svg { display: none; }
    .ssf-page .ssf-money .grid { gap: 20px !important; }
    .ssf-page .ssf-money .grid.mb-6 { margin-bottom: 20px; }

    /* A wide table (cheques, transfers) scrolls inside its card on a narrow screen instead of being cut off */
    .ssf-page .ssf-card > .p-0 { overflow-x: auto; -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; }

    /* Small tables inside the cards (collections, cash, expenses, summary) */
    .ssf-page .ssf-mini { font-size: 13px !important; font-variant-numeric: tabular-nums; border-collapse: collapse; }
    .ssf-page .ssf-mini th { padding: 8px 12px !important; font-size: 11px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; color: #86868b !important; background: #fafafc; border-bottom: 1px solid rgba(60, 60, 67, .1); }
    .ssf-page .ssf-mini thead tr { border: 0 !important; }
    .ssf-page .ssf-mini td { padding: 7px 12px !important; font-size: 13px !important; color: #1d1d1f; border: 0 !important; }
    .ssf-page .ssf-mini tbody tr { border-top: 1px solid rgba(60, 60, 67, .08) !important; }
    .ssf-page .ssf-mini tbody tr:first-child { border-top: 0 !important; }
    .ssf-page .ssf-mini td.text-right, .ssf-page .ssf-mini th.text-right { white-space: nowrap; }
    .ssf-page .ssf-mini td.italic { padding: 14px 12px !important; color: #86868b !important; font-style: normal; }
    .ssf-page .ssf-mini tfoot { border-top: 1px solid rgba(60, 60, 67, .12) !important; }
    .ssf-page .ssf-mini tfoot tr, .ssf-page .ssf-mini tfoot td { background: #fafafc !important; font-weight: 600; }
    .ssf-page .ssf-mini input[type="number"], .ssf-page .ssf-mini input[type="text"] { height: 30px; padding: 0 8px !important; border: 1px solid rgba(60, 60, 67, .16) !important; border-radius: 8px !important; background: rgba(118, 118, 128, .06); font-size: 13px !important; font-variant-numeric: tabular-nums; box-shadow: none !important; }
    .ssf-page .ssf-mini input[type="number"] { width: 84px !important; }
    .ssf-page .ssf-mini input:focus { outline: none; border-color: #0071e3 !important; background: #fff; box-shadow: 0 0 0 3px rgba(0, 113, 227, .18) !important; }
    .ssf-page .ssf-mini input[readonly], .ssf-page .ssf-mini input:disabled { background: rgba(118, 118, 128, .1); color: #3a3a3c; }
    .ssf-page .ssf-mini a, .ssf-page .ssf-mini button.underline { color: #0071e3; text-decoration: none; font-weight: 500; }
    .ssf-page .ssf-mini [class*="text-blue-6"].underline, .ssf-page .ssf-mini a.underline { text-decoration: none; }

    /* The account box of an added expense line: as wide as the cell, styled like the other fields */
    .ssf-page .ssf-mini td .flex:has(> select) { display: flex; align-items: center; gap: 6px; width: 100%; }
    .ssf-page .ssf-mini td .flex > .select2-container { flex: 1 1 auto; width: auto !important; min-width: 160px; }
    .ssf-page .ssf-mini td .select2-container .select2-selection--single { height: 30px !important; border: 1px solid rgba(60, 60, 67, .16) !important; border-radius: 8px !important; background: #fff !important; box-shadow: none !important; }
    .ssf-page .ssf-mini td .select2-container .select2-selection__rendered { line-height: 28px !important; padding-left: 10px !important; font-size: 13px; color: #1d1d1f; }
    .ssf-page .ssf-mini td .select2-container .select2-selection__placeholder { color: #86868b; }
    .ssf-page .ssf-mini td .select2-container .select2-selection__arrow { height: 28px !important; }
    .ssf-page .ssf-mini td .select2-container--open .select2-selection--single, .ssf-page .ssf-mini td .select2-container--focus .select2-selection--single { border-color: #0071e3 !important; box-shadow: 0 0 0 3px rgba(0, 113, 227, .18) !important; }
    .ssf-page .ssf-mini td .flex > button { flex: none; width: 24px; height: 24px; border-radius: 50%; color: #d70015; }
    body > .select2-container .select2-dropdown, .ssf-page .ssf-mini .select2-dropdown { min-width: 260px; border-color: rgba(60, 60, 67, .16); border-radius: 10px; box-shadow: 0 12px 32px rgba(0, 0, 0, .14); overflow: hidden; }

    body > .select2-container .select2-results__option { padding: 7px 10px; font-size: 13px; }
    body > .select2-container .select2-results__option--highlighted[aria-selected] { background: #0071e3; color: #fff; }
    body > .select2-container .select2-search__field { border-radius: 8px; border-color: rgba(60, 60, 67, .16); }

    /* Item (batch) table */
    .ssf-page .ssf-items #settlementTableContainer { margin: 0 !important; text-align: left !important; }
    .ssf-page .ssf-items #settlementTableContainer > div > h3 { margin: 0 0 10px; font-size: 17px; font-weight: 600; letter-spacing: -.01em; text-align: left; color: #1d1d1f; }
    .ssf-page .ssf-items .detail-table-scroll { border: 0 !important; border-radius: 18px !important; background: #fff; box-shadow: 0 1px 1px rgba(0, 0, 0, .02), 0 4px 18px rgba(0, 0, 0, .05); scrollbar-color: #c7c7cc transparent; }
    .ssf-page .ssf-items .detail-table-scroll::-webkit-scrollbar-thumb { background: #c7c7cc; }
    .ssf-page .ssf-items .detail-table-scroll::-webkit-scrollbar-track { background: transparent; }
    .ssf-page .ssf-items table { font-variant-numeric: tabular-nums; }
    .ssf-page .ssf-items thead tr { background: #fafafc !important; color: #86868b !important; }
    .ssf-page .ssf-items thead th { padding: 10px 10px !important; border-bottom: 1px solid rgba(60, 60, 67, .12) !important; font-size: 11px !important; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; white-space: nowrap; }
    .ssf-page .ssf-items tbody { font-weight: 500 !important; }
    .ssf-page .ssf-items tbody td { padding: 8px 10px !important; border-color: rgba(60, 60, 67, .08) !important; vertical-align: middle; }
    .ssf-page .ssf-items tbody tr + tr { border-top: 1px solid rgba(60, 60, 67, .08); }
    .ssf-page .ssf-items tbody input { height: 32px; min-width: 72px; max-width: 110px; padding: 0 8px !important; border: 1px solid rgba(60, 60, 67, .16) !important; border-radius: 8px !important; background: rgba(118, 118, 128, .06); font-size: 14px; font-variant-numeric: tabular-nums; text-align: right; box-shadow: none !important; }
    .ssf-page .ssf-items tbody input:focus { outline: none; border-color: #0071e3 !important; background: #fff; box-shadow: 0 0 0 3px rgba(0, 113, 227, .18) !important; }
    .ssf-page .ssf-items tfoot tr { background: #fafafc !important; border-color: rgba(60, 60, 67, .12) !important; }
    .ssf-page .ssf-items tfoot td { padding: 9px 10px !important; white-space: nowrap; }
    /* Keep the product name in view while the numbers scroll sideways */
    .ssf-page .ssf-items tbody td:first-child, .ssf-page .ssf-items thead th:first-child { position: sticky; left: 0; z-index: 1; background: #fff; box-shadow: 1px 0 0 rgba(60, 60, 67, .08); }
    .ssf-page .ssf-items thead th:first-child { background: #fafafc; }
    /* A "#" column on every list card (collections, cash, expenses); the Sales Summary is a statement, so it has none.
       The number is drawn by CSS, so the rows the scripts build need no change. An empty-state row is not counted. */
    .ssf-page .ssf-card:not(:has([data-help-section="summary"])) .ssf-mini tbody { counter-reset: ssf-line; }
    .ssf-page .ssf-card:not(:has([data-help-section="summary"])) .ssf-mini tr::before { display: table-cell; width: 34px; padding: 7px 0 7px 12px; color: #8e8e93; font-size: 12px; font-weight: 500; font-variant-numeric: tabular-nums; vertical-align: middle; content: ""; }
    .ssf-page .ssf-card:not(:has([data-help-section="summary"])) .ssf-mini thead tr::before { content: "#"; padding-top: 8px; padding-bottom: 8px; font-size: 11px; font-weight: 600; background: #fafafc; border-bottom: 1px solid rgba(60, 60, 67, .1); }
    .ssf-page .ssf-card:not(:has([data-help-section="summary"])) .ssf-mini tbody tr:not(:has(td[colspan])) { counter-increment: ssf-line; }
    .ssf-page .ssf-card:not(:has([data-help-section="summary"])) .ssf-mini tbody tr:not(:has(td[colspan]))::before { content: counter(ssf-line); }
    .ssf-page .ssf-card:not(:has([data-help-section="summary"])) .ssf-mini tfoot tr::before { background: #fafafc; }

    /* B/F (In) and B/F (Out) stay in the page (a hidden field and the scripts' totals live there) but are not shown */
    .ssf-page .ssf-items .ss-hide-col { display: none !important; }
    .ssf-page #grandTotalBFIn, .ssf-page #grandTotalBalance, .ssf-page #grandTotalBFInValue, .ssf-page #grandTotalBalanceValue, .ssf-page [id^="bf-in-"], .ssf-page [id^="bf-out-"] { display: none !important; }
    .ssf-page .ssf-items tbody { counter-reset: ss-row; }
    .ssf-page .ssf-items tbody tr { counter-increment: ss-row; }
    .ssf-page .ssf-items td.ss-no::before { content: counter(ss-row); }
    .ssf-page .ssf-items .ss-no { width: 36px; padding-left: 14px !important; padding-right: 4px !important; color: #8e8e93; font-size: 12.5px; font-weight: 500; font-variant-numeric: tabular-nums; }
    .ssf-page .ssf-items tbody td.ss-no, .ssf-page .ssf-items thead th.ss-no { position: static; box-shadow: none; }
    .ssf-page .ssf-items tbody td.ss-no + td, .ssf-page .ssf-items thead th.ss-no + th { position: sticky; left: 0; z-index: 1; background: #fff; box-shadow: 1px 0 0 rgba(60, 60, 67, .08); }
    .ssf-page .ssf-items thead th.ss-no + th { background: #fafafc; }
    .ssf-page #settlementHelpText { margin: 8px 2px 0; padding: 0 !important; color: #6e6e73 !important; font-size: 13px; }

    /* Notes and the action bar */
    .ssf-page .ssf-notes { padding: 16px 18px; }
    .ssf-page .ssf-notes textarea { border-radius: 10px; border-color: rgba(60, 60, 67, .16); }
    .ssf-page .ssf-actions { position: sticky; bottom: 12px; z-index: 20; display: flex; justify-content: flex-end; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 16px; background: rgba(255, 255, 255, .78); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); box-shadow: 0 0 0 1px rgba(0, 0, 0, .04), 0 8px 28px rgba(0, 0, 0, .1); }
    .ssf-page .ssf-actions-figure { display: flex; flex-direction: column; margin-right: auto; padding-left: 6px; line-height: 1.2; }
    .ssf-page .ssf-actions-figure span { font-size: 11px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; color: #86868b; }
    .ssf-page .ssf-actions-figure b { font-size: 17px; font-weight: 600; font-variant-numeric: tabular-nums; color: #1d1d1f; }
    .ssf-page .ssf-actions .ak-btn { min-height: 40px; padding: 0 18px; transition: transform 160ms var(--ssf-press), background-color 160ms ease; }
    .ssf-page .ssf-actions .ak-btn:active:not(:disabled) { transform: scale(.97); }
    .ssf-page .ssf-actions .ak-btn:disabled { opacity: .6; cursor: progress; }

    /* Special-edit warning */
    .ssf-page .bg-amber-50 { border-radius: 14px; }

    /* Entry modals (credit sales, recoveries, cheques, transfers, slips, advance tax, % expense, AMR) */
    .ssf-page .fixed.inset-0.z-50 > .fixed.inset-0[class*="bg-gray-900"] { background: rgba(0, 0, 0, .32); -webkit-backdrop-filter: none; backdrop-filter: none; }
    .ssf-page .fixed.inset-0.z-50 .relative.w-full.bg-white { border-radius: 20px; box-shadow: 0 24px 64px rgba(0, 0, 0, .22); font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", system-ui, "Segoe UI", Roboto, sans-serif; }
    .ssf-page .fixed.inset-0.z-50 [class*="from-green-600"][class*="to-green-700"] { background: #fff !important; border-bottom: 1px solid rgba(60, 60, 67, .1) !important; padding: 18px 18px 14px 22px; }
    .ssf-page .fixed.inset-0.z-50 [class*="from-green-600"] h3 { color: #1d1d1f !important; font-size: 19px; font-weight: 600; letter-spacing: -.02em; }
    .ssf-page .fixed.inset-0.z-50 [class*="from-green-600"] p { color: #6e6e73 !important; font-size: 13px; }
    .ssf-page .fixed.inset-0.z-50 [class*="from-green-600"] > button { display: inline-grid; place-items: center; width: 30px; height: 30px; padding: 0; border-radius: 50%; background: #e8e8ed; color: #1d1d1f !important; }
    .ssf-page .fixed.inset-0.z-50 [class*="from-green-600"] > button svg { width: 14px; height: 14px; }
    .ssf-page .fixed.inset-0.z-50 [class*="from-green-500"][class*="to-green-600"] { background: #fafafc !important; border-color: rgba(60, 60, 67, .1) !important; }
    .ssf-page .fixed.inset-0.z-50 [class*="from-green-500"] h4 { color: #1d1d1f !important; }
    .ssf-page .fixed.inset-0.z-50 [class*="from-green-500"] span[class*="bg-white"], .ssf-page .fixed.inset-0.z-50 [class*="from-green-500"] span[class*="bg-green"] { background: rgba(0, 113, 227, .1) !important; color: #0071e3 !important; }
    .ssf-page .fixed.inset-0.z-50 .bg-gray-50.flex.justify-end { background: #fff !important; border-color: rgba(60, 60, 67, .1) !important; }
    .ssf-page .fixed.inset-0.z-50 button.bg-green-600 { background: #0071e3 !important; border-radius: 980px !important; box-shadow: none; transition: transform 160ms var(--ssf-press), background-color 160ms ease; }
    .ssf-page .fixed.inset-0.z-50 button.bg-white.border { border-radius: 980px !important; border-color: transparent !important; background: rgba(118, 118, 128, .12) !important; color: #1d1d1f !important; }
    .ssf-page .fixed.inset-0.z-50 button:active { transform: scale(.97); }
    @media (hover: hover) and (pointer: fine) { .ssf-page .fixed.inset-0.z-50 button.bg-green-600:hover { background: #0077ed !important; } }
    .ssf-page .fixed.inset-0.z-50 input:not([type="checkbox"]):not([type="radio"]):not([type="file"]), .ssf-page .fixed.inset-0.z-50 select, .ssf-page .fixed.inset-0.z-50 textarea { border-radius: 10px; border-color: rgba(60, 60, 67, .16); }

    /* Tables inside the entry modals: always the full width of the modal, one line per figure */
    .ssf-page .fixed.inset-0.z-50 table { width: 100% !important; min-width: 100% !important; font-variant-numeric: tabular-nums; }
    .ssf-page .fixed.inset-0.z-50 thead th { background: #fafafc !important; color: #86868b !important; font-size: 11px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; border-color: rgba(60, 60, 67, .1) !important; white-space: nowrap; }
    .ssf-page .fixed.inset-0.z-50 tbody td { border-color: rgba(60, 60, 67, .08) !important; }
    .ssf-page .fixed.inset-0.z-50 td.text-right, .ssf-page .fixed.inset-0.z-50 th.text-right, .ssf-page .fixed.inset-0.z-50 td[class*="font-mono"] { white-space: nowrap; }
    .ssf-page .fixed.inset-0.z-50 tfoot tr, .ssf-page .fixed.inset-0.z-50 tr[class*="bg-green-50"], .ssf-page .fixed.inset-0.z-50 tr[class*="bg-emerald-50"] { background: rgba(52, 199, 89, .08) !important; border-color: rgba(52, 199, 89, .25) !important; }
    .ssf-page .fixed.inset-0.z-50 .overflow-x-auto.border { border-color: rgba(60, 60, 67, .12) !important; border-radius: 12px !important; }

    @media (max-width: 1023px) {
        .ssf-page .ssf-mini input[type="number"] { width: 72px !important; }
    }
    @media (max-width: 640px) {
        .ssf-page .ssf-start-body { padding: 14px; }
        .ssf-page .ssf-card-head { padding: 10px 10px 8px 14px; }
        .ssf-page .ssf-section-title { font-size: 19px; }
        .ssf-page .ssf-mini th, .ssf-page .ssf-mini td { padding-left: 10px !important; padding-right: 10px !important; }
        .ssf-page .ssf-actions { bottom: 0; margin: 0 -16px; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); border-radius: 0; }
        .ssf-page .ssf-actions { flex-wrap: wrap; }
        .ssf-page .ssf-actions-figure { flex-basis: 100%; flex-direction: row; justify-content: space-between; align-items: baseline; padding: 0 2px 2px; }
        .ssf-page .ssf-actions > .ak-btn { flex: 1; justify-content: center; }
        /* 16px stops iOS from zooming into a field */
        .ssf-page input, .ssf-page select, .ssf-page textarea { font-size: 16px !important; }
    }
    @media (prefers-reduced-motion: reduce) {
        .ss-help-q, .ssf-page .ssf-add, .ssf-page .ssf-actions .ak-btn { transition: none; }
    }
    @media (prefers-reduced-transparency: reduce) {
        .ssf-page .ssf-actions { background: #fff; -webkit-backdrop-filter: none; backdrop-filter: none; }
    }
</style>
