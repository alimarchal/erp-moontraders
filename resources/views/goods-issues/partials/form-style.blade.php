{{--
    Goods Issue create / edit: card layout on top of settings.partials.ui-style.
    Only the page shell is styled here; every field, its name, id and the
    form's JavaScript are unchanged.
--}}
<style>
    .gf-page { max-width: none; }
    .gf-card { margin-bottom: 20px; }
    .gf-card .uf-card-head > div { min-width: 0; }
    .gf-head-stats { display: flex; flex-wrap: wrap; gap: 6px; }
    .gf-head-stats .ak-pill { margin: 0; }

    /* Products table: keep the component, match the card look */
    .gf-table { padding: 0; }
    .gf-table > div > .form-table-scroll { border-radius: 0 0 var(--ak-radius) var(--ak-radius); }
    .gf-table .form-table-scroll thead tr { background: #f3f4f6 !important; color: #374151 !important; }
    .gf-table .form-table-scroll thead th { padding: 10px 8px !important; font-size: 11px; letter-spacing: .03em; border-bottom: 1px solid var(--ak-border); }
    .gf-table .form-table-scroll tbody tr:hover td { background: #f8fafc; }
    .gf-table .form-table-scroll tfoot tr:first-child td { border-top: 2px solid var(--ak-border); }
    .gf-table .form-table-scroll tfoot tr:last-child td { padding: 12px 10px; }

    /* Sticky bar: totals on the left, Cancel / Save on the right */
    .gf-actions { margin-top: 4px; }
    .gf-totals { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 18px; font-size: 13px; color: var(--ak-muted); }
    .gf-totals b { color: var(--ak-text); font-variant-numeric: tabular-nums; }
    .gf-warn { color: #b91c1c; font-weight: 600; }
    .gf-actions > div:last-child { display: flex; align-items: center; gap: 10px; }
</style>
