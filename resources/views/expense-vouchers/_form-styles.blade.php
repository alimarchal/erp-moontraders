<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">
<style>
    .ak-field label.ev-bi { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 4px; }
    .ev-ur { font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', 'Geeza Pro', serif; font-size: 15px; font-weight: 600; color: var(--ak-navy); line-height: 1.5; display: inline-flex; align-items: center; }
    .ev-bi > span:first-child { display: inline-flex; align-items: center; }
    .ev-bi .ev-ur { position: relative; top: 2px; }
    .ev-form-foot .ak-btn, .ak-title { display: inline-flex; align-items: center; gap: 10px; }
    .ak-title .ev-ur { margin: 0 !important; position: relative; top: 1px; }
    .ev-form { padding: 20px; }
    .ev-form-grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
    @media (min-width: 900px) { .ev-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .ev-span-2 { grid-column: span 2; } .ev-span-3 { grid-column: span 3; } }

    /* Standard field size, with a clear focus ring */
    .ev-form .ak-field input, .ev-form .ak-field select, .ev-form .ak-field textarea, .ev-form .select2-container .select2-selection--single {
        font-size: 14px; border: 1px solid var(--ak-border); border-radius: 8px; transition: border-color .15s ease, box-shadow .15s ease;
    }
    .ev-form .ak-field input, .ev-form .ak-field select { height: 40px; padding: 0 12px; box-sizing: border-box; }
    .ev-form .ak-field textarea { width: 100%; padding: 8px 12px; }
    .ev-form .ak-field input:focus, .ev-form .ak-field select:focus, .ev-form .ak-field textarea:focus,
    .ev-form .select2-container--focus .select2-selection--single, .ev-form .select2-container--open .select2-selection--single {
        outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, .2);
    }
    .ev-form .select2-container { width: 100% !important; display: block; }
    .ev-form .select2-container .select2-selection--single { height: 40px; padding: 0 30px 0 12px; display: flex; align-items: center; box-shadow: none; }
    .ev-form .select2-container .select2-selection__rendered { font-size: 14px; padding: 0; line-height: 1.3; }
    .ev-form .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
    .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
    .ak-hint { margin: 4px 0 0; font-size: 12px; color: var(--ak-muted); }
    .ev-form-foot { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--ak-line); }
    .ev-form .ak-field input.ev-file { height: auto; padding: 8px 10px; font-size: 14px; background: #f8fafc; border-style: dashed; }
    .ev-form .ak-field input.ev-file::file-selector-button { margin-right: 10px; padding: 6px 12px; border: 1px solid var(--ak-border); border-radius: 8px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; }
    .ev-current-file { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-top: 8px; font-size: 14px; }
    .ev-current-file a { color: #4338ca; font-weight: 600; text-decoration: underline; }
    .ev-remove { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
    .ev-remove input { height: 16px; width: 16px; }
</style>
