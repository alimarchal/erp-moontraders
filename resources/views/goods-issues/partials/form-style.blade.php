{{--
    Goods Issue create / edit / add items: the Apple look on top of settings.partials.ui-style and x-apple-skin.
    Only the page is styled here; every field, its name, id and the form's JavaScript are unchanged.
    Width: max-w-7xl. The products table fits the card on a laptop and becomes one card per product
    below 1024px (each cell carries a data-label), so nothing scrolls sideways.
--}}
<style>
    .gf-page { max-width: 80rem; margin: 0 auto; padding: 20px 16px 96px; display: flex; flex-direction: column; gap: 16px; }
    @media (min-width: 640px) { .gf-page { padding-left: 24px; padding-right: 24px; } }
    .gf-page, .gf-page * { min-width: 0; }
    .gf-card { margin: 0; }
    .gf-page > form { display: flex; flex-direction: column; gap: 16px; }
    html.apl-on .gf-table thead th { min-width: 0 !important; }
    html.apl-on .gf-table td select:not(.select2-hidden-accessible), html.apl-on .gf-table td .select2-container { width: 100% !important; max-width: 100% !important; }
    .gf-page select.select2-hidden-accessible { position: absolute !important; width: 1px !important; height: 1px !important; padding: 0 !important; border: 0 !important; overflow: hidden !important; clip: rect(0 0 0 0) !important; white-space: nowrap !important; }
    .gf-card .uf-card-head > div { min-width: 0; }
    .gf-head-stats { display: flex; flex-wrap: wrap; gap: 6px; }
    .gf-head-stats .ak-pill { margin: 0; }
    .gf-lede { margin: 0; font-size: 14px; color: #6e6e73; }
    .gf-lede .ak-status { vertical-align: middle; }

    /* Cards and step numbers */
    html.apl-on .gf-page .uf-card { border-radius: 20px; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.05), 0 8px 24px -8px rgba(0,0,0,.14); }
    html.apl-on .gf-page .uf-card-head { padding: 18px 22px 8px; display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px; }
    html.apl-on .gf-page .uf-card-title { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; letter-spacing: -.01em; color: #1d1d1f; }
    html.apl-on .gf-page .uf-step { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #0071e3; color: #fff; font-size: 12.5px; font-weight: 600; }
    html.apl-on .gf-page .uf-card-sub { margin: 4px 0 0 34px; font-size: 13px; color: #6e6e73; }
    html.apl-on .gf-page .uf-body { padding: 10px 22px 20px; }

    /* Fields: filled, rounded, blue focus ring */
    html.apl-on .gf-page label { font-size: 12.5px !important; font-weight: 500 !important; color: #6e6e73 !important; }
    html.apl-on .gf-page .uf-body input:not([type=checkbox]):not([type=radio]):not([type=hidden]),
    html.apl-on .gf-page .uf-body select:not(.select2-hidden-accessible),
    html.apl-on .gf-page .uf-body textarea {
        border: 0 !important; border-radius: 10px !important; background-color: rgba(118,118,128,.12) !important; box-shadow: none !important;
        font-size: 15px; color: #1d1d1f; min-height: 40px;
    }
    html.apl-on .gf-page .uf-body textarea { padding: 10px 12px; }
    html.apl-on .gf-page .uf-body input:focus, html.apl-on .gf-page .uf-body select:focus, html.apl-on .gf-page .uf-body textarea:focus {
        outline: none; background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important;
    }
    html.apl-on .gf-page .uf-body select:disabled { opacity: .55; cursor: not-allowed; }

    /* Products table: Apple header, compact filled inputs, fits the card on a laptop */
    .gf-table { padding: 0; }
    html.apl-on .gf-table .form-table-scroll { overflow-x: auto !important; max-height: none; border-radius: 0; }
    html.apl-on .gf-table table { width: 100%; min-width: 0 !important; border-collapse: separate; border-spacing: 0; }
    html.apl-on .gf-table thead tr { background: #fff !important; color: #6e6e73 !important; }
    html.apl-on .gf-table thead th { padding: 10px 6px !important; font-size: 11.5px !important; font-weight: 600 !important; letter-spacing: 0 !important; text-transform: none !important; line-height: 1.25; color: #6e6e73 !important; background: rgba(255,255,255,.92) !important; border-bottom: 1px solid rgba(60,60,67,.14) !important; }
    html.apl-on .gf-table tbody { background: #fff !important; }
    html.apl-on .gf-table tbody tr td { padding: 8px 6px !important; border-bottom: 1px solid rgba(60,60,67,.08); border-top: 0 !important; }
    html.apl-on .gf-table tbody tr:hover td { background: #fafafc; }
    html.apl-on .gf-table td input:not([type=checkbox]):not([type=hidden]), html.apl-on .gf-table td select:not(.select2-hidden-accessible) {
        height: 36px; border: 0 !important; border-radius: 9px !important; background-color: rgba(118,118,128,.12) !important; box-shadow: none !important; font-size: 14px; color: #1d1d1f;
    }
    html.apl-on .gf-table td input:focus, html.apl-on .gf-table td select:focus { outline: none; background-color: #fff !important; box-shadow: 0 0 0 3px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
    html.apl-on .gf-table td input.bg-red-50, html.apl-on .gf-table td select.bg-red-50 { background-color: rgba(255,59,48,.1) !important; color: #c4161c; }
    html.apl-on .gf-table td input.border-red-300 { background-color: rgba(255,59,48,.1) !important; color: #c4161c; }
    html.apl-on .gf-table td input.bg-gray-200, html.apl-on .gf-table td select.bg-gray-200 { background-color: rgba(118,118,128,.07) !important; color: #aeaeb2; }
    html.apl-on .gf-table td input.bg-gray-100 { background-color: rgba(118,118,128,.07) !important; }
    html.apl-on .gf-table td [id^="batch_info_"], html.apl-on .gf-table td [id^="price_breakdown_"] { max-width: none; font-size: 12px; line-height: 1.45; color: #6e6e73; }
    html.apl-on .gf-table td[data-label="Total value"] { font-variant-numeric: tabular-nums; color: #1d1d1f; }
    /* Line number, as on the issue page: small, grey, centred */
    html.apl-on .gf-table td.gf-no { text-align: center; font-size: 12.5px; font-weight: 500; color: #6e6e73; font-variant-numeric: tabular-nums; }
    html.apl-on .gf-table td[data-label=""] button { border-radius: 50% !important; }
    html.apl-on .gf-table td[data-label=""] button:hover { background: rgba(255,59,48,.12) !important; }
    html.apl-on .gf-table tfoot tr, html.apl-on .gf-table tfoot td { background: #fff !important; }
    html.apl-on .gf-table tfoot tr:first-child td { border-top: 1px solid rgba(60,60,67,.18) !important; padding: 12px 6px !important; color: #1d1d1f; font-variant-numeric: tabular-nums; }
    html.apl-on .gf-table tfoot tr:last-child td { padding: 12px 16px 16px !important; }
    html.apl-on .gf-table tfoot input[type=number] { height: 34px; border: 0 !important; border-radius: 9px !important; background: rgba(118,118,128,.12) !important; }
    html.apl-on .gf-table tfoot button.bg-blue-600 { height: 34px; padding: 0 14px 0 10px !important; border-radius: 980px !important; background: rgba(0,113,227,.1) !important; color: #0066cc !important; font-weight: 500; }
    html.apl-on .gf-table tfoot button.bg-blue-600:hover { background: rgba(0,113,227,.16) !important; }
    /* Laptop: give the product and the price breakdown the room, keep number fields narrow */
    @media (min-width: 1024px) {
        html.apl-on .gf-table td[data-label="SKU"] { width: 24% !important; }
        html.apl-on .gf-table td.gf-no { width: 36px; }
        html.apl-on .gf-table td[data-label="Non-promo only"] { width: 56px; }
        html.apl-on .gf-table td[data-label="Qty available"], html.apl-on .gf-table td[data-label="Carton"], html.apl-on .gf-table td[data-label="Pieces"], html.apl-on .gf-table td[data-label="Qty issued"] { width: 88px; }
        html.apl-on .gf-table td[data-label="UOM"] { width: 92px; }
        html.apl-on .gf-table td[data-label="Total value"] { width: 104px; }
        html.apl-on .gf-table td[data-label=""] { width: 44px; }
        html.apl-on .gf-table thead th { width: auto !important; }
    }

    /* Sticky bar: totals on the left, Cancel / Save on the right */
    .gf-actions { position: sticky; bottom: 12px; z-index: 20; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; margin-top: 4px; padding: 12px 16px; border-radius: 18px;
        background: rgba(255,255,255,.86); -webkit-backdrop-filter: saturate(180%) blur(16px); backdrop-filter: saturate(180%) blur(16px);
        box-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 10px 30px -10px rgba(0,0,0,.22); }
    .gf-totals { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 16px; font-size: 13.5px; color: #6e6e73; }
    .gf-totals b { color: #1d1d1f; font-weight: 600; font-variant-numeric: tabular-nums; }
    .gf-warn { color: #c4161c; font-weight: 600; }
    .gf-actions > div:last-child { display: flex; align-items: center; gap: 10px; margin-left: auto; }
    html.apl-on .gf-actions button[type=button].inline-flex, html.apl-on .gf-actions .gf-submit { height: 40px; padding: 0 20px; border: 0; border-radius: 980px; background: #0071e3 !important; color: #fff; font-size: 15px; font-weight: 500; letter-spacing: -.01em; text-transform: none; }
    html.apl-on .gf-actions button[type=button].inline-flex:hover { background: #0077ed !important; }
    html.apl-on .gf-actions .ak-btn { height: 40px; border-radius: 980px; }

    /* Read-only facts (Add items page) and banners */
    .gf-facts { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0; margin: 0; border-radius: 14px; background: #f5f5f7; }
    .gf-facts > div { padding: 12px 14px; }
    .gf-facts > div + div { box-shadow: inset 1px 0 0 rgba(60,60,67,.12); }
    .gf-facts dt { margin: 0; font-size: 12px; color: #6e6e73; }
    .gf-facts dd { margin: 2px 0 0; font-size: 14.5px; font-weight: 600; color: #1d1d1f; overflow-wrap: anywhere; }
    .gf-banner { display: flex; gap: 10px; padding: 13px 16px; border-radius: 16px; font-size: 14px; line-height: 1.45; color: #1d1d1f; box-shadow: 0 0 0 1px rgba(0,0,0,.03), 0 6px 18px -8px rgba(0,0,0,.12); }
    .gf-banner::before { content: ''; flex: none; width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; }
    .gf-banner-amber { background: #fff8eb; } .gf-banner-amber::before { background: #ff9f0a; }
    .gf-banner-blue { background: #eef5ff; } .gf-banner-blue::before { background: #0071e3; }
    .gf-banner a { color: #0066cc; font-weight: 600; }
    .gf-existing { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; }
    .gf-existing th { padding: 8px; font-size: 11.5px; font-weight: 600; color: #6e6e73; text-align: left; border-bottom: 1px solid rgba(60,60,67,.18); }
    .gf-existing td { padding: 9px 8px; border-bottom: 1px solid rgba(60,60,67,.08); color: #1d1d1f; overflow-wrap: anywhere; }
    .gf-existing .gf-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }

    /* Tablets and phones: one card per product, label on the left */
    @media screen and (max-width: 1023px) {
        html.apl-on .gf-table .form-table-scroll { overflow-x: visible !important; }
        html.apl-on .gf-table table, html.apl-on .gf-table tbody, html.apl-on .gf-table tr, html.apl-on .gf-table td, html.apl-on .gf-table tfoot { display: block; width: 100% !important; }
        html.apl-on .gf-table thead { display: none; }
        html.apl-on .gf-table tbody tr { margin: 12px 14px; width: auto !important; padding: 6px 0; border-radius: 16px; background: #fff; box-shadow: 0 0 0 1px rgba(60,60,67,.12); }
        html.apl-on .gf-table tbody tr:hover td { background: transparent; }
        html.apl-on .gf-table tbody tr td { display: grid; grid-template-columns: 120px minmax(0, 1fr); align-items: center; column-gap: 10px; row-gap: 0; padding: 6px 14px !important; border: 0; text-align: left !important; }
        html.apl-on .gf-table tbody tr td::before { content: attr(data-label); font-size: 12.5px; font-weight: 500; color: #6e6e73; }
        html.apl-on .gf-table tbody tr td::before { grid-column: 1; grid-row: 1 / span 6; align-self: start; padding-top: 10px; }
        html.apl-on .gf-table tbody tr td > * { grid-column: 2; }
        html.apl-on .gf-table td[data-label="SKU"] > * { grid-column: 1; }
        html.apl-on .gf-table td[data-label="Price breakdown"]::before { padding-top: 0; }
        html.apl-on .gf-table td[data-label="SKU"] { grid-template-columns: minmax(0, 1fr); padding-top: 4px !important; }
        html.apl-on .gf-table td.gf-no { display: block; padding: 8px 14px 0 !important; text-align: left !important; font-size: 12px; font-weight: 600; color: #0058b0; }
        html.apl-on .gf-table td.gf-no::before { content: 'Line #'; font-weight: 500; color: #6e6e73; }
        html.apl-on .gf-table td[data-label="SKU"]::before { display: none; }
        html.apl-on .gf-table td[data-label="Non-promo only"] input { justify-self: start; }
        html.apl-on .gf-table td[data-label="Total value"] { font-size: 16px; }
        html.apl-on .gf-table td[data-label=""] { display: flex; justify-content: flex-end; padding-bottom: 8px !important; }
        html.apl-on .gf-table td[data-label=""]::before { display: none; }
        html.apl-on .gf-table tfoot tr { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        html.apl-on .gf-table tfoot td { width: auto !important; }
        html.apl-on .gf-table tfoot td:empty { display: none; }
        html.apl-on .gf-table tfoot tr:first-child td[colspan] { text-align: left !important; }
        .gf-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .gf-facts > div:nth-child(odd) { box-shadow: none; }
        .gf-facts > div:nth-child(n+3) { border-top: 1px solid rgba(60,60,67,.12); }
    }
    @media screen and (max-width: 640px) {
        html.apl-on .gf-page .uf-card-head { padding: 16px 16px 6px; }
        html.apl-on .gf-page .uf-body { padding: 8px 16px 16px; }
        html.apl-on .gf-page .uf-card-sub { margin-left: 0; }
        html.apl-on .gf-table tbody tr { margin: 10px; }
        html.apl-on .gf-table tbody tr td { grid-template-columns: 104px minmax(0, 1fr); padding: 6px 12px !important; }
        .gf-actions { bottom: 8px; padding: 10px 12px; }
        .gf-actions > div:last-child { width: 100%; }
        .gf-actions > div:last-child > * { flex: 1 1 0; justify-content: center; }
        .gf-existing thead { display: none; }
        .gf-existing, .gf-existing tbody, .gf-existing tr, .gf-existing td { display: block; width: 100%; }
        .gf-existing tr { padding: 8px 0; border-bottom: 1px solid rgba(60,60,67,.12); }
        .gf-existing td { display: flex; justify-content: space-between; gap: 10px; padding: 2px 0; border: 0; }
        .gf-existing td::before { content: attr(data-label); flex: none; font-size: 12px; color: #6e6e73; }
        .gf-existing td { text-align: right; }
        .gf-existing td.gf-num { text-align: right; }
    }
    /* Select2: the same fields and menu as the Claim Register filters (chevron, round clear, blurred pop-up list) */
        html.apl-on .gf-page .select2-container { margin-top: 4px; }
        html.apl-on .gf-page .select2-container--default .select2-selection--single,
        html.apl-on .gf-page .select2-container--default .select2-selection--multiple {
            min-height: 40px; height: auto; padding: 0 !important; border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; transition: box-shadow .15s ease, background-color .15s ease; }
        html.apl-on .gf-page .select2-container--default .select2-selection--single { height: 40px !important; }
        html.apl-on .gf-page .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px !important; padding-left: 12px !important; padding-right: 52px !important; font-size: 15px; color: #1d1d1f; }
        html.apl-on .gf-page .select2-container--default .select2-selection--single .select2-selection__placeholder,
        html.apl-on .gf-page .select2-container--default .select2-search--inline .select2-search__field::placeholder { color: #8e8e93 !important; }
        /* chevron like every other field, and a small round clear button just before it */
        html.apl-on .gf-page .select2-container--default .select2-selection--single .select2-selection__arrow { top: 0 !important; right: 10px !important; width: 14px !important; height: 40px !important;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat center / 12px 12px;
            transition: transform .2s cubic-bezier(.23,1,.32,1); }
        html.apl-on .gf-page .select2-container--default .select2-selection--single .select2-selection__arrow b { display: none !important; }
        html.apl-on .gf-page .select2-container--open .select2-selection--single .select2-selection__arrow { transform: rotate(180deg); }
        html.apl-on .gf-page .select2-container--default .select2-selection__clear {
            position: absolute !important; top: 50% !important; right: 32px !important; float: none !important; transform: translateY(-50%); z-index: 2;
            display: grid !important; place-items: center; width: 18px; height: 18px; margin: 0 !important; padding: 0 !important; border: 0 !important; border-radius: 50%;
            background: rgba(118,118,128,.38) !important; color: #fff !important; font-size: 0 !important; line-height: 0 !important; cursor: pointer; transition: background-color .15s ease, transform .1s ease-out; }
        html.apl-on .gf-page .select2-container--default .select2-selection__clear::before {
            content: ''; width: 8px; height: 8px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='4' stroke-linecap='round'%3E%3Cpath d='M6 6l12 12M18 6 6 18'/%3E%3C/svg%3E") no-repeat center / contain; }
        html.apl-on .gf-page .select2-container--default .select2-selection__clear:hover { background: rgba(118,118,128,.6) !important; }
        html.apl-on .gf-page .select2-container--default .select2-selection__clear:active { transform: translateY(-50%) scale(.9); }
        html.apl-on .gf-page .select2-container--default .select2-selection--multiple .select2-selection__clear { right: 10px !important; }
        html.apl-on .gf-page .select2-container--default .select2-selection--multiple { padding: 2px 8px !important; }
        html.apl-on .gf-page .select2-container--default .select2-selection--multiple .select2-selection__choice {
            position: relative; margin: 5px 4px 3px 0 !important; padding: 2px 10px 2px 24px !important; border: 0 !important; border-radius: 980px !important; background: #fff !important; color: #1d1d1f; font-size: 13px; line-height: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
        html.apl-on .gf-page .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            position: absolute; left: 6px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; display: grid; place-items: center; margin: 0 !important; padding: 0 !important;
            border: 0 !important; border-radius: 50%; background: rgba(118,118,128,.16) !important; color: #6e6e73 !important; font-size: 11px; line-height: 1; }
        html.apl-on .gf-page .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover { background: rgba(118,118,128,.3) !important; color: #1d1d1f !important; }
        html.apl-on .gf-page .select2-container--default .select2-selection--multiple .select2-selection__choice__display { padding: 0 !important; }
        html.apl-on .gf-page .select2-container--default .select2-search--inline .select2-search__field { margin-top: 6px; font-size: 15px; font-family: inherit; }
        html.apl-on .gf-page .select2-container--focus .select2-selection, html.apl-on .gf-page .select2-container--open .select2-selection {
            background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
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
    html.apl-on .gf-table .select2-container--default .select2-selection--single { height: 36px !important; min-height: 36px; border-radius: 9px !important; }
    html.apl-on .gf-table .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; font-size: 14px; padding-right: 30px !important; }
    html.apl-on .gf-table .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    html.apl-on .gf-table .select2-container { margin-top: 0; }
    /* Product lists open wide enough for long names */
    html.apl-on .select2-container--open .select2-dropdown { min-width: 320px; }

    /* The page's modals (alert, existing issue, confirm append): Apple sheet look */
    html.apl-on .fixed.inset-0 .relative.transform.overflow-hidden.rounded-lg.bg-white { border-radius: 22px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 30px 80px rgba(0,0,0,.28) !important; }
    html.apl-on .fixed.inset-0 .relative.transform.overflow-hidden.rounded-lg.bg-white h3 { font-size: 17px; font-weight: 600; letter-spacing: -.01em; color: #1d1d1f; }
    html.apl-on .fixed.inset-0 .relative.transform.overflow-hidden.rounded-lg.bg-white .text-gray-600 { font-size: 14px; line-height: 1.5; color: #424245; }
    html.apl-on .fixed.inset-0 .flex.flex-row.justify-end.bg-gray-100 { gap: 10px; background: #fbfbfd !important; border-top: 1px solid rgba(60,60,67,.12); }
    html.apl-on .fixed.inset-0 .flex.flex-row.justify-end.bg-gray-100 button { height: 38px; padding: 0 18px !important; border: 0 !important; border-radius: 980px !important; font-size: 14px !important; font-weight: 500 !important; letter-spacing: -.01em !important; text-transform: none !important; box-shadow: none !important; }
    html.apl-on .fixed.inset-0 .flex.flex-row.justify-end.bg-gray-100 button.bg-white { background: rgba(118,118,128,.14) !important; color: #1d1d1f !important; }
    html.apl-on .fixed.inset-0 .flex.flex-row.justify-end.bg-gray-100 button:not(.bg-white) { background: #0071e3 !important; color: #fff !important; }
    html.apl-on .fixed.inset-0 .flex.flex-row.justify-end.bg-gray-100 button:active { transform: scale(.97); }
    html.apl-on .fixed.inset-0 .rounded.bg-blue-50, html.apl-on .fixed.inset-0 .rounded.bg-amber-50 { border-radius: 10px; }

    @media print { .gf-actions { position: static; box-shadow: none; } }
    /* Spacing system (8pt grid): one gutter for every card, one gap between sections, so edges line up */
    .gf-page { --gf-gutter: 24px; --gf-gap: 20px; gap: var(--gf-gap); padding-top: 24px; }
    .gf-page > form { gap: var(--gf-gap); }
    .gf-lede { margin: -4px 0 0 4px; }
    html.apl-on .gf-page .uf-card-head { padding: 22px var(--gf-gutter) 0; }
    html.apl-on .gf-page .uf-card-sub { margin: 4px 0 0 34px; line-height: 1.45; }
    html.apl-on .gf-page .uf-body { padding: 18px var(--gf-gutter) var(--gf-gutter); }
    html.apl-on .gf-page .uf-body .grid { column-gap: 16px !important; row-gap: 20px !important; }
    html.apl-on .gf-page .uf-body label { display: block; margin-bottom: 6px; line-height: 1.2; }
    html.apl-on .gf-page .uf-body .mt-1 { margin-top: 0 !important; }
    html.apl-on .gf-page .uf-body textarea { min-height: 88px; }
    html.apl-on .gf-page .gf-table { margin-top: 16px; }
    html.apl-on .gf-table thead th { padding: 12px 6px !important; }
    html.apl-on .gf-table tbody tr td { padding-top: 10px !important; padding-bottom: 10px !important; vertical-align: middle; }
    @media (min-width: 1024px) {
        html.apl-on .gf-table thead th:first-child, html.apl-on .gf-table tbody td:first-child { padding-left: var(--gf-gutter) !important; }
        html.apl-on .gf-table thead th:last-child, html.apl-on .gf-table tbody td:last-child { padding-right: var(--gf-gutter) !important; }
        html.apl-on .gf-table tfoot tr td:first-child { padding-left: var(--gf-gutter) !important; }
        html.apl-on .gf-table tfoot tr td:last-child { padding-right: var(--gf-gutter) !important; }
        html.apl-on .gf-table tfoot tr:last-child td { padding: 14px var(--gf-gutter) var(--gf-gutter) !important; }
    }
    .gf-actions { padding: 14px var(--gf-gutter); }
    /* Issue details: five fields on a laptop, two per row on a tablet, one on a phone */
    @media (min-width: 768px) and (max-width: 1023px) {
        html.apl-on .gf-page .uf-body .grid.md\:grid-cols-5 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
    }
    @media (max-width: 640px) {
        .gf-page { --gf-gutter: 16px; --gf-gap: 14px; padding-top: 16px; }
        html.apl-on .gf-page .uf-card-head { padding-top: 18px; }
        html.apl-on .gf-page .uf-body .grid { row-gap: 16px !important; }
    }
    /* Motion: menus and sheets move only transform and opacity, so they stay smooth and can be interrupted */
    @media (prefers-reduced-motion: reduce) { html.apl-on .select2-dropdown { animation: none !important; } }
    /* apple-design: feedback on press, size-specific tracking, a real material for the floating bar */
    html.apl-on .gf-page button:not(:disabled), html.apl-on .gf-page a.ak-btn { transition: transform 100ms ease-out, background-color .15s ease, box-shadow .15s ease; }
    html.apl-on .gf-page button:not(:disabled):active, html.apl-on .gf-page a.ak-btn:active { transform: scale(.97); }
    html.apl-on .gf-page .uf-card-title { letter-spacing: -.022em; line-height: 1.25; }
    html.apl-on .gf-page label, html.apl-on .gf-table thead th { letter-spacing: .01em !important; }
    html.apl-on .gf-page .uf-card-sub, .gf-lede { letter-spacing: 0; line-height: 1.45; }
    .gf-totals b, html.apl-on .gf-table td { font-variant-numeric: tabular-nums; }
    .gf-actions { background: rgba(255,255,255,.72); -webkit-backdrop-filter: blur(20px) saturate(180%); backdrop-filter: blur(20px) saturate(180%);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.6), 0 0 0 1px rgba(0,0,0,.06), 0 12px 32px -12px rgba(0,0,0,.28); will-change: transform; }
    html.apl-on .select2-container--below .select2-dropdown { transform-origin: top center; }
    @media (prefers-reduced-motion: reduce) {
        html.apl-on .gf-page button:active, html.apl-on .gf-page a.ak-btn:active { transform: none; }
    }
    @media (prefers-reduced-transparency: reduce) {
        .gf-actions, html.apl-on .select2-dropdown { background: #fff !important; -webkit-backdrop-filter: none !important; backdrop-filter: none !important; }
    }
    @media (prefers-contrast: more) {
        html.apl-on .gf-page .uf-card, .gf-actions { box-shadow: 0 0 0 1px #6e6e73 !important; }
        html.apl-on .gf-page .uf-body input, html.apl-on .gf-page .uf-body textarea, html.apl-on .gf-table td input, html.apl-on .gf-page .select2-selection { box-shadow: inset 0 0 0 1px #6e6e73 !important; }
    }
    /* Price breakdown: quiet grey detail, only the line's sum stands out (restraint over colour) */
    html.apl-on .gf-table td[data-label="Price breakdown"] { font-size: 12px; line-height: 1.5; color: #6e6e73; }
    html.apl-on .gf-table td[data-label="Price breakdown"] [class*="text-indigo"], html.apl-on .gf-table td[data-label="Price breakdown"] [class*="text-blue"] { color: #6e6e73 !important; font-weight: 500 !important; }
    html.apl-on .gf-table td[data-label="Price breakdown"] [class*="text-green"] { color: #1d1d1f !important; font-weight: 600 !important; }
    html.apl-on .gf-table td[data-label="Price breakdown"] [class*="text-red"] { color: #c4161c !important; }
    html.apl-on .gf-table td[data-label="Price breakdown"] [class*="text-amber"], html.apl-on .gf-table td[data-label="Price breakdown"] [class*="text-orange"] { color: #8a4b00 !important; }
    @media (min-width: 1024px) { html.apl-on .gf-table td[data-label="UOM"] { width: 108px !important; } }
    html.apl-on .gf-table td[data-label="UOM"] select { padding-left: 10px !important; padding-right: 26px !important; background-position: right 6px center !important; }
    /* Remove: line action, note and the sheet (Apple sheet: blurred scrim, rises from the bottom on phones) */
    .gf-removed { display: block; margin-top: 2px; font-size: 12px; font-weight: 500; color: #8a4b00; }
    .gf-remove { display: inline-flex; align-items: center; gap: 4px; height: 30px; padding: 0 12px; border: 0; border-radius: 980px; background: rgba(255,59,48,.1); color: #d70015; font-size: 13px; font-weight: 500; cursor: pointer; transition: transform 100ms ease-out, background-color .15s ease; }
    .gf-remove:hover:not(:disabled) { background: rgba(255,59,48,.18); }
    .gf-remove:active:not(:disabled) { transform: scale(.97); }
    .gf-remove:disabled { opacity: .4; cursor: not-allowed; }
    .gf-remove:focus-visible { outline: 2px solid #ff3b30; outline-offset: 2px; }
    .gf-scrim { background: rgba(0,0,0,.28); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); }
    .gf-sheet { position: relative; width: 100%; max-width: 480px; max-height: 92vh; overflow-y: auto; background: #fff; border-radius: 22px; box-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 30px 80px rgba(0,0,0,.28); }
    .gf-sheet-in { transition: opacity .2s cubic-bezier(.23,1,.32,1), transform .2s cubic-bezier(.23,1,.32,1); }
    .gf-sheet-out { transition: opacity .12s ease-out, transform .12s ease-out; }
    .gf-sheet-from { opacity: 0; transform: scale(.96) translateY(8px); }
    .gf-sheet-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 20px 22px 4px; }
    .gf-sheet-head h2 { margin: 0; font-size: 17px; font-weight: 600; letter-spacing: -.022em; color: #1d1d1f; }
    .gf-sheet-head p { margin: 4px 0 0; font-size: 13.5px; color: #6e6e73; }
    .gf-sheet-head p b { color: #1d1d1f; font-weight: 600; }
    .gf-sheet-close { flex: none; width: 30px; height: 30px; display: grid; place-items: center; border: 0; border-radius: 50%; background: rgba(120,120,128,.14); color: #3a3a40; cursor: pointer; }
    .gf-sheet-body { padding: 12px 22px 8px; display: flex; flex-direction: column; gap: 6px; }
    .gf-sheet-body label, .gf-sheet-label { margin: 10px 0 0; font-size: 12.5px; font-weight: 500; letter-spacing: .01em; color: #6e6e73; }
    .gf-sheet-body input, .gf-sheet-body textarea { width: 100%; min-height: 40px; padding: 8px 12px; border: 0; border-radius: 10px; background: rgba(118,118,128,.12); font-size: 15px; color: #1d1d1f; }
    .gf-sheet-body input:focus, .gf-sheet-body textarea:focus { outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3; }
    .gf-qty { display: flex; gap: 8px; }
    .gf-qty input { font-variant-numeric: tabular-nums; }
    .gf-qty button { flex: none; height: 40px; padding: 0 14px; border: 0; border-radius: 10px; background: rgba(0,113,227,.1); color: #0066cc; font-weight: 500; cursor: pointer; }
    .gf-hint { margin: 2px 0 0; font-size: 12.5px; color: #c4161c; }
    .gf-preview { margin: 4px 0 0; padding: 4px 12px; list-style: none; border-radius: 12px; background: #f5f5f7; }
    .gf-preview li { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; font-size: 13.5px; color: #424245; font-variant-numeric: tabular-nums; }
    .gf-preview li + li { border-top: 1px solid rgba(60,60,67,.1); }
    .gf-preview b { color: #1d1d1f; }
    .gf-journal { margin: 8px 0 0; font-size: 13px; color: #6e6e73; }
    .gf-journal b { color: #1d1d1f; }
    .gf-sheet-foot { position: sticky; bottom: 0; display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px 18px; background: rgba(255,255,255,.9); -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px); border-top: 1px solid rgba(60,60,67,.1); }
    .gf-btn-grey, .gf-btn-red { height: 40px; padding: 0 18px; border: 0; border-radius: 980px; font-size: 15px; font-weight: 500; cursor: pointer; transition: transform 100ms ease-out; }
    .gf-btn-grey { background: rgba(118,118,128,.14); color: #1d1d1f; }
    .gf-btn-red { background: #ff3b30; color: #fff; }
    .gf-btn-red:disabled { opacity: .4; cursor: not-allowed; }
    .gf-btn-grey:active, .gf-btn-red:active:not(:disabled) { transform: scale(.97); }
    @media (max-width: 640px) {
        .gf-sheet { max-width: none; border-radius: 20px 20px 0 0; }
        .gf-sheet-from { transform: translateY(24px); }
        .gf-sheet-foot { padding-bottom: calc(16px + env(safe-area-inset-bottom)); }
        .gf-sheet-foot > * { flex: 1 1 0; }
    }
    @media (prefers-reduced-motion: reduce) { .gf-sheet-from { transform: none; } }
</style>
