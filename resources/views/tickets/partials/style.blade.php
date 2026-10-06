{{-- Ticket pages: small additions on top of settings.partials.ui-style (same look as Goods Issues). --}}
<style>
    .tk-grid { display: grid; gap: 20px; grid-template-columns: 1fr; }
    @media (min-width: 1024px) { .tk-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
    .tk-stack { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
    .tk-list { margin: 0; display: flex; flex-direction: column; font-size: 14px; }
    .tk-list > div { display: flex; justify-content: space-between; gap: 16px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; }
    .tk-list > div:last-child { border-bottom: 0; }
    .tk-list dt { color: var(--ak-muted); flex: none; }
    .tk-list dd { margin: 0; font-weight: 600; color: var(--ak-text); text-align: right; word-break: break-word; }
    .tk-list dd small { display: block; font-weight: 400; color: var(--ak-muted); font-size: 12px; }
    .tk-feed { margin: 0; padding: 0; list-style: none; }
    .tk-feed li { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .tk-feed li:last-child { border-bottom: 0; }
    .tk-feed i { flex: none; width: 9px; height: 9px; margin-top: 5px; border-radius: 50%; background: var(--ak-navy); }
    .tk-feed i.is-green { background: #16a34a; } .tk-feed i.is-red { background: #dc2626; } .tk-feed i.is-grey { background: #94a3b8; }
    .tk-feed small { display: block; color: var(--ak-muted); font-size: 12px; }
    .tk-feed blockquote { margin: 4px 0 0; padding: 6px 10px; border-radius: 6px; background: #f8fafc; color: #334155; }
    .tk-chip { display: inline-block; margin: 2px 4px 2px 0; padding: 2px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: 600; }
    .tk-up { color: #15803d; font-weight: 600; } .tk-down { color: #b91c1c; font-weight: 600; } .tk-flat { color: var(--ak-muted); }
    .tk-warn { padding: 10px 16px; border-top: 1px solid #fcd34d; background: #fffbeb; color: #78350f; font-size: 13px; }
    .tk-type-icon { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #eef2ff; color: var(--ak-navy); flex: none; }
    .tk-type-icon svg { width: 18px; height: 18px; }

    /* Create / edit */
    .tk-row { border: 1px solid var(--ak-border); border-radius: 10px; margin-bottom: 14px; background: #fff; }
    .tk-row-head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--ak-soft); border-bottom: 1px solid var(--ak-line); border-radius: 10px 10px 0 0; }
    .tk-row-body { padding: 14px; display: flex; flex-direction: column; gap: 14px; }
    .tk-pick { position: relative; flex: 1; min-width: 0; }
    .tk-pick-btn { width: 100%; height: 40px; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 0 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; background: #fff; font-size: 14px; text-align: left; cursor: pointer; }
    .tk-pick-btn:focus-visible, .tk-pick-btn:hover { border-color: #4f46e5; outline: none; }
    .tk-pick-list { position: absolute; z-index: 30; left: 0; right: 0; top: 44px; background: #fff; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: var(--ak-shadow-hover); overflow: hidden; }
    .tk-pick-list input { width: 100%; height: 38px; border: 0; border-bottom: 1px solid var(--ak-line); padding: 0 12px; font-size: 14px; }
    .tk-pick-list input:focus { outline: none; box-shadow: none; }
    .tk-pick-list ul { margin: 0; padding: 4px 0; max-height: 240px; overflow: auto; list-style: none; font-size: 14px; }
    .tk-pick-list li { padding: 8px 12px; cursor: pointer; }
    .tk-pick-list li:hover { background: #eef2ff; }
    .tk-tbl { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .tk-tbl th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--ak-muted); padding: 8px 10px; background: #f8fafc; border-bottom: 1px solid var(--ak-line); }
    .tk-tbl td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; }
    .tk-tbl input { width: 100%; max-width: 170px; height: 34px; padding: 0 10px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; font-variant-numeric: tabular-nums; }
    .tk-tbl input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .tk-tbl input.tk-pct { max-width: 90px; height: 26px; margin-top: 4px; font-size: 12px; color: var(--ak-muted); }
    .tk-delta { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .tk-delta-up { background: #dcfce7; color: #166534; } .tk-delta-down { background: #fee2e2; color: #991b1b; } .tk-delta-flat { background: #f1f5f9; color: #475569; }
    .tk-batches { display: grid; gap: 8px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .tk-batches { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .tk-batch { display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid var(--ak-border); border-radius: 8px; cursor: pointer; font-size: 13px; }
    .tk-batch.is-on { border-color: #4f46e5; background: #eef2ff; }
    .tk-batch b { display: block; color: var(--ak-text); } .tk-batch small { color: var(--ak-muted); }
    .tk-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--ak-text); }
    .tk-textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; }
    .tk-textarea:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .tk-add { width: 100%; padding: 12px; border: 2px dashed var(--ak-border-strong); border-radius: 10px; background: none; color: var(--ak-muted); font-weight: 600; cursor: pointer; }
    .tk-add:hover { border-color: #4f46e5; color: #4f46e5; }
    .tk-types { display: grid; gap: 10px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .tk-types { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .tk-type { display: block; padding: 12px 14px; border: 2px solid var(--ak-border); border-radius: 10px; text-decoration: none; color: var(--ak-text); background: #fff; }
    .tk-type:hover { border-color: #a5b4fc; }
    .tk-type.is-on { border-color: var(--ak-navy); background: #f5f7ff; }
    .tk-type b { display: flex; align-items: center; gap: 8px; font-size: 14px; }
    .tk-type small { display: block; margin-top: 4px; color: var(--ak-muted); font-size: 12px; line-height: 1.4; }
    .tk-gen { display: grid; gap: 16px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .tk-gen { grid-template-columns: repeat(3, minmax(0, 1fr)); } .tk-gen .tk-2 { grid-column: span 2; } .tk-gen .tk-3 { grid-column: 1 / -1; } }
    .tk-gen select, .tk-gen input[type=text], .tk-gen input[type=number] { width: 100%; height: 40px; padding: 0 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; background: #fff; }
    .tk-gen select:focus, .tk-gen input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .ak-seg button { padding: 8px 16px; border: 0; background: #fff; color: var(--ak-text); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
    .ak-seg button + button { border-left: 1px solid var(--ak-border); }
    .ak-seg button.is-on { background: var(--ak-navy); color: #fff; }
    .tk-top { display: grid; gap: 14px 16px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .tk-top { grid-template-columns: 240px 1fr; } .tk-top-wide { grid-column: 1 / -1; } }
    .tk-top select { width: 100%; height: 40px; }
    .tk-gen4 { display: grid; gap: 14px 16px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .tk-gen4 { grid-template-columns: repeat(4, minmax(0, 1fr)); } .tk-gen4 .tk-span2 { grid-column: span 2; } .tk-gen4 .tk-span3 { grid-column: span 3; } .tk-gen4 .tk-span4 { grid-column: 1 / -1; } }
    .tk-gen4 input[type=text], .tk-gen4 input[type=number], .tk-gen4 select { width: 100%; height: 40px; padding: 0 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; background: #fff; }
    .tk-gen4 input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .tk-grid-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .tk-grid-table thead th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--ak-muted); padding: 8px 10px; background: #f8fafc; border-bottom: 1px solid var(--ak-line); white-space: nowrap; }
    .tk-grid-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .tk-grid-table tbody:last-child td { border-bottom: 0; }
    .tk-cell input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; font-variant-numeric: tabular-nums; }
    .tk-cell input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .tk-cell input:disabled { background: #f8fafc; }
    .tk-cell small { display: block; margin-top: 3px; color: var(--ak-muted); font-size: 11.5px; white-space: nowrap; }
    .tk-cell .tk-delta { margin-left: 4px; padding: 0 7px; font-size: 11px; }
    .tk-mini { width: 100%; height: 36px; padding: 0 8px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 13px; background: #fff; }
    .tk-sub td { background: #f8fafc; padding-top: 6px; padding-bottom: 10px; }
    .tk-batches { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .tk-grid-table .select2-container .select2-selection--single { height: 36px; display: flex; align-items: center; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; }
    .tk-grid-table .select2-container .select2-selection__arrow { top: 4px !important; }
    .tk-top .select2-container .select2-selection--single, .tk-gen4 .select2-container .select2-selection--single { height: 40px; display: flex; align-items: center; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; }
    .tk-top .select2-container .select2-selection__arrow, .tk-gen4 .select2-container .select2-selection__arrow { top: 7px !important; }
    .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
    .select2-search--dropdown .select2-search__field { border-radius: 6px; padding: 6px 8px; }
    .tk-batch { padding: 5px 10px; font-size: 12px; }
    @media print { .uf-actions, .ak-head-actions { display: none !important; } }
</style>
