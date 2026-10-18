<style>
    .db-page { --s1: #2563eb; --s2: #f97316; --s3: #16a34a; --s4: #7c3aed; --s5: #0891b2; --s6: #e11d48; --s7: #f59e0b; --s8: #64748b; --navy: #0b2a6f; }
    .db-page * { box-sizing: border-box; }

    /* ---- top bar ---- */
    .db-top { background: linear-gradient(110deg, #0b2a6f 0%, #1d4ed8 60%, #0891b2 120%); border-radius: 16px; padding: 14px 20px; color: #fff; box-shadow: 0 14px 30px -16px rgba(11,42,111,.7); }
    .db-top-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
    .db-greet { margin: 0; font-size: 21px; line-height: 1.2; font-weight: 800; letter-spacing: -.01em; }
    .db-top-title p { margin: 5px 0 0; display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: center; font-size: 12.5px; color: #dbeafe; }
    .db-period { display: inline-flex; align-items: center; gap: 6px; padding: 3px 11px; border-radius: 999px; background: rgba(255,255,255,.18); color: #fff; font-weight: 700; }
    .db-period svg { width: 14px; height: 14px; }
    .db-live { display: inline-flex; align-items: center; gap: 6px; }
    .db-live i { width: 8px; height: 8px; border-radius: 50%; background: #4ade80; box-shadow: 0 0 0 3px rgba(74,222,128,.3); }
    .db-top-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .db-btn { display: inline-flex; align-items: center; gap: 7px; height: 34px; padding: 0 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,.35); background: rgba(255,255,255,.12); color: #fff; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; transition: background .2s, transform .2s; }
    .db-btn:hover { background: rgba(255,255,255,.24); transform: translateY(-1px); }
    .db-btn svg { width: 16px; height: 16px; }
    .db-btn-primary { background: #fff; color: var(--navy); border-color: #fff; }
    .db-btn-primary:hover { background: #e0e7ff; }
    .db-filter-panel { margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,.25); display: grid; gap: 8px; }
    .db-filter-label { font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #bfdbfe; }
    .db-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .db-chip { padding: 5px 12px; border-radius: 999px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.3); color: #fff; font-size: 12.5px; font-weight: 700; text-decoration: none; transition: background .2s; }
    .db-chip:hover { background: rgba(255,255,255,.26); }
    .db-chip.is-on { background: #fff; color: var(--navy); border-color: #fff; }
    .db-range { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; color: #dbeafe; font-size: 13px; }
    .db-range input[type=date] { height: 34px; padding: 0 10px; border-radius: 10px; border: 1px solid #fff; background: #fff; color: #0f172a; }

    /* ---- scope row ---- */
    .db-scope { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 13px; color: var(--ak-muted); }
    .db-supplier-pick { display: inline-flex; align-items: center; border: 1px solid var(--navy); border-radius: 999px; overflow: hidden; background: #fff; font-size: 12px; font-weight: 700; }
    .db-supplier-pick span { padding: 5px 11px; background: var(--navy); color: #fff; }
    .db-supplier-pick select { border: 0; height: 28px; padding: 0 28px 0 10px; font-size: 12.5px; font-weight: 700; color: var(--ak-text); background-color: #fff; }
    .db-supplier-pick select:focus { outline: none; box-shadow: none; }
    .db-pending { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-left: auto; }
    .db-pending > span { font-size: 12px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #92400e; }
    .db-pending a { display: inline-flex; align-items: center; gap: 7px; padding: 4px 11px; border-radius: 999px; background: #fef3c7; border: 1px solid #fcd34d; font-size: 12.5px; font-weight: 700; color: #78350f; text-decoration: none; }
    .db-pending a:hover { background: #fde68a; }
    .db-pending a b { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: #d97706; color: #fff; font-size: 12px; }

    /* ---- solid colour tiles ---- */
    .db-tiles { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 900px) { .db-tiles { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1280px) { .db-tiles { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .db-kpi { position: relative; display: block; overflow: hidden; min-height: 112px; border-radius: 14px; padding: 13px 15px 10px; color: #fff; text-decoration: none; background: linear-gradient(140deg, var(--c1), var(--c2)); box-shadow: 0 14px 26px -14px var(--c2); transition: transform .25s cubic-bezier(.2,.8,.2,1), box-shadow .25s; }
    .db-kpi:hover { transform: translateY(-3px); box-shadow: 0 20px 32px -14px var(--c2); }
    .db-kpi::after { content: ""; position: absolute; right: -26px; top: -26px; width: 92px; height: 92px; border-radius: 50%; background: rgba(255,255,255,.13); }
    .db-kpi-top { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .db-kpi-label { font-size: 11px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; opacity: .95; }
    .db-kpi-icon { display: inline-flex; width: 26px; height: 26px; align-items: center; justify-content: center; border-radius: 8px; background: rgba(255,255,255,.22); flex: none; }
    .db-kpi-icon svg { width: 15px; height: 15px; }
    .db-kpi-value { position: relative; z-index: 1; margin-top: 7px; font-size: 24px; line-height: 1.1; font-weight: 800; letter-spacing: -.02em; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .db-kpi-value small { margin-right: 3px; font-size: 12px; font-weight: 700; opacity: .85; }
    .db-kpi-hint { position: relative; z-index: 1; margin-top: 5px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; font-size: 11.5px; line-height: 1.3; opacity: .95; }
    .db-delta { display: inline-flex; align-items: center; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; background: rgba(255,255,255,.26); }
    .db-spark { position: relative; z-index: 1; height: 26px; margin: 2px -4px -6px; }

    /* ---- chart cards ---- */
    .db-row { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); }
    .db-card { display: flex; flex-direction: column; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 14px 8px; min-width: 0; box-shadow: 0 8px 22px -14px rgba(15,23,42,.4); }
    .db-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 2px; }
    .db-card h3 { margin: 0; padding-left: 9px; border-left: 4px solid var(--accent, var(--s1)); font-size: 14px; line-height: 1.25; font-weight: 800; color: var(--navy); }
    .db-card-sub { margin: 2px 0 0 13px; font-size: 11.5px; color: var(--ak-muted); }
    .db-card-head a { font-size: 12px; font-weight: 700; color: var(--s1); white-space: nowrap; text-decoration: none; }
    .db-card-head a:hover { text-decoration: underline; }
    .db-big { margin: 4px 0 0 13px; font-size: 21px; font-weight: 800; letter-spacing: -.02em; color: #0f172a; font-variant-numeric: tabular-nums; }
    .db-big small { font-size: 12px; font-weight: 700; color: var(--ak-muted); margin-right: 3px; }
    .db-big em { margin-left: 8px; font-style: normal; font-size: 11.5px; font-weight: 800; padding: 1px 8px; border-radius: 999px; }
    .db-big em.up { background: #dcfce7; color: #166534; }
    .db-big em.down { background: #fee2e2; color: #991b1b; }
    .db-chart { min-height: 220px; flex: 1; }
    .db-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; min-height: 160px; text-align: center; color: var(--ak-muted); font-size: 13px; }
    .db-empty b { color: var(--ak-text); font-size: 14px; }

    /* ---- late credit list ---- */
    .db-aging-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .db-aging-chips button { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border: 1px solid #cbd5e1; border-radius: 999px; background: #fff; font-size: 12.5px; font-weight: 700; color: #0f172a; cursor: pointer; }
    .db-aging-chips button b { color: #e11d48; }
    .db-aging-chips button:hover { border-color: var(--navy); }
    .db-aging-chips button.is-on { background: var(--navy); border-color: var(--navy); color: #fff; }
    .db-aging-chips button.is-on b { color: #fda4af; }
    .db-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .db-filters select, .db-filters input { height: 32px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 12.5px; background: #fff; color: #0f172a; max-width: 100%; }
    .db-filters input { width: 210px; }
    .db-aging-scroll { max-height: 26rem; overflow: auto; border-top: 1px solid #e2e8f0; }

    /* ---- top performers strip ---- */
    .db-strip { display: grid; gap: 0; grid-template-columns: repeat(2, minmax(0, 1fr)); overflow: hidden; border-radius: 14px; background: linear-gradient(110deg, #0b2a6f, #1e3a8a); color: #fff; box-shadow: 0 14px 28px -16px rgba(11,42,111,.8); }
    @media (min-width: 900px) { .db-strip { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
    .db-strip > div { display: flex; align-items: center; gap: 11px; padding: 12px 16px; border-left: 1px solid rgba(255,255,255,.14); min-width: 0; }
    .db-strip > div:first-child { border-left: 0; }
    .db-strip i { flex: none; width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--dot); color: #fff; }
    .db-strip i svg { width: 18px; height: 18px; }
    .db-strip small { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #93c5fd; }
    .db-strip b { display: block; font-size: 13.5px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .db-strip span { display: block; font-size: 12px; color: #dbeafe; font-variant-numeric: tabular-nums; }

    /* ---- what the numbers say ---- */
    .db-insights { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
    .db-insight { background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid var(--tone, #4f46e5); border-radius: 12px; padding: 10px 14px; box-shadow: 0 8px 22px -14px rgba(15,23,42,.4); }
    .db-insight b { display: block; font-size: 20px; line-height: 1.2; font-weight: 800; letter-spacing: -.02em; color: var(--tone, #4f46e5); font-variant-numeric: tabular-nums; }
    .db-insight h3 { margin: 1px 0 3px; font-size: 12.5px; font-weight: 800; color: var(--ak-text); }
    .db-insight p { margin: 0; font-size: 12px; line-height: 1.45; color: #475569; }

    .apexcharts-tooltip { box-shadow: 0 10px 24px -8px rgba(15,23,42,.35) !important; border-radius: 10px !important; }
    @media print {
        @page { size: A4 landscape; margin: 0.5in; }
        nav, .db-filter-panel, .db-top-actions { display: none !important; }
        .db-kpi, .db-card, .db-insight, .db-top, .db-strip { break-inside: avoid; box-shadow: none !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
