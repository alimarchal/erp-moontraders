{{-- Stock adjustment create / edit: small additions on top of goods-issues.partials.form-style for the
     adjustment lines (Select2 product picker, batch scope tick, add-line button in the table footer). --}}
<style>
    html.apl-on .gf-page .uf-body .select2-container .select2-selection--single,
    html.apl-on .gf-table td .select2-container .select2-selection--single { height: 40px; border: 0 !important; border-radius: 10px !important; background: rgba(118,118,128,.12) !important; display: flex; align-items: center; }
    html.apl-on .gf-table td .select2-container .select2-selection--single { height: 36px; border-radius: 9px !important; }
    html.apl-on .gf-table td input[readonly] { background-color: rgba(118,118,128,.07) !important; }
    .gf-table .tk-grid-table { width: 100%; }
    html.apl-on .gf-table tfoot .tk-add { width: auto; height: 34px; padding: 0 14px; border: 0; border-radius: 980px; background: rgba(0,113,227,.1); color: #0066cc; font-weight: 500; cursor: pointer; }
    html.apl-on .gf-table tfoot .tk-add:hover:not(:disabled) { background: rgba(0,113,227,.16); }
    html.apl-on .gf-table tfoot .tk-add:disabled { opacity: .5; cursor: not-allowed; }
    @media (min-width: 1024px) {
        html.apl-on .gf-table td[data-label="SKU"] { width: 22% !important; }
        html.apl-on .gf-table td[data-label="Batch"] { width: 18%; }
        html.apl-on .gf-table td[data-label="System qty"], html.apl-on .gf-table td[data-label="Counted qty"] { width: 96px; }
        html.apl-on .gf-table td[data-label="Selling cost"], html.apl-on .gf-table td[data-label="Unit cost"] { width: 110px; }
        html.apl-on .gf-table td[data-label="Difference"] { width: 90px; }
    }
</style>
