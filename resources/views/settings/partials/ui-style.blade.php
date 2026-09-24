{{-- Shared list / form UI styles for Settings screens (users, roles). Plain CSS (ak- prefix) so nothing depends on a Tailwind rebuild. --}}
@once
<style>
    /* Colour roles (use colour for meaning only):
       navy  = primary action, selection, focus    green = approved / success
       amber = pending / needs attention           red   = errors, destructive
       slate = neutral information. Text is black / near-black for contrast (WCAG AA). */
    :root {
        --ak-navy: #172554; --ak-navy-hover: #1e3a8a; --ak-navy-tint: #e0e7ff;
        --ak-green: #15803d; --ak-green-bg: #dcfce7; --ak-amber: #b45309; --ak-amber-bg: #fef3c7;
        --ak-slate-bg: #f1f5f9; --ak-border: #cbd5e1; --ak-border-strong: #94a3b8; --ak-line: #e2e8f0;
        --ak-text: #0f172a; --ak-muted: #475569; --ak-soft: #f8fafc; --ak-radius: 12px;
        --ak-shadow: 0 1px 2px rgba(15,23,42,.06), 0 1px 3px rgba(15,23,42,.08);
        --ak-shadow-hover: 0 4px 6px rgba(15,23,42,.06), 0 10px 20px rgba(15,23,42,.08);
    }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    [x-cloak] { display: none !important; }

    /* Header */
    .ak-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; }
    .ak-crumbs { display: flex; gap: 6px; font-size: 12px; color: var(--ak-muted); }
    .ak-crumbs a { color: var(--ak-muted); text-decoration: none; }
    .ak-crumbs a:hover { color: var(--ak-text); text-decoration: underline; }
    .ak-title { margin: 2px 0 0; font-size: 24px; line-height: 1.2; font-weight: 700; color: var(--ak-text); }
    .ak-sub { margin: 4px 0 0; font-size: 13px; color: var(--ak-muted); }
    .ak-scope { display: inline-block; padding: 1px 10px; border: 1px solid var(--ak-border); border-radius: 999px; background: #fff; color: var(--ak-text); font-weight: 600; font-size: 12px; }
    .ak-scope-limited { border-color: #93c5fd; background: #eff6ff; color: #1e3a8a; }
    .ak-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }

    /* Buttons */
    .ak-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 16px; border-radius: 8px; border: 1px solid transparent;
        font-size: 13px; font-weight: 600; line-height: 1; white-space: nowrap; cursor: pointer; text-decoration: none; transition: background .15s, border-color .15s, color .15s; }
    .ak-btn:focus-visible, .ak-icon:focus-visible, .ak-tab:focus-visible, .ak-kpi:focus-visible, .ak-chip:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
    .ak-btn[disabled] { opacity: .6; cursor: progress; }
    .ak-btn-primary { background: var(--ak-navy); color: #fff; }
    .ak-btn-primary:hover { background: var(--ak-navy-hover); }
    .ak-btn-outline { background: #fff; border-color: var(--ak-border); color: var(--ak-text); }
    .ak-btn-outline:hover { background: var(--ak-soft); border-color: #6b7280; }
    .ak-btn-ghost { background: transparent; border-color: var(--ak-border); color: var(--ak-text); }
    .ak-btn-ghost:hover { background: var(--ak-soft); }
    .ak-btn-success { background: var(--ak-green); color: #fff; }
    .ak-btn-success:hover { background: #166534; }
    .ak-btn-danger-outline { background: #fff; border-color: #fca5a5; color: #b91c1c; }
    .ak-btn-danger-outline:hover { background: #fef2f2; border-color: #dc2626; }
    .ak-btn-sm { height: 30px; padding: 0 12px; font-size: 12px; border-radius: 6px; }
    /* Row action buttons: same size and weight whether the next step is View or Approve */
    .ak-row-btn { height: 32px; min-width: 88px; padding: 0 14px; font-size: 12.5px; font-weight: 700; border-radius: 7px; box-shadow: 0 1px 2px rgba(15,23,42,.15); }
    .ak-row-btn svg { width: 15px; height: 15px; }
    .ak-btn-primary.ak-row-btn:hover { background: var(--ak-navy-hover); }

    .ak-seg { display: inline-flex; border: 1px solid var(--ak-border); border-radius: 8px; overflow: hidden; font-size: 12px; font-weight: 600; margin-right: 4px; }
    .ak-seg a, .ak-seg span { padding: 9px 12px; color: var(--ak-text); text-decoration: none; background: #fff; }
    .ak-seg a:hover { background: var(--ak-soft); }
    .ak-seg [aria-current] { background: var(--ak-navy); color: #fff; }

    /* Dropdown menus */
    .ak-menu { position: relative; }
    .ak-menu-list { position: absolute; right: 0; top: calc(100% + 6px); z-index: 40; min-width: 220px; padding: 6px; background: #fff;
        border: 1px solid var(--ak-border); border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,.15); }
    .ak-menu-list a, .ak-menu-list button { display: block; width: 100%; padding: 9px 12px; border: 0; background: none; border-radius: 6px; text-align: left;
        font-size: 13px; color: var(--ak-text); text-decoration: none; cursor: pointer; }
    .ak-menu-list a:hover, .ak-menu-list button:hover { background: var(--ak-soft); }

    /* Page */
    .ak-page { max-width: 80rem; margin: 0 auto; padding: 24px 16px 64px; display: flex; flex-direction: column; gap: 20px; }
    @media (min-width: 640px) { .ak-page { padding-left: 24px; padding-right: 24px; } }
    @media (min-width: 1024px) { .ak-page { padding-left: 32px; padding-right: 32px; } }
    .ak-alert { padding: 12px 16px; border-radius: var(--ak-radius); border: 1px solid; font-size: 14px; }
    .ak-alert ul { margin: 6px 0 0 18px; list-style: disc; }
    .ak-alert-error { background: #fef2f2; border-color: #fca5a5; color: #7f1d1d; }
    .ak-alert-warn { background: var(--ak-amber-bg); border-color: #fcd34d; color: #78350f; }

    /* KPI cards: compact (about 96px tall) so the table stays above the fold.
       Icon chip on the left, label, value, one hint. Only the actionable card
       is a link: it lifts on hover and shows a navy ring when selected. */
    .ak-kpis { display: grid; gap: 16px; grid-template-columns: repeat(1, minmax(0, 1fr)); }
    @media (min-width: 640px) { .ak-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .ak-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .ak-kpi { display: flex; align-items: flex-start; gap: 14px; padding: 16px 18px; background: #fff; border: 1px solid var(--ak-border);
        border-radius: var(--ak-radius); box-shadow: var(--ak-shadow); text-decoration: none; color: var(--ak-text);
        transition: box-shadow .15s, border-color .15s, transform .15s; }
    a.ak-kpi:hover { border-color: var(--ak-border-strong); box-shadow: var(--ak-shadow-hover); transform: translateY(-1px); }
    .ak-kpi.is-active { border-color: var(--ak-navy); box-shadow: 0 0 0 1px var(--ak-navy), var(--ak-shadow); }
    .ak-kpi-body { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .ak-kpi-label { font-size: 13px; font-weight: 600; color: var(--ak-muted); }
    .ak-kpi-icon { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; }
    .ak-kpi-icon svg { width: 22px; height: 22px; }
    .ak-tone-navy { background: var(--ak-navy-tint); color: var(--ak-navy); }
    .ak-tone-amber { background: var(--ak-amber-bg); color: var(--ak-amber); }
    .ak-tone-green { background: var(--ak-green-bg); color: var(--ak-green); }
    .ak-tone-slate { background: var(--ak-slate-bg); color: #334155; }
    .ak-kpi-value { font-size: 24px; line-height: 1.2; font-weight: 700; letter-spacing: -.01em; font-variant-numeric: tabular-nums; color: var(--ak-text); white-space: nowrap; }
    .ak-kpi-value-sm { font-size: 18px; line-height: 1.35; white-space: normal; overflow-wrap: anywhere; }
    .ak-kpi-hint { font-size: 12px; color: var(--ak-muted); }
    .ak-kpi-action .ak-kpi-hint { color: var(--ak-amber); font-weight: 600; }

    /* Results card */
    .ak-card { background: #fff; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); box-shadow: var(--ak-shadow); overflow: visible; }
    .ak-tabs { display: flex; flex-wrap: wrap; gap: 4px; padding: 0 16px; border-bottom: 1px solid var(--ak-line); }
    .ak-tab { display: inline-flex; align-items: center; gap: 8px; padding: 14px 12px 12px; margin-bottom: -1px; border-bottom: 3px solid transparent;
        font-size: 14px; font-weight: 600; color: var(--ak-muted); text-decoration: none; }
    .ak-tab:hover { color: var(--ak-text); border-bottom-color: var(--ak-line); }
    .ak-tab.is-active { color: var(--ak-navy); border-bottom-color: var(--ak-navy); }
    .ak-count { display: inline-block; min-width: 22px; padding: 1px 7px; border-radius: 999px; background: #e5e7eb; color: var(--ak-text); font-size: 12px; font-weight: 700; text-align: center; }
    .ak-tab.is-active .ak-count, .ak-count-dark { background: var(--ak-navy); color: #fff; }
    .ak-filters { background: var(--ak-soft) !important; }

    /* Filters */
    .ak-filters { padding: 16px; border-bottom: 1px solid var(--ak-line); background: #fafafa; }
    .ak-filter-row, .ak-filter-advanced { display: grid; gap: 14px; grid-template-columns: 1fr; align-items: start; }
    @media (min-width: 768px) {
        .ak-filter-row { grid-template-columns: minmax(0, 2.4fr) minmax(0, 1fr) minmax(0, 1fr) auto; }
        .ak-filter-advanced { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    .ak-filter-advanced { margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--ak-line); }
    .ak-field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--ak-text); }
    .ak-field input, .ak-field select { width: 100%; height: 38px; padding: 0 12px; border: 1px solid var(--ak-border); border-radius: 8px; background: #fff; color: var(--ak-text); font-size: 14px; }
    .ak-field select { padding-right: 32px; }
    .ak-field input:focus, .ak-field select:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .ak-help { margin: 5px 0 0; font-size: 12px; color: var(--ak-muted); }
    .ak-search { position: relative; }
    .ak-search svg { position: absolute; left: 11px; top: 10px; width: 18px; height: 18px; color: #6b7280; pointer-events: none; }
    .ak-search input { padding-left: 36px; padding-right: 36px; }
    .ak-search kbd { position: absolute; right: 9px; top: 9px; padding: 0 6px; border: 1px solid var(--ak-line); border-radius: 4px; font-size: 11px; color: var(--ak-muted); background: #fff; }
    .ak-filter-buttons { display: flex; gap: 8px; padding-top: 25px; }

    .ak-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 16px; border-bottom: 1px solid var(--ak-line); }
    .ak-chips-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); }
    .ak-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border: 1px solid var(--ak-border); border-radius: 999px; background: #fff;
        font-size: 12px; color: var(--ak-text); text-decoration: none; }
    .ak-chip:hover { border-color: #dc2626; color: #b91c1c; }
    .ak-chips-clear { font-size: 12px; font-weight: 600; color: #b91c1c; }

    /* Data table -- light header, horizontal rules only, sticky header,
       sticky name + actions columns, hover row, comfortable/compact
       density, and stacked "cards" on phones. */
    .ak-table-wrap { padding: 0 0 4px; }
    .ak-dt-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; font-size: 13px; color: var(--ak-muted); }
    .ak-dt-toolbar p { margin: 0; }
    .ak-dt-toolbar b { color: var(--ak-text); }
    .ak-seg-sm { margin: 0; }
    .ak-seg-sm button { padding: 6px 12px; border: 0; background: #fff; font-size: 12px; font-weight: 600; color: var(--ak-text); cursor: pointer; }
    .ak-seg-sm button + button { border-left: 1px solid var(--ak-border); }
    .ak-seg-sm button.is-on { background: var(--ak-navy); color: #fff; }

    .ak-dt-scroll { max-height: 40rem; overflow: auto; border-top: 1px solid var(--ak-line); border-bottom: 1px solid var(--ak-line); }
    .ak-dt-scroll:focus-visible { outline: 3px solid #93c5fd; outline-offset: -3px; }
    .ak-dt { width: 100%; min-width: 1000px; border-collapse: separate; border-spacing: 0; font-size: 14px; color: var(--ak-text); background: #fff; }
    .ak-dt th, .ak-dt td { padding: 14px 10px; text-align: left; vertical-align: top; border-bottom: 1px solid #e5e7eb; background: #fff; }
    .ak-dt-scroll.is-compact .ak-dt th, .ak-dt-scroll.is-compact .ak-dt td { padding-top: 7px; padding-bottom: 7px; }
    .ak-dt-scroll.is-compact .ak-hide-compact { display: none; }  /* compact: drop secondary lines (secondary lines) */

    .ak-dt thead th { position: sticky; top: 0; z-index: 3; background: #f3f4f6; border-bottom: 1px solid var(--ak-border);
        font-size: 12px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: #111827; white-space: nowrap; vertical-align: middle; }
    .ak-dt thead th a { display: inline-flex; align-items: center; gap: 4px; color: inherit; text-decoration: none; }
    .ak-dt thead th a::after { content: "\25B4\25BE"; font-size: 9px; letter-spacing: -1px; color: #9ca3af; opacity: 0; transition: opacity .15s; }
    .ak-dt thead th a:hover::after { opacity: 1; }
    .ak-dt thead th[aria-sort="ascending"] a::after, .ak-dt thead th[aria-sort="descending"] a::after { content: none; }
    .ak-dt thead th[aria-sort="ascending"], .ak-dt thead th[aria-sort="descending"] { color: var(--ak-navy); background: #e0e7ff; }
    .ak-dt thead th a span { font-size: 10px; }

    .ak-dt tbody tr:hover td { background: #f8fafc; }
    .ak-dt tbody tr:last-child td { border-bottom: 0; }
    .ak-dt .ak-c { text-align: center; }
    .ak-dt .ak-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ak-dt td.ak-num:not(:has(div)) { vertical-align: top; }
    .ak-dt tfoot td { position: sticky; bottom: 0; z-index: 3; background: #f3f4f6; border-top: 2px solid var(--ak-border); border-bottom: 0; font-weight: 700; }
    .ak-dt .ak-foot-label { text-align: right; color: var(--ak-muted); font-weight: 600; }

    /* Sticky columns keep the name and the row's actions in view while scrolling sideways */
    .ak-dt .ak-sticky-1 { position: sticky; left: 0; z-index: 2; width: 44px; min-width: 44px; }
    .ak-dt .ak-sticky-2 { position: sticky; left: 44px; z-index: 2; min-width: 180px; box-shadow: 1px 0 0 #e5e7eb; }
    /* Actions stay in the flow (a sticky right column hid Status on laptop widths). */
    .ak-dt .ak-sticky-end { white-space: nowrap; }
    .ak-dt thead .ak-sticky-1, .ak-dt thead .ak-sticky-2 { z-index: 4; }

    .ak-muted { color: var(--ak-muted); font-size: 12px; margin-top: 2px; }
    .ak-mono { font-variant-numeric: tabular-nums; letter-spacing: .01em; }
    .ak-strong { font-weight: 700; }
    .ak-dt a.ak-primary-link { color: var(--ak-text); font-weight: 700; text-decoration: none; }
    .ak-dt a.ak-primary-link:hover { color: var(--ak-navy); text-decoration: underline; }
    .ak-status { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .ak-status i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .ak-status-green { background: var(--ak-green-bg); color: #166534; }
    .ak-status-amber { background: var(--ak-amber-bg); color: #92400e; }
    /* Fixed slots (main button + print + edit + regenerate) so buttons line up in every row */
    .ak-actions { display: grid; grid-template-columns: repeat(3, 32px); align-items: center; justify-content: end; gap: 4px; }
    .ak-actions .ak-icon, .ak-slot { width: 32px; height: 32px; }
    .ak-icon-view { color: var(--ak-navy); background: var(--ak-navy-tint); }
    .ak-icon-view:hover { background: var(--ak-navy); color: #fff; }
    .ak-slot { display: inline-flex; }
    .ak-actions .ak-row-btn { width: 88px; min-width: 0; padding: 0 10px; }
    
    .ak-icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 0; border-radius: 6px; background: transparent; color: #374151; cursor: pointer; }
    .ak-icon svg { width: 18px; height: 18px; }
    .ak-icon:hover { background: #e5e7eb; color: #000; }

    /* Phones: each row becomes a labelled card (no sideways scrolling) */
    /* screen only: in print the table must stay a table even on a narrow page */
    @media screen and (max-width: 767px) {
        .ak-dt { min-width: 0; }
        .ak-dt thead { display: none; }
        .ak-dt, .ak-dt tbody, .ak-dt tr, .ak-dt td, .ak-dt tfoot { display: block; width: 100%; }
        .ak-dt tbody tr { margin: 12px; width: auto; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); overflow: hidden; }
        .ak-dt td { display: flex; justify-content: space-between; gap: 12px; padding: 8px 12px; text-align: right; }
        .ak-dt td::before { content: attr(data-label); font-size: 12px; font-weight: 700; color: var(--ak-muted); text-align: left; }
        .ak-dt td[data-label=""]::before { content: none; }
        .ak-dt .ak-sticky-1, .ak-dt .ak-sticky-2, .ak-dt .ak-sticky-end { position: static; box-shadow: none; width: auto; min-width: 0; }
        .ak-dt tfoot tr { display: flex; justify-content: space-between; }
        .ak-dt tfoot td { position: static; }
        .ak-dt tfoot td:empty { display: none; }
        .ak-actions { justify-content: flex-end; width: 100%; }
        .ak-dt-scroll { max-height: none; }
    }

    /* Pagination + empty */
    .ak-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px 16px; font-size: 14px; color: var(--ak-text); }
    .ak-pager p { margin: 0; }
    .ak-pager-right { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; }
    .ak-perpage { display: flex; align-items: center; gap: 8px; font-size: 13px; }
    .ak-perpage select { height: 32px; padding: 0 28px 0 10px; border: 1px solid var(--ak-border); border-radius: 6px; font-size: 13px; }
    .ak-pager nav > div:last-child > div:first-child { display: none; } /* Laravel's own "Showing x to y" duplicates ours */
    .ak-empty { padding: 56px 24px; text-align: center; }
    .ak-empty svg { width: 44px; height: 44px; margin: 0 auto 12px; color: #9ca3af; }
    .ak-empty h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--ak-text); }
    .ak-empty p { margin: 6px 0 18px; font-size: 14px; color: var(--ak-muted); }

    /* Printed header: only on paper */
    .ak-print-head { display: none; }

    /* ---------------- Print (Ctrl+P): a formal table, no cards or controls ---------------- */
    @media print {
        body { background: #fff !important; }
        nav, header, .ak-kpis, .ak-tabs, .ak-filters, .ak-chips, .ak-dt-toolbar, .ak-pager, .ak-empty .ak-btn,
        .ak-head-actions, [role="alert"], .ak-alert { display: none !important; }
        .ak-page { max-width: none; padding: 0; gap: 0; }
        .ak-card { border: 0; box-shadow: none; border-radius: 0; }

        .ak-print-head { display: block !important; margin-bottom: 8px; color: #000; }
        .ak-print-bank { font-size: 15px; font-weight: 800; text-align: center; }
        .ak-print-title { font-size: 12px; font-weight: 700; text-align: center; margin: 2px 0 8px; }
        .ak-print-meta { width: 100%; border-collapse: collapse; font-size: 9.5px; }
        .ak-print-meta th, .ak-print-meta td { border: 1px solid #000; padding: 3px 6px; text-align: left; vertical-align: top; }
        .ak-print-meta th { width: 70px; background: #eee; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .ak-dt-scroll { max-height: none !important; overflow: visible !important; border: 0; }
        .ak-dt { min-width: 0 !important; width: 100%; font-size: 9.5px; border-collapse: collapse !important; }
        .ak-dt th, .ak-dt td { position: static !important; box-shadow: none !important; padding: 3px 5px !important; border: 1px solid #000 !important; background: #fff !important; color: #000 !important; }
        .ak-dt thead { display: table-header-group; }
        .ak-dt tfoot { display: table-row-group; }
        .ak-dt thead th { background: #e5e7eb !important; font-size: 8.5px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .ak-dt thead th a { color: #000 !important; }
        .ak-dt thead th a::after { content: none !important; }
        .ak-dt tr { break-inside: avoid; }
        .ak-dt .ak-sticky-end { display: none !important; }          /* Actions column */
        .ak-dt a.ak-primary-link { color: #000 !important; text-decoration: none; }
        .ak-status { padding: 0; font-size: 9.5px; font-weight: 700; background: none !important; color: #000 !important; }
        .ak-status i { display: none; }
        .ak-muted { font-size: 8.5px; color: #333 !important; }
        .ak-dt-scroll.is-compact .ak-hide-compact { display: block !important; }
    }

    /* ---------------- Lists: extras used by Users ---------------- */
    .ak-avatar { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%;
        background: var(--ak-navy-tint); color: var(--ak-navy); font-size: 12px; font-weight: 700; letter-spacing: .02em; }
    .ak-person { display: flex; align-items: flex-start; gap: 10px; }
    .ak-pill { display: inline-block; padding: 1px 8px; margin: 0 4px 4px 0; border-radius: 999px; background: #eef2ff; color: #1e3a8a; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .ak-pill-red { background: #fee2e2; color: #991b1b; }
    .ak-status-red { background: #fee2e2; color: #991b1b; }
    .ak-bulk { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 16px; background: #eef2ff; border-top: 1px solid #c7d2fe; border-bottom: 1px solid #c7d2fe; font-size: 13px; color: var(--ak-text); }
    .ak-bulk select { height: 30px; padding: 0 28px 0 10px; border: 1px solid var(--ak-border); border-radius: 6px; font-size: 13px; }
    .ak-dt input[type="checkbox"], .ak-bulk input[type="checkbox"] { width: 16px; height: 16px; border-radius: 4px; border: 1px solid var(--ak-border-strong); color: var(--ak-navy); }
    .ak-icon-danger:hover { background: #fee2e2; color: #b91c1c; }
    @media (min-width: 768px) {
        .ak-filter-row-users { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) minmax(110px, .6fr) auto; }
        .ak-filter-advanced-users { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }

    /* ---------------- Forms (user add / edit) ---------------- */
    .uf-grid { display: grid; gap: 20px; grid-template-columns: 1fr; }
    @media (min-width: 1024px) { .uf-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
    .uf-card { background: #fff; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); box-shadow: var(--ak-shadow); }
    .uf-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--ak-line); background: var(--ak-soft); border-radius: var(--ak-radius) var(--ak-radius) 0 0; }
    .uf-card-title { margin: 0; display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 700; color: var(--ak-text); }
    .uf-step { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--ak-navy); color: #fff; font-size: 12px; font-weight: 700; }
    .uf-card-sub { margin: 2px 0 0 34px; font-size: 12.5px; color: var(--ak-muted); }
    .uf-body { padding: 20px; }
    .uf-fields { display: grid; gap: 16px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .uf-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .uf-field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--ak-text); }
    .uf-field input, .uf-field select { width: 100%; height: 40px; padding: 0 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; background: #fff; color: var(--ak-text); font-size: 14px; }
    .uf-field input:focus, .uf-field select:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .uf-field input[readonly] { background: var(--ak-slate-bg); color: var(--ak-muted); cursor: not-allowed; }
    .uf-req { color: #dc2626; }
    .uf-error { margin: 5px 0 0; font-size: 12.5px; color: #b91c1c; font-weight: 600; }
    .uf-pass { position: relative; }
    .uf-pass input { padding-right: 64px; }
    .uf-pass button { position: absolute; right: 4px; top: 4px; height: 32px; padding: 0 10px; border: 0; border-radius: 6px; background: transparent; font-size: 12px; font-weight: 600; color: var(--ak-muted); cursor: pointer; }
    .uf-pass button:hover { background: var(--ak-slate-bg); color: var(--ak-text); }

    .uf-summary dl { margin: 0; display: flex; flex-direction: column; gap: 10px; font-size: 13.5px; }
    .uf-summary dl > div { display: flex; justify-content: space-between; gap: 12px; }
    .uf-summary dt { color: var(--ak-muted); }
    .uf-summary dd { margin: 0; font-weight: 700; color: var(--ak-text); text-align: right; }
    .uf-summary .uf-total { padding-top: 10px; border-top: 1px dashed var(--ak-line); }
    .uf-bar { height: 6px; border-radius: 999px; background: var(--ak-line); overflow: hidden; }
    .uf-bar span { display: block; height: 100%; background: var(--ak-navy); border-radius: 999px; transition: width .2s; }
    .uf-mod { font-size: 12.5px; }
    .uf-mod div { display: flex; justify-content: space-between; margin-bottom: 4px; }

    .uf-roles { display: grid; gap: 12px; grid-template-columns: 1fr; }
    @media (min-width: 640px) { .uf-roles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .uf-roles { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .uf-role { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid var(--ak-border); border-radius: 10px; background: #fff; cursor: pointer; transition: border-color .15s, background .15s; }
    .uf-role:hover { border-color: var(--ak-border-strong); }
    .uf-role.is-on { border-color: var(--ak-green); background: #f0fdf4; box-shadow: 0 0 0 1px var(--ak-green); }
    .uf-role input { margin-top: 2px; width: 16px; height: 16px; border-radius: 4px; color: var(--ak-green); }
    .uf-role b { display: block; font-size: 14px; color: var(--ak-text); }
    .uf-role small { display: block; font-size: 12px; color: var(--ak-muted); }
    .uf-note { padding: 10px 14px; border-radius: 8px; font-size: 13px; }
    .uf-note-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; }
    .uf-note-warn { background: var(--ak-amber-bg); border: 1px solid #fcd34d; color: #78350f; }

    .uf-actions { position: sticky; bottom: 0; z-index: 20; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 20px;
        background: rgba(255,255,255,.96); border: 1px solid var(--ak-border); border-radius: var(--ak-radius); box-shadow: 0 -4px 16px rgba(15,23,42,.08); backdrop-filter: blur(4px); }
    .uf-actions p { margin: 0; font-size: 12.5px; color: var(--ak-muted); }
    .uf-actions div { display: flex; gap: 10px; }
    .uf-changed { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 999px; background: var(--ak-amber-bg); color: #92400e; font-weight: 700; }

    /* Review modal (same pattern as the Approve modal) */
    .uf-modal { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .uf-modal-bg { position: absolute; inset: 0; background: rgba(15,23,42,.45); backdrop-filter: blur(2px); }
    .uf-modal-box { position: relative; width: 100%; max-width: 560px; max-height: calc(100vh - 32px); overflow: auto; background: #fff; border-radius: 14px; box-shadow: 0 25px 50px rgba(0,0,0,.25); }
    .uf-modal-body { display: flex; gap: 16px; padding: 22px 22px 16px; }
    .uf-modal-icon { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 50%; }
    .uf-modal-icon svg { width: 22px; height: 22px; }
    .uf-modal h3 { margin: 0; font-size: 17px; font-weight: 700; color: var(--ak-text); }
    .uf-review { margin-top: 12px; padding: 12px; border: 1px solid var(--ak-border); border-radius: 8px; background: var(--ak-soft); }
    .uf-review dl { margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px 14px; font-size: 13px; }
    .uf-review dt { color: var(--ak-muted); }
    .uf-review dd { margin: 0; font-weight: 700; color: var(--ak-text); }
    .uf-review .uf-wide { grid-column: 1 / -1; }
    .uf-diff { margin: 10px 0 0; padding: 0; list-style: none; font-size: 12.5px; }
    .uf-diff li { padding: 2px 0; }
    .uf-diff .add { color: #166534; }
    .uf-diff .rem { color: #b91c1c; }
    .uf-warn { margin: 10px 0 0; padding: 0; list-style: none; font-size: 13px; color: #92400e; }
    .uf-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; background: var(--ak-slate-bg); border-radius: 0 0 14px 14px; }

    /* ---------------- Permission matrix ---------------- */
    .pm-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 20px; border-bottom: 1px solid var(--ak-line); }
    .pm-toolbar .ak-search { flex: 1 1 260px; }
    .pm-toolbar .ak-search input { width: 100%; height: 38px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; }
    .pm-tabs { display: flex; flex-wrap: wrap; gap: 4px; padding: 0 20px; border-bottom: 1px solid var(--ak-line); }
    .pm-tabs button { display: inline-flex; align-items: center; gap: 8px; padding: 12px 10px 10px; margin-bottom: -1px; border: 0; border-bottom: 3px solid transparent; background: none;
        font-size: 14px; font-weight: 600; color: var(--ak-muted); cursor: pointer; }
    .pm-tabs button:hover { color: var(--ak-text); }
    .pm-tabs button.is-active { color: var(--ak-navy); border-bottom-color: var(--ak-navy); }
    .pm-tabs button.is-active .ak-count { background: var(--ak-navy); color: #fff; }
    .pm-hint { margin: 0; padding: 10px 20px; font-size: 12.5px; color: var(--ak-muted); background: #fcfcfd; border-bottom: 1px solid var(--ak-line); }
    .pm-scroll { max-height: 34rem; overflow: auto; }
    .pm-section { border-bottom: 1px solid var(--ak-line); }
    .pm-section-head { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 9px 20px; background: #f3f4f6; border-bottom: 1px solid var(--ak-line); }
    .pm-section-head h4 { margin: 0; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #111827; }
    .pm-section-head h4 span { margin-left: 6px; font-weight: 600; color: var(--ak-muted); letter-spacing: 0; text-transform: none; }
    .pm-links { display: flex; gap: 12px; font-size: 12px; font-weight: 600; }
    .pm-links button { border: 0; background: none; padding: 0; cursor: pointer; color: var(--ak-navy); }
    .pm-links button.rem { color: #b91c1c; }
    .pm-links button:hover { text-decoration: underline; }
    .pm-row { display: grid; grid-template-columns: 1fr; gap: 6px; padding: 9px 20px; border-bottom: 1px solid #f1f5f9; }
    .pm-row:last-child { border-bottom: 0; }
    .pm-row:hover { background: #fafafa; }
    @media (min-width: 768px) { .pm-row { grid-template-columns: 240px minmax(0, 1fr); align-items: center; } }
    .pm-row-name { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; color: var(--ak-text); }
    .pm-row-name button { border: 0; background: none; padding: 0; font-size: 11.5px; font-weight: 600; color: var(--ak-navy); cursor: pointer; }
    .pm-row-name button:hover { text-decoration: underline; }
    .pm-row-name .ak-count { font-size: 11px; min-width: 0; padding: 0 6px; }
    .pm-acts { display: flex; flex-wrap: wrap; gap: 6px; }
    .pm-act { position: relative; display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 10px; border: 1px solid var(--ak-border); border-radius: 999px; background: #fff;
        font-size: 12.5px; font-weight: 600; color: #334155; cursor: pointer; user-select: none; transition: background .12s, border-color .12s; }
    .pm-act:hover { border-color: var(--ak-border-strong); }
    .pm-act input { width: 14px; height: 14px; margin: 0; border-radius: 3px; border: 1px solid var(--ak-border-strong); color: var(--ak-navy); }
    .pm-act.is-on { background: var(--ak-navy-tint); border-color: #818cf8; color: var(--ak-navy); }
    .pm-act.is-role { background: var(--ak-green-bg); border-color: #86efac; color: #166534; cursor: default; }
    .pm-act .pm-tick { font-size: 12px; }
    .pm-act small { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
    .pm-act.is-danger.is-on { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
    .pm-empty { padding: 30px 20px; text-align: center; font-size: 14px; color: var(--ak-muted); }
    .pm-legend { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; font-size: 12px; color: var(--ak-muted); }
    .pm-legend .pm-act { height: 22px; font-size: 11px; cursor: default; }
</style>
@endonce
