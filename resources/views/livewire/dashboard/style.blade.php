<style>
    /* ---- top bar: period, supplier and filters ---- */
    .db-top { background: #fff; border: 1px solid var(--ak-border); border-radius: 14px; padding: 14px 18px; box-shadow: 0 6px 18px -8px rgba(15,23,42,.18), 0 1px 3px rgba(15,23,42,.06); }
    .db-top-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
    .db-top-title { min-width: 0; }
    .db-top-title .db-greet { margin: 0; font-size: 20px; line-height: 1.2; font-weight: 800; letter-spacing: -.01em; color: var(--ak-text); }
    .db-top-title p { margin: 5px 0 0; display: flex; flex-wrap: wrap; gap: 6px 8px; align-items: center; font-size: 12.5px; color: var(--ak-muted); }
    .db-period { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-weight: 700; }
    .db-period svg { width: 14px; height: 14px; }
    .db-top-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .db-btn { display: inline-flex; align-items: center; gap: 7px; height: 36px; padding: 0 14px; border-radius: 10px; border: 1px solid var(--ak-border-strong); background: #fff; color: var(--ak-text); font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; transition: transform .2s, box-shadow .2s, background .2s; }
    .db-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 14px -6px rgba(15,23,42,.3); background: #f8fafc; }
    .db-btn svg { width: 16px; height: 16px; }
    .db-btn-primary { background: var(--ak-navy-hover); border-color: var(--ak-navy-hover); color: #fff; }
    .db-btn-primary:hover { background: #1e40af; }
    .db-live { display: inline-flex; align-items: center; gap: 6px; }
    .db-live i { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; }
    .db-filter-panel { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--ak-line); display: grid; gap: 8px; }
    .db-filter-label { font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--ak-muted); }
    .db-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .db-chip { padding: 6px 12px; border-radius: 999px; background: #f1f5f9; border: 1px solid var(--ak-border); color: #334155; font-size: 12.5px; font-weight: 700; text-decoration: none; transition: background .2s, transform .2s; }
    .db-chip:hover { background: #e2e8f0; transform: translateY(-1px); }
    .db-chip.is-on { background: var(--ak-navy-hover); color: #fff; border-color: var(--ak-navy-hover); }
    .db-range { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; color: var(--ak-muted); font-size: 13px; }
    .db-range input[type=date] { height: 36px; padding: 0 10px; border-radius: 10px; border: 1px solid var(--ak-border-strong); background: #fff; color: var(--ak-text); }

    /* ---- headline tiles ---- */
    .db-tiles { display: grid; gap: 12px; grid-template-columns: repeat(1, minmax(0, 1fr)); }
    @media (min-width: 560px) { .db-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1100px) { .db-tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .db-kpi { position: relative; display: block; overflow: hidden; background: #fff; border: 1px solid var(--ak-border); border-radius: 12px; padding: 12px 14px 10px; color: var(--ak-text); text-decoration: none; box-shadow: 0 6px 18px -8px rgba(15,23,42,.2), 0 1px 3px rgba(15,23,42,.07); transition: transform .25s cubic-bezier(.2,.8,.2,1), box-shadow .25s ease, border-color .25s ease; }
    .db-kpi::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--tone, #1d4ed8); }
    .db-kpi.is-link:hover, .db-kpi:hover { transform: translateY(-3px); box-shadow: 0 16px 30px -12px var(--glow, rgba(29,78,216,.38)), 0 4px 10px rgba(15,23,42,.08); border-color: var(--tone, #1d4ed8); }
    .db-kpi-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .db-kpi-label { font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: var(--ak-muted); }
    .db-kpi-icon { display: inline-flex; width: 30px; height: 30px; align-items: center; justify-content: center; border-radius: 8px; background: var(--soft, rgba(29,78,216,.12)); color: var(--tone, #1d4ed8); flex: none; }
    .db-kpi-icon svg { width: 17px; height: 17px; }
    .db-kpi-value { margin-top: 6px; font-size: 24px; line-height: 1.15; font-weight: 800; letter-spacing: -.02em; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .db-kpi-value small { margin-right: 3px; font-size: 12px; font-weight: 700; color: var(--ak-muted); }
    .db-kpi-hint { margin-top: 4px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; font-size: 11.5px; line-height: 1.35; color: var(--ak-muted); }
    .db-delta { display: inline-flex; align-items: center; padding: 0 7px; border-radius: 999px; font-size: 11px; font-weight: 800; }
    .db-delta.up { background: #dcfce7; color: #166534; }
    .db-delta.down { background: #fee2e2; color: #991b1b; }
    .db-delta.flat { background: #f1f5f9; color: #475569; }
    .db-spark { height: 34px; margin: 4px -4px -6px; }

    /* ---- highlights: rings ---- */
    .db-gauges { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; padding: 6px 12px 12px; }
    @media (min-width: 900px) { .db-gauges { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
    .db-gauges figure { margin: 0; text-align: center; padding: 6px 4px; border-radius: 12px; transition: background .2s; }
    .db-gauges figure:hover { background: #f8fafc; }
    .db-gauges figcaption { font-size: 12px; line-height: 1.4; color: var(--ak-muted); }
    .db-gauges figcaption b { display: block; margin-bottom: 2px; font-size: 12.5px; color: var(--ak-text); }

    /* ---- group titles ---- */
    .db-group { display: flex; align-items: baseline; gap: 8px; margin: 0 2px; font-size: 11.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--ak-muted); }
    .db-group span { font-weight: 500; letter-spacing: 0; text-transform: none; }

    .db-stack > .db-group + * { margin-top: 10px; }

    /* ---- insights ---- */
    .db-insights { display: grid; gap: 12px; grid-template-columns: 1fr; }
    @media (min-width: 700px) { .db-insights { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1100px) { .db-insights { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .db-insight { background: #fff; border: 1px solid var(--ak-border); border-left: 4px solid var(--tone, #4f46e5); border-radius: 12px; padding: 12px 14px; box-shadow: 0 6px 18px -8px rgba(15,23,42,.2), 0 1px 3px rgba(15,23,42,.07); }
    .db-insight b { display: block; font-size: 22px; line-height: 1.2; font-weight: 800; letter-spacing: -.02em; color: var(--tone, #4f46e5); font-variant-numeric: tabular-nums; }
    .db-insight h3 { margin: 2px 0 4px; font-size: 13px; font-weight: 800; color: var(--ak-text); }
    .db-insight p { margin: 0; font-size: 12.3px; line-height: 1.5; color: #475569; }

    /* ---- investment ---- */
    .db-invest { display: grid; gap: 16px; grid-template-columns: 1fr; padding: 16px 18px 18px; }
    @media (min-width: 900px) { .db-invest { grid-template-columns: minmax(0, 1fr) minmax(0, 1.7fr); align-items: center; } }
    .db-hero-num { font-size: 40px; line-height: 1.05; font-weight: 800; letter-spacing: -.03em; font-variant-numeric: tabular-nums; color: var(--ak-text); }
    .db-hero-num small { font-size: 16px; font-weight: 700; color: var(--ak-muted); margin-right: 4px; }
    .db-inv-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 12px; padding: 7px 0; border-top: 1px solid var(--ak-line); }
    .db-inv-row:first-child { border-top: 0; }
    .db-inv-name { font-size: 13px; font-weight: 700; color: var(--ak-text); }
    .db-inv-name small { display: block; font-size: 11.5px; font-weight: 500; color: var(--ak-muted); }
    .db-inv-val { text-align: right; font-size: 13.5px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .db-inv-val small { display: block; font-size: 11.5px; font-weight: 700; }
    .db-inv-bar { grid-column: 1 / -1; height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
    .db-inv-bar > span { display: block; height: 100%; border-radius: 999px; background: var(--bar, #1d4ed8); }

    /* ---- the module sections keep their markup; give their tiles the same look ---- */
    .db-page .ak-kpis { gap: 12px; }
    .db-page .ak-kpi { position: relative; overflow: hidden; padding: 12px 14px; border-radius: 12px; gap: 12px; box-shadow: 0 6px 18px -8px rgba(15,23,42,.2), 0 1px 3px rgba(15,23,42,.07); }
    .db-page .ak-kpi::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #94a3b8; }
    .db-page .ak-kpi:has(.ak-tone-navy)::before { background: #1d4ed8; }
    .db-page .ak-kpi:has(.ak-tone-green)::before { background: #16a34a; }
    .db-page .ak-kpi:has(.ak-tone-amber)::before { background: #d97706; }
    .db-page .ak-kpi:has(.ak-tone-slate)::before { background: #64748b; }
    .db-page .ak-kpi-label { font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .db-page .ak-kpi-value { font-size: 22px; font-weight: 800; }
    .db-card, .db-pending { box-shadow: 0 6px 18px -8px rgba(15,23,42,.2), 0 1px 3px rgba(15,23,42,.07); }
    .db-section-head h2 { display: flex; align-items: center; gap: 10px; }
    .db-section-head h2::before { content: ""; width: 8px; height: 22px; border-radius: 4px; background: var(--ak-navy-hover); }
    .apexcharts-tooltip { box-shadow: 0 10px 24px -8px rgba(15,23,42,.35) !important; border-radius: 10px !important; }
    @media print {
        @page { size: A4 landscape; margin: 8mm; }
        nav, .db-filter-panel, .db-top-actions, .db-toc { display: none !important; }
        .db-kpi, .db-card, .db-insight { break-inside: avoid; box-shadow: none !important; }
    }
</style>
