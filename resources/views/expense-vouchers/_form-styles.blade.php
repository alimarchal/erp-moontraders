{{-- Expense voucher create / edit, on top of goods-issues.partials.form-style (cards, filled fields, Select2,
     sticky action bar). Only the voucher's own pieces are styled here: the payee switch, the field grid, notes. --}}
<style>
    .ev-sections { display: flex; flex-direction: column; gap: 16px; }
    .ev-grid { display: grid; gap: 12px 16px; grid-template-columns: minmax(0, 1fr); }
    .ev-grid > * { min-width: 0; }
    @media (min-width: 900px) { .ev-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .ev-span-2 { grid-column: span 2; } .ev-span-3 { grid-column: 1 / -1; } }
    .ev-form .ak-field { display: flex; flex-direction: column; gap: 4px; }
    .ev-form .ak-field input:not([type=checkbox]):not([type=radio]):not([type=file]), .ev-form .ak-field select:not(.select2-hidden-accessible) { height: 40px; padding: 0 12px; width: 100%; box-sizing: border-box; }
    .ev-form .ak-field textarea { width: 100%; }
    html.apl-on .ev-form .ak-field select:not(.select2-hidden-accessible) { -webkit-appearance: none; appearance: none; padding-right: 34px !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important; background-repeat: no-repeat !important; background-position: right 12px center !important; }
    .ev-form .select2-container { width: 100% !important; display: block; position: relative; margin-top: 0 !important; }
    .ev-amount-input { font-size: 17px !important; font-weight: 600; font-variant-numeric: tabular-nums; }
    .ak-hint { margin: 2px 0 0; font-size: 12px; color: #6e6e73; }
    html.apl-on .ev-form .ev-add-shop { height: 40px; }
    .ev-field-error { margin: 2px 0 0; font-size: 12.5px; color: #c4161c; }
    html.apl-on .ev-form .ak-btn[disabled] { opacity: .45; cursor: progress; transform: none; }

    /* Who is paid: a segmented switch */
    .ev-seg { display: inline-flex; flex-wrap: wrap; gap: 2px; padding: 2px; border-radius: 11px; background: rgba(118,118,128,.12); }
    .ev-seg label { display: inline-flex; align-items: center; gap: 8px; margin: 0; padding: 7px 14px; border-radius: 9px; font-size: 14px !important; font-weight: 500 !important; color: #1d1d1f !important; cursor: pointer; transition: background-color .15s ease, box-shadow .15s ease; }
    .ev-seg label input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
    .ev-seg label.is-on { background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.12), 0 0 0 .5px rgba(0,0,0,.04); }
    .ev-seg label:has(input:focus-visible) { outline: 2px solid #0071e3; outline-offset: 1px; }
    .ev-seg label:active { transform: scale(.98); }

    .ev-inline { display: flex; gap: 8px; align-items: center; }
    .ev-inline .select2-container { flex: 1 1 auto; min-width: 0; }
    .ev-add-shop { white-space: nowrap; height: 40px; border-radius: 980px !important; }
    .ev-note { margin: 12px 0 0; padding: 9px 12px; border-radius: 10px; background: rgba(0,113,227,.07); font-size: 13px; color: #0b3d91; }
    .ev-file { height: auto !important; padding: 8px 10px !important; }
    .ev-files { margin: 6px 0 0; padding-left: 18px; font-size: 13px; color: #6e6e73; list-style: disc; }
    .ev-current-file { display: flex; margin-top: 8px; font-size: 14px; }
    .ev-current-file a { color: #0066cc; font-weight: 500; }
    .ev-remove { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
    .ev-ur { font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', serif; }
    @media (max-width: 640px) {
        .ev-form .gf-actions > div:last-child { display: flex; flex-wrap: wrap; gap: 8px; width: 100%; }
        .ev-form .gf-actions > div:last-child .ak-btn { flex: 1 1 0; min-width: 0; margin: 0; }
        .ev-form .gf-actions > div:last-child .gf-submit { flex: 1 1 100%; order: -1; }
        .ev-seg { display: flex; }
        .ev-seg label { display: flex !important; flex: 1 1 100%; justify-content: center; }
        .ev-inline { flex-wrap: wrap; }
        .ev-inline .select2-container, .ev-add-shop { flex-basis: 100%; }
    }

    /* Bill lines: the goods-issues line table, a card per line below 1024px */
    .ev-lines { margin: 0; padding: 0; border: 0; min-width: 0; }
    .ev-lines[disabled] { display: none; }
    html.apl-on .ev-lines.gf-table td .select2-container { margin-top: 0 !important; }
    html.apl-on .ev-lines.gf-table td input:not([type=checkbox]):not([type=hidden]) { width: 100%; height: 38px; padding: 0 10px; border: 0 !important; border-radius: 10px !important; background: rgba(118,118,128,.12) !important; font-size: 15px; box-sizing: border-box; }
    html.apl-on .ev-lines.gf-table td input:focus { outline: none; background: #fff !important; box-shadow: 0 0 0 3px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
    html.apl-on .ev-lines.gf-table td .ev-line-amount { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
    html.apl-on .ev-lines.gf-table thead th:nth-child(5) { text-align: right; }
    html.apl-on .ev-lines.gf-table td.ev-line-remove { text-align: center; }
    .ev-remove-line { display: inline-grid; place-items: center; width: 32px; height: 32px; border: 0; border-radius: 50%; background: transparent; color: #c4161c; cursor: pointer; transition: background-color .15s ease; }
    .ev-remove-line svg { width: 18px; height: 18px; }
    .ev-remove-line:hover:not(:disabled) { background: rgba(255,59,48,.12); }
    .ev-remove-line:disabled { opacity: .3; cursor: not-allowed; }
    html.apl-on .ev-lines.gf-table tfoot td.ev-total-label { text-align: right; font-weight: 600; color: #1d1d1f; }
    html.apl-on .ev-lines.gf-table tfoot td.ev-total { text-align: right; font-size: 17px; font-weight: 700; color: #1d1d1f; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ev-lines-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
    .ev-add-line { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 16px 0 12px; border: 0; border-radius: 980px; background: rgba(0,113,227,.1); color: #0066cc; font-size: 14px; font-weight: 500; cursor: pointer; transition: background-color .15s ease, transform .1s ease-out; }
    .ev-add-line svg { width: 16px; height: 16px; }
    .ev-add-line:hover:not(:disabled) { background: rgba(0,113,227,.16); }
    .ev-add-line:active:not(:disabled) { transform: scale(.97); }
    .ev-add-line:disabled { opacity: .45; cursor: not-allowed; }
    @media (min-width: 1024px) {
        html.apl-on .ev-lines.gf-table td.gf-no { width: 36px; }
        html.apl-on .ev-lines.gf-table td[data-label="Category"] { width: 22%; }
        html.apl-on .ev-lines.gf-table td[data-label="Expense account"] { width: 30%; }
        html.apl-on .ev-lines.gf-table td[data-label="Amount (Rs)"] { width: 150px; }
        html.apl-on .ev-lines.gf-table td.ev-line-remove { width: 48px; }
        html.apl-on .ev-lines.gf-table tbody tr td { vertical-align: top; }
        html.apl-on .ev-lines.gf-table td.gf-no, html.apl-on .ev-lines.gf-table td.ev-line-remove { padding-top: 14px !important; }
    }
    @media screen and (max-width: 1023px) {
        html.apl-on .ev-lines.gf-table tfoot tr:first-child { justify-content: flex-end; gap: 12px; padding: 4px 14px; }
        html.apl-on .ev-lines.gf-table tbody tr { position: relative; }
        html.apl-on .ev-lines.gf-table td.ev-line-remove { position: absolute; top: 4px; right: 8px; width: auto !important; padding: 0 !important; }
    }
    /* Phones: the label sits above its field, so the account name has the full card width */
    @media screen and (max-width: 640px) {
        html.apl-on .ev-lines.gf-table tbody tr td:not(.gf-no):not(.ev-line-remove) { grid-template-columns: minmax(0, 1fr); row-gap: 4px; padding: 4px 12px !important; }
        html.apl-on .ev-lines.gf-table tbody tr td:not(.gf-no):not(.ev-line-remove)::before { grid-row: auto; padding-top: 0; }
        html.apl-on .ev-lines.gf-table tbody tr td > * { grid-column: 1; }
        html.apl-on .ev-lines.gf-table tbody tr { padding-bottom: 10px; }
    }

    /* New shop dialog */
    .ev-modal-grid { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
    @media (max-width: 520px) { .ev-modal-grid { grid-template-columns: minmax(0, 1fr); } }
    .ev-modal-grid .ak-field input, .ev-modal-grid .ak-field select { width: 100%; height: 40px; padding: 0 12px; border: 0; border-radius: 10px; background: rgba(118,118,128,.12); font-size: 15px; box-sizing: border-box; }
    .ev-modal-grid .ak-field input:focus, .ev-modal-grid .ak-field select:focus { outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3; }
</style>
