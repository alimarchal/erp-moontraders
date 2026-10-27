{{-- Settings -> Products -> Import from Excel (/settings/products/import). --}}
@php
    $result = session('import_result');
    $dry = (bool) ($result['dry_run'] ?? false);
    $labels = [
        'created' => $dry ? 'New' : 'Added',
        'updated' => $dry ? 'Will update' : 'Updated',
        'unchanged' => 'No change',
        'skipped' => 'Skipped',
        'error' => 'Error',
    ];
    $tones = ['created' => 'green', 'updated' => 'green', 'unchanged' => 'amber', 'skipped' => 'amber', 'error' => 'red'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('products.index') }}">Products</a><span aria-hidden="true">›</span><span>Import</span>
                </nav>
                <h1 class="ak-title">Import products from Excel</h1>
                <p class="ak-sub">Add many SKUs at once. Existing product codes are skipped, or updated if you choose to.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('products.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('products.import.template') }}" class="ak-btn ak-btn-outline">Download template</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />

        @if ($result)
            <section class="ak-card" aria-label="Import result">
                <div class="uf-card-head">
                    <h2 class="uf-card-title">
                        {{ $result['dry_run'] ? 'Check only — nothing was saved' : 'Import finished' }}
                        <span class="ak-muted" style="font-weight:400">{{ $result['file'] }}@if ($result['update_existing'] ?? false) · updating {{ ($result['update_scope'] ?? 'all') === 'prices' ? 'prices only' : 'every filled column' }}@endif</span>
                    </h2>
                </div>
                <div class="ak-kpis" style="padding:16px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr))">
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ $result['dry_run'] ? 'New — will be added' : 'Added' }}</span><span class="ak-kpi-value">{{ number_format($result['counts']['created']) }}</span></span></div>
                    @if ($result['update_existing'] ?? false)
                        <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ $result['dry_run'] ? 'Existing — will be updated' : 'Updated' }}</span><span class="ak-kpi-value">{{ number_format($result['counts']['updated']) }}</span></span></div>
                        <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Existing — no change</span><span class="ak-kpi-value">{{ number_format($result['counts']['unchanged']) }}</span></span></div>
                    @endif
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ ($result['update_existing'] ?? false) ? 'Skipped' : 'Duplicates skipped' }}</span><span class="ak-kpi-value">{{ number_format($result['counts']['skipped']) }}</span></span></div>
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Rows with errors</span><span class="ak-kpi-value">{{ number_format($result['counts']['errors']) }}</span></span></div>
                </div>
                @if (! empty($result['ignored_columns']))
                    <div class="ak-alert ak-alert-warn" style="margin:0 16px 16px"><b>Columns not recognised and ignored:</b> {{ implode(', ', $result['ignored_columns']) }}. Check the spelling against the template, otherwise those values are not imported.</div>
                @endif
                @if (! ($result['update_existing'] ?? false) && ($result['counts']['skipped'] ?? 0) > 0)
                    <div class="ak-alert" style="margin:0 16px 16px; background:#eff6ff; color:#1e3a8a">{{ number_format($result['counts']['skipped']) }} row(s) matched products that already exist and were left untouched. To change them (for example a new conversion factor or price), tick “Update products that already exist” and check again.</div>
                @endif
                @if ($result['dry_run'])
                    <div class="ak-alert ak-alert-warn" style="margin:0 16px 16px">Looks right? Choose the same file below, untick “Check only” and import.{{ ($result['update_existing'] ?? false) ? ' Keep “Update products that already exist” ticked to apply the changes listed.' : '' }}</div>
                @endif
                @if (count($result['report']))
                    <div class="ak-dt-scroll" tabindex="0" aria-label="Row by row result">
                        <table class="ak-dt">
                            <thead><tr><th scope="col" class="ak-c">Row</th><th scope="col">Result</th><th scope="col">Code</th><th scope="col">Product</th><th scope="col">Details</th></tr></thead>
                            <tbody>
                                @foreach ($result['report'] as $line)
                                    <tr>
                                        <td class="ak-c ak-mono">{{ $line['row'] }}</td>
                                        <td><span class="ak-status ak-status-{{ $tones[$line['action']] ?? 'red' }}"><i aria-hidden="true"></i>{{ $labels[$line['action']] ?? 'Error' }}</span></td>
                                        <td class="ak-mono">{{ $line['code'] ?: '—' }}</td>
                                        <td>{{ $line['name'] ?: '—' }}</td>
                                        <td class="ak-muted">{{ $line['message'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        <div class="uf-grid">
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title"><span class="uf-step">1</span> Upload file</h2></div>
                <div class="uf-body">
                    <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <div class="uf-fields" style="grid-template-columns: 1fr">
                            <div class="uf-field">
                                <label for="import_file">Excel file (.xlsx, .xls, .csv — max 5 MB) <span class="uf-req">*</span></label>
                                <input id="import_file" type="file" name="import_file" accept=".xlsx,.xls,.csv" required style="height:auto; padding:8px 12px">
                                @error('import_file') <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                            <label style="display:flex; gap:8px; align-items:center; font-size:14px">
                                <input type="checkbox" name="dry_run" value="1" checked style="width:16px;height:16px">
                                Check only — show what would happen without saving
                            </label>
                            @can('product-edit')
                                <label style="display:flex; gap:8px; align-items:flex-start; font-size:14px">
                                    <input type="checkbox" name="update_existing" value="1" style="width:16px;height:16px; margin-top:3px">
                                    <span>Update products that already exist (same Product Code) instead of skipping them.
                                        <span class="ak-muted" style="display:block; font-size:12.5px">Only columns filled in the file change; blank cells and missing columns keep the stored value. Price changes are logged and a new sell price is applied to stock in hand.</span></span>
                                </label>
                                <div style="margin-left:24px; display:flex; flex-direction:column; gap:4px; font-size:14px">
                                    <label style="display:flex; gap:8px; align-items:center"><input type="radio" name="update_scope" value="all" checked style="width:15px;height:15px"> Update every column filled in the file</label>
                                    <label style="display:flex; gap:8px; align-items:center"><input type="radio" name="update_scope" value="prices" style="width:15px;height:15px"> Prices only (Sell, Cost and Expiry price) — names, brands and categories in the file are ignored</label>
                                </div>
                            @endcan
                        </div>
                        <div style="margin-top:20px; display:flex; justify-content:flex-end">
                            <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy"><span x-show="!busy">Run import</span><span x-show="busy" x-cloak>Working…</span></button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">How it works</h2></div>
                <div class="uf-body" style="font-size:13.5px; line-height:1.6">
                    <ol style="margin:0 0 12px 18px; list-style:decimal">
                        <li>Download the template (same columns as the product Excel export) and fill one product per row.</li>
                        <li>Required: <b>Product Code</b>, <b>Product Name</b> and <b>UOM</b> (defaults to <i>Piece</i> if empty).</li>
                        <li>Run with “Check only” first, then import for real.</li>
                    </ol>
                    <p><b>A row matches an existing product</b> when its <b>Product Code</b> already exists (even if deactivated). By default such rows are skipped and never overwritten, so prices and stock cannot be changed by accident. A code that appears twice in the file is skipped the second time.</p>
                    <p><b>Update products that already exist</b> (needs the product edit permission): matching rows are changed instead of skipped, but only the columns filled in the file. “Check only” lists every change as <i>old → new</i>, so you can see what is new, what will be updated and what is already up to date before anything is saved. Deleted products are never touched.</p>
                    <p><b>Category, Supplier, UOM, Sales UOM</b> must match existing names exactly. <b>Valuation Method:</b> {{ implode(', ', $valuationMethods) }}. <b>Powder?</b> Yes / No. <b>Status:</b> Active / Inactive. Optional <b>Conversion Factor</b> = base units per sales unit.</p>
                    <p class="ak-muted">If any value is wrong, only that row is reported; other rows still import.</p>
                </div>
            </section>
        </div>

        <section class="uf-card gh-flow" style="margin-top:20px" aria-labelledby="pi-flow">
            <style>
                .gh-flow .fc-box { fill: #fff; stroke: #172554; stroke-width: 1.4; }
                .gh-flow .fc-box-green { fill: #dcfce7; stroke: #15803d; stroke-width: 1.4; }
                .gh-flow .fc-box-amber { fill: #fef3c7; stroke: #b45309; stroke-width: 1.4; }
                .gh-flow .fc-box-red { fill: #fee2e2; stroke: #b91c1c; stroke-width: 1.4; }
                .gh-flow .fc-box-slate { fill: #f1f5f9; stroke: #64748b; stroke-width: 1.4; }
                .gh-flow .fc-text { font-size: 12.5px; fill: #0f172a; font-family: ui-sans-serif, system-ui, sans-serif; }
                .gh-flow .fc-bold { font-weight: 700; }
                .gh-flow .fc-small { font-size: 11px; fill: #475569; font-family: ui-sans-serif, system-ui, sans-serif; }
                .gh-flow .fc-line { stroke: #475569; stroke-width: 1.4; fill: none; }
            </style>
            <div class="uf-card-head"><h2 class="uf-card-title" id="pi-flow">Flow: how an import runs</h2></div>
            <div class="uf-body" style="overflow-x:auto">
                <p style="font-size:13.5px; margin:0 0 8px">Always run “Check only” first. It runs exactly the same checks as the real import and rolls everything back, so what it shows is what the import will do.</p>
                <svg viewBox="0 0 960 150" width="100%" role="img" aria-label="Import process" style="min-width:760px"><defs><marker id="fc-arrow-pp" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box" x="5" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="87.5" y="46" text-anchor="middle">1. Prepare</text><text class="fc-small" x="87.5" y="64" text-anchor="middle">Template or export,</text><text class="fc-small" x="87.5" y="78" text-anchor="middle">one product per row</text><path class="fc-line" style="marker-end:url(#fc-arrow-pp)" d="M170 57 H192" /><rect class="fc-box" x="194" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="276.5" y="46" text-anchor="middle">2. Check only</text><text class="fc-small" x="276.5" y="64" text-anchor="middle">Upload with “Check only”</text><text class="fc-small" x="276.5" y="78" text-anchor="middle">ticked (nothing saved)</text><path class="fc-line" style="marker-end:url(#fc-arrow-pp)" d="M359 57 H381" /><rect class="fc-box-amber" x="383" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="465.5" y="46" text-anchor="middle">3. Read the result</text><text class="fc-small" x="465.5" y="64" text-anchor="middle">New / Will update /</text><text class="fc-small" x="465.5" y="78" text-anchor="middle">No change / Skipped / Error</text><path class="fc-line" style="marker-end:url(#fc-arrow-pp)" d="M548 57 H570" /><rect class="fc-box" x="572" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="654.5" y="46" text-anchor="middle">4. Fix the file</text><text class="fc-small" x="654.5" y="64" text-anchor="middle">if any row shows Error,</text><text class="fc-small" x="654.5" y="78" text-anchor="middle">then check again</text><path class="fc-line" style="marker-end:url(#fc-arrow-pp)" d="M737 57 H759" /><rect class="fc-box-green" x="761" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="843.5" y="46" text-anchor="middle">5. Import for real</text><text class="fc-small" x="843.5" y="64" text-anchor="middle">Untick “Check only”,</text><text class="fc-small" x="843.5" y="78" text-anchor="middle">same file, run import</text><path class="fc-line" style="marker-end:url(#fc-arrow-pp)" d="M654.5 94 V122 H276.5 V96" /><text class="fc-small" x="425.5" y="138">repeat until no Error</text></svg>
                <h3 style="margin:18px 0 6px; font-size:14px; font-weight:700; color:var(--ak-navy)">What happens to each row</h3>
                <p style="font-size:13.5px; margin:0 0 8px">Amber boxes are checks, green is saved, red is an error on that row only, amber outcomes are left untouched.</p>
                <svg viewBox="0 0 860 634" width="100%" role="img" aria-label="Row decision flow chart" style="min-width:700px;max-width:880px"><defs><marker id="fc-arrow-pi" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="20" y="10" width="330" height="40" rx="9" /><text class="fc-text fc-bold" x="185.0" y="34.0" text-anchor="middle">One row of the file</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M185.0 50 V76" /><text class="fc-small" x="191.0" y="67">yes</text><rect class="fc-box-amber" x="20" y="76" width="330" height="40" rx="9" /><text class="fc-text" x="185.0" y="100.0" text-anchor="middle">Product Code filled in?</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M350 96.0 H418" /><text class="fc-small" x="358" y="91.0">no</text><rect class="fc-box-red" x="420" y="79.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="100.0" text-anchor="middle">ERROR: Product Code is required</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M185.0 116 V142" /><text class="fc-small" x="191.0" y="133">no</text><rect class="fc-box-amber" x="20" y="142" width="330" height="46" rx="9" /><text class="fc-text" x="185.0" y="161.0" text-anchor="middle">Same code already handled</text><text class="fc-text" x="185.0" y="177.0" text-anchor="middle">earlier in this file?</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M350 165.0 H418" /><text class="fc-small" x="358" y="160.0">yes</text><rect class="fc-box-amber" x="420" y="148.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="169.0" text-anchor="middle">SKIPPED: duplicate inside the file</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M185.0 188 V214" /><text class="fc-small" x="191.0" y="205">yes</text><rect class="fc-box-amber" x="20" y="214" width="330" height="46" rx="9" /><text class="fc-text" x="185.0" y="233.0" text-anchor="middle">Product Code already in</text><text class="fc-text" x="185.0" y="249.0" text-anchor="middle">the system?</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M350 237.0 H418" /><text class="fc-small" x="358" y="232.0">no</text><rect class="fc-box-green" x="420" y="207.0" width="420" height="60" rx="9" /><text class="fc-text" x="630.0" y="225.0" text-anchor="middle">Check the values (names exist, numbers valid,</text><text class="fc-text" x="630.0" y="241.0" text-anchor="middle">barcode free): bad = ERROR, good = NEW</text><text class="fc-text" x="630.0" y="257.0" text-anchor="middle">(added; in Check only: &quot;New&quot;)</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M185.0 260 V286" /><text class="fc-small" x="191.0" y="277">yes</text><rect class="fc-box-amber" x="20" y="286" width="330" height="46" rx="9" /><text class="fc-text" x="185.0" y="305.0" text-anchor="middle">“Update products that already</text><text class="fc-text" x="185.0" y="321.0" text-anchor="middle">exist” ticked and user can edit?</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M350 309.0 H418" /><text class="fc-small" x="358" y="304.0">no</text><rect class="fc-box-amber" x="420" y="292.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="313.0" text-anchor="middle">SKIPPED: existing product left untouched</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M185.0 332 V358" /><text class="fc-small" x="191.0" y="349">no</text><rect class="fc-box-amber" x="20" y="358" width="330" height="40" rx="9" /><text class="fc-text" x="185.0" y="382.0" text-anchor="middle">Product was deleted?</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M350 378.0 H418" /><text class="fc-small" x="358" y="373.0">yes</text><rect class="fc-box-amber" x="420" y="361.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="382.0" text-anchor="middle">SKIPPED: restore it before updating</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M185.0 398 V424" /><text class="fc-small" x="191.0" y="415">yes</text><rect class="fc-box-amber" x="20" y="424" width="330" height="46" rx="9" /><text class="fc-text" x="185.0" y="443.0" text-anchor="middle">Filled-in values valid?</text><text class="fc-text" x="185.0" y="459.0" text-anchor="middle">(names found, numbers, barcode)</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M350 447.0 H418" /><text class="fc-small" x="358" y="442.0">no</text><rect class="fc-box-red" x="420" y="430.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="451.0" text-anchor="middle">ERROR: that row only, others continue</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M185.0 470 V496" /><text class="fc-small" x="191.0" y="487">yes</text><rect class="fc-box-amber" x="20" y="496" width="330" height="46" rx="9" /><text class="fc-text" x="185.0" y="515.0" text-anchor="middle">Any filled-in value different</text><text class="fc-text" x="185.0" y="531.0" text-anchor="middle">from the stored one?</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M350 519.0 H418" /><text class="fc-small" x="358" y="514.0">no</text><rect class="fc-box-amber" x="420" y="502.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="523.0" text-anchor="middle">NO CHANGE: already up to date</text><path class="fc-line" style="marker-end:url(#fc-arrow-pi)" d="M185.0 542 V568" /><rect class="fc-box-green" x="20" y="568" width="330" height="56" rx="9" /><text class="fc-text fc-bold" x="185.0" y="584.0" text-anchor="middle">UPDATED</text><text class="fc-text" x="185.0" y="600.0" text-anchor="middle">only the differing columns are saved;</text><text class="fc-text" x="185.0" y="616.0" text-anchor="middle">each change listed as old → new</text></svg>
            </div>
        </section>
    </div>
</x-app-layout>
