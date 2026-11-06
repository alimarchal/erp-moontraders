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
    @media (max-width: 640px) { .ev-inline .select2-container { flex-basis: 100%; } .ev-form { padding: 14px; } .ev-seg label { flex: 1 1 100%; } .ev-form-foot { flex-wrap: wrap; } .ev-form-foot .ak-btn { flex: 1 1 100%; justify-content: center; } }
    .ev-form-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); }
    .ev-form-grid > *, .ev-panel-grid > *, .ev-modal-grid > * { min-width: 0; }
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
    /* Payee choice, panels and the new-shop dialog */
    .ev-form .ak-field .ev-seg label { display: inline-flex; align-items: center; gap: 8px; margin: 0; flex-direction: row; }
    .ev-form .ak-field .ev-seg input[type=radio], .ev-form .ak-field input[type=checkbox], .ev-form .ev-seg input[type=radio] { width: 16px; height: 16px; padding: 0; box-shadow: none; flex: none; border-radius: 50%; }
    .ev-form .ak-field input[type=checkbox] { border-radius: 4px; }
    .ev-payee { border: 0; padding: 0; margin: 0; min-width: 0; }
    .ev-seg { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
    .ev-seg label { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border: 1px solid var(--ak-border); border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; background: #fff; transition: border-color .15s, background .15s; }
    .ev-seg label:hover { border-color: #a5b4fc; }
    .ev-seg label.is-on { border-color: #4f46e5; background: #eef2ff; color: #312e81; box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
    .ev-seg input { accent-color: #4f46e5; }
    .ev-panel { border: 1px solid var(--ak-line); background: #f8fafc; border-radius: 12px; padding: 16px; }
    .ev-panel-grid { display: grid; gap: 14px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 900px) { .ev-panel-grid { grid-template-columns: 2fr 1fr; } .ev-panel-grid .ev-span-2 { grid-column: auto; } }
    .ev-inline { display: flex; flex-wrap: wrap; gap: 8px; align-items: stretch; }
    .ev-inline .select2-container { flex: 1 1 220px; }
    .ev-inline .select2-container { flex: 1; min-width: 0; }
    .ev-add-shop { white-space: nowrap; height: 40px; }
    .ev-note { margin: 10px 0 0; padding: 9px 12px; border-left: 3px solid #4f46e5; background: #eef2ff; border-radius: 6px; font-size: 13px; color: #312e81; }
    .ev-files { margin: 8px 0 0; padding-left: 18px; font-size: 13px; color: var(--ak-muted); list-style: disc; }
    .ev-modal-grid { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
    @media (max-width: 520px) { .ev-modal-grid { grid-template-columns: minmax(0, 1fr); } }
    .ev-modal-grid .ak-field input, .ev-modal-grid .ak-field select { width: 100%; height: 40px; padding: 0 12px; border: 1px solid var(--ak-border); border-radius: 8px; font-size: 14px; box-sizing: border-box; }
    .ev-modal-grid .ak-field label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 4px; }
</style>
