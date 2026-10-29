<style>
    .ev-form { padding: 20px; }
    .ev-form-grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
    @media (min-width: 900px) { .ev-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .ev-span-2 { grid-column: span 2; } .ev-span-3 { grid-column: span 3; } }
    .ev-form .ak-field input, .ev-form .ak-field select { height: 40px; box-sizing: border-box; }
    .ev-form .ak-field textarea { width: 100%; border: 1px solid var(--ak-border); border-radius: 8px; padding: 8px 12px; font-size: 14px; }
    .ak-hint { margin: 4px 0 0; font-size: 12px; color: var(--ak-muted); }
    .ev-form-foot { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--ak-line); }
    .ev-form .select2-container { width: 100% !important; display: block; }
    .ev-form .select2-container .select2-selection--single { height: 40px; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
    .ev-form .select2-container .select2-selection__arrow { top: 7px !important; }
    .ev-form .select2-container--focus .select2-selection--single, .ev-form .select2-container--open .select2-selection--single { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
</style>
