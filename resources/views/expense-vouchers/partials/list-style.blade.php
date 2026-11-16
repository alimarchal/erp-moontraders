{{-- Expense Vouchers list: the Goods Issues filter panel, stage capsules and table rules, sized so the six
     columns fit the card from 1024px up; below that every row is a card. --}}
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
    @media (min-width: 1280px) { .gl-filter-row { grid-template-columns: minmax(220px, 2fr) 160px 160px minmax(170px, 1.3fr) 90px auto; } }
    @media (min-width: 900px) and (max-width: 1279px) { .gl-filter-row { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr); } }
    .gl-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
    .gl-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
    @media (min-width: 900px) { .gl-advanced { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    #ev-filter-panel { overflow: visible; }
    .ak-filters .select2-container { width: 100% !important; display: block; }
    .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
    .select2-search--dropdown .select2-search__field { border-radius: 6px; padding: 6px 8px; }

    /* stage capsule, as on Goods Issues */
    .gl-stage { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .gl-stage::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .gl-stage-green { background: rgba(52,199,89,.14); color: #1f7a35; }
    .gl-stage-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
    .gl-stage-red { background: rgba(255,59,48,.12); color: #c4161c; }
    .gl-stage-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
    .gl-tab-warn:not(.is-active) .ak-count { background: #fef3c7; color: #b45309; }
    section[aria-label="Expense vouchers"] > .ak-tabs { position: sticky; top: 0; z-index: 25; background: #fff; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }

    /* table: fits the card, long names are cut with … and shown in full on hover */
    .ak-dt-scroll { max-height: calc(100vh - 190px); min-height: 18rem; }
    .ev-table { min-width: 0 !important; width: 100%; table-layout: fixed; }
    .ev-c-no { width: 44px; } .ev-c-voucher { width: 196px; } .ev-c-amount { width: 116px; } .ev-c-status { width: 160px; } .ev-c-act { width: 124px; }
    .ev-table td { overflow: hidden; }
    .ev-clamp { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ev-acc { display: flex; align-items: baseline; gap: 6px; min-width: 0; }
    .ev-more { flex: none; padding: 1px 7px; border-radius: 980px; background: rgba(0,113,227,.1); color: #0066cc; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .ev-num { font-weight: 600; letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: calc(100% - 22px); display: inline-block; vertical-align: bottom; padding: 4px 0; }
    .ev-people { margin-top: 3px; font-size: 12px; }
    .ev-vline { display: flex; align-items: center; gap: 4px; min-width: 0; white-space: nowrap; }
    .ev-vline .ev-num { max-width: none; flex: 0 1 auto; min-width: 0; }
    .ev-meta { white-space: nowrap; }
    .ev-table td.ev-no { color: #8e8e93; font-size: 12.5px; font-variant-numeric: tabular-nums; }
    .ev-pending > td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
    .ev-table > tbody > tr:hover > td { background: #f1f5f9; }
    .ev-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
    .ak-dt-scroll.is-compact .ev-table td { padding-top: 6px; padding-bottom: 6px; vertical-align: middle; }
    .ak-dt-scroll.is-compact .ev-table td .ak-muted { font-size: 12px; }
    html.apl-on .ev-table .ak-muted, html.apl-on .ak-tab .ak-count { color: #6e6e73 !important; }
    .gl-act { display: flex; align-items: center; justify-content: flex-end; gap: 4px; white-space: nowrap; }
    .gl-view { gap: 6px; }
    .gl-more { width: 32px; height: 32px; }
    .gl-more svg { width: 18px; height: 18px; }

    /* Phones and tablets (below 1024px): each voucher is a card, label left and value right */
    @media screen and (max-width: 1023px) {
        .ev-table { table-layout: auto; }
        .ev-table thead { display: none; }
        .ev-table, .ev-table tbody, .ev-table tr, .ev-table td, .ev-table tfoot { display: block; width: 100%; }
        .ev-table tbody tr { margin: 12px; width: auto; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); overflow: hidden; }
        .ev-table tbody > tr > td { display: grid; grid-template-columns: auto minmax(0, 1fr); column-gap: 12px; align-items: start; padding: 8px 12px !important; width: 100% !important; text-align: right; white-space: normal; }
        .ev-table tbody > tr > td::before { content: attr(data-label); grid-row: 1 / span 3; font-size: 12px; font-weight: 700; color: var(--ak-muted); text-align: left; }
        .ev-table tbody > tr > td > * { grid-column: 2; justify-self: end; max-width: 100%; }
        .ev-table td.ev-no, .ev-table td[data-label=""]::before { display: none !important; }
        .ev-table td.ak-sticky-end { display: flex !important; justify-content: flex-end; position: static; box-shadow: none; }
        .ev-table tfoot tr { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; }
        .ev-table tfoot td { position: static; width: auto !important; }
        .ev-table tfoot td:empty { display: none; }
        .ev-clamp { white-space: normal; overflow-wrap: anywhere; }
        .ev-num { max-width: none; }
    }
    @media print {
        .gl-ranges, .ak-filters, .ak-tabs { display: none !important; }
        .ev-table tfoot td { position: static; }
    }
</style>
