{{-- Shared Payroll look (hub tabs and the run page): Apple tokens on top of the ak- / gl- / gq- styles of Goods Issues. --}}
<style>
    :root { --pr-blue: #0071e3; --pr-text: #1d1d1f; --pr-muted: #6e6e73; --pr-fill: rgba(118,118,128,.12); --pr-fill-2: rgba(118,118,128,.22);
        --pr-sep: rgba(60,60,67,.14); --pr-green: #1f7a35; --pr-green-bg: rgba(52,199,89,.14); --pr-red: #c4161c; --pr-red-bg: rgba(255,59,48,.12);
        --pr-amber: #8a4b00; --pr-amber-bg: rgba(255,159,10,.16); --pr-grey-bg: rgba(120,120,128,.14); --pr-radius: 20px;
        --pr-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08); }

    /* header buttons: pills like the rest of the Apple skin */
    .pr-head-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border: 0; border-radius: 980px; background: var(--pr-fill);
        color: var(--pr-text); font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; transition: background-color .15s ease, transform .1s ease-out; }
    .pr-head-btn:hover { background: var(--pr-fill-2); }
    .pr-head-btn:active { transform: scale(.97); }
    .pr-head-btn:focus-visible { outline: 2px solid var(--pr-blue); outline-offset: 2px; }
    .pr-head-primary { background: var(--pr-blue); color: #fff; }
    .pr-head-primary:hover { background: #0077ed; }
    .pr-head-count { min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: var(--pr-blue); color: #fff; font-size: 11px; display: inline-grid; place-items: center; }

    /* section tabs: one segmented control under the header */
    .pr-tabs { display: flex; gap: 4px; padding: 4px; border-radius: 14px; background: var(--pr-fill); overflow-x: auto; scrollbar-width: none; }
    .pr-tabs::-webkit-scrollbar { display: none; }
    .pr-tab { flex: 1 0 auto; display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 36px; padding: 6px 14px; border-radius: 10px;
        color: var(--pr-text); font-size: 14px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: background-color .15s ease; }
    .pr-tab:hover { background: rgba(255,255,255,.55); }
    .pr-tab.is-active { background: #fff; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,.1), 0 0 0 .5px rgba(0,0,0,.04); }
    .pr-tab:focus-visible { outline: 2px solid var(--pr-blue); outline-offset: 1px; }
    .pr-tab-count { font-size: 12px; font-weight: 500; color: var(--pr-muted); font-variant-numeric: tabular-nums; }

    /* cards, stats and capsules */
    .pr-card { background: #fff; border-radius: var(--pr-radius); box-shadow: var(--pr-shadow); }
    .pr-stats { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
    .pr-stat { padding: 14px 18px; }
    .pr-stat small { display: block; font-size: 12.5px; font-weight: 500; color: var(--pr-muted); }
    .pr-stat b { display: block; margin-top: 4px; font-size: 22px; font-weight: 600; letter-spacing: -.02em; color: var(--pr-text); font-variant-numeric: tabular-nums; }
    .pr-stat b.is-earn { color: var(--pr-green); } .pr-stat b.is-ded { color: var(--pr-red); }
    .pr-cap { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .pr-cap::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .pr-cap-green { background: var(--pr-green-bg); color: var(--pr-green); } .pr-cap-amber { background: var(--pr-amber-bg); color: var(--pr-amber); }
    .pr-cap-red { background: var(--pr-red-bg); color: var(--pr-red); } .pr-cap-grey { background: var(--pr-grey-bg); color: #3a3a40; }
    .pr-cap-blue { background: rgba(0,113,227,.1); color: #0058b0; }
    .pr-earn { color: var(--pr-green); } .pr-ded { color: var(--pr-red); }
    .pr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    /* sheet (modal): the Goods Issue quick look, wider, with a sticky footer */
    .pr-sheet-wrap { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
    .pr-scrim { position: absolute; inset: 0; background: rgba(0,0,0,.28); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); }
    .pr-sheet { position: relative; width: 100%; max-width: 560px; max-height: min(90vh, 860px); display: flex; flex-direction: column; overflow: hidden; background: #fff;
        border-radius: 22px; box-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 24px 64px -12px rgba(0,0,0,.35); }
    .pr-sheet-lg { max-width: 980px; }
    .pr-sheet-head { display: flex; align-items: flex-start; gap: 12px; padding: 20px 22px 14px; border-bottom: 1px solid var(--pr-sep); }
    .pr-sheet-head h3 { margin: 0; font-size: 19px; font-weight: 600; letter-spacing: -.015em; color: var(--pr-text); }
    .pr-sheet-head p { margin: 3px 0 0; font-size: 13.5px; color: var(--pr-muted); }
    .pr-sheet-body { flex: 1; overflow-y: auto; padding: 18px 22px; overscroll-behavior: contain; }
    .pr-sheet-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; padding: 14px 22px; border-top: 1px solid var(--pr-sep); background: rgba(250,250,252,.92); }
    .pr-sheet-foot .pr-foot-note { margin-right: auto; font-size: 13px; color: var(--pr-muted); }
    .pr-x { margin-left: auto; flex: none; width: 30px; height: 30px; display: inline-grid; place-items: center; border: 0; border-radius: 50%; background: var(--pr-fill); color: #3a3a40; cursor: pointer; }
    .pr-x:active { transform: scale(.94); }
    .pr-x:focus-visible { outline: 2px solid var(--pr-blue); outline-offset: 2px; }
    .pr-enter { transition: opacity .22s cubic-bezier(.23,1,.32,1), transform .26s cubic-bezier(.23,1,.32,1); }
    .pr-leave { transition: opacity .14s ease-out, transform .14s ease-out; }
    .pr-from { opacity: 0; transform: translateY(8px) scale(.98); }
    .pr-to { opacity: 1; transform: none; }
    @media (prefers-reduced-motion: reduce) { .pr-enter, .pr-leave { transition: opacity .15s ease; } .pr-from { transform: none; } }
    @media (max-width: 640px) {
        .pr-sheet-wrap { align-items: flex-end; padding: 0; }
        .pr-sheet, .pr-sheet-lg { max-width: none; max-height: 92vh; border-radius: 22px 22px 0 0; }
    }

    /* form controls inside sheets: filled Apple fields */
    .pr-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
    .pr-field > span, .pr-label { font-size: 13px; font-weight: 500; color: var(--pr-muted); }
    .pr-input, .pr-select, .pr-textarea { width: 100%; height: 40px; padding: 0 12px; border: 0 !important; border-radius: 11px !important; background-color: var(--pr-fill) !important;
        box-shadow: none !important; font-size: 15px; color: var(--pr-text); }
    .pr-textarea { height: auto; min-height: 70px; padding: 10px 12px; }
    .pr-select { -webkit-appearance: none; appearance: none; padding-right: 32px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important; background-position: right 11px center !important; }
    .pr-input:focus, .pr-select:focus, .pr-textarea:focus { outline: none; background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px var(--pr-blue) !important; }
    .pr-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 16px; border: 0; border-radius: 980px; font-size: 14px; font-weight: 500;
        text-decoration: none; cursor: pointer; background: var(--pr-fill); color: var(--pr-text); transition: background-color .15s ease, transform .1s ease-out; }
    .pr-btn:hover { background: var(--pr-fill-2); }
    .pr-btn:active { transform: scale(.97); }
    .pr-btn:focus-visible { outline: 2px solid var(--pr-blue); outline-offset: 2px; }
    .pr-btn:disabled { opacity: .5; cursor: not-allowed; }
    .pr-btn-primary { background: var(--pr-blue); color: #fff; } .pr-btn-primary:hover { background: #0077ed; }
    .pr-btn-danger { background: var(--pr-red-bg); color: #d70015; } .pr-btn-danger:hover { background: rgba(255,59,48,.2); }
    .pr-btn-green { background: #248a3d; color: #fff; } .pr-btn-green:hover { background: #1f7a35; }
    .pr-btn-sm { height: 32px; padding: 0 12px; font-size: 13px; }
    .pr-seg { display: inline-flex; padding: 2px; border-radius: 9px; background: var(--pr-fill); }
    .pr-seg label { position: relative; }
    .pr-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .pr-seg span { display: block; padding: 5px 12px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; }
    .pr-seg input:checked + span { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.12); }
    .pr-seg input:focus-visible + span { outline: 2px solid var(--pr-blue); }

    /* earnings | deductions: the two-column layout of the salary window and the payslip */
    .pr-split { display: grid; gap: 16px; grid-template-columns: 1fr; }
    @media (min-width: 820px) { .pr-split { grid-template-columns: 1fr 1fr; } }
    .pr-col { border-radius: 16px; background: #fbfbfd; box-shadow: inset 0 0 0 1px var(--pr-sep); padding: 14px; min-width: 0; }
    .pr-col-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
    .pr-col-head h4 { margin: 0; font-size: 15px; font-weight: 600; letter-spacing: -.01em; }
    .pr-col-earn h4 { color: var(--pr-green); } .pr-col-ded h4 { color: var(--pr-red); }
    .pr-col-total { display: flex; justify-content: space-between; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--pr-sep); font-weight: 600; font-variant-numeric: tabular-nums; }
    .pr-net { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px; margin-top: 16px; padding: 14px 16px; border-radius: 16px; background: rgba(0,113,227,.06); }
    .pr-net small { font-size: 13px; font-weight: 500; color: var(--pr-muted); }
    .pr-net b { font-size: 26px; font-weight: 600; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }

    /* tables in this module */
    .pr-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .pr-table th { position: sticky; top: 0; z-index: 1; padding: 10px 12px; background: #fafafc; border-bottom: 1px solid var(--pr-sep); font-size: 12px; font-weight: 600; color: var(--pr-muted); text-align: left; white-space: nowrap; }
    .pr-table td { padding: 10px 12px; border-bottom: 1px solid rgba(60,60,67,.08); vertical-align: middle; }
    .pr-table tbody tr:hover td { background: #f5f5f7; }
    .pr-table tfoot td { position: sticky; bottom: 0; background: #fafafc; border-top: 1px solid var(--pr-sep); font-weight: 600; }
    .pr-scroll { max-height: calc(100vh - 260px); min-height: 16rem; overflow: auto; border-radius: 0 0 var(--pr-radius) var(--pr-radius); }
    .pr-empty { padding: 48px 20px; text-align: center; color: var(--pr-muted); }
    .pr-empty b { display: block; font-size: 17px; color: var(--pr-text); margin-bottom: 4px; }
    .pr-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--pr-sep); }
    .pr-toolbar p { margin: 0; font-size: 14px; color: var(--pr-muted); } .pr-toolbar p b { color: var(--pr-text); }
    .pr-toolbar .pr-spacer { flex: 1; }
    .pr-filters { padding: 16px; }
    .pr-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
    @media (min-width: 900px) { .pr-filter-row { grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); } }
    .pr-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 10px 16px; border-bottom: 1px solid var(--pr-sep); }
    .pr-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 980px; background: var(--pr-fill); color: var(--pr-text); font-size: 13px; text-decoration: none; }
    .pr-chip:hover { background: var(--pr-fill-2); }
    .pr-subtabs { display: flex; flex-wrap: wrap; gap: 6px; padding: 12px 16px 0; }
    .pr-subtab { padding: 5px 12px; border-radius: 980px; background: var(--pr-fill); color: var(--pr-text); font-size: 13px; font-weight: 500; text-decoration: none; }
    .pr-subtab.is-on { background: var(--pr-blue); color: #fff; }
    .pr-row-menu { position: fixed; z-index: 70; min-width: 180px; padding: 4px; background: #fff; border-radius: 12px; box-shadow: 0 0 0 1px rgba(0,0,0,.06), 0 12px 32px -8px rgba(0,0,0,.25); }
    .pr-row-menu a, .pr-row-menu button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 12px; border: 0; border-radius: 8px; background: none; color: var(--pr-text); font-size: 14px; text-align: left; text-decoration: none; cursor: pointer; }
    .pr-row-menu a:hover, .pr-row-menu button:hover { background: #f5f5f7; }
    .pr-row-menu .is-danger { color: #d70015; }
    .pr-bulkbar { position: sticky; bottom: 12px; z-index: 30; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 0 auto; width: fit-content; max-width: calc(100% - 24px);
        padding: 8px 10px 8px 16px; border-radius: 980px; background: rgba(29,29,31,.88); color: #fff; -webkit-backdrop-filter: blur(20px) saturate(180%); backdrop-filter: blur(20px) saturate(180%);
        box-shadow: 0 12px 32px -8px rgba(0,0,0,.4); font-size: 14px; }
    .pr-bulkbar .pr-btn { height: 32px; background: rgba(255,255,255,.16); color: #fff; }
    .pr-bulkbar .pr-btn:hover { background: rgba(255,255,255,.26); }
    .pr-bulkbar .pr-btn-primary { background: var(--pr-blue); }
    .pr-check { width: 18px; height: 18px; border-radius: 5px; accent-color: var(--pr-blue); cursor: pointer; }

    /* phones: tables become cards */
    @media screen and (max-width: 767px) {
        .pr-table.pr-cards thead { display: none; }
        .pr-table.pr-cards, .pr-table.pr-cards tbody, .pr-table.pr-cards tr, .pr-table.pr-cards td { display: block; width: 100%; }
        .pr-table.pr-cards tr { padding: 12px 14px; border-bottom: 1px solid var(--pr-sep); }
        .pr-table.pr-cards td { padding: 3px 0; border: 0; display: flex; justify-content: space-between; gap: 12px; text-align: right; }
        .pr-table.pr-cards td::before { content: attr(data-label); color: var(--pr-muted); font-size: 13px; text-align: left; }
        .pr-table.pr-cards td[data-label=""]::before { content: none; }
        .pr-table.pr-cards tfoot { display: block; }
        .pr-scroll { max-height: none; }
    }
    [x-cloak] { display: none !important; }
    @media print { .no-print, .pr-tabs, .pr-bulkbar { display: none !important; } }
</style>
