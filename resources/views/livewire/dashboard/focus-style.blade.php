{{-- Styles for the Focus dashboard (Option A). Everything is scoped to .fx-page so the Classic board is untouched. --}}
<style>
    .fx-page {
        --fx-bg: #f5f5f7; --fx-card: #fff; --fx-ink: #1d1d1f; --fx-ink2: #6e6e73; --fx-ink3: #86868b;
        --fx-line: rgba(0, 0, 0, .07); --fx-fill: rgba(118, 118, 128, .12); --fx-fill2: rgba(118, 118, 128, .08);
        --fx-blue: #0071e3; --fx-green: #34c759; --fx-green-ink: #1f7a36; --fx-orange: #ff9f0a; --fx-orange-ink: #b25000;
        --fx-red: #ff3b30; --fx-red-ink: #c4161c; --fx-indigo: #5e5ce6; --fx-grey: #8e8e93;
        --fx-shadow: 0 1px 2px rgba(0, 0, 0, .04), 0 6px 20px -8px rgba(0, 0, 0, .08);
        --fx-radius: 18px;
        font-family: 'Inter', 'Noto Nastaliq Urdu', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-feature-settings: 'cv11', 'ss01';
        color: var(--fx-ink);
        -webkit-font-smoothing: antialiased;
        gap: 16px !important;
    }
    .min-h-screen:has(.fx-page) { background: #f5f5f7; }
    .fx-page :is(h1, h2, h3, p) { margin: 0; }
    .fx-num { font-variant-numeric: tabular-nums; }
    .fx-page a { color: inherit; }
    .fx-page :focus-visible { outline: 2px solid var(--fx-blue); outline-offset: 2px; border-radius: 8px; }

    /* ---- top bar ---- */
    .fx-bar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px 20px; }
    .fx-bar-title { min-width: 0; }
    .fx-bar h1 { font-size: 26px; line-height: 1.15; font-weight: 700; letter-spacing: -.022em; }
    .fx-bar-meta { margin-top: 4px; display: flex; flex-wrap: wrap; align-items: center; gap: 2px 8px; font-size: 13px; line-height: 1.4; color: var(--fx-ink2); }
    .fx-bar-meta b { font-weight: 600; color: var(--fx-ink); }
    .fx-dot-sep::before { content: '·'; margin-right: 8px; color: var(--fx-ink3); }
    .fx-live { display: inline-flex; align-items: center; gap: 6px; }
    .fx-live i { width: 6px; height: 6px; border-radius: 50%; background: var(--fx-green); }
    .fx-scope { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 6px; }
    .fx-tag { display: inline-flex; align-items: center; height: 22px; padding: 0 8px; border-radius: 6px; background: var(--fx-fill2); font-size: 11.5px; font-weight: 500; color: var(--fx-ink2); white-space: nowrap; }
    .fx-tag.is-limited { background: rgba(255, 159, 10, .14); color: var(--fx-orange-ink); }
    .fx-bar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }

    .fx-seg { display: inline-flex; padding: 2px; border-radius: 9px; background: var(--fx-fill); gap: 2px; max-width: 100%; }
    .fx-seg > a, .fx-seg > button { position: relative; display: inline-flex; align-items: center; justify-content: center; height: 28px; padding: 0 12px; border: 0; border-radius: 7px; background: transparent; font: inherit; font-size: 13px; font-weight: 500; color: var(--fx-ink); text-decoration: none; white-space: nowrap; cursor: pointer; transition: background-color .2s ease, box-shadow .2s ease; }
    .fx-seg > a:hover, .fx-seg > button:hover { background: rgba(255, 255, 255, .55); }
    .fx-seg > .is-on, .fx-seg > [aria-pressed="true"], .fx-seg > [aria-selected="true"] { background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 0 0 .5px rgba(0, 0, 0, .04); font-weight: 600; }
    .fx-seg-sm > a, .fx-seg-sm > button { height: 26px; padding: 0 10px; font-size: 12.5px; }

    .fx-btn { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border: 0; border-radius: 9px; background: #fff; box-shadow: 0 0 0 .5px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .05); font: inherit; font-size: 13px; font-weight: 500; color: var(--fx-ink); text-decoration: none; cursor: pointer; transition: transform .1s ease-out, background-color .2s; }
    .fx-btn:hover { background: #fafafa; }
    .fx-btn:active { transform: scale(.97); }
    .fx-btn svg { width: 15px; height: 15px; color: var(--fx-ink2); }
    .fx-btn-primary { background: var(--fx-blue); color: #fff; box-shadow: none; }
    .fx-btn-primary:hover { background: #0077ed; }
    .fx-select { display: inline-flex; align-items: center; height: 32px; padding-left: 10px; border-radius: 9px; background: #fff; box-shadow: 0 0 0 .5px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .05); font-size: 13px; color: var(--fx-ink2); max-width: 100%; }
    .fx-select select { height: 30px; border: 0; padding: 0 28px 0 6px; background-color: transparent; font: inherit; font-size: 13px; font-weight: 500; color: var(--fx-ink); max-width: 220px; text-overflow: ellipsis; }
    .fx-select select:focus { outline: none; box-shadow: none; }

    .fx-custom { flex-basis: 100%; display: grid; gap: 10px; padding: 14px 16px; border-radius: 14px; background: #fff; box-shadow: var(--fx-shadow); }
    .fx-custom-label { font-size: 12px; font-weight: 600; color: var(--fx-ink2); }
    .fx-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .fx-chip { display: inline-flex; align-items: center; height: 28px; padding: 0 11px; border-radius: 999px; background: var(--fx-fill2); font-size: 12.5px; font-weight: 500; color: var(--fx-ink); text-decoration: none; }
    .fx-chip:hover { background: var(--fx-fill); }
    .fx-chip.is-on { background: var(--fx-ink); color: #fff; }
    .fx-range { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 13px; color: var(--fx-ink2); }
    .fx-range input[type=date] { height: 32px; padding: 0 10px; border: 0; border-radius: 9px; box-shadow: 0 0 0 .5px rgba(0, 0, 0, .18); background: #fff; font: inherit; font-size: 13px; color: var(--fx-ink); }

    /* ---- cards ---- */
    .fx-card { display: flex; flex-direction: column; gap: 12px; min-width: 0; padding: 18px 20px; border-radius: var(--fx-radius); background: var(--fx-card); box-shadow: var(--fx-shadow); }
    .fx-card-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 8px 12px; }
    .fx-card-head > div:first-child { min-width: 0; }
    .fx-card h2 { font-size: 17px; line-height: 1.25; font-weight: 600; letter-spacing: -.012em; }
    .fx-sub { margin-top: 2px !important; font-size: 12.5px; line-height: 1.4; color: var(--fx-ink2); }
    .fx-card-head a, .fx-link { font-size: 13px; font-weight: 500; color: var(--fx-blue) !important; text-decoration: none; white-space: nowrap; }
    .fx-card-head a:hover, .fx-link:hover { text-decoration: underline; }
    .fx-big { font-size: 26px; line-height: 1.1; font-weight: 650; letter-spacing: -.022em; font-variant-numeric: tabular-nums; }
    .fx-big small { margin-right: 3px; font-size: 13px; font-weight: 500; letter-spacing: 0; color: var(--fx-ink2); }
    .fx-empty { padding: 28px 8px; text-align: center; font-size: 13px; color: var(--fx-ink2); }
    .fx-empty b { display: block; margin-bottom: 2px; font-size: 14px; font-weight: 600; color: var(--fx-ink); }

    .fx-chipd { display: inline-flex; align-items: center; height: 22px; padding: 0 7px; border-radius: 6px; font-size: 12px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fx-chipd.up { background: rgba(52, 199, 89, .14); color: var(--fx-green-ink); }
    .fx-chipd.down { background: rgba(255, 59, 48, .11); color: var(--fx-red-ink); }
    .fx-chipd.warn { background: rgba(255, 159, 10, .15); color: var(--fx-orange-ink); }

    /* ---- key figures ---- */
    .fx-kpis { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 768px) { .fx-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1280px) { .fx-kpis.is-six { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .fx-kpi { container-type: inline-size; position: relative; display: flex; flex-direction: column; min-width: 0; padding: 14px 16px 8px; border-radius: 16px; background: #fff; box-shadow: var(--fx-shadow); text-decoration: none; color: var(--fx-ink); transition: box-shadow .2s ease, transform .1s ease-out; }
    a.fx-kpi:hover { box-shadow: 0 1px 2px rgba(0, 0, 0, .05), 0 10px 26px -10px rgba(0, 0, 0, .16); }
    a.fx-kpi:active { transform: scale(.985); }
    .fx-kpi-label { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; color: var(--fx-ink2); }
    .fx-kpi-label i { width: 7px; height: 7px; flex: none; border-radius: 50%; background: var(--tone); }
    /* Sized from the card's own width, so the longest figure always fits on one line. */
    .fx-kpi-value { margin-top: 6px; font-size: clamp(17px, 14.5cqi, 28px); line-height: 1.1; font-weight: 650; letter-spacing: -.024em; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fx-kpi-value small { margin-right: 3px; font-size: .5em; font-weight: 500; letter-spacing: 0; color: var(--fx-ink2); }
    .fx-kpi-foot { margin-top: 6px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; font-size: 12px; line-height: 1.35; color: var(--fx-ink3); }
    .fx-kpi-foot .fx-chipd { height: 20px; font-size: 11.5px; }
    .fx-spark { height: 30px; margin: 4px -6px -4px; }
    .fx-kpi:not(:has(.fx-spark)) { padding-bottom: 14px; }

    /* ---- main grid: one column on phones (cards ordered by importance), 8 + 4 from 1024px ---- */
    .fx-grid { display: flex; flex-direction: column; gap: 16px; }
    .fx-col { display: contents; }
    .fx-o1 { order: 1; } .fx-o2 { order: 2; } .fx-o3 { order: 3; } .fx-o4 { order: 4; } .fx-o5 { order: 5; } .fx-o6 { order: 6; } .fx-o7 { order: 7; }
    @media (min-width: 1024px) {
        .fx-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); align-items: start; }
        .fx-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        .fx-col-main { grid-column: span 8; }
        .fx-col-side { grid-column: span 4; }
        .fx-grid.is-single .fx-col-main, .fx-grid.is-single .fx-col-side { grid-column: 1 / -1; }
        .fx-col-main .fx-legend, .fx-col-main .fx-inv-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 28px; }
    }

    .fx-chart { min-height: 280px; margin: 0 -8px -6px; }

    /* ---- ranked list with inline bars ---- */
    .fx-rank { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
    .fx-rank li { display: grid; grid-template-columns: 22px minmax(0, 1fr) auto; align-items: baseline; column-gap: 10px; row-gap: 6px; }
    .fx-rank-n { font-size: 12px; font-weight: 600; color: var(--fx-ink3); font-variant-numeric: tabular-nums; }
    .fx-rank-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; font-weight: 500; }
    .fx-rank-name a { text-decoration: none; }
    .fx-rank-name a:hover { color: var(--fx-blue); }
    .fx-rank-name span { margin-left: 6px; font-size: 12px; font-weight: 400; color: var(--fx-ink3); }
    .fx-rank-v { font-size: 13.5px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fx-bar-track { grid-column: 2 / -1; height: 6px; border-radius: 3px; background: var(--fx-fill2); overflow: hidden; }
    .fx-bar-track i { display: block; height: 100%; border-radius: 3px; background: var(--tone, var(--fx-blue)); }

    /* ---- compact lists (needs attention, insights, investment, legends) ---- */
    .fx-list { list-style: none; margin: 0 -8px; padding: 0; }
    .fx-list > li + li { border-top: .5px solid var(--fx-line); }
    .fx-row { display: flex; align-items: center; gap: 10px; min-height: 40px; padding: 8px; border-radius: 10px; text-decoration: none; color: var(--fx-ink); font-size: 14px; }
    a.fx-row:hover { background: var(--fx-fill2); }
    .fx-row > span:first-child { flex: 1; min-width: 0; }
    .fx-count { min-width: 26px; height: 22px; padding: 0 7px; border-radius: 999px; background: rgba(255, 159, 10, .16); color: var(--fx-orange-ink); font-size: 12.5px; font-weight: 600; font-variant-numeric: tabular-nums; display: inline-flex; align-items: center; justify-content: center; }
    .fx-chev { width: 14px; height: 14px; flex: none; color: #c7c7cc; }

    .fx-insight { display: grid; grid-template-columns: 8px minmax(0, 1fr) auto; column-gap: 10px; row-gap: 2px; padding: 10px 8px; }
    .fx-insight i { width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; background: var(--tone); }
    .fx-insight h3 { font-size: 13.5px; font-weight: 600; line-height: 1.35; }
    .fx-insight b { font-size: 13.5px; font-weight: 650; font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
    .fx-insight p { grid-column: 2 / -1; font-size: 12.5px; line-height: 1.45; color: var(--fx-ink2); }

    .fx-stack { display: flex; gap: 2px; height: 10px; border-radius: 5px; overflow: hidden; background: var(--fx-fill2); }
    .fx-stack i { display: block; min-width: 3px; background: var(--tone); }
    .fx-legend { list-style: none; margin: 0; padding: 0; display: grid; gap: 2px; }
    .fx-legend li > * { width: 100%; }
    .fx-leg { display: grid; grid-template-columns: 8px minmax(0, 1fr) auto 42px; align-items: center; gap: 10px; min-height: 32px; padding: 4px 8px; margin: 0 -8px; border: 0; border-radius: 9px; background: transparent; font: inherit; font-size: 13.5px; color: var(--fx-ink); text-align: left; text-decoration: none; }
    button.fx-leg { cursor: pointer; }
    button.fx-leg:hover { background: var(--fx-fill2); }
    .fx-leg > i { width: 8px; height: 8px; border-radius: 50%; background: var(--tone); }
    .fx-leg > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fx-leg > span small { margin-left: 4px; color: var(--fx-ink3); font-size: 12px; }
    .fx-leg > b { font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fx-leg > em { font-style: normal; font-size: 12.5px; color: var(--fx-ink3); text-align: right; font-variant-numeric: tabular-nums; }
    .fx-split + .fx-split { padding-top: 14px; border-top: .5px solid var(--fx-line); }
    .fx-split h3 { margin-bottom: 10px !important; font-size: 12.5px; font-weight: 600; color: var(--fx-ink2); }
    .fx-split .fx-stack { margin-bottom: 8px; }

    .fx-inv { display: grid; grid-template-columns: minmax(0, 1fr) auto; column-gap: 10px; row-gap: 5px; padding: 6px 0; }
    .fx-inv span { font-size: 13.5px; }
    .fx-inv b { font-size: 13.5px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fx-inv b.neg { color: var(--fx-red-ink); }
    .fx-inv .fx-bar-track { grid-column: 1 / -1; height: 4px; }

    /* ---- the shared late-credit table, in this layout's clothes ---- */
    .fx-page .db-aging { border: 0; border-radius: var(--fx-radius); box-shadow: var(--fx-shadow); order: 8; }
    .fx-page .db-aging .db-card-head { padding: 18px 20px 12px !important; }
    .fx-page .db-aging .db-card-head h3 { padding-left: 0; border-left: 0; font-size: 17px; font-weight: 600; letter-spacing: -.012em; color: var(--fx-ink); }
    .fx-page .db-aging .db-card-sub { margin-left: 0; font-size: 12.5px; color: var(--fx-ink2); }
    .fx-page .db-aging-chips button { height: 28px; padding: 0 11px; border: 0; background: var(--fx-fill2); font-weight: 500; color: var(--fx-ink); }
    .fx-page .db-aging-chips button:hover { background: var(--fx-fill); }
    .fx-page .db-aging-chips button b { color: var(--fx-red-ink); font-weight: 600; }
    .fx-page .db-aging-chips button.is-on { background: var(--fx-ink); color: #fff; }
    .fx-page .db-aging-chips button.is-on b { color: #ffb4ae; }
    .fx-page .db-filters select, .fx-page .db-filters input { border: 0; border-radius: 9px; box-shadow: 0 0 0 .5px rgba(0, 0, 0, .18); font-size: 13px; }
    .fx-page .db-aging-scroll { border-top: .5px solid var(--fx-line); }
    .fx-page .db-aging > p.ak-muted { padding: 10px 20px 14px !important; }
    .fx-page .db-aging a:not(.ak-primary-link) { color: var(--fx-blue); }

    /* ---- phones ---- */
    @media (max-width: 639px) {
        .fx-bar h1 { font-size: 22px; }
        .fx-bar-meta { flex-direction: column; align-items: flex-start; gap: 0; }
        .fx-dot-sep::before { display: none; }
        .fx-bar-actions { width: 100%; }
        .fx-seg { display: flex; width: 100%; }
        .fx-seg > a, .fx-seg > button { flex: 1 1 0; padding: 0 6px; font-size: 12.5px; }
        .fx-select { flex: 1 1 0; min-width: 0; }
        .fx-select select { max-width: none; width: 100%; }
        .fx-card { padding: 16px; }
        .fx-kpi { padding: 12px 12px 6px; }
        .fx-chart { min-height: 230px; margin: 0 -6px -6px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .fx-page *, .fx-page *::before, .fx-page *::after { transition: none !important; animation: none !important; }
        .fx-btn:active, a.fx-kpi:active { transform: none; }
    }

    @media print {
        .min-h-screen:has(.fx-page) { background: #fff; }
        .fx-bar-actions, .fx-custom, .fx-card-head .fx-seg { display: none !important; }
        .fx-kpi, .fx-card, .fx-page .db-aging { box-shadow: none !important; border: 1px solid #d2d2d7 !important; break-inside: avoid; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .fx-kpis.is-six { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        .fx-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); align-items: start; }
        .fx-col { display: flex; flex-direction: column; gap: 12px; }
        .fx-col-main { grid-column: span 8; } .fx-col-side { grid-column: span 4; }
    }
</style>
