{{-- Styles of the Urdu guide: the full Help page and the per-screen Help window share them (x-bank.print-styles). --}}
@once
{{-- Urdu text of the guide and the Help window: Noto Nastaliq Urdu from the Google Fonts CDN --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400..700&display=swap" rel="stylesheet">
    <style>
        .bh { font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Noto Naskh Arabic', Tahoma, serif; font-size: 17px; line-height: 2.5; color: var(--ak-text); }
        .bh-layout { display: grid; gap: 20px; grid-template-columns: 1fr; align-items: start; }
        @media (min-width: 1024px) { .bh-layout { grid-template-columns: 270px minmax(0, 1fr); } .bh-toc { position: sticky; top: 16px; max-height: calc(100vh - 32px); overflow-y: auto; } }
        .bh-toc, .bh section { background: #fff; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); box-shadow: var(--ak-shadow); }
        .bh-toc { padding: 14px 18px; font-size: 15px; line-height: 2.3; }
        .bh-toc b { display: block; color: var(--ak-navy); font-size: 17px; }
        .bh-toc ol { margin: 0; padding-inline-start: 24px; list-style: decimal; }
        .bh-toc a { color: var(--ak-text); text-decoration: none; } .bh-toc a:hover { color: var(--ak-navy); text-decoration: underline; }
        .bh-main { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
        .bh section { padding: 20px 26px; scroll-margin-top: 16px; }
        .bh h2 { margin: 0 0 10px; padding-bottom: 6px; border-bottom: 2px solid var(--ak-line); font-size: 24px; font-weight: 700; line-height: 2.2; color: var(--ak-navy); }
        .bh h3 { margin: 16px 0 4px; font-size: 19px; font-weight: 700; line-height: 2.2; color: #166534; }
        .bh p { margin: 6px 0; }
        .bh ol, .bh ul { margin: 6px 0; padding-inline-start: 28px; } .bh ol { list-style: decimal; } .bh ul { list-style: disc; } .bh li { margin: 2px 0; }
        .bh .en { direction: ltr; unicode-bidi: isolate; display: inline-block; padding: 0 7px; border-radius: 6px; background: #eef2ff; color: var(--ak-navy); font-family: Figtree, Inter, Arial, sans-serif; font-size: 13.5px; font-weight: 600; line-height: 1.7; white-space: nowrap; }
        .bh code.en { background: #f1f5f9; font-family: ui-monospace, Menlo, monospace; font-weight: 500; }
        .bh .note, .bh .tip, .bh .warn { margin: 12px 0; padding: 8px 16px; border-radius: 10px; border: 1px solid; font-size: 16px; }
        .bh .note { background: #fffbeb; border-color: #fcd34d; } .bh .tip { background: #eff6ff; border-color: #bfdbfe; } .bh .warn { background: #fef2f2; border-color: #fecaca; }
        .bh table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 15.5px; line-height: 2.3; }
        .bh th, .bh td { border: 0.75pt solid #000; padding: 4px 12px; text-align: right; vertical-align: top; }
        .bh th { background: #e6e6e6; font-weight: 700; white-space: nowrap; }
        .bh td.en-cell { direction: ltr; text-align: left; font-family: ui-monospace, Menlo, monospace; font-size: 13px; line-height: 1.8; white-space: nowrap; }
        .bh .badge { display: inline-block; padding: 0 12px; border-radius: 999px; direction: ltr; font-family: Figtree, Inter, Arial, sans-serif; font-size: 13px; font-weight: 700; line-height: 1.9; white-space: nowrap; }
        .bh .b-gray { background: #f1f5f9; color: #334155; } .bh .b-blue { background: #dbeafe; color: #1e40af; } .bh .b-amber { background: #fef3c7; color: #92400e; }
        .bh .b-red { background: #fee2e2; color: #991b1b; } .bh .b-green { background: #dcfce7; color: #166534; }
        .bh .flow { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 12px 0; font-size: 15.5px; }
        .bh .flow .step { padding: 0 14px; border: 1px solid #c7d2fe; border-radius: 10px; background: #eef2ff; }
        .bh .flow .arrow { color: var(--ak-muted); }
        .bh figure { margin: 14px 0; }
        .bh figure img { display: block; width: 100%; height: auto; border: 1px solid var(--ak-border); border-radius: 10px; }
        .bh figcaption { margin-top: 2px; font-size: 14.5px; color: var(--ak-muted); text-align: center; }
        .bh .shots { display: grid; gap: 14px; grid-template-columns: 1fr; }
        @media (min-width: 1100px) { .bh .shots { grid-template-columns: 1fr 1fr; } }
        @media print {
            .bh-layout { display: block; } .bh-toc { display: none; } .bh { font-size: 13px; line-height: 2.2; }
            .bh section { border: 0; box-shadow: none; padding: 0 0 10px; break-inside: auto; } .bh figure, .bh table tr { break-inside: avoid; }
        }
    </style>
@endonce
