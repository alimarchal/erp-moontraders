<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">
<style>
    .ak-field label.ev-bi { display: flex; justify-content: space-between; align-items: center; min-height: 30px; gap: 12px; margin-bottom: 6px; font-size: 15px; font-weight: 600; color: var(--ak-text); }
    .ev-ur { font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', 'Geeza Pro', serif; font-size: 18px; font-weight: 600; color: var(--ak-navy); line-height: 1.5; display: inline-flex; align-items: center; }
    .ev-bi > span:first-child { line-height: 1.5; display: inline-flex; align-items: center; }
    .ev-form-foot .ak-btn, .ak-title { display: inline-flex; align-items: center; gap: 10px; }
    .ak-title .ev-ur { margin: 0 !important; position: relative; top: 1px; }
    .ev-form { padding: 24px; }
    .ev-form-grid { display: grid; gap: 22px 20px; grid-template-columns: 1fr; }
    @media (min-width: 900px) { .ev-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .ev-span-2 { grid-column: span 2; } .ev-span-3 { grid-column: span 3; } }

    /* Inputs and dropdowns: larger, with a clear resting, hover and focus state */
    .ev-form .ak-field input, .ev-form .ak-field select, .ev-form .ak-field textarea,
    .ev-form .select2-container .select2-selection--single {
        font-size: 16px; color: #0f172a; background: #fff; border: 1.5px solid #cbd5e1; border-radius: 10px;
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .ev-form .ak-field input, .ev-form .ak-field select { height: 50px; padding: 0 14px; box-sizing: border-box; }
    .ev-form .ak-field textarea { width: 100%; padding: 12px 14px; line-height: 1.5; }
    .ev-form .ak-field input::placeholder, .ev-form .ak-field textarea::placeholder { color: #94a3b8; font-size: 15px; }
    .ev-form .ak-field input:hover, .ev-form .ak-field select:hover, .ev-form .ak-field textarea:hover,
    .ev-form .select2-container:hover .select2-selection--single { border-color: #94a3b8; }
    .ev-form .ak-field input:focus, .ev-form .ak-field select:focus, .ev-form .ak-field textarea:focus,
    .ev-form .select2-container--focus .select2-selection--single, .ev-form .select2-container--open .select2-selection--single {
        outline: none; border-color: #4f46e5; background: #fbfbff; box-shadow: 0 0 0 4px rgba(79, 70, 229, .18);
    }
    .ev-form .select2-container { width: 100% !important; display: block; }
    .ev-form .select2-container .select2-selection--single { height: 50px; padding: 0 36px 0 14px; display: flex; align-items: center; box-shadow: none; }
    .ev-form .select2-container .select2-selection__rendered { font-size: 16px; padding: 0; line-height: 1.3; color: #0f172a; }
    .ev-form .select2-container .select2-selection__placeholder { color: #94a3b8; }
    .ev-form .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; right: 10px !important; }
    .select2-dropdown { border: 1.5px solid #4f46e5; border-radius: 10px; font-size: 16px; overflow: hidden; box-shadow: 0 12px 28px -8px rgba(15, 23, 42, .25); }
    .select2-results__option { padding: 10px 14px; }
    .select2-search--dropdown .select2-search__field { border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 16px; }
    .select2-search--dropdown .select2-search__field:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, .18); }
    .ak-hint { margin: 6px 0 0; font-size: 13px; color: var(--ak-muted); }
    .ev-form-foot { display: flex; justify-content: flex-end; gap: 10px; margin-top: 26px; padding-top: 20px; border-top: 1px solid var(--ak-line); }
    .ev-form-foot .ak-btn { height: 46px; padding: 0 22px; font-size: 15px; }
</style>
<style>
    /* Naskh sits a little high next to the sans-serif English; nudge it so both read on one line */
    .ev-bi .ev-ur { position: relative; top: 3px; }
</style>
<style>
    .ev-form .ak-field input.ev-file { height: auto; padding: 10px 12px; font-size: 15px; background: #f8fafc; border-style: dashed; }
    .ev-form .ak-field input.ev-file::file-selector-button { margin-right: 12px; padding: 8px 14px; border: 1px solid var(--ak-border); border-radius: 8px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
    .ev-current-file { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-top: 8px; font-size: 14px; }
    .ev-current-file a { color: #4338ca; font-weight: 600; text-decoration: underline; }
    .ev-remove { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
    .ev-remove input { height: 18px; width: 18px; }
</style>
