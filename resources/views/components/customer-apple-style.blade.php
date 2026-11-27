{{-- Apple look shared by the customer screens: Settings › Customers (list and view), the Customer Account Statement
     (list and one account) and the Customer Balances report. Same building blocks as Goods Issues: a filter sheet opened
     from the header, sticky segmented tabs, a hairline table that turns into cards on a tablet or phone, and capsule
     links that read as links. Needs ui-style and <x-apple-skin /> (html.apl-on). --}}
@once
<style>
    /* Filter sheet opened from the header's filter button */
    html.apl-on .cs-filters { border: 0 !important; border-radius: 20px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08) !important; overflow: hidden; }
    html.apl-on .cs-filters .ak-field label { font-size: 13px; font-weight: 500; color: #6e6e73; text-transform: none; letter-spacing: 0; }
    html.apl-on .cs-filters .ak-btn { height: 40px; border-radius: 980px !important; text-transform: none; transition: transform .1s ease-out, background-color .15s ease; }
    html.apl-on .cs-filters .ak-btn:active { transform: scale(.97); }
    html.apl-on .cs-filters .ak-btn-primary { background: #0071e3 !important; border-color: #0071e3 !important; }
    html.apl-on .cs-filters .ak-btn-ghost { background: rgba(120,120,128,.14) !important; border: 0 !important; color: #1d1d1f !important; }
    html.apl-on .cs-filters input[type=text], html.apl-on .cs-filters input[type=number] { width: 100%; height: 40px; padding: 0 12px; border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; font-size: 15px; color: #1d1d1f; }
    html.apl-on .cs-filters input[type=text]:focus, html.apl-on .cs-filters input[type=number]:focus { outline: none; background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
    .cs-filter-row, .cs-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
    .cs-advanced { margin-top: 12px; }
    @media (min-width: 640px) { .cs-filter-row, .cs-advanced { grid-template-columns: repeat(2, minmax(0, 1fr)); } .cs-filter-row > .ak-field:first-child, .cs-filter-row > .ak-filter-buttons { grid-column: 1 / -1; } }
    @media (min-width: 1024px) {
        .cs-filter-row { grid-template-columns: minmax(240px, 2fr) repeat(var(--cs-cols, 3), minmax(140px, 1fr)) auto; }
        .cs-filter-row > .ak-field:first-child, .cs-filter-row > .ak-filter-buttons { grid-column: auto; }
        .cs-advanced { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    .cs-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
    .cs-filters .select2-container { width: 100% !important; display: block; }
    .cs-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 14px; }
    .cs-ranges > span { margin-right: 4px; font-size: 13px; color: #6e6e73; }
    .cs-ranges a { padding: 5px 12px; border-radius: 980px; background: rgba(118,118,128,.12); color: #1d1d1f; font-size: 13px; font-weight: 500; text-decoration: none; transition: background-color .15s ease, transform .1s ease-out; }
    .cs-ranges a:hover { background: rgba(118,118,128,.22); }
    .cs-ranges a:active { transform: scale(.96); }
    .cs-ranges a[aria-current] { background: #0071e3; color: #fff; }
    .cs-ranges a.cs-clear { background: rgba(255,59,48,.1); color: #d70015; }

    /* Tabs stay in view while the list scrolls */
    .cs-card > .ak-tabs { position: sticky; top: 0; z-index: 25; background: #fff; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }

    /* Table: one clear link per row, quiet second lines, numbers right-aligned and tabular */
    .cs-table td { vertical-align: top; }
    .cs-table .cs-no { width: 44px; color: #8e8e93; font-size: 12.5px; font-variant-numeric: tabular-nums; }
    .cs-table .ak-muted { color: #6e6e73 !important; }
    .cs-table .cs-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cs-table th.cs-num { text-align: right; }
    .cs-table tbody > tr:hover > td { background: #f5f5f7; }
    .cs-table tfoot td { font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); font-variant-numeric: tabular-nums; }
    .cs-name { display: inline-block; padding: 2px 0; color: #1d1d1f; font-weight: 600; letter-spacing: -.01em; text-decoration: none; }
    .cs-name:hover { color: #0066cc; text-decoration: underline; text-underline-offset: 3px; }
    .cs-name:focus-visible, .cs-link:focus-visible, .cs-amount-link:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; border-radius: 6px; }
    .cs-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
    /* A link that looks like one: blue, with an arrow, and a 32px hit area */
    .cs-link { display: inline-flex; align-items: center; gap: 4px; min-height: 28px; color: #0066cc; font-weight: 500; text-decoration: none; }
    .cs-link:hover { text-decoration: underline; text-underline-offset: 3px; }
    .cs-link svg { width: 14px; height: 14px; flex: none; }
    .cs-amount-link { color: inherit; font-weight: 600; text-decoration: underline; text-decoration-color: rgba(0,102,204,.35); text-underline-offset: 3px; }
    .cs-amount-link:hover { color: #0066cc; text-decoration-color: currentColor; }
    .cs-due { color: #c4161c; } .cs-advance { color: #1f7a35; }
    .cs-cap { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .cs-cap::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .cs-cap-green { background: rgba(52,199,89,.14); color: #1f7a35; }
    .cs-cap-red { background: rgba(255,59,48,.12); color: #c4161c; }
    .cs-cap-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
    .cs-cap-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
    .cs-cap-blue { background: rgba(0,113,227,.12); color: #0058b0; }

    /* Row actions: View, and the rest in a small menu */
    .cs-act { display: flex; align-items: center; justify-content: flex-end; gap: 4px; white-space: nowrap; }
    .cs-act .ak-btn { gap: 6px; }
    .cs-more { width: 32px; height: 32px; }
    .cs-more svg { width: 18px; height: 18px; }
    .cs-menu { position: fixed; z-index: 70; min-width: 190px; padding: 4px; background: #fff; border: 1px solid var(--ak-border); border-radius: 12px; box-shadow: 0 10px 25px rgba(15,23,42,.15); }
    .cs-menu a, .cs-menu button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 12px; border: 0; border-radius: 8px; background: transparent; color: var(--ak-text); font: inherit; font-size: 14px; text-align: left; text-decoration: none; cursor: pointer; }
    .cs-menu a:hover, .cs-menu button:hover { background: var(--ak-soft); }
    .cs-menu svg { width: 17px; height: 17px; flex: none; color: var(--ak-muted); }
    .cs-menu hr { margin: 4px 0; border: 0; border-top: 1px solid #e5e7eb; }
    .cs-menu .is-danger, .cs-menu .is-danger svg { color: #b91c1c; }
    .cs-menu .is-danger:hover { background: #fef2f2; }
    .cs-menu .is-ok, .cs-menu .is-ok svg { color: #15803d; }

    /* Compact rows hide the second line of each cell */
    .ak-dt-scroll.is-compact .cs-table td { padding-top: 7px; padding-bottom: 7px; vertical-align: middle; }
    .ak-dt-scroll.is-compact .cs-table .cs-sub { display: none; }

    /* Detail pages: a hero card, grouped facts and the linked accounts */
    .cs-hero { display: grid; gap: 16px; padding: 22px 24px; }
    .cs-hero-top { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .cs-hero h2 { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -.025em; line-height: 1.15; color: #1d1d1f; }
    .cs-hero p { margin: 0; font-size: 14.5px; color: #424245; }
    .cs-stats { display: grid; gap: 1px; grid-template-columns: repeat(2, minmax(0, 1fr)); overflow: hidden; border-radius: 14px; background: rgba(60,60,67,.1); }
    @media (min-width: 900px) { .cs-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .cs-stat { padding: 12px 16px; background: #f5f5f7; }
    .cs-stat span { display: block; font-size: 12.5px; color: #6e6e73; }
    .cs-stat b { display: block; margin-top: 2px; font-size: 20px; font-weight: 600; letter-spacing: -.02em; font-variant-numeric: tabular-nums; color: #1d1d1f; }
    .cs-stat small { display: block; margin-top: 2px; font-size: 12px; color: #6e6e73; }
    .cs-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1024px) { .cs-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .cs-section { padding: 18px 20px; }
    .cs-section h3 { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 8px; font-size: 15px; font-weight: 600; letter-spacing: -.01em; color: #1d1d1f; }
    .cs-facts { display: grid; grid-template-columns: minmax(110px, 40%) minmax(0, 1fr); margin: 0; }
    .cs-facts dt, .cs-facts dd { margin: 0; padding: 9px 0; border-bottom: 1px solid rgba(60,60,67,.1); font-size: 14px; }
    .cs-facts dt { color: #6e6e73; }
    .cs-facts dd { color: #1d1d1f; text-align: right; overflow-wrap: anywhere; }
    .cs-facts dt:nth-last-child(2), .cs-facts dd:last-child { border-bottom: 0; }
    .cs-accounts { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
    .cs-account { display: flex; flex-direction: column; gap: 4px; padding: 14px 16px; border-radius: 14px; background: #fff; box-shadow: 0 0 0 1px rgba(60,60,67,.12); color: #1d1d1f; text-decoration: none; transition: box-shadow .15s ease, transform .1s ease-out; }
    .cs-account:hover { box-shadow: 0 0 0 1px #0071e3, 0 6px 18px rgba(0,0,0,.06); }
    .cs-account:active { transform: scale(.99); }
    .cs-account:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; }
    .cs-account-top { display: flex; justify-content: space-between; gap: 10px; font-size: 12.5px; color: #6e6e73; }
    .cs-account b { font-size: 15px; font-weight: 600; }
    .cs-account-bal { font-size: 22px; font-weight: 600; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
    .cs-account-go { margin-top: 2px; color: #0066cc; font-size: 13.5px; font-weight: 500; }
    .cs-print-head { display: none; }

    /* Tablets (768–1023px): rows become cards, as on a phone (the phone cards come from ui-style) */
    @media screen and (min-width: 768px) and (max-width: 1023px) {
        .cs-table { min-width: 0 !important; }
        .cs-table thead { display: none; }
        .cs-table, .cs-table tbody, .cs-table tr, .cs-table td, .cs-table tfoot { display: block; width: 100%; }
        .cs-table tbody tr { margin: 12px; width: auto; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); overflow: hidden; }
        .cs-table td { display: flex; justify-content: space-between; gap: 12px; padding: 8px 12px; text-align: right; }
        .cs-table td::before { content: attr(data-label); font-size: 12px; font-weight: 700; color: var(--ak-muted); text-align: left; }
        .cs-table td[data-label=""]::before { display: none; }
        .cs-table .ak-sticky-end { position: static; box-shadow: none; width: auto; min-width: 0; }
        .cs-table tfoot tr { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; }
        .cs-table tfoot td { width: auto; }
        .cs-table tfoot td:empty { display: none; }
    }
    /* Phones and tablets: the row number steps aside, a cell's two lines stack on the right */
    @media screen and (max-width: 1023px) {
        .cs-table td.cs-no { display: none !important; }
        .cs-table td.cs-stack { display: grid !important; grid-template-columns: auto minmax(0, 1fr); column-gap: 12px; row-gap: 2px; align-items: start; align-content: start; }
        .cs-table td.cs-stack::before { grid-row: 1; }
        .cs-table td.cs-stack > * { grid-column: 2; justify-self: end; text-align: right; }
        .cs-table td.cs-num, .cs-table td.ak-sticky-end { display: flex !important; justify-content: space-between !important; align-items: center; }
        .cs-table td.ak-sticky-end { justify-content: flex-end !important; }
        .cs-table tfoot td { position: static; }
    }
    @media screen and (max-width: 640px) {
        .cs-hero { padding: 18px 16px; }
        .cs-hero h2 { font-size: 22px; }
        .cs-stat b { font-size: 17px; }
        .cs-section { padding: 16px; }
        .cs-facts { grid-template-columns: 1fr; }
        .cs-facts dt { padding-bottom: 0; border-bottom: 0; }
        .cs-facts dd { text-align: left; }
    }
    @media print {
        .cs-filters, .cs-card > .ak-tabs, .cs-act, .cs-ranges { display: none !important; }
        .cs-print-head { display: block; margin-bottom: 8px; text-align: center; font-weight: 700; }
        .cs-print-head small { display: block; font-weight: 400; font-size: 10px; }
        .cs-table tfoot td { position: static; }
        .cs-account { box-shadow: none; border: 1px solid #000; }
    }
    @media (prefers-reduced-motion: reduce) {
        .cs-ranges a, .cs-account, html.apl-on .cs-filters .ak-btn { transition: none; }
        .cs-ranges a:active, .cs-account:active, html.apl-on .cs-filters .ak-btn:active { transform: none; }
    }
</style>
<script>
    // The header's filter button opens the page's own filter sheet; type-to-search lists (Select2) in that sheet
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('toggle')?.addEventListener('click', function (event) { event.preventDefault(); window.dispatchEvent(new CustomEvent('cs-toggle-filters')); });
        if (! window.jQuery || ! jQuery.fn.select2) { return; }
        jQuery('.cs-select').each(function () {
            const $select = jQuery(this);
            $select.select2({ width: '100%', placeholder: $select.data('placeholder') || 'All', allowClear: $select.find('option[value=""]').length > 0 });
        });
    });
</script>
@endonce
