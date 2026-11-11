{{-- Shared assets of every Bank Register page: the Settings list/form styles, black & white laser printing, and the
     transaction pop-up (any link with data-entry="<url>" opens that transaction in a modal instead of a new page). --}}
@props(['menuButton' => false])
@include('settings.partials.ui-style')
<style>
    .bank-printonly { display: none; }
    .bk-dl { display: grid; gap: 14px 20px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; }
    .bk-dl dt { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ak-muted); }
    .bk-dl dd { margin: 2px 0 0; font-size: 14px; font-weight: 600; color: var(--ak-text); word-break: break-word; }
    .bk-dl .bk-wide { grid-column: 1 / -1; }
    .bk-box { border: 1px solid var(--ak-border); border-radius: 10px; padding: 12px 14px; background: #fff; }
    .bk-box h4 { margin: 0 0 6px; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ak-muted); }
    .bk-box p { margin: 0; font-size: 13.5px; color: var(--ak-text); }
    .bk-amount { margin: 6px 0 0; font-size: 30px; line-height: 1.1; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--ak-text); }
    .ak-status-slate { background: var(--ak-slate-bg); color: #334155; }
    .ak-status-blue { background: #dbeafe; color: #1e3a8a; }
    .ak-seg button { padding: 9px 16px; border: 0; background: #fff; color: var(--ak-text); font: inherit; font-size: 14px; cursor: pointer; }
    .ak-seg button + button { border-left: 1px solid var(--ak-border); }
    .ak-seg button.is-on { background: var(--ak-navy); color: #fff; }
    .bk-in { color: #166534; } .bk-out { color: #991b1b; }
    .bk-stack { display: flex; flex-direction: column; gap: 14px; }
    .bk-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .bk-inline-form { display: flex; gap: 8px; }
    .bk-modal-box.bk-action-box { width: min(96vw, 34rem); max-width: 34rem; }
    .bk-modal-head p { margin: 2px 0 0; font-size: 12px; color: rgba(255,255,255,.85); }
    .bk-modal-head.bk-head-red { background: linear-gradient(to right, #dc2626, #b91c1c); }
    .bk-modal-foot { flex: none; display: flex; justify-content: flex-end; gap: 10px; padding: 12px 18px; border-top: 1px solid var(--ak-border); background: #f9fafb; }
    .bk-btn-red { background: #dc2626; color: #fff; border-color: #dc2626; } .bk-btn-red:hover { background: #b91c1c; }
    .bk-btn-green { background: #16a34a; color: #fff; border-color: #16a34a; } .bk-btn-green:hover { background: #15803d; }
    .bk-action-box .bk-modal-body p { margin: 0; font-size: 13.5px; color: var(--ak-text); }
    .bk-action-box label { display: block; margin-bottom: 4px; font-size: 12px; font-weight: 700; color: var(--ak-text); }
    .bk-action-box textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; }
    /* Sheet tables. On screen: a quiet card with hairlines. On paper: the black grid of the Cash Collection report (see @media print). */
    table.bk-t { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; border: 1px solid var(--ak-line); border-radius: 10px; background: #fff; font-size: 14px; line-height: 1.4; color: var(--ak-text); }
    table.bk-t th, table.bk-t td { padding: 9px 14px; border-bottom: 1px solid var(--ak-line); vertical-align: top; text-align: left; word-break: break-word; }
    table.bk-t tbody tr:last-child > * { border-bottom: 0; }
    table.bk-t th { background: var(--ak-soft); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); white-space: nowrap; }
    table.bk-t tbody th { width: 18%; padding-top: 11px; }
    table.bk-t tbody td { font-variant-numeric: tabular-nums; }
    table.bk-t tr.cap th { padding: 11px 14px; background: #fff; font-size: 14px; letter-spacing: 0; text-transform: none; color: var(--ak-text); white-space: normal; }
    table.bk-t tr.cap th small { float: right; margin-inline-start: 12px; font-size: 12px; font-weight: 500; color: var(--ak-muted); }
    table.bk-t .n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    table.bk-t .c { text-align: center; }
    table.bk-t .b { font-weight: 700; }
    table.bk-t .bk-none { padding: 18px 14px; text-align: center; color: var(--ak-muted); }
    table.bk-t tr { break-inside: avoid; page-break-inside: avoid; }
    table.bk-t a.ak-primary-link { color: var(--ak-navy); font-weight: 600; text-decoration: underline; text-decoration-color: #c7d2fe; text-underline-offset: 3px; }
    table.bk-t a.ak-primary-link:hover { text-decoration-color: currentColor; }
    /* Attached files: the name opens the file, and so do the icons at the end of the row */
    table.bk-files td { vertical-align: middle; }
    .bk-file-name { display: inline-flex; align-items: center; gap: 10px; max-width: 100%; color: var(--ak-text); font-weight: 600; text-decoration: none; }
    .bk-file-name:hover span:last-child { color: var(--ak-navy); text-decoration: underline; }
    .bk-file-type { flex: none; min-width: 38px; padding: 4px 6px; border-radius: 6px; background: var(--ak-navy-tint); color: var(--ak-navy); font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; text-align: center; }
    .bk-file-actions { display: inline-flex; align-items: center; justify-content: center; gap: 4px; }
    .bk-file-actions form { display: inline-flex; margin: 0; }
    .bk-file-actions .ak-icon { width: 32px; height: 32px; }
    .bk-file-actions .ak-icon svg, .bk-clip svg { width: 18px; height: 18px; }
    .bk-icon-soft:hover { background: var(--ak-slate-bg); color: var(--ak-text); }
    .bk-icon-danger { color: #b91c1c; } .bk-icon-danger:hover { background: #fee2e2; }
    .bk-clip { display: inline-flex; align-items: center; gap: 4px; height: 28px; padding: 0 9px; border-radius: 999px; background: var(--ak-navy-tint); color: var(--ak-navy); font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; text-decoration: none; }
    .bk-clip:hover { background: var(--ak-navy); color: #fff; }
    .bk-clip:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
    .bk-spacer { flex: 1 1 auto; }
    .bk-actions { padding-top: 14px; border-top: 1px solid var(--ak-line); }
    /* Press and hover feedback: only the properties that change, quick and eased out */
    .ak-btn, .ak-icon, .bk-clip { transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .12s cubic-bezier(.23, 1, .32, 1); }
    .ak-btn:active, .ak-icon:active, .bk-clip:active { transform: scale(.97); }
    .bk-skeleton { display: grid; gap: 12px; }
    .bk-skeleton i { display: block; height: 18px; border-radius: 6px; background: var(--ak-slate-bg); animation: bk-pulse 1s ease-in-out infinite alternate; }
    .bk-skeleton i:first-child { height: 84px; border-radius: 10px; } .bk-skeleton i:last-child { width: 60%; }
    @keyframes bk-pulse { from { opacity: 1; } to { opacity: .45; } }
    .bk-note { margin: 0; padding: 10px 14px; border: 1px solid #fecaca; border-radius: 10px; background: #fef2f2; color: #7f1d1d; font-size: 13.5px; }
    /* Attach a file: every control has its own label and full width, nothing is cut off */
    .bk-attach { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; padding: 12px 14px; border: 1px solid var(--ak-border); border-radius: 10px; background: #f8fafc; }
    @media (min-width: 760px) { .bk-attach { grid-template-columns: minmax(0, 1.4fr) 190px minmax(0, 1.2fr) auto; } }
    .bk-attach label { display: block; margin-bottom: 4px; font-size: 12px; font-weight: 700; color: var(--ak-text); }
    .bk-attach select, .bk-attach input:not([type=file]) { width: 100%; height: 38px; box-sizing: border-box; padding: 0 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; background-color: #fff; font-size: 14px; color: var(--ak-text); }
    .bk-attach select { padding-right: 32px; }
    .bk-attach input[type=file] { width: 100%; height: 38px; box-sizing: border-box; padding: 5px 8px; border: 1px solid var(--ak-border-strong); border-radius: 8px; background: #fff; font-size: 13px; }
    .bk-attach .ak-btn { height: 38px; }
    /* Filter panel: the same layout as Expense Vouchers */
    .bk-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
    @media (min-width: 900px) { .bk-filter-row { grid-template-columns: minmax(220px, 2fr) 150px 150px minmax(170px, 1.3fr) 150px auto; } }
    .bk-filter-row .ak-field input, .bk-advanced .ak-field input { height: 38px; box-sizing: border-box; }
    .bk-filter-row .ak-field input[type="date"] { padding: 0 10px; font-size: 14px; }
    .bk-filter-row .ak-filter-buttons { display: flex; gap: 8px; padding-top: 0; }
    .bk-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
    @media (min-width: 900px) { .bk-advanced { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    #bk-filter-panel .ak-filters { border-bottom: 0; border-radius: inherit; }
    #bk-filter-panel .select2-container { width: 100% !important; display: block; }
    .bk-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
    .bk-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
    .bk-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
    .bk-ranges a:hover { background: var(--ak-soft); }
    .bk-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
    .bk-ranges a.bk-clear { border-color: #fecaca; color: #b91c1c; }
    .bk-ranges a.bk-clear:hover { background: #fef2f2; }
    .ak-tab.is-empty { opacity: .55; }
    .bk-row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 6px; white-space: nowrap; }
    .bk-row-actions .ak-icon { width: 32px; height: 32px; }
    .bk-more > summary { cursor: pointer; padding: 14px 20px; font-weight: 600; color: var(--ak-text); }
    .ak-btn.is-busy { opacity: .6; pointer-events: none; }
    .bk-inline-form input { flex: 1; min-width: 0; height: 38px; padding: 0 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; }
    .bk-file { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--ak-line); }
    .bk-file a { font-weight: 700; color: var(--ak-navy); }
    /* The transaction pop-up: same look as the Sales Settlement windows (blurred page behind, green title bar, own scroll),
       sized from the screen: wide on a desktop, nearly full width on a tablet, a full-screen sheet on a phone. */
    .bk-modal-box { width: min(96vw, 64rem); max-width: 64rem; max-height: min(92vh, 60rem); max-height: min(92dvh, 60rem); display: flex; flex-direction: column; overflow: hidden; border-radius: 12px; animation: bk-pop .2s cubic-bezier(.23, 1, .32, 1); }
    .bk-modal-head { flex: none; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 18px; background: linear-gradient(to right, #16a34a, #15803d); color: #fff; }
    .bk-modal-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #fff; }
    .bk-modal-head .ak-btn { background: rgba(255,255,255,.14); color: #fff; border-color: rgba(255,255,255,.55); }
    .bk-modal-head .ak-btn:hover { background: rgba(255,255,255,.28); }
    .bk-modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 18px 22px 22px; background: #fff; }
    @keyframes bk-pop { from { opacity: 0; transform: scale(.97); } to { opacity: 1; transform: none; } }
    @keyframes bk-fade { from { opacity: 0; } to { opacity: 1; } }
    #bk-entry-modal .uf-modal-bg, .bk-action-modal .uf-modal-bg { animation: bk-fade .2s ease-out; }
    @media (prefers-reduced-motion: reduce) { .bk-modal-box, #bk-entry-modal .uf-modal-bg, .bk-action-modal .uf-modal-bg, .bk-skeleton i { animation: none; } .ak-btn:active, .ak-icon:active, .bk-clip:active { transform: none; } }
    @media (max-width: 640px) {
        #bk-entry-modal { padding: 0; align-items: stretch; }
        .bk-modal-box { width: 100vw; max-width: none; height: 100vh; height: 100dvh; max-height: none; border-radius: 0; }
        .bk-modal-head { padding: 12px 14px; } .bk-modal-body { padding: 14px; }
    }
    /* Transaction detail: the facts on one side, the receipt files and the actions on the other (one column on a phone) */
    .bk-hero { padding: 16px 18px; border: 1px solid var(--ak-line); border-radius: 10px; background: var(--ak-soft); }
    .bk-hero .bk-amount { letter-spacing: -.02em; }
    .bk-hero .bk-amount { margin: 6px 0 2px; }
    .bk-detail-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
    .bk-detail-main, .bk-detail-side { min-width: 0; }
    @media (min-width: 860px) { .bk-detail-grid { grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); align-items: start; gap: 18px; } .bk-detail-side { position: sticky; top: 0; } }
    .bk-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .bk-link-row { cursor: pointer; }
    .bk-tabs-nav { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; padding: 4px; background: #fff; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); box-shadow: var(--ak-shadow); }
    .bk-tabs-nav a { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; color: var(--ak-muted); text-decoration: none; }
    .bk-tabs-nav a:hover { background: var(--ak-slate-bg); color: var(--ak-text); }
    .bk-tabs-nav a.is-active { background: var(--ak-navy); color: #fff; }
    .bk-tabs-nav .bk-badge { min-width: 20px; padding: 0 6px; border-radius: 999px; background: #b45309; color: #fff; font-size: 11px; font-weight: 700; line-height: 20px; text-align: center; }
    .bk-help-link { margin-inline-start: auto; }
    /* Bulk actions: a bar fixed to the bottom of the window, so ticking a row never moves the rows under the pointer */
    #bulk-form.ak-bulk { position: fixed; z-index: 40; left: 50%; bottom: 18px; transform: translateX(-50%); width: min(96vw, 72rem); display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 16px; border: 1px solid var(--ak-navy); border-radius: 12px; background: #fff; box-shadow: 0 12px 32px rgba(15,23,42,.25); }
    #bulk-form .select2-container { width: 220px !important; }
    #bulk-form .select2-container .select2-selection--single { height: 38px; display: flex; align-items: center; padding: 0 30px 0 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; }
    #bulk-form .select2-container .select2-selection__rendered { padding: 0; line-height: 1.2; }
    #bulk-form .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
    #bulk-form label { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
    /* The menu panel (opened with the Menu button) */
    .bk-menu { display: flex; flex-direction: column; gap: 14px; margin-top: 16px; padding: 18px 20px; }
    .bk-menu-group { display: grid; gap: 4px 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 900px) { .bk-menu-group { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .bk-menu-group + .bk-menu-group { padding-top: 14px; border-top: 1px solid var(--ak-line); }
    .bk-menu-group .bk-menu-title { grid-column: 1 / -1; }
    .bk-menu-title { margin: 0 0 6px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--ak-muted); }
    .bk-menu-item { display: flex; flex-direction: column; gap: 1px; width: 100%; padding: 8px 10px; border: 0; border-radius: 8px; background: none; text-align: start; text-decoration: none; cursor: pointer; font: inherit; }
    .bk-menu-item:hover { background: var(--ak-slate-bg); }
    .bk-menu-item.is-active { background: #eef2ff; }
    .bk-menu-item b { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: var(--ak-navy); }
    .bk-menu-item span { font-size: 12px; color: var(--ak-muted); }
    .bk-menu-item .bk-badge { min-width: 20px; padding: 0 6px; border-radius: 999px; background: #b45309; color: #fff; font-size: 11px; font-weight: 700; line-height: 20px; text-align: center; }
    .bk-menu-standalone { display: flex; justify-content: flex-end; gap: 8px; padding-top: 16px; }
    .ak-tabs .bk-tab-extra { margin-inline-start: auto; align-self: center; padding: 4px 10px; border: 1px solid #fcd34d; border-radius: 999px; background: #fffbeb; color: #92400e; font-size: 12px; font-weight: 700; text-decoration: none; }
    .ak-tabs .bk-tab-extra.is-active { background: #92400e; border-color: #92400e; color: #fff; }
    .bk-nav-sep { margin-inline: 8px 2px; padding-inline-start: 12px; border-inline-start: 1px solid var(--ak-border); font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ak-muted); }
    .bk-cards { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
    .bk-card-link { display: flex; flex-direction: column; gap: 6px; padding: 16px 18px; background: #fff; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); box-shadow: var(--ak-shadow); text-decoration: none; color: var(--ak-text); }
    .bk-card-link:hover { border-color: var(--ak-navy); }
    .bk-card-link b { font-size: 15px; color: var(--ak-navy); }
    .bk-card-link span { font-size: 13px; color: var(--ak-muted); }
    .bk-attach .select2-container { width: 100% !important; display: block; }
    .bk-attach .select2-container .select2-selection--single { height: 38px; display: flex; align-items: center; padding: 0 30px 0 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; }
    .bk-attach .select2-container .select2-selection__rendered { padding: 0; line-height: 1.2; }
    .bk-attach .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
    @media (max-width: 640px) { .bk-dl { grid-template-columns: 1fr; } }

    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        html, body { background: #fff !important; }
        /* Laser printer, black and white: no colour anywhere except the attached receipts and files */
        *, *::before, *::after { color: #000 !important; text-shadow: none !important; box-shadow: none !important; -webkit-print-color-adjust: economy; print-color-adjust: economy; }
        *:not(thead th):not(.ak-dt thead th) { background-color: transparent !important; }
        *:not(.ak-dt td):not(.ak-dt th) { border-color: #000 !important; }
        img, embed, iframe, object, canvas { filter: none !important; }
        .bank-noprint, nav, footer, [x-cloak], .select2-container--open, .bk-tabs-nav, #bk-entry-modal { display: none !important; }
        .bank-printonly { display: block !important; } span.bank-printonly { display: inline !important; }
        .bk-box { border: 1px solid #000 !important; border-radius: 0; break-inside: avoid; }
        .bk-amount { font-size: 22px; }
        .ak-status, .ak-pill { border: 1px solid #000; padding: 0 5px !important; font-weight: 700; }
        .ak-kpis { display: none !important; }
        .ak-muted { font-size: 10px !important; color: #222 !important; }
        .ak-card, .uf-card { border: 0 !important; }
        .ak-dt tbody tr:nth-child(even) td { background: #f3f3f3 !important; }
        /* The register list prints as the same black grid as the report sheets */
        .ak-dt { border-collapse: collapse !important; font-size: 10px; }
        .ak-dt th, .ak-dt td { border: 0.75pt solid #000 !important; padding: 3px 5px !important; }
        .ak-dt thead th { background: #e6e6e6 !important; }
        table.bk-t { border-collapse: collapse; border: 0; border-radius: 0; overflow: visible; font-size: 10px; line-height: 1.35; }
        table.bk-t th, table.bk-t td, table.bk-t tbody tr:last-child > * { border: 0.75pt solid #000 !important; padding: 3px 6px; }
        table.bk-t th { background: #e6e6e6 !important; letter-spacing: .2px; }
        table.bk-t tr.cap th { background: #fff !important; padding: 4px 2px 3px; border-width: 0 0 1px !important; text-transform: uppercase; }
        table.bk-t tr.cap th small { text-transform: none; font-weight: 400; }
        .bk-note { border: 1px solid #000; border-radius: 0; }
        table.bk-t th { font-size: 8.5px; } table.bk-t tr.cap th { font-size: 11px; } table.bk-t tr.cap th small { font-size: 9px; }
        .select2-selection__arrow, .select2-selection__clear { display: none !important; }
        a { text-decoration: none !important; }
        thead { display: table-header-group; }
        tr, .bank-keep { break-inside: avoid; page-break-inside: avoid; }
        body.bk-modal-open .ak-page, body.bk-modal-open nav, body.bk-modal-open header { display: none !important; }
        body.bk-modal-open #bk-entry-modal { display: block !important; position: static !important; }
        body.bk-modal-open #bk-entry-modal .uf-modal-bg, body.bk-modal-open .bk-modal-head button { display: none !important; }
        body.bk-modal-open .uf-modal-box { max-width: none; max-height: none; overflow: visible; display: block; }
        body.bk-modal-open .bk-modal-body { overflow: visible; }
        .bk-detail-grid { display: block; } .bk-detail-side { position: static; } .bk-hero { border: 0; background: none; padding: 0; }
        body.bk-modal-open .bk-modal-head { background: #fff; color: #000; border-bottom: 1px solid #000; }
        body.bk-modal-open .bk-modal-head h3 { color: #000; }
    }
</style>

<x-bank.subnav :button="$menuButton" />
<x-attachment-compressor />
<div id="bk-entry-modal" class="uf-modal bank-noprint" style="display:none" role="dialog" aria-modal="true" aria-labelledby="bk-entry-title">
    <div class="uf-modal-bg" data-bk-close></div>
    <div class="uf-modal-box bk-modal-box">
        <div class="bk-modal-head">
            <h3 id="bk-entry-title">Transaction</h3>
            <div class="bk-row">
                <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" id="bk-entry-print">Print</button>
                <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" data-bk-close>Close</button>
            </div>
        </div>
        <div class="bk-modal-body" id="bk-entry-body" aria-live="polite"></div>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('bk-entry-modal');
        var body = document.getElementById('bk-entry-body');
        if (!modal) { return; }

        var title = document.getElementById('bk-entry-title');
        var opener = null;

        function isOpen() { return modal.style.display !== 'none'; }
        function close() {
            modal.style.display = 'none';
            document.body.classList.remove('bk-modal-open');
            document.body.style.overflow = '';
            if (opener && document.contains(opener)) { opener.focus(); }
            opener = null;
        }
        function open(url, from) {
            opener = from || document.activeElement;
            title.textContent = 'Transaction';
            body.innerHTML = '<div class="bk-skeleton" role="status" aria-label="Loading the transaction"><i></i><i></i><i></i><i></i></div>';
            modal.style.display = 'flex';
            document.body.classList.add('bk-modal-open');
            document.body.style.overflow = 'hidden';
            modal.querySelector('.bk-modal-head [data-bk-close]').focus();
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.text(); })
                .then(function (html) {
                    body.innerHTML = html;
                    body.scrollTop = 0;
                    var named = body.querySelector('[data-bk-title]');
                    if (named) { title.textContent = named.getAttribute('data-bk-title'); }
                    if (window.initSelect2) { window.initSelect2(body); }
                })
                .catch(function () { body.innerHTML = '<p class="ak-muted">This transaction could not be loaded. Please try again.</p>'; });
        }

        document.addEventListener('click', function (e) {
            var link = e.target.closest('[data-entry]');
            if (link && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.target.closest('input, button, select, textarea, label')) {
                if (link.tagName === 'TR' && e.target.closest('a:not([data-entry])')) { return; }
                e.preventDefault();
                open(link.getAttribute('data-entry'), link.tagName === 'TR' ? link.querySelector('a[data-entry]') : link);
                return;
            }
            if (e.target.closest('[data-bk-close]')) { close(); }
        });
        document.addEventListener('keydown', function (e) {
            if (!isOpen()) { return; }
            // an action window (reverse, delete, reject) opened on top closes first and keeps its own keys
            if (document.querySelector('.bk-action-modal:not([style*="display: none"])')) { return; }
            if (e.key === 'Escape') { close(); return; }
            if (e.key !== 'Tab') { return; }
            var stops = Array.prototype.filter.call(modal.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'), function (el) { return !el.disabled && el.offsetParent !== null; });
            if (!stops.length) { return; }
            var first = stops[0], last = stops[stops.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            else if (!modal.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
        });
        document.getElementById('bk-entry-print').addEventListener('click', function () { window.print(); });
    })();

    // Collapsed sections (history) are opened so they appear on paper.
    window.addEventListener('beforeprint', function () { document.querySelectorAll('details').forEach(function (d) { d.dataset.wasOpen = d.open ? '1' : ''; d.open = true; }); });
    window.addEventListener('afterprint', function () { document.querySelectorAll('details').forEach(function (d) { d.open = d.dataset.wasOpen === '1'; }); });
</script>
