{{-- Cheque Register: the Goods Issues filter panel, stage capsules and table rules (the table fits its card from
     1024px up, every row is a card below that), plus the status, bulk and history sheets. --}}
<style>
    html.apl-on .rg-gi-filters { border: 0 !important; border-radius: 20px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08) !important; }
    html.apl-on .rg-gi-filters .ak-field label { font-size: 13px; font-weight: 500; color: #6e6e73; text-transform: none; letter-spacing: 0; }
    html.apl-on .apl-fields input[type=number] { height: 40px; border: 0 !important; border-radius: 11px !important; background-color: rgba(118,118,128,.12) !important; box-shadow: none !important; font-size: 15px; color: #1d1d1f; }
    html.apl-on .apl-fields input[type=number]:focus { outline: none; background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
    html.apl-on .rg-gi-filters .ak-btn { height: 38px; border-radius: 980px !important; text-transform: none; transition: transform .1s ease-out, background-color .15s ease; }
    html.apl-on .rg-gi-filters .ak-btn:active { transform: scale(.97); }
    html.apl-on .rg-gi-filters .ak-btn-primary { background: #0071e3 !important; border-color: #0071e3 !important; }
    html.apl-on .rg-gi-filters .ak-btn-ghost { background: rgba(120,120,128,.14) !important; border: 0 !important; color: #1d1d1f !important; }
    html.apl-on .rg-gi-filters .gl-ranges a { border: 0 !important; background: rgba(118,118,128,.12); color: #1d1d1f; font-weight: 500; font-size: 13px; padding: 5px 12px; transition: background-color .15s ease, transform .1s ease-out; }
    html.apl-on .rg-gi-filters .gl-ranges a:hover { background: rgba(118,118,128,.22); }
    html.apl-on .rg-gi-filters .gl-ranges a:active { transform: scale(.96); }
    html.apl-on .gl-ranges a[aria-current] { background: #0071e3 !important; border-color: #0071e3 !important; color: #fff !important; }
    html.apl-on .rg-gi-filters .gl-ranges .gl-clear { background: rgba(255,59,48,.1); color: #d70015; }
    html.apl-on .rg-gi-filters .gl-ranges-label { font-size: 13px; font-weight: 500; color: #6e6e73; }
    .gl-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
    .gl-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
    .gl-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
    .gl-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
    @media (min-width: 1280px) { .gl-filter-row { grid-template-columns: minmax(200px, 1.6fr) minmax(170px, 1.2fr) 150px 150px 90px auto; } }
    @media (min-width: 900px) and (max-width: 1279px) { .gl-filter-row { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr); } }
    .gl-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
    .gl-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
    @media (min-width: 640px) { .gl-advanced { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .gl-advanced { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    #cr-filter-panel { overflow: visible; }
    .ak-filters .select2-container { width: 100% !important; display: block; }
    .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
    .select2-search--dropdown .select2-search__field { border-radius: 6px; padding: 6px 8px; }
    .cr-required { color: #d70015; }

    /* stage capsule, as on Goods Issues */
    .gl-stage { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .gl-stage::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .gl-stage-green { background: rgba(52,199,89,.14); color: #1f7a35; }
    .gl-stage-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
    .gl-stage-red { background: rgba(255,59,48,.12); color: #c4161c; }
    .gl-stage-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
    .gl-tab-warn:not(.is-active) .ak-count { background: #fef3c7; color: #b45309; }
    section[aria-label="Cheques"] > .ak-tabs { position: sticky; top: 0; z-index: 25; background: #fff; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }

    /* table: fits the card, long names are cut with … and shown in full on hover */
    .ak-dt-scroll { max-height: calc(100vh - 190px); min-height: 18rem; }
    .cr-table { min-width: 0 !important; width: 100%; table-layout: fixed; }
    .cr-c-no { width: 44px; } .cr-c-check { width: 40px; } .cr-c-cheque { width: 158px; } .cr-c-company { width: 104px; } .cr-c-bank { width: 138px; }
    .cr-c-amount { width: 112px; } .cr-c-status { width: 164px; } .cr-c-act { width: 104px; }
    .cr-table td { overflow: hidden; }
    .cr-clamp { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cr-line { display: flex; align-items: center; gap: 4px; min-width: 0; white-space: nowrap; }
    .cr-num { font-weight: 600; letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; padding: 4px 0; font-variant-numeric: tabular-nums; }
    .cr-table td.cr-no { color: #8e8e93; font-size: 12.5px; font-variant-numeric: tabular-nums; }
    .cr-table td.cr-check, .cr-table th.cr-c-check { padding-left: 12px !important; padding-right: 0 !important; }
    .cr-check input { width: 18px; height: 18px; border-radius: 6px; border: 1.5px solid #8e8e93; color: #0071e3; cursor: pointer; }
    .cr-check input:focus { box-shadow: 0 0 0 3px rgba(0,113,227,.25); outline: none; }
    .cr-sub { display: block; margin-top: 3px; font-size: 11.5px; color: #6e6e73; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cr-sub-warn { color: #8a4b00; }
    .cr-pending > td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
    .cr-table > tbody > tr:hover > td { background: #f1f5f9; }
    .cr-table > tbody > tr.is-picked > td { background: rgba(0,113,227,.07); }
    .cr-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
    .ak-dt-scroll.is-compact .cr-table td { padding-top: 6px; padding-bottom: 6px; vertical-align: middle; }
    .ak-dt-scroll.is-compact .cr-table td .ak-muted, .ak-dt-scroll.is-compact .cr-sub { font-size: 12px; }
    .ak-dt-scroll.is-compact .cr-hide-compact { display: none; }
    html.apl-on .cr-table .ak-muted, html.apl-on .ak-tab .ak-count { color: #6e6e73 !important; }
    .gl-act { display: flex; align-items: center; justify-content: flex-end; gap: 4px; white-space: nowrap; }
    .cr-hist { display: inline-flex; flex: none; width: 24px; height: 24px; align-items: center; justify-content: center; border: 0; border-radius: 980px; background: transparent; color: #8e8e93; cursor: pointer; transition: background-color .15s ease, color .15s ease; }
    .cr-hist:hover { background: rgba(0,113,227,.1); color: #0071e3; }
    .cr-hist:focus-visible { outline: 2px solid #0071e3; outline-offset: 1px; }
    .cr-hist svg { width: 15px; height: 15px; }
    .ak-empty .cr-empty-note { max-width: 420px; margin: 0 auto; }

    /* Laptops (1024–1279px): narrower columns so the customer keeps room; long text is cut with … */
    @media screen and (min-width: 1024px) and (max-width: 1279px) {
        .cr-c-no { width: 36px; } .cr-c-cheque { width: 128px; } .cr-c-company { width: 80px; } .cr-c-bank { width: 108px; }
        .cr-c-amount { width: 104px; } .cr-c-status { width: 142px; } .cr-c-act { width: 86px; }
        .cr-table .cr-hide-compact { display: none; }
    }
    /* Phones and tablets (below 1024px): each cheque is a card, label left and value right */
    @media screen and (max-width: 1023px) {
        .cr-table { table-layout: auto; }
        .cr-table thead { display: none; }
        .cr-table, .cr-table tbody, .cr-table tr, .cr-table td, .cr-table tfoot { display: block; width: 100%; }
        .cr-table tbody tr { margin: 12px; width: auto; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); overflow: hidden; }
        .cr-table tbody > tr > td { display: grid; grid-template-columns: auto minmax(0, 1fr); column-gap: 12px; row-gap: 2px; align-items: start; align-content: start; padding: 8px 12px !important; width: 100% !important; height: auto; text-align: right; white-space: normal; }
        .cr-table tbody > tr > td::before { content: attr(data-label); grid-row: 1; grid-column: 1; font-size: 12px; font-weight: 700; color: var(--ak-muted); text-align: left; }
        .cr-table td.cr-check-none { display: none !important; }
        .cr-table td.cr-check { align-items: center; }
        .cr-table tbody > tr > td > * { grid-column: 2; justify-self: end; max-width: 100%; }
        .cr-table td.cr-no, .cr-table td[data-label=""]::before { display: none !important; }
        .cr-table td.ak-sticky-end { display: flex !important; justify-content: flex-end; position: static; box-shadow: none; }
        .cr-table tfoot tr { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; }
        .cr-table tfoot td { position: static; width: auto !important; }
        .cr-table tfoot td:empty { display: none; }
        .cr-clamp, .cr-sub { white-space: normal; overflow-wrap: anywhere; }
        .cr-line { justify-content: flex-end; }
    }
    @media print {
        .gl-ranges, .ak-filters, .ak-tabs, .cr-bar { display: none !important; }
        .cr-table tfoot td { position: static; }
    }

    /* Selected cheques bar */
    .cr-bar { position: fixed; left: 50%; bottom: 20px; z-index: 40; transform: translateX(-50%); display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px 14px; padding: 10px 10px 10px 20px; border-radius: 28px;
        background: rgba(29,29,31,.86); color: #fff; -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); box-shadow: 0 10px 40px rgba(0,0,0,.25); font-size: 14px; width: max-content; max-width: calc(100vw - 32px); }
    .cr-bar b { font-variant-numeric: tabular-nums; }
    .cr-bar-link { color: rgba(255,255,255,.75); background: none; border: 0; font-size: 13px; cursor: pointer; padding: 0 6px; }
    .cr-bar-link:hover { color: #fff; }

    /* Sheets (status, bulk and history windows) */
    .crx-scrim { background: rgba(0,0,0,.28) !important; -webkit-backdrop-filter: blur(8px) saturate(120%); backdrop-filter: blur(8px) saturate(120%); }
    .crx-sheet { border-radius: 22px !important; box-shadow: 0 0 0 .5px rgba(0,0,0,.1), 0 30px 80px rgba(0,0,0,.25) !important; }
    .crx-sheet-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 18px 14px 24px; border-bottom: 1px solid rgba(60,60,67,.14); background: rgba(255,255,255,.92); color: #1d1d1f; }
    .crx-sheet-head h3 { margin: 0; font-size: 19px; font-weight: 600; letter-spacing: -.02em; color: #1d1d1f; }
    .crx-sheet-head p { margin: 2px 0 0; font-size: 13px; color: #6e6e73; }
    .crx-tag { display: inline-flex; margin-inline-end: 6px; padding: 1px 8px; border-radius: 980px; background: rgba(52,199,89,.14); color: #1f7a35; font-size: 11px; font-weight: 600; vertical-align: 2px; }
    .crx-close { display: inline-flex; width: 30px; height: 30px; flex: none; align-items: center; justify-content: center; border: 0; border-radius: 980px; background: rgba(120,120,128,.14); color: #3a3a40; cursor: pointer; transition: transform .12s ease; }
    .crx-close:active { transform: scale(.94); }
    .crx-label { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 500; color: #6e6e73; }
    .crx-seg { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 2px; padding: 3px; border-radius: 12px; background: rgba(118,118,128,.12); }
    .crx-seg-option { display: block; padding: 8px 6px; border-radius: 9px; text-align: center; font-size: 14px; font-weight: 500; color: #1d1d1f; cursor: pointer; transition: background-color .2s ease, box-shadow .2s ease; }
    .crx-seg-option:hover { background: rgba(255,255,255,.55); }
    .crx-seg-option.is-on { background: #fff; font-weight: 600; box-shadow: 0 3px 8px rgba(0,0,0,.12), 0 3px 1px rgba(0,0,0,.04); }
    .crx-seg-option.is-on.tone-cleared { color: #1f7a35; } .crx-seg-option.is-on.tone-bounced { color: #c4161c; } .crx-seg-option.is-on.tone-pending { color: #8a4b00; }
    .crx-sheet input[type="date"], .crx-sheet input[type="number"]:not(.cash-notes input), .crx-sheet input[type="text"], .crx-sheet select {
        height: 42px; width: 100%; padding: 0 12px; border: 0 !important; border-radius: 10px; background-color: rgba(118,118,128,.12); box-shadow: none !important; font-size: 16px; color: #1d1d1f; }
    .crx-sheet input:focus, .crx-sheet select:focus { outline: none; background-color: #fff; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
    .crx-readonly { display: flex; align-items: center; justify-content: space-between; height: 42px; padding: 0 14px; border-radius: 10px; background: rgba(118,118,128,.08); font-variant-numeric: tabular-nums; font-weight: 600; color: #1d1d1f; }
    .crx-readonly small { font-weight: 400; color: #8e8e93; font-size: 12px; }
    .crx-notes { padding: 12px 14px; border-radius: 14px; background: #f5f5f7; }
    .crx-help { margin-top: 4px; font-size: 12px; color: #6e6e73; }
    .crx-warn { margin-top: 8px; padding: 8px 12px; border-radius: 10px; background: rgba(255,59,48,.1); color: #c4161c; font-size: 13px; font-weight: 600; }
    .crx-info { margin-top: 8px; padding: 8px 12px; border-radius: 10px; background: rgba(255,159,10,.14); color: #8a4b00; font-size: 13px; }
    .crx-picked { max-height: 168px; overflow-y: auto; margin: 0; padding: 0; list-style: none; border-radius: 12px; border: 1px solid rgba(60,60,67,.12); }
    .crx-picked li { display: flex; gap: 10px; align-items: baseline; padding: 7px 12px; border-bottom: 1px solid rgba(60,60,67,.08); font-size: 13.5px; }
    .crx-picked li:last-child { border-bottom: 0; }
    .crx-picked .n { font-weight: 600; font-variant-numeric: tabular-nums; }
    .crx-picked .c { flex: 1; min-width: 0; color: #6e6e73; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .crx-picked .a { font-variant-numeric: tabular-nums; font-weight: 600; }
    .crx-btn { display: inline-flex; align-items: center; justify-content: center; height: 40px; padding: 0 20px; border: 0; border-radius: 980px; font-size: 15px; font-weight: 500; cursor: pointer; transition: background-color .2s ease, transform .12s ease; }
    .crx-btn:active { transform: scale(.97); }
    .crx-btn:disabled { opacity: .45; cursor: not-allowed; }
    .crx-btn-secondary { background: rgba(120,120,128,.14); color: #1d1d1f; }
    .crx-btn-primary { background: #0071e3; color: #fff; } .crx-btn-primary:hover { background: #0077ed; }
    .crx-btn-primary.is-cleared { background: #34c759; }
    .crx-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; border-top: 1px solid rgba(60,60,67,.14); background: #fbfbfd; }
    .crx-history th { font-size: 12px; font-weight: 500; color: #6e6e73; padding: 8px 10px; border-bottom: 1px solid rgba(60,60,67,.14); text-align: left; white-space: nowrap; }
    .crx-history td { padding: 10px; border-bottom: 1px solid rgba(60,60,67,.08); font-size: 13px; vertical-align: top; }
    .crx-history .muted { color: #6e6e73; font-size: 12px; }
    .crx-history a { color: #0066cc; }
    @media screen and (max-width: 639px) {
        .crx-history thead { display: none; }
        .crx-history, .crx-history tbody, .crx-history tr, .crx-history td { display: block; width: 100%; }
        .crx-history tr { padding: 8px 0; border-bottom: 1px solid rgba(60,60,67,.14); }
        .crx-history td { border: 0; padding: 3px 4px; }
        .crx-history td::before { content: attr(data-label) ' '; font-weight: 600; color: #6e6e73; font-size: 12px; }
    }
    @media (prefers-reduced-motion: reduce) { .crx-btn, .crx-close { transition: none; } .crx-btn:active { transform: none; } }
</style>
