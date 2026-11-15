{{-- Apple look for form fields, shared by Goods Issues and Expense Vouchers: filled 40px fields with an 11px radius,
     a chevron that turns when a Select2 list opens, a round clear button and the blue focus ring. Put the class
     "apl-fields" on any container (Goods Issues uses its filter panel, .rg-gi-filters). Needs <x-apple-skin />. --}}
@once
<style>
        html.apl-on :is(.rg-gi-filters, .apl-fields) input[type=search], html.apl-on :is(.rg-gi-filters, .apl-fields) input[type=date], html.apl-on :is(.rg-gi-filters, .apl-fields) select:not(.select2-hidden-accessible) {
            height: 40px; border: 0 !important; border-radius: 11px !important; background-color: rgba(118,118,128,.12) !important; box-shadow: none !important; font-size: 15px; color: #1d1d1f; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .ak-search { border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .ak-search input { background: transparent !important; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .ak-search kbd { border-radius: 6px; background: #fff; border-color: rgba(0,0,0,.08); }
        html.apl-on :is(.rg-gi-filters, .apl-fields) select:not(.select2-hidden-accessible) { -webkit-appearance: none; appearance: none; padding-right: 34px !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important; background-repeat: no-repeat !important; background-position: right 12px center !important; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) input:focus, html.apl-on :is(.rg-gi-filters, .apl-fields) select:focus, html.apl-on :is(.rg-gi-filters, .apl-fields) .ak-search:focus-within { outline: none; background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .ak-search:focus-within input { box-shadow: none !important; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container .select2-selection--single { height: 40px !important; border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; padding: 0 52px 0 12px !important; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container .select2-selection__rendered { padding: 0 !important; font-size: 15px; color: #1d1d1f; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container .select2-selection__placeholder { color: #8e8e93; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container .select2-selection__arrow { top: 0 !important; right: 10px !important; width: 14px !important; height: 100% !important; transform: none !important;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat center / 12px 12px; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container .select2-selection__arrow b { display: none !important; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container--open .select2-selection__arrow { transform: rotate(180deg) !important; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container .select2-selection__clear { position: absolute !important; top: 50% !important; right: 32px !important; float: none !important; margin: 0 !important; transform: translateY(-50%);
            display: grid !important; place-items: center; width: 18px; height: 18px; padding: 0 !important; border-radius: 50%; background: rgba(118,118,128,.38) !important; color: transparent !important; font-size: 0 !important; line-height: 0 !important; cursor: pointer; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container .select2-selection__clear::before { content: ''; width: 8px; height: 8px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='4' stroke-linecap='round'%3E%3Cpath d='M6 6l12 12M18 6 6 18'/%3E%3C/svg%3E") no-repeat center / contain; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container .select2-selection__clear:hover { background: rgba(118,118,128,.6) !important; }
        html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container--focus .select2-selection--single, html.apl-on :is(.rg-gi-filters, .apl-fields) .select2-container--open .select2-selection--single { background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
</style>
@endonce
