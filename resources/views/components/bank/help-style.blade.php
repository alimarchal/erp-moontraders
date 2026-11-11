{{-- Styles of the Urdu guide: the full Help page and the per-screen Help window share them (x-bank.print-styles). --}}
@once
{{-- Urdu text: Noto Nastaliq Urdu; English words, codes and numbers inside it: Inter. Both from the Google Fonts CDN --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&family=Noto+Nastaliq+Urdu:wght@400..700&display=swap" rel="stylesheet">
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
        .bh .en { direction: ltr; unicode-bidi: isolate; display: inline-block; padding: 1px 8px; border-radius: 7px; background: #eef4ff; color: #0b3d91; font-family: 'Inter', -apple-system, 'Segoe UI', Arial, sans-serif; font-size: 13px; font-weight: 600; line-height: 1.6; letter-spacing: 0; vertical-align: 0.15em; white-space: nowrap; }
        /* English words and numbers running inside an Urdu sentence: left-to-right, Latin font, same line */
        .bh bdi.lt { direction: ltr; unicode-bidi: isolate; font-family: 'Inter', -apple-system, 'Segoe UI', Arial, sans-serif; font-size: .82em; font-weight: 500; letter-spacing: 0; line-height: 1; vertical-align: 0.12em; padding: 0 .15em; color: inherit; }
        .bh b bdi.lt, .bh th bdi.lt, .bh h2 bdi.lt, .bh h3 bdi.lt { font-weight: 700; }
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
        /* Flow at the end of a Help window: a vertical timeline (right to left): number, joining line, Urdu step, English screen name */
        .bh .hf { margin-top: 4px; padding: 16px 18px 6px; border-radius: 16px; background: #fbfbfd; border: 1px solid var(--ak-border); }
        .bh .hf-title { display: flex; align-items: center; gap: 10px; margin: 0 0 12px; color: var(--ak-navy); font-size: 19px; line-height: 2; }
        .bh .hf-steps { margin: 0; padding: 0; list-style: none; }
        .bh .hf-step { position: relative; display: grid; grid-template-columns: 30px minmax(0, 1fr); column-gap: 14px; margin: 0; padding-bottom: 14px; }
        .bh .hf-step:not(:last-child)::before { content: ''; position: absolute; inset-inline-start: 14px; top: 32px; bottom: 2px; width: 2px; border-radius: 2px; background: #d6e4ff; }
        .bh .hf-num { display: inline-grid; place-items: center; width: 30px; height: 30px; margin-top: 6px; border-radius: 50%; background: #0071e3; color: #fff;
            font-family: 'Inter', -apple-system, Arial, sans-serif; font-size: 13px; font-weight: 700; line-height: 1; box-shadow: 0 0 0 4px #fbfbfd; position: relative; z-index: 1; }
        .bh .hf-body { display: flex; flex-wrap: wrap; align-items: baseline; gap: 2px 12px; min-width: 0; padding: 6px 14px 8px; border-radius: 12px; background: #fff; border: 1px solid #ececf0; box-shadow: 0 1px 2px rgba(0,0,0,.03); }
        .bh .hf-body b { font-size: 16px; line-height: 2.2; color: var(--ak-text); }
        .bh .hf-body small { flex-basis: 100%; order: 3; font-size: 14px; line-height: 2.1; color: #55555b; }
        .bh .hf-body .en { order: 2; margin-inline-start: auto; font-size: 12px; font-weight: 600; background: #f0f0f5; color: #3a3a40; }
        @media (max-width: 640px) { .bh .hf-body .en { margin-inline-start: 0; } }
        @media print {
            .bh .hf { break-inside: avoid; background: none; }
            .bh-layout { display: block; } .bh-toc { display: none; } .bh { font-size: 13px; line-height: 2.2; }
            .bh section { border: 0; box-shadow: none; padding: 0 0 10px; break-inside: auto; } .bh figure, .bh table tr { break-inside: avoid; }
        }
    </style>
@endonce
