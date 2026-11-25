{{-- Field grid, bilingual labels and footer for the supplier create / edit form. --}}
<style>
    .ev-form-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); }
    .ev-form-grid > * { min-width: 0; }
    @media (min-width: 900px) {
        .ev-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .ev-form-grid .ev-span-2 { grid-column: span 2; }
        .ev-form-grid .ev-span-3 { grid-column: 1 / -1; }
    }
    .ev-form .ak-field { display: flex; flex-direction: column; gap: 4px; }
    .ev-form .ak-field label.ev-bi { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; font-size: 13px; font-weight: 600; color: #374151; }
    .ev-form .ak-field input:not([type=checkbox]):not([type=radio]), .ev-form .ak-field select { height: 40px; padding: 0 12px; width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; }
    .ev-form .ak-field textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; }
    .ev-ur { font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', serif; }
    .ev-form-foot { display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
</style>
