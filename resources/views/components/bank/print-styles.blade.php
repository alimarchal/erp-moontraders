{{-- Print layout shared by every Bank Register page: hides controls, prints tables full width and in colour. --}}
<style>
    .bank-printonly { display: none; }
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        html, body, .bg-gray-100 { background: #fff !important; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .bank-noprint, nav, footer, [x-cloak], .select2-container--open { display: none !important; }
        .bank-printonly { display: block !important; }
        button, input[type="checkbox"], input[type="file"], input[name="reason"], textarea,
        a[class*="bg-"][class*="text-white"] { display: none !important; }
        .shadow, .shadow-lg, .shadow-xl, .shadow-2xl { box-shadow: none !important; }
        .max-w-7xl { max-width: none !important; }
        .py-6, .py-4 { padding-top: 0 !important; padding-bottom: 0 !important; }
        .overflow-x-auto, .overflow-hidden { overflow: visible !important; }
        table { width: 100% !important; font-size: 10.5px !important; }
        th, td { padding: 3px 5px !important; }
        thead { display: table-header-group; }
        tr, .bank-keep { break-inside: avoid; page-break-inside: avoid; }
        a { color: inherit !important; text-decoration: none !important; }
        .select2-container { display: inline-block !important; }
        .select2-selection__arrow, .select2-selection__clear { display: none !important; }
    }
</style>
<script>
    // Collapsed sections (history, notes) are opened so they appear on paper.
    window.addEventListener('beforeprint', () => document.querySelectorAll('details').forEach(d => { d.dataset.wasOpen = d.open ? '1' : ''; d.open = true; }));
    window.addEventListener('afterprint', () => document.querySelectorAll('details').forEach(d => { d.open = d.dataset.wasOpen === '1'; }));
</script>
