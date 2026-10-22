{{-- Shared assets of every Bank Register page: the Settings list/form styles, black & white laser printing, and the
     transaction pop-up (any link with data-entry="<url>" opens that transaction in a modal instead of a new page). --}}
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
    .bk-inline-form input { flex: 1; min-width: 0; height: 38px; padding: 0 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; font-size: 14px; }
    .bk-file { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--ak-line); }
    .bk-file a { font-weight: 700; color: var(--ak-navy); }
    .bk-modal-box { max-width: 760px; }
    .bk-modal-head { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 22px; background: #fff; border-bottom: 1px solid var(--ak-line); }
    .bk-modal-head h3 { margin: 0; font-size: 17px; font-weight: 700; }
    .bk-modal-body { padding: 18px 22px 22px; }
    .bk-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .bk-link-row { cursor: pointer; }
    .bk-tabs-nav { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; padding: 4px; background: #fff; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); box-shadow: var(--ak-shadow); }
    .bk-tabs-nav a { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; color: var(--ak-muted); text-decoration: none; }
    .bk-tabs-nav a:hover { background: var(--ak-slate-bg); color: var(--ak-text); }
    .bk-tabs-nav a.is-active { background: var(--ak-navy); color: #fff; }
    .bk-tabs-nav .bk-badge { min-width: 20px; padding: 0 6px; border-radius: 999px; background: #b45309; color: #fff; font-size: 11px; font-weight: 700; line-height: 20px; text-align: center; }
    .bk-help-link { margin-inline-start: auto; }
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
        .bank-printonly { display: block !important; }
        .bk-box { border: 1px solid #000 !important; border-radius: 0; break-inside: avoid; }
        .bk-amount { font-size: 22px; }
        .ak-status, .ak-pill { border: 1px solid #000; padding: 0 5px !important; font-weight: 700; }
        .ak-kpis { display: none !important; }
        .ak-card, .uf-card { border: 0 !important; }
        .ak-dt tbody tr:nth-child(even) td { background: #f3f3f3 !important; }
        .select2-selection__arrow, .select2-selection__clear { display: none !important; }
        a { text-decoration: none !important; }
        thead { display: table-header-group; }
        tr, .bank-keep { break-inside: avoid; page-break-inside: avoid; }
        body.bk-modal-open .ak-page, body.bk-modal-open nav, body.bk-modal-open header { display: none !important; }
        body.bk-modal-open #bk-entry-modal { display: block !important; position: static !important; }
        body.bk-modal-open #bk-entry-modal .uf-modal-bg, body.bk-modal-open .bk-modal-head button { display: none !important; }
        body.bk-modal-open .uf-modal-box { max-width: none; max-height: none; overflow: visible; }
    }
</style>

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

        function close() { modal.style.display = 'none'; document.body.classList.remove('bk-modal-open'); document.body.style.overflow = ''; }
        function open(url) {
            body.innerHTML = '<p class="ak-muted">Loading…</p>';
            modal.style.display = 'flex';
            document.body.classList.add('bk-modal-open');
            document.body.style.overflow = 'hidden';
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.text(); })
                .then(function (html) {
                    body.innerHTML = html;
                    if (window.initSelect2) { window.initSelect2(body); }
                })
                .catch(function () { body.innerHTML = '<p class="ak-muted">This transaction could not be loaded. Please try again.</p>'; });
        }

        document.addEventListener('click', function (e) {
            var link = e.target.closest('[data-entry]');
            if (link && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.target.closest('input, button, select, textarea, label')) {
                if (link.tagName === 'TR' && e.target.closest('a:not([data-entry])')) { return; }
                e.preventDefault();
                open(link.getAttribute('data-entry'));
                return;
            }
            if (e.target.closest('[data-bk-close]')) { close(); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display !== 'none') { close(); } });
        document.getElementById('bk-entry-print').addEventListener('click', function () { window.print(); });
    })();

    // Collapsed sections (history) are opened so they appear on paper.
    window.addEventListener('beforeprint', function () { document.querySelectorAll('details').forEach(function (d) { d.dataset.wasOpen = d.open ? '1' : ''; d.open = true; }); });
    window.addEventListener('afterprint', function () { document.querySelectorAll('details').forEach(function (d) { d.open = d.dataset.wasOpen === '1'; }); });
</script>
