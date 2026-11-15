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
        .ev-seg { display: flex; }
        .ev-seg label { display: flex !important; flex: 1 1 100%; justify-content: center; }
        .ev-inline { flex-wrap: wrap; }
        .ev-inline .select2-container, .ev-add-shop { flex-basis: 100%; }
    }

    /* New shop dialog */
    .ev-modal-grid { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
    @media (max-width: 520px) { .ev-modal-grid { grid-template-columns: minmax(0, 1fr); } }
    .ev-modal-grid .ak-field input, .ev-modal-grid .ak-field select { width: 100%; height: 40px; padding: 0 12px; border: 0; border-radius: 10px; background: rgba(118,118,128,.12); font-size: 15px; box-sizing: border-box; }
    .ev-modal-grid .ak-field input:focus, .ev-modal-grid .ak-field select:focus { outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3; }
</style>
