{{--
    Apple-style skin for the Bank Register list only (/bank-statement-entries). Every rule is scoped under .apl
    (the page) or .apl-on (the header slot), so the shared ak- styles of other screens are untouched.
    Principles (Apple HIG / WWDC "Designing Fluid Interfaces", Emil Kowalski's design engineering):
    system font with size-specific tracking, grouped inset surfaces on a grey canvas, hairline separators,
    a segmented control for the status steps, translucent floating chrome, press feedback on pointer-down,
    strong ease-out curves under 250ms, and gentler equivalents for reduced motion / transparency / more contrast.
    Print is left to the existing black-and-white rules.
--}}
@once
<style>
    :root {
        --apl-bg: #f5f5f7;
        --apl-surface: #ffffff;
        --apl-fill: rgba(118, 118, 128, .12);
        --apl-fill-2: rgba(118, 118, 128, .08);
        --apl-sep: rgba(60, 60, 67, .14);
        --apl-label: #1d1d1f;
        --apl-label-2: #6e6e73;
        --apl-label-3: #86868b;
        --apl-blue: #0071e3;
        --apl-blue-hover: #0077ed;
        --apl-blue-tint: rgba(0, 113, 227, .1);
        --apl-green: #1a7f37;
        --apl-green-tint: rgba(52, 199, 89, .14);
        --apl-red: #d70015;
        --apl-red-tint: rgba(255, 59, 48, .12);
        --apl-orange: #b25000;
        --apl-orange-tint: rgba(255, 149, 0, .16);
        --apl-indigo-tint: rgba(88, 86, 214, .12);
        --apl-radius: 18px;
        --apl-radius-sm: 10px;
        --apl-shadow: 0 1px 1px rgba(0, 0, 0, .02), 0 4px 18px rgba(0, 0, 0, .05);
        --apl-ease-out: cubic-bezier(.23, 1, .32, 1);
        --apl-font: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", system-ui, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }

    /* Canvas: the whole page sits on Apple's light grey, cards float on it */
    .apl-on body { background: var(--apl-bg); }
    .apl-on body .min-h-screen { background: var(--apl-bg) !important; }

    /* ---------- Header: the canvas material with a hairline (not sticky: it is tall and would hide rows) ---------- */
    .apl-on header.bg-white {
        background: rgba(245, 245, 247, .72) !important;
        -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px);
        box-shadow: 0 1px 0 var(--apl-sep) !important;
    }
    .apl-on .ak-head, .apl-on .ak-page, .apl-on .uf-modal, .apl-on .bk-menu, .apl-on .apl-sheet { font-family: var(--apl-font); -webkit-font-smoothing: antialiased; color: var(--apl-label); }
    .apl-on .ak-head { align-items: flex-end; }
    .apl-on .ak-crumbs { font-size: 12px; letter-spacing: 0; color: var(--apl-label-3); }
    .apl-on .ak-crumbs a { color: var(--apl-label-3); }
    .apl-on .ak-title { margin-top: 2px; font-size: 32px; line-height: 1.1; font-weight: 700; letter-spacing: -.022em; color: var(--apl-label); }
    .apl-on .ak-sub { margin-top: 6px; font-size: 15px; line-height: 1.4; letter-spacing: -.01em; color: var(--apl-label-2); }

    /* ---------- Buttons: pill shapes, Apple blue, press feedback on pointer-down ---------- */
    .apl-on .ak-btn, .apl-on .ak-btn {
        height: 36px; padding: 0 16px; border-radius: 980px; border: 0;
        font-size: 14px; font-weight: 500; letter-spacing: -.01em;
        transition: background-color .2s ease, color .2s ease, box-shadow .2s ease, transform .16s var(--apl-ease-out);
    }
    .apl-on .ak-btn:active, .apl-on .ak-btn:active, .apl-on .ak-icon:active, .apl-on .bk-clip:active, .apl-on .apl-seg a:active { transform: scale(.97); transition-duration: .1s; }
    .apl-on .ak-btn-primary, .apl-on .ak-btn-primary { background: var(--apl-blue); color: #fff; }
    .apl-on .ak-btn-primary:hover, .apl-on .ak-btn-primary:hover { background: var(--apl-blue-hover); }
    .apl-on .ak-btn-outline, .apl-on .ak-btn-outline, .apl-on .ak-btn-ghost { background: var(--apl-fill); color: var(--apl-label); }
    .apl-on .ak-btn-outline:hover, .apl-on .ak-btn-outline:hover, .apl-on .ak-btn-ghost:hover { background: rgba(118, 118, 128, .2); border-color: transparent; }
    .apl-on .ak-btn-success { background: var(--apl-green); color: #fff; }
    .apl-on .ak-btn-success:hover { background: #156d2f; }
    .apl-on .ak-btn-sm { height: 28px; padding: 0 12px; font-size: 13px; border-radius: 980px; }
    .apl-on .ak-btn:focus-visible, .apl-on .ak-btn:focus-visible, .apl-on a:focus-visible, .apl-on .ak-icon:focus-visible, .apl-on .ak-dt-scroll:focus-visible {
        outline: 3px solid rgba(0, 113, 227, .45); outline-offset: 2px;
    }
    .apl-on .ak-count, .apl-on .ak-count { min-width: 20px; padding: 0 6px; border-radius: 980px; font-size: 12px; font-weight: 600; line-height: 20px; }
    .apl-on .ak-count-dark, .apl-on .ak-count-dark { background: var(--apl-blue); color: #fff; }

    /* ---------- Page rhythm ---------- */
    .apl-on .ak-page { gap: 20px; padding-top: 28px; }
    .apl-on .ak-card { background: var(--apl-surface); border: 0; border-radius: var(--apl-radius); box-shadow: var(--apl-shadow); }
    .apl-on .ak-alert { border-radius: 14px; border: 0; }
    .apl-on .ak-alert[role="note"] { margin: 14px 20px 0 !important; padding: 12px 16px; border: 0 !important; background: var(--apl-blue-tint) !important; color: #00407f !important; font-size: 13px; line-height: 1.45; }

    /* ---------- Summary: large tabular figures, like the Wallet / Stocks headers ---------- */
    .apl-summary { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 900px) { .apl-summary { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .apl-stat { padding: 18px 20px; background: var(--apl-surface); border-radius: var(--apl-radius); box-shadow: var(--apl-shadow); }
    .apl-stat-label { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 13px; font-weight: 500; letter-spacing: -.005em; color: var(--apl-label-2); }
    .apl-stat-label i { width: 8px; height: 8px; border-radius: 50%; background: var(--apl-label-3); }
    .apl-stat-value { margin: 6px 0 0; font-size: 28px; line-height: 1.1; font-weight: 600; letter-spacing: -.025em; font-variant-numeric: tabular-nums; color: var(--apl-label); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .apl-stat-value small { margin-inline-end: 4px; font-size: 14px; font-weight: 500; letter-spacing: 0; color: var(--apl-label-3); }
    .apl-stat.is-in .apl-stat-label i { background: #34c759; }
    .apl-stat.is-out .apl-stat-label i { background: #ff3b30; }
    .apl-stat.is-net .apl-stat-label i { background: var(--apl-blue); }
    .apl-stat.is-in .apl-stat-value { color: var(--apl-green); }
    .apl-stat.is-out .apl-stat-value { color: var(--apl-red); }
    @media (max-width: 640px) { .apl-stat-value { font-size: 22px; } }

    /* ---------- Filters panel ---------- */
    .apl-on #bk-filter-panel { overflow: hidden; }
    .apl-on .ak-filters { padding: 20px; border: 0; background: var(--apl-surface) !important; }
    .apl-on .ak-field label { margin-bottom: 6px; font-size: 12px; font-weight: 500; letter-spacing: 0; color: var(--apl-label-2); }
    .apl-on .ak-field input, .apl-on .ak-field select {
        height: 38px; border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2);
        font-family: var(--apl-font); font-size: 15px; letter-spacing: -.01em; color: var(--apl-label);
        transition: box-shadow .2s ease, background-color .2s ease;
    }
    .apl-on .ak-field input:focus, .apl-on .ak-field select:focus { background: #fff; box-shadow: 0 0 0 4px rgba(0, 113, 227, .25), 0 0 0 1px var(--apl-blue); }
    .apl-on .ak-search svg { color: var(--apl-label-3); }
    .apl-on .select2-container--default .select2-selection--single { height: 38px; border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2); }
    .apl-on .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; color: var(--apl-label); font-size: 15px; }
    .apl-on .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
    .apl-on .select2-container--default.select2-container--focus .select2-selection--single,
    .apl-on .select2-container--default.select2-container--open .select2-selection--single { background: #fff; box-shadow: 0 0 0 4px rgba(0, 113, 227, .25), 0 0 0 1px var(--apl-blue); }
    .apl-on .bk-advanced { padding-top: 16px; margin-top: 16px; border-top: 1px solid var(--apl-sep); }
    .apl-on .bk-ranges { margin-top: 16px; gap: 8px; }
    .apl-on .bk-ranges-label { font-size: 12px; font-weight: 500; color: var(--apl-label-2); }
    .apl-on .bk-ranges a { padding: 5px 12px; border: 0; border-radius: 980px; background: var(--apl-fill-2); font-size: 13px; font-weight: 500; color: var(--apl-label); transition: background-color .2s ease, color .2s ease; }
    .apl-on .bk-ranges a:hover { background: var(--apl-fill); }
    .apl-on .bk-ranges a[aria-current] { background: var(--apl-label); color: #fff; }
    .apl-on .bk-ranges a.bk-clear { background: transparent; color: var(--apl-red); }
    .apl-on .bk-ranges a.bk-clear:hover { background: var(--apl-red-tint); }

    /* ---------- Status steps: a segmented control (scrolls sideways on small screens) ---------- */
    .apl-on .ak-tabs { display: flex; flex-wrap: nowrap; align-items: center; gap: 12px; padding: 16px 20px 0; border: 0; overflow-x: auto; scrollbar-width: none; }
    .apl-on .ak-tabs::-webkit-scrollbar { display: none; }
    .apl-on .apl-seg { display: inline-flex; flex: none; gap: 2px; padding: 3px; border-radius: 12px; background: var(--apl-fill); }
    .apl-on .apl-seg .ak-tab {
        gap: 6px; margin: 0; padding: 6px 12px; border: 0; border-radius: 9px;
        font-size: 13px; font-weight: 500; letter-spacing: -.01em; color: var(--apl-label); white-space: nowrap;
        transition: background-color .2s ease, box-shadow .2s ease, color .2s ease, transform .16s var(--apl-ease-out);
    }
    .apl-on .apl-seg .ak-tab:hover { background: rgba(255, 255, 255, .55); border: 0; }
    .apl-on .apl-seg .ak-tab.is-active { background: #fff; color: var(--apl-label); font-weight: 600; box-shadow: 0 3px 8px rgba(0, 0, 0, .12), 0 3px 1px rgba(0, 0, 0, .04); }
    .apl-on .apl-seg .ak-tab.is-empty { color: var(--apl-label-3); }
    .apl-on .apl-seg .ak-count { min-width: 0; padding: 0; background: none; color: var(--apl-label-3); font-weight: 500; font-variant-numeric: tabular-nums; }
    .apl-on .apl-seg .ak-tab.is-active .ak-count { background: none; color: var(--apl-label-2); }
    .apl-on .ak-tabs .bk-tab-extra { flex: none; margin-inline-start: auto; padding: 6px 12px; border: 0; border-radius: 980px; background: var(--apl-orange-tint); color: var(--apl-orange); font-size: 13px; font-weight: 600; }
    .apl-on .ak-tabs .bk-tab-extra.is-active { background: #ff9500; color: #fff; }

    /* ---------- Filter chips ---------- */
    .apl-on .ak-chips { padding: 14px 20px 0; border: 0; gap: 8px; }
    .apl-on .ak-chips-label { font-weight: 500; color: var(--apl-label-2); }
    .apl-on .ak-chip { padding: 4px 10px; border: 0; border-radius: 980px; background: var(--apl-blue-tint); color: var(--apl-blue); font-size: 13px; transition: background-color .2s ease, color .2s ease; }
    .apl-on .ak-chip b { font-weight: 600; }
    .apl-on .ak-chip:hover { background: var(--apl-red-tint); color: var(--apl-red); border: 0; }
    .apl-on .ak-chips-clear { font-size: 13px; font-weight: 500; color: var(--apl-blue); text-decoration: none; }

    /* ---------- Table: hairlines, quiet header, roomy rows ---------- */
    .apl-on .ak-dt-toolbar { padding: 14px 20px 10px; font-size: 13px; color: var(--apl-label-2); }
    .apl-on .ak-dt-toolbar b { font-weight: 600; font-variant-numeric: tabular-nums; }
    .apl-on .ak-dt-scroll { border: 0; border-top: 1px solid var(--apl-sep); }
    .apl-on .ak-dt { font-size: 14px; letter-spacing: -.005em; color: var(--apl-label); }
    .apl-on .ak-dt th, .apl-on .ak-dt td { padding: 13px 12px; border-bottom: 1px solid var(--apl-sep); vertical-align: middle; }
    .apl-on .ak-dt th:first-child, .apl-on .ak-dt td:first-child { padding-left: 20px; }
    .apl-on .ak-dt th:last-child, .apl-on .ak-dt td:last-child { padding-right: 20px; }
    .apl-on .ak-dt thead th {
        background: rgba(255, 255, 255, .86); -webkit-backdrop-filter: saturate(180%) blur(12px); backdrop-filter: saturate(180%) blur(12px);
        border-bottom: 1px solid var(--apl-sep); padding-top: 10px; padding-bottom: 10px;
        font-size: 12px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--apl-label-2);
    }
    .apl-on .ak-dt tbody td, .apl-on .ak-dt td.ak-num:not(:has(div)) { vertical-align: middle; transition: background-color .15s ease; }
    .apl-on .apl-dup { display: none; } /* the totals cards above already show these; kept for print */
    .apl-on .ak-dt tbody tr:hover td { background: #fafafc; }
    .apl-on .ak-dt tbody tr:has(input[type="checkbox"]:checked) td { background: var(--apl-blue-tint); }
    .apl-on .ak-dt .ak-mono { color: var(--apl-label-2); }
    .apl-on .ak-dt a.ak-primary-link { font-weight: 600; color: var(--apl-label); }
    .apl-on .ak-dt a.ak-primary-link:hover { color: var(--apl-blue); text-decoration: none; }
    .apl-on .ak-muted { margin-top: 2px; font-size: 12px; color: var(--apl-label-3); }
    .apl-on .ak-muted b { font-weight: 600; color: var(--apl-label-2); }
    .apl-on .ak-dt .bk-in, .apl-on .bk-in { color: var(--apl-green); font-weight: 500; }
    .apl-on .ak-dt .bk-out, .apl-on .bk-out { color: var(--apl-red); font-weight: 500; }
    .apl-on input[type="checkbox"] { width: 18px; height: 18px; border-radius: 5px; border-color: rgba(60, 60, 67, .3); color: var(--apl-blue); cursor: pointer; }
    .apl-on input[type="checkbox"]:focus { box-shadow: 0 0 0 3px rgba(0, 113, 227, .3); }

    /* Status: tinted capsules, system colours */
    .apl-on .ak-status { padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; letter-spacing: -.005em; }
    .apl-on .ak-status i { width: 6px; height: 6px; }
    .apl-on .ak-status-slate { background: var(--apl-fill-2); color: #48484a; }
    .apl-on .ak-status-blue { background: var(--apl-blue-tint); color: #0058b0; }
    .apl-on .ak-status-amber { background: var(--apl-orange-tint); color: var(--apl-orange); }
    .apl-on .ak-status-green { background: var(--apl-green-tint); color: var(--apl-green); }
    .apl-on .ak-status-red { background: var(--apl-red-tint); color: var(--apl-red); }

    .apl-on .bk-clip { height: 26px; padding: 0 9px; border-radius: 980px; background: var(--apl-fill-2); color: var(--apl-label); font-weight: 600; }
    .apl-on .bk-clip:hover { background: var(--apl-blue); color: #fff; }
    .apl-on .bk-clip svg { width: 15px; height: 15px; }
    .apl-on .ak-icon { border-radius: 980px; transition: background-color .2s ease, color .2s ease, transform .16s var(--apl-ease-out); }
    .apl-on .ak-icon-view { background: var(--apl-blue-tint); color: var(--apl-blue); }
    .apl-on .ak-icon-view:hover { background: var(--apl-blue); color: #fff; }

    /* ---------- Bulk bar: floating translucent toolbar ---------- */
    .apl-on #bulk-form.ak-bulk {
        bottom: 24px; padding: 10px 14px 10px 20px; border: 1px solid rgba(255, 255, 255, .5); border-radius: 22px;
        background: rgba(255, 255, 255, .72) !important; -webkit-backdrop-filter: saturate(180%) blur(24px); backdrop-filter: saturate(180%) blur(24px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, .14), 0 0 0 .5px rgba(0, 0, 0, .08);
        font-size: 14px; color: var(--apl-label);
        animation: apl-rise .28s var(--apl-ease-out);
    }
    @keyframes apl-rise { from { opacity: 0; transform: translate(-50%, 12px) scale(.98); } to { opacity: 1; transform: translate(-50%, 0) scale(1); } }

    /* ---------- Pager + empty ---------- */
    .apl-on .ak-pager { padding: 14px 20px 18px; font-size: 13px; color: var(--apl-label-2); }
    .apl-on .ak-pager b { font-weight: 600; color: var(--apl-label); }
    .apl-on .ak-pager nav span[aria-current] span, .apl-on .ak-pager nav a, .apl-on .ak-pager nav span > span { border-color: var(--apl-sep) !important; }
    .apl-on .ak-pager nav span[aria-current] span { background: var(--apl-label) !important; color: #fff !important; }
    .apl-on .ak-empty { padding: 72px 24px; }
    .apl-on .ak-empty svg { color: var(--apl-label-3); }
    .apl-on .ak-empty h2 { font-size: 22px; font-weight: 600; letter-spacing: -.02em; }
    .apl-on .ak-empty p { font-size: 15px; color: var(--apl-label-2); }

    /* Panels that open (filters, menu) materialise: scale + fade from just above, strong ease-out */
    .apl-on [x-transition], .apl-menu-in { transform-origin: top center; }

    /* ---------- Tabs on the other screens (no segmented wrapper): the track is the .ak-tabs row itself ---------- */
    .apl-on .ak-tabs:not(:has(.apl-seg)) { width: fit-content; max-width: calc(100% - 40px); margin: 16px 20px 0; padding: 3px; gap: 2px; border-radius: 12px; background: var(--apl-fill); }
    .apl-on .ak-tabs .ak-tab {
        gap: 6px; margin: 0; padding: 6px 12px; border: 0; border-radius: 9px;
        font-size: 13px; font-weight: 500; letter-spacing: -.01em; color: var(--apl-label); white-space: nowrap;
        transition: background-color .2s ease, box-shadow .2s ease, transform .16s var(--apl-ease-out);
    }
    .apl-on .ak-tabs .ak-tab:hover { background: rgba(255, 255, 255, .55); border: 0; color: var(--apl-label); }
    .apl-on .ak-tabs .ak-tab.is-active { background: #fff; color: var(--apl-label); font-weight: 600; box-shadow: 0 3px 8px rgba(0, 0, 0, .12), 0 3px 1px rgba(0, 0, 0, .04); }
    .apl-on .ak-tabs .ak-tab:active { transform: scale(.97); }
    .apl-on .ak-tabs .ak-count { min-width: 0; padding: 0; background: none; color: var(--apl-label-3); font-weight: 500; }

    /* KPI cards used by some bank screens */
    .apl-on .ak-kpi { border: 0; border-radius: var(--apl-radius); box-shadow: var(--apl-shadow); transition: box-shadow .2s ease, transform .2s var(--apl-ease-out); }
    .apl-on a.ak-kpi:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0, 0, 0, .04), 0 12px 28px rgba(0, 0, 0, .08); }
    .apl-on .ak-kpi.is-active { box-shadow: 0 0 0 2px var(--apl-blue), var(--apl-shadow); }
    .apl-on .ak-kpi-icon { border-radius: 12px; }
    .apl-on .ak-kpi-label { font-weight: 500; color: var(--apl-label-2); }
    .apl-on .ak-kpi-value { font-weight: 600; letter-spacing: -.025em; }

    /* ---------- Menu panel: a grouped popover list, like a macOS menu / iOS inset list ---------- */
    .apl-on .bk-menu {
        position: relative; margin-top: 12px; padding: 8px; gap: 0; border: 0; border-radius: 20px;
        background: rgba(255, 255, 255, .82) !important; -webkit-backdrop-filter: saturate(180%) blur(24px); backdrop-filter: saturate(180%) blur(24px);
        box-shadow: 0 0 0 .5px rgba(0, 0, 0, .08), 0 18px 50px rgba(0, 0, 0, .12);
    }
    .apl-on .bk-menu-group { gap: 2px 6px; padding: 10px 8px; }
    .apl-on .bk-menu-group + .bk-menu-group { padding-top: 10px; border-top: 1px solid var(--apl-sep); }
    .apl-on .bk-menu-title { margin: 0 0 4px 10px; font-size: 12px; font-weight: 600; letter-spacing: 0; text-transform: none; color: var(--apl-label-3); }
    .apl-on .bk-menu-item { gap: 2px; padding: 10px 12px; border-radius: 12px; transition: background-color .15s ease, transform .16s var(--apl-ease-out); }
    .apl-on .bk-menu-item:hover { background: var(--apl-fill-2); }
    .apl-on .bk-menu-item:active { transform: scale(.98); }
    .apl-on .bk-menu-item.is-active { background: var(--apl-blue-tint); }
    .apl-on .bk-menu-item b { font-size: 14px; font-weight: 600; letter-spacing: -.01em; color: var(--apl-label); }
    .apl-on .bk-menu-item.is-active b { color: var(--apl-blue); }
    .apl-on .bk-menu-item span { font-size: 12px; color: var(--apl-label-2); }
    .apl-on .bk-menu-item .bk-badge { background: #ff9500; color: #fff; }
    .apl-on .bk-menu-standalone .ak-btn { height: 36px; }

    /* ---------- Modals: one sheet style for the transaction, action and help windows ---------- */
    .apl-on .uf-modal-bg { background: rgba(0, 0, 0, .28); -webkit-backdrop-filter: blur(8px) saturate(120%); backdrop-filter: blur(8px) saturate(120%); }
    .apl-on .uf-modal-box { border-radius: 22px; box-shadow: 0 0 0 .5px rgba(0, 0, 0, .1), 0 30px 80px rgba(0, 0, 0, .25); }
    .apl-on .bk-modal-box { border-radius: 22px; background: #fff; animation: apl-sheet .3s var(--apl-ease-out); }
    @keyframes apl-sheet { from { opacity: 0; transform: translateY(10px) scale(.97); } to { opacity: 1; transform: none; } }
    .apl-on .bk-modal-head,
    .apl-on .bk-modal-head.bk-head-red {
        padding: 14px 16px 14px 22px; background: rgba(255, 255, 255, .86); color: var(--apl-label);
        -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px);
        border-bottom: 1px solid var(--apl-sep);
    }
    .apl-on .bk-modal-head h3 { font-size: 17px; font-weight: 600; letter-spacing: -.02em; color: var(--apl-label); }
    .apl-on .bk-modal-head p { margin-top: 1px; font-size: 13px; color: var(--apl-label-2); }
    .apl-on .bk-modal-head.bk-head-red h3 { color: var(--apl-red); }
    .apl-on .bk-modal-head .ak-btn { background: var(--apl-fill); color: var(--apl-label); border: 0; }
    .apl-on .bk-modal-head .ak-btn:hover { background: rgba(118, 118, 128, .2); }
    .apl-on .bk-modal-body { padding: 20px 22px 24px; }
    .apl-on .bk-modal-foot { padding: 12px 16px; border-top: 1px solid var(--apl-sep); background: var(--apl-bg); }
    .apl-on .bk-action-box textarea { border: 0; border-radius: 12px; background: var(--apl-fill-2); font-family: var(--apl-font); }
    .apl-on .bk-action-box textarea:focus { outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0, 113, 227, .25), 0 0 0 1px var(--apl-blue); }
    .apl-on .bk-action-box label { font-weight: 500; color: var(--apl-label-2); }
    .apl-on .bk-btn-red { background: #ff3b30; border: 0; } .apl-on .bk-btn-red:hover { background: var(--apl-red); }
    .apl-on .bk-btn-green { background: #34c759; border: 0; color: #fff; } .apl-on .bk-btn-green:hover { background: var(--apl-green); }
    .apl-on .ak-btn-danger-outline { background: var(--apl-red-tint); color: var(--apl-red); border: 0; }
    .apl-on .ak-btn-danger-outline:hover { background: #ff3b30; color: #fff; }
    .apl-on .bk-skeleton i { border-radius: 10px; background: var(--apl-fill-2); }

    /* Detail pieces inside the transaction window and show/edit screens */
    .apl-on .bk-hero { border: 0; border-radius: 16px; background: var(--apl-bg); }
    .apl-on .bk-amount { font-weight: 600; letter-spacing: -.03em; }
    .apl-on .bk-box { border: 0; border-radius: 14px; background: var(--apl-bg); }
    .apl-on .bk-box h4, .apl-on .bk-dl dt { font-size: 12px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--apl-label-2); }
    .apl-on .bk-dl dd { font-weight: 500; color: var(--apl-label); }
    .apl-on table.bk-t { border: 0; border-radius: 14px; box-shadow: 0 0 0 1px var(--apl-sep); }
    .apl-on table.bk-t th, .apl-on table.bk-t td { border-bottom-color: var(--apl-sep); }
    .apl-on table.bk-t th { background: var(--apl-bg); font-size: 12px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--apl-label-2); }
    .apl-on table.bk-t tr.cap th { background: #fff; font-weight: 600; letter-spacing: -.01em; }
    .apl-on table.bk-t a.ak-primary-link { color: var(--apl-blue); text-decoration: none; }
    .apl-on .bk-file-type { border-radius: 8px; background: var(--apl-blue-tint); color: var(--apl-blue); }
    .apl-on .bk-attach { border: 0; border-radius: 14px; background: var(--apl-bg); }
    .apl-on .bk-note { border: 0; border-radius: 14px; background: var(--apl-red-tint); color: #8a0010; }
    .apl-on .bk-actions { border-top-color: var(--apl-sep); }
    .apl-on .ak-field textarea { border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2); font-family: var(--apl-font); font-size: 15px; }
    .apl-on .ak-field textarea:focus { outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0, 113, 227, .25), 0 0 0 1px var(--apl-blue); }

    /* ---------- Urdu guide: full page and the per-screen Help window ---------- */
    .apl-on .bh-toc, .apl-on .bh section { border: 0; border-radius: var(--apl-radius); box-shadow: var(--apl-shadow); }
    .apl-on .bh-toc { background: rgba(255, 255, 255, .8); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); }
    .apl-on .bh-toc b { color: var(--apl-label); }
    .apl-on .bh-toc a { display: block; margin: 0 -10px; padding: 0 10px; border-radius: 10px; transition: background-color .15s ease; }
    .apl-on .bh-toc a:hover { background: var(--apl-fill-2); color: var(--apl-blue); text-decoration: none; }
    .apl-on .bh h2 { border-bottom: 1px solid var(--apl-sep); color: var(--apl-label); }
    .apl-on .bh h3 { color: var(--apl-blue); }
    .apl-on .bh .en { border-radius: 7px; background: var(--apl-fill-2); color: var(--apl-label); font-family: var(--apl-font); }
    .apl-on .bh .note, .apl-on .bh .tip, .apl-on .bh .warn { border: 0; border-radius: 14px; }
    .apl-on .bh .note { background: var(--apl-orange-tint); } .apl-on .bh .tip { background: var(--apl-blue-tint); } .apl-on .bh .warn { background: var(--apl-red-tint); }
    .apl-on .bh .flow .step { border: 0; background: var(--apl-fill-2); }
    .apl-on .bh figure img { border: 0; border-radius: 14px; box-shadow: 0 0 0 1px var(--apl-sep), 0 8px 24px rgba(0, 0, 0, .06); }
    .apl-on .bk-help-box { width: min(96vw, 52rem); max-width: 52rem; }
    .apl-on .bk-help-box .bk-modal-body { background: var(--apl-bg); }
    .apl-on .bk-help-box .bh-main { gap: 14px; }
    .apl-on .bk-help-box .bh section { padding: 16px 22px; box-shadow: 0 0 0 1px var(--apl-sep); }
    .apl-on .bk-help-box .bh h2 { font-size: 21px; }

    /* ---------- Screens on the older page header (x-page-header) and Tailwind tables: Categories, report sheets ---------- */
    .apl-on header.bg-white h2 { font-family: var(--apl-font); font-size: 28px; font-weight: 700; letter-spacing: -.022em; color: var(--apl-label); }
    .apl-on header.bg-white .bg-blue-950 {
        height: 36px; padding: 0 12px; border: 0; border-radius: 980px; background: var(--apl-fill) !important; color: var(--apl-label) !important;
        transition: background-color .2s ease, transform .16s var(--apl-ease-out);
    }
    .apl-on header.bg-white .bg-blue-950 svg { width: 20px; height: 20px; }
    .apl-on header.bg-white .bg-blue-950:hover { background: rgba(118, 118, 128, .2) !important; }
    .apl-on header.bg-white .bg-blue-950:active { transform: scale(.95); }
    .apl-on header.bg-white .flex > a.bg-blue-950:first-child, .apl-on header.bg-white .flex > div:first-child > a.bg-blue-950 { background: var(--apl-blue) !important; color: #fff !important; }
    .apl-on .bec-table, .apl-on #filters { font-family: var(--apl-font); }
    .apl-on .bec-table table { overflow: hidden; border-radius: var(--apl-radius); box-shadow: var(--apl-shadow); background: #fff; }
    .apl-on .bec-table thead tr, .apl-on .bec-table thead th { background: #fff !important; color: var(--apl-label-2) !important; font-size: 12px; font-weight: 500; letter-spacing: 0; text-transform: none; }
    .apl-on .bec-table thead th { padding-top: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--apl-sep); }
    .apl-on .bec-table tbody tr { border-color: var(--apl-sep); transition: background-color .15s ease; }
    .apl-on .bec-table tbody tr:hover { background: #fafafc; }
    .apl-on .bec-table td { padding-top: 10px !important; padding-bottom: 10px !important; }
    .apl-on .bec-table td .rounded-md { border-radius: 980px; }
    .apl-on .bk-menu-standalone { align-items: center; }

    /* ---------- Forms (add / edit entry, setup forms, scans, reconciliation) ---------- */
    .apl-on .uf-card { border: 0; border-radius: var(--apl-radius); box-shadow: var(--apl-shadow); overflow: hidden; }
    .apl-on .uf-card-head { padding: 18px 22px 6px; border: 0; background: #fff; }
    .apl-on .uf-card-title { font-size: 19px; font-weight: 600; letter-spacing: -.02em; color: var(--apl-label); }
    .apl-on .uf-card-sub { color: var(--apl-label-2); }
    .apl-on .uf-field label { font-size: 12px; font-weight: 500; color: var(--apl-label-2); }
    .apl-on .uf-field input:not([type="checkbox"]):not([type="radio"]):not([type="file"]), .apl-on .uf-field select, .apl-on .uf-field textarea {
        border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2); font-family: var(--apl-font); font-size: 15px; color: var(--apl-label);
        transition: box-shadow .2s ease, background-color .2s ease;
    }
    .apl-on .uf-field input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):focus, .apl-on .uf-field select:focus, .apl-on .uf-field textarea:focus {
        outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0, 113, 227, .25), 0 0 0 1px var(--apl-blue);
    }
    .apl-on .uf-field input[type="file"] { padding: 6px; border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2); }
    .apl-on .uf-field input[type="file"]::file-selector-button { margin-inline-end: 10px; padding: 5px 12px; border: 0; border-radius: 980px; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, .1); font: 500 13px var(--apl-font); cursor: pointer; }
    .apl-on .uf-field .select2-container--default .select2-selection--single { height: 40px; border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2); }
    .apl-on .uf-field .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px; font-size: 15px; color: var(--apl-label); }

    /* Two-choice switches (Received / Paid out, Waiting / Linked): segmented control */
    .apl-on .ak-seg { gap: 2px; padding: 2px; border: 0; border-radius: 10px; background: var(--apl-fill); }
    .apl-on .ak-seg a, .apl-on .ak-seg span, .apl-on .ak-seg button {
        padding: 7px 14px; border: 0 !important; border-radius: 8px; background: transparent; color: var(--apl-label);
        font: 500 13px var(--apl-font); letter-spacing: -.01em; cursor: pointer;
        transition: background-color .2s ease, box-shadow .2s ease, transform .16s var(--apl-ease-out);
    }
    .apl-on .ak-seg a:hover, .apl-on .ak-seg button:hover { background: rgba(255, 255, 255, .55); }
    .apl-on .ak-seg [aria-current], .apl-on .ak-seg .is-on, .apl-on .ak-seg [aria-pressed="true"] {
        background: #fff !important; color: var(--apl-label) !important; font-weight: 600; box-shadow: 0 3px 8px rgba(0, 0, 0, .12), 0 3px 1px rgba(0, 0, 0, .04);
    }
    .apl-on .ak-seg button:active, .apl-on .ak-seg a:active { transform: scale(.97); }

    /* Reports index tiles */
    .apl-on .bk-card-link { border: 0; border-radius: var(--apl-radius); box-shadow: var(--apl-shadow); transition: box-shadow .2s ease, transform .2s var(--apl-ease-out); }
    .apl-on .bk-card-link:hover { transform: translateY(-2px); box-shadow: 0 2px 4px rgba(0, 0, 0, .04), 0 14px 32px rgba(0, 0, 0, .09); }
    .apl-on .bk-card-link:active { transform: scale(.98); }
    .apl-on .bk-card-link b { font-size: 16px; font-weight: 600; letter-spacing: -.015em; color: var(--apl-label); }
    .apl-on .bk-card-link span { color: var(--apl-label-2); }

    /* ---------- Expense vouchers and vendor payments (ev- / vp- / pv- / xs- classes) ---------- */
    html.apl-on .ev-ur { color: inherit; }
    html.apl-on .ak-title .ev-ur { color: var(--apl-label-2); font-weight: 600; }
    html.apl-on .ev-form .ak-field input:not([type="radio"]):not([type="checkbox"]):not(.ev-file), html.apl-on .ev-form .ak-field select, html.apl-on .ev-form .ak-field textarea,
    html.apl-on .ev-form .select2-container .select2-selection--single, html.apl-on .ev-modal-grid .ak-field input, html.apl-on .ev-modal-grid .ak-field select {
        border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2); font-family: var(--apl-font); font-size: 15px; color: var(--apl-label);
    }
    html.apl-on .ev-form .ak-field input:focus, html.apl-on .ev-form .ak-field select:focus, html.apl-on .ev-form .ak-field textarea:focus,
    html.apl-on .ev-form .select2-container--focus .select2-selection--single, html.apl-on .ev-form .select2-container--open .select2-selection--single {
        background: #fff; box-shadow: 0 0 0 4px rgba(0, 113, 227, .25), 0 0 0 1px var(--apl-blue);
    }
    html.apl-on .ev-form .ak-field input.ev-file { border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2); }
    html.apl-on .ev-form .ak-field input.ev-file::file-selector-button { border: 0; border-radius: 980px; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, .1); font-family: var(--apl-font); font-weight: 500; }
    html.apl-on .ev-seg { gap: 2px; padding: 3px; border-radius: 12px; background: var(--apl-fill); width: fit-content; max-width: 100%; }
    html.apl-on .ev-seg label { padding: 8px 14px; border: 0; border-radius: 9px; background: transparent; font-weight: 500; color: var(--apl-label); box-shadow: none;
        transition: background-color .2s ease, box-shadow .2s ease, transform .16s var(--apl-ease-out); }
    html.apl-on .ev-seg label:hover { background: rgba(255, 255, 255, .55); }
    html.apl-on .ev-seg label:active { transform: scale(.97); }
    html.apl-on .ev-seg label.is-on { background: #fff; color: var(--apl-label); font-weight: 600; box-shadow: 0 3px 8px rgba(0, 0, 0, .12), 0 3px 1px rgba(0, 0, 0, .04); }
    html.apl-on .ev-seg input { accent-color: var(--apl-blue); }
    html.apl-on .ev-panel { border: 0; border-radius: 16px; background: var(--apl-bg); }
    html.apl-on .ev-note { border: 0; border-radius: 12px; background: var(--apl-blue-tint); color: #00407f; }
    html.apl-on .ev-form-foot { border-top-color: var(--apl-sep); }
    html.apl-on .ev-current-file a { color: var(--apl-blue); text-decoration: none; }
    html.apl-on .ev-ranges a { border: 0; border-radius: 980px; background: var(--apl-fill-2); font-weight: 500; color: var(--apl-label); }
    html.apl-on .ev-ranges a:hover { background: var(--apl-fill); }
    html.apl-on .ev-ranges a[aria-current] { background: var(--apl-label); color: #fff; }
    html.apl-on .ev-ranges a.ev-clear { background: transparent; color: var(--apl-red); }
    html.apl-on .ev-ranges-label { font-weight: 500; color: var(--apl-label-2); }
    html.apl-on .ak-filters .select2-container .select2-selection--single { border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2); }
    html.apl-on .ev-table tfoot td, html.apl-on .ak-dt tfoot td { background: rgba(255, 255, 255, .92); border-top: 1px solid var(--apl-sep); font-weight: 600; }
    html.apl-on .ak-dt th .ev-ur { color: inherit; }
    /* voucher page: progress steps, details, ledger lines */
    html.apl-on .ev-pad, html.apl-on .pv-pad { padding: 20px 22px; }
    html.apl-on .ev-steps li { font-weight: 500; color: var(--apl-label-3); }
    html.apl-on .ev-steps li + li::before { background: var(--apl-sep); }
    html.apl-on .ev-steps .dot { border: 0; background: var(--apl-fill); color: var(--apl-label-2); }
    html.apl-on .ev-steps li.is-done { color: var(--apl-green); } html.apl-on .ev-steps li.is-done .dot { background: #34c759; color: #fff; }
    html.apl-on .ev-steps li.is-now { color: var(--apl-blue); } html.apl-on .ev-steps li.is-now .dot { background: var(--apl-blue); color: #fff; }
    html.apl-on .ev-steps li.is-bad { color: var(--apl-red); } html.apl-on .ev-steps li.is-bad .dot { background: #ff3b30; color: #fff; }
    html.apl-on .ev-dl dt, html.apl-on .pv-dl dt { font-size: 12px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--apl-label-2); }
    html.apl-on .ev-dl dd, html.apl-on .pv-dl dd { font-weight: 500; color: var(--apl-label); }
    html.apl-on .ev-amount { font-weight: 600; letter-spacing: -.03em; }
    html.apl-on .ev-lines th, html.apl-on .vp-table th, html.apl-on .pv-t th {
        font-size: 12px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--apl-label-2); border-bottom: 1px solid var(--apl-sep);
    }
    html.apl-on .ev-lines td, html.apl-on .vp-table td, html.apl-on .pv-t td { border-bottom: 1px solid var(--apl-sep); }
    html.apl-on .vp-table input[type=number] { height: 36px; border: 0; border-radius: var(--apl-radius-sm); background: var(--apl-fill-2); font-family: var(--apl-font); font-size: 15px; font-variant-numeric: tabular-nums; }
    html.apl-on .vp-table input[type=number]:focus { outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0, 113, 227, .25), 0 0 0 1px var(--apl-blue); }
    html.apl-on .vp-total { border-top: 1px solid var(--apl-sep); background: rgba(255, 255, 255, .86); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); }
    html.apl-on .xs-kpi { border: 0; border-radius: var(--apl-radius); box-shadow: var(--apl-shadow); }
    html.apl-on .xs-kpi small { font-size: 13px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--apl-label-2); }
    html.apl-on .xs-t th { background: #fff; font-size: 12px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--apl-label-2); border-bottom: 1px solid var(--apl-sep); }
    html.apl-on .xs-t td { border-bottom: 1px solid var(--apl-sep); }
    html.apl-on .xs-kpi b { font-weight: 600; letter-spacing: -.025em; }
    /* Tailwind-built dialogs on these screens (new shop, approve, reject...) */
    html.apl-on .ak-page [role="dialog"] .rounded-lg, html.apl-on .ak-page [role="dialog"] .rounded-xl { border-radius: 22px; }

    /* ---------- Phones: rows become inset grouped cards ---------- */
    @media screen and (max-width: 767px) {
        .apl-on .ak-title { font-size: 28px; }
        .apl-on .ak-dt tbody tr { margin: 10px 12px; border: 0; border-radius: 14px; background: #fff; box-shadow: 0 0 0 1px var(--apl-sep); }
        .apl-on .ak-dt td { padding: 9px 14px; }
        .apl-on .ak-dt td:first-child, .apl-on .ak-dt td:last-child { padding-left: 14px; padding-right: 14px; }
        .apl-on .ak-dt td::before { font-weight: 500; color: var(--apl-label-2); }
    }

    /* ---------- Accessibility preferences ---------- */
    @media (prefers-reduced-motion: reduce) {
        .apl-on *, .apl-on * { transition-duration: .01ms !important; }
        .apl-on .ak-btn:active, .apl-on .ak-icon:active, .apl-on .bk-clip:active, .apl-on .apl-seg a:active, .apl-on .ak-btn:active { transform: none; }
        .apl-on #bulk-form.ak-bulk, .apl-on .bk-modal-box { animation: none; }
    }
    @media (prefers-reduced-transparency: reduce) {
        .apl-on header.bg-white { background: var(--apl-bg) !important; backdrop-filter: none; -webkit-backdrop-filter: none; }
        .apl-on .ak-dt thead th { background: #fff; backdrop-filter: none; -webkit-backdrop-filter: none; }
        .apl-on #bulk-form.ak-bulk { background: #fff !important; backdrop-filter: none; -webkit-backdrop-filter: none; }
    }
    @media (prefers-contrast: more) {
        :root { --apl-sep: rgba(60, 60, 67, .45); --apl-label-2: #3a3a3c; --apl-label-3: #48484a; }
        .apl-on .ak-card, .apl-on .apl-stat { box-shadow: 0 0 0 1px var(--apl-sep); }
    }

    @media print {
        .apl-on body, .apl-on body .min-h-screen { background: #fff !important; }
        .apl-summary { display: none !important; }
        .apl-on .ak-tabs { display: none !important; }
    }
</style>
@endonce
