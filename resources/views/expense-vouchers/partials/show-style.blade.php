{{-- Expense voucher page: a document card that reads like the paper payment voucher, a side column (payee,
     workflow, attachments) and one action bar. Uses the --apl tokens of <x-apple-skin />. Motion is limited to
     press feedback and colour; nothing moves for reduced motion. --}}
<style>
    .ev-show { display: flex; flex-direction: column; gap: 16px; }
    html.apl-on .ev-show .ak-card { border: 0; border-radius: 20px; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.05), 0 8px 24px -8px rgba(0,0,0,.12); }
    .ev-mono { font-variant-numeric: tabular-nums; letter-spacing: .01em; }
    .ev-show a:not(.ak-btn) { color: #0066cc; text-decoration: none; }
    .ev-show a:not(.ak-btn):hover { text-decoration: underline; }

    /* Banners */
    .ev-banner { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 14px; font-size: 14px; line-height: 1.5; overflow-wrap: anywhere; }
    .ev-banner svg { flex: none; width: 18px; height: 18px; margin-top: 2px; }
    .ev-banner-red { background: rgba(255,59,48,.1); color: #9b1c1c; }
    .ev-banner-amber { background: rgba(255,159,10,.14); color: #7a4100; }
    .ev-banner-sub { display: block; opacity: .9; }
    .ev-show .ev-banner a { color: inherit !important; text-decoration: underline; }

    /* Action bar: translucent material, sticky under the header on desktop */
    .ev-actionbar { position: sticky; top: 8px; z-index: 30; display: flex; align-items: center; justify-content: space-between; gap: 12px 16px; flex-wrap: wrap;
        padding: 10px 12px 10px 16px; border-radius: 18px; background: rgba(255,255,255,.82); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px);
        box-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 6px 20px -8px rgba(0,0,0,.18); }
    .ev-actionbar-info { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1 1 260px; }
    .ev-actionbar-hint { font-size: 13.5px; color: var(--apl-label-2, #6e6e73); line-height: 1.35; }
    .ev-actionbar-buttons { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
    .ev-actionbar-buttons form { margin: 0; display: contents; }
    html.apl-on .ev-show .ak-btn { height: 36px; white-space: nowrap; }
    html.apl-on .ev-show .ak-btn-sm { height: 28px; }
    html.apl-on .ev-show .ak-btn[disabled] { opacity: .45; cursor: not-allowed; transform: none; }
    html.apl-on .ev-show form[aria-busy=true] .ak-btn[disabled] { cursor: progress; }
    html.apl-on .ev-btn-quiet { background: transparent; color: var(--apl-label-2, #6e6e73); padding: 0 12px; }
    html.apl-on .ev-btn-quiet:hover { background: var(--apl-fill, rgba(118,118,128,.12)); }
    html.apl-on .ev-btn-danger { color: var(--apl-red, #d70015); }
    html.apl-on .ev-btn-danger:hover { background: var(--apl-red-tint, rgba(255,59,48,.12)); }

    /* Status badge */
    .gl-stage { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; line-height: 1.4; }
    .gl-stage::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .gl-stage-green { background: rgba(52,199,89,.14); color: #1f7a35; }
    .gl-stage-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
    .gl-stage-red { background: rgba(255,59,48,.12); color: #c4161c; }
    .gl-stage-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
    .ev-chip { display: inline-flex; align-items: center; margin-left: 8px; padding: 2px 9px; border-radius: 980px; font-size: 11.5px; font-weight: 600; background: rgba(120,120,128,.14); color: #3a3a40; white-space: nowrap; vertical-align: middle; }

    /* Layout */
    .ev-layout { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1024px) { .ev-layout { grid-template-columns: minmax(0, 1fr) minmax(300px, 360px); } }
    .ev-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .ev-card { padding: 18px 20px; }
    .ev-h2 { margin: 0 0 10px; font-size: 17px; font-weight: 600; letter-spacing: -.01em; color: #1d1d1f; display: flex; align-items: center; gap: 8px; }
    .ev-h3 { margin: 16px 0 6px; font-size: 13px; font-weight: 600; color: var(--apl-label-2, #6e6e73); }
    .ev-hint { margin: 0; font-size: 13px; line-height: 1.45; color: #6e6e73; }
    .ev-count { min-width: 20px; padding: 0 7px; border-radius: 980px; font-size: 12px; line-height: 20px; text-align: center; background: rgba(120,120,128,.14); color: #3a3a40; }
    .ev-sub { display: block; font-size: 13px; font-weight: 400; color: #6e6e73; }
    .ev-label { margin: 0; font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--apl-label-2, #6e6e73); }

    /* Document */
    .ev-doc { padding: 0; overflow: hidden; }
    .ev-doc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px 24px; flex-wrap: wrap; padding: 22px 24px 18px; border-bottom: 1px solid rgba(60,60,67,.12); }
    .ev-doc-brand { min-width: 0; flex: 1 1 260px; }
    .ev-doc-company { margin: 0; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #1d1d1f; }
    .ev-doc-owner { margin: 2px 0 0; font-size: 13px; color: #6e6e73; overflow-wrap: anywhere; }
    .ev-doc-title { margin: 12px 0 0; font-size: 24px; line-height: 1.15; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; color: #1d1d1f; }
    .ev-doc-urdu { margin: 4px 0 0; font-family: 'Noto Nastaliq Urdu', 'Noto Naskh Arabic', serif; font-size: 15px; line-height: 2; color: #6e6e73; text-align: left; }
    .ev-doc-meta { display: grid; grid-template-columns: auto auto; gap: 6px 16px; margin: 0; padding: 12px 14px; border-radius: 14px; background: var(--apl-bg, #f5f5f7); font-size: 14px; }
    .ev-doc-meta > div { display: contents; }
    .ev-doc-meta dt { color: #6e6e73; font-weight: 500; }
    .ev-doc-meta dd { margin: 0; font-weight: 600; color: #1d1d1f; text-align: right; }
    .ev-amount-band { padding: 18px 24px; background: linear-gradient(180deg, rgba(0,113,227,.05), rgba(0,113,227,0)); border-bottom: 1px solid rgba(60,60,67,.12); }
    .ev-amount { margin: 4px 0 0; font-size: 40px; font-weight: 600; line-height: 1.05; letter-spacing: -.03em; color: #1d1d1f; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .ev-amount small { margin-right: 6px; font-size: 20px; font-weight: 500; letter-spacing: 0; color: #6e6e73; }
    .ev-words { margin: 6px 0 0; font-size: 15px; font-weight: 500; color: #424245; font-style: italic; overflow-wrap: anywhere; }
    .ev-block { padding: 18px 24px; border-bottom: 1px solid rgba(60,60,67,.12); }
    .ev-block:last-child { border-bottom: 0; }
    .ev-block-head { display: flex; justify-content: space-between; align-items: baseline; gap: 4px 16px; flex-wrap: wrap; margin-bottom: 10px; }
    .ev-block-head .ev-h2 { margin: 0; }
    .ev-dl { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px 24px; margin: 0; }
    .ev-dl dt, .ev-facts dt { font-size: 12.5px; font-weight: 500; color: #6e6e73; }
    .ev-dl dd, .ev-facts dd { margin: 2px 0 0; font-size: 15px; font-weight: 500; color: #1d1d1f; overflow-wrap: anywhere; }

    /* Particulars: a debit / credit voucher table */
    .ev-particulars { width: 100%; border-collapse: collapse; font-size: 14px; }
    .ev-particulars th { text-align: left; font-size: 12px; font-weight: 600; color: #6e6e73; padding: 8px 8px; border-bottom: 1px solid rgba(60,60,67,.18); white-space: nowrap; }
    .ev-particulars td { padding: 10px 8px; border-bottom: 1px solid rgba(60,60,67,.08); color: #1d1d1f; vertical-align: top; overflow-wrap: anywhere; }
    .ev-particulars th:first-child, .ev-particulars td:first-child { padding-left: 0; }
    .ev-particulars th:last-child, .ev-particulars td:last-child { padding-right: 0; }
    .ev-particulars .ev-p-code { width: 64px; color: #6e6e73; }
    .ev-particulars .ev-p-account { font-weight: 500; }
    .ev-particulars .ev-p-narration { color: #424245; }
    .ev-particulars .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 110px; }
    .ev-particulars tfoot td { font-weight: 700; border-bottom: 0; border-top: 1px solid rgba(60,60,67,.18); }

    /* Notes paid out */
    .ev-notes { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
    .ev-notes li { display: grid; grid-template-columns: auto 1fr auto; align-items: baseline; gap: 6px; padding: 8px 12px; border-radius: 12px; background: var(--apl-bg, #f5f5f7); font-size: 14px; }
    .ev-note-face { font-weight: 600; }
    .ev-note-x { color: #6e6e73; }
    .ev-notes b { font-variant-numeric: tabular-nums; }
    .ev-notes .ev-notes-total { grid-column: 1 / -1; background: rgba(52,199,89,.12); color: #1f5c2e; }

    /* Payee card */
    .ev-payee-name { margin: 0 0 10px; font-size: 16px; font-weight: 600; color: #1d1d1f; overflow-wrap: anywhere; }
    .ev-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 16px; margin: 0; }
    .ev-facts .ev-wide { grid-column: 1 / -1; }
    .ev-owed { color: #b25000 !important; font-weight: 600 !important; }
    .ev-history { list-style: none; margin: 0; padding: 0; }
    .ev-history li + li { border-top: 1px solid rgba(60,60,67,.08); }
    .ev-history a { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 6px; margin: 0 -6px; border-radius: 10px; color: #1d1d1f !important; text-decoration: none !important; transition: background-color .15s ease; }
    @media (hover: hover) { .ev-history a:hover { background: var(--apl-bg, #f5f5f7); } }
    .ev-history-main, .ev-history-end { display: flex; flex-direction: column; min-width: 0; }
    .ev-history-end { align-items: flex-end; gap: 3px; }
    .ev-history b { font-size: 13.5px; font-weight: 600; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .ev-history small { font-size: 12px; color: #6e6e73; }
    .ev-history .gl-stage { font-size: 11px; padding: 1px 8px; }

    /* Workflow trail */
    .ev-trail { list-style: none; margin: 0; padding: 0; }
    .ev-trail li { position: relative; display: flex; gap: 12px; padding-bottom: 14px; }
    .ev-trail li:last-child { padding-bottom: 0; }
    .ev-trail li:not(:last-child)::after { content: ''; position: absolute; left: 6px; top: 18px; bottom: 0; width: 1.5px; background: rgba(60,60,67,.14); }
    .ev-dot { flex: none; width: 14px; height: 14px; margin-top: 3px; border-radius: 50%; background: #fff; box-shadow: inset 0 0 0 2px rgba(60,60,67,.25); }
    .ev-trail li.is-done .ev-dot { background: #34c759; box-shadow: none; }
    .ev-trail li.is-now .ev-dot { background: #0071e3; box-shadow: 0 0 0 4px rgba(0,113,227,.18); }
    .ev-trail li.is-bad .ev-dot { background: #ff3b30; box-shadow: none; }
    .ev-trail div { display: flex; flex-direction: column; min-width: 0; font-size: 13.5px; line-height: 1.4; }
    .ev-trail b { font-weight: 600; color: #1d1d1f; }
    .ev-trail span { color: #424245; overflow-wrap: anywhere; }
    .ev-trail small { font-size: 12px; color: #6e6e73; }
    .ev-trail li.is-todo b, .ev-trail li.is-todo span { color: #86868b; }

    /* Attachments */
    .ev-files { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 10px; }
    .ev-file { display: flex; flex-direction: column; width: 100%; padding: 0; border: 0; border-radius: 12px; overflow: hidden; background: var(--apl-bg, #f5f5f7); cursor: zoom-in; text-align: left; transition: transform .16s cubic-bezier(.23,1,.32,1), box-shadow .2s ease; }
    .ev-file:active { transform: scale(.97); }
    .ev-file:focus-visible { outline: 3px solid rgba(0,113,227,.45); outline-offset: 2px; }
    @media (hover: hover) { .ev-file:hover { box-shadow: 0 0 0 1px rgba(0,0,0,.08), 0 6px 14px -6px rgba(0,0,0,.2); } }
    .ev-file img, .ev-file-icon { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: #e8e8ed; }
    .ev-file-icon { display: grid; place-items: center; font-size: 13px; font-weight: 700; letter-spacing: .06em; color: #d70015; background: rgba(255,59,48,.08); }
    .ev-file-name { display: block; padding: 6px 8px; font-size: 12px; color: #1d1d1f; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ev-preview { position: relative; width: 100%; max-width: 56rem; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden; border-radius: 20px; background: #fff; box-shadow: 0 30px 80px rgba(0,0,0,.25); }
    .ev-preview-head { display: flex; align-items: center; gap: 8px; padding: 10px 12px 10px 18px; border-bottom: 1px solid rgba(60,60,67,.12); }
    .ev-preview-head h3 { flex: 1; min-width: 0; margin: 0; font-size: 15px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ev-preview-body { flex: 1; overflow: auto; display: flex; align-items: center; justify-content: center; padding: 8px; background: #f5f5f7; }

    /* Sheets (approve, reject, delete, reverse) */
    .ev-sheet-body { padding: 22px 22px 18px; }
    .ev-sheet-body h3 { margin: 0; font-size: 19px; font-weight: 600; letter-spacing: -.015em; }
    .ev-sheet-text { margin: 10px 0 0; font-size: 14px; line-height: 1.5; color: #424245; }
    .ev-sheet-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 16px; margin: 14px 0 0; padding: 12px 14px; border-radius: 14px; background: var(--apl-bg, #f5f5f7); }
    .ev-sheet-summary dt { font-size: 12px; color: #6e6e73; }
    .ev-sheet-summary dd { margin: 1px 0 0; font-size: 14px; font-weight: 500; color: #1d1d1f; overflow-wrap: anywhere; }
    .ev-sheet-warn { margin-top: 14px; padding: 12px 14px; border-radius: 14px; background: rgba(255,159,10,.14); color: #7a4100; font-size: 14px; }
    .ev-sheet-warn p { margin: 0; }
    .ev-sheet .cash-notes { margin-top: 10px; }
    .ev-check { display: flex; align-items: flex-start; gap: 10px; margin-top: 8px; cursor: pointer; color: #1d1d1f; font-weight: 500; }
    .ev-check input { flex: none; width: 18px; height: 18px; margin-top: 1px; accent-color: #0071e3; }
    .ev-field-label { display: block; margin: 14px 0 6px; font-size: 13px; font-weight: 500; color: var(--apl-label-2, #6e6e73); }
    .ev-textarea, .ev-input { display: block; width: 100%; border: 0; border-radius: 11px; background: rgba(118,118,128,.12); padding: 10px 12px; font-size: 15px; color: #1d1d1f; font-family: inherit; }
    .ev-input { height: 40px; padding: 0 12px; }
    .ev-textarea:focus, .ev-input:focus { outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3; }
    .ev-error { margin: 6px 0 0; font-size: 13px; color: #c4161c; }
    html.apl-on .ev-show .uf-modal-foot { padding: 12px 16px; background: var(--apl-bg, #f5f5f7); border-top: 1px solid rgba(60,60,67,.12); border-radius: 0 0 22px 22px; flex-wrap: wrap; }

    @media (max-width: 1023px) {
        .ev-dl { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    /* Phone: the action bar sits at the bottom, the particulars become stacked rows */
    @media (max-width: 767px) {
        .ev-show.has-actionbar { padding-bottom: 132px; }
        .ev-actionbar { position: fixed; top: auto; left: 0; right: 0; bottom: 0; border-radius: 18px 18px 0 0; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); }
        .ev-actionbar-info { flex-basis: 100%; }
        .ev-actionbar-buttons { width: 100%; justify-content: stretch; }
        .ev-actionbar-buttons > .ak-btn, .ev-actionbar-buttons > form > .ak-btn { flex: 1 1 0; }
        .ev-actionbar-buttons [data-primary-action] { flex: 1 1 100%; order: -1; }
        .ev-doc-head, .ev-amount-band, .ev-block { padding-left: 16px; padding-right: 16px; }
        .ev-doc-meta { width: 100%; }
        .ev-doc-title { font-size: 20px; }
        .ev-amount { font-size: 32px; }
        .ev-dl { grid-template-columns: minmax(0, 1fr); }
        .ev-card { padding: 16px; }
        .ev-particulars thead { display: none; }
        .ev-particulars, .ev-particulars tbody, .ev-particulars tfoot { display: block; }
        .ev-particulars tr { display: grid; grid-template-columns: auto 1fr; gap: 2px 10px; padding: 10px 0; border-bottom: 1px solid rgba(60,60,67,.1); }
        .ev-particulars td { display: block; padding: 0 !important; border: 0; width: auto !important; }
        .ev-particulars .ev-p-narration { grid-column: 1 / -1; font-size: 13px; }
        .ev-particulars .n { grid-column: 1 / -1; text-align: left; }
        .ev-particulars .n:empty { display: none; }
        .ev-particulars .n::before { content: attr(data-label) ' Rs '; color: #6e6e73; font-weight: 500; }
        .ev-particulars tfoot tr { border: 0; grid-template-columns: 1fr; }
        .ev-particulars tfoot td { border-top: 0; }
        .ev-sheet-summary { grid-template-columns: minmax(0, 1fr); }
    }
    @media (prefers-reduced-motion: reduce) { .ev-file, .ev-file:active { transition: none; transform: none; } }
    @media (prefers-reduced-transparency: reduce) { .ev-actionbar { background: #fff; -webkit-backdrop-filter: none; backdrop-filter: none; } }
    @media print { .ev-actionbar, .ev-side { display: none !important; } .ev-layout { display: block; } }
</style>
