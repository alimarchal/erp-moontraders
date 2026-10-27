{{-- Settings -> Customers -> Import from Excel (/settings/customers/import). --}}
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
                    <a href="{{ route('customers.index') }}">Customers</a><span aria-hidden="true">›</span><span>Import</span>
                </nav>
                <h1 class="ak-title">Import customers from Excel</h1>
                <p class="ak-sub">Add many shops at once. Existing shop codes are skipped or updated, as you choose.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('customers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('customers.import.template') }}" class="ak-btn ak-btn-outline">Download template</a>
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
                        <span class="ak-muted" style="font-weight:400">{{ $result['file'] }}@if (($result['duplicate_mode'] ?? 'skip') === 'update') · existing shops: update @endif</span>
                    </h2>
                </div>
                <div class="ak-kpis" style="padding:16px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr))">
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ $result['dry_run'] ? 'New — will be added' : 'Added' }}</span><span class="ak-kpi-value">{{ number_format($result['counts']['created']) }}</span></span></div>
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ $result['dry_run'] ? 'Existing — will be updated' : 'Updated' }}</span><span class="ak-kpi-value">{{ number_format($result['counts']['updated']) }}</span></span></div>
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Existing — no change</span><span class="ak-kpi-value">{{ number_format($result['counts']['unchanged']) }}</span></span></div>
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Skipped (duplicates)</span><span class="ak-kpi-value">{{ number_format($result['counts']['skipped']) }}</span></span></div>
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Rows with errors</span><span class="ak-kpi-value">{{ number_format($result['counts']['errors']) }}</span></span></div>
                </div>
                @if (! empty($result['ignored_columns']))
                    <div class="ak-alert ak-alert-warn" style="margin:0 16px 16px"><b>Columns not recognised and ignored:</b> {{ implode(', ', $result['ignored_columns']) }}. Check the spelling against the template, otherwise those values are not imported.</div>
                @endif
                @if (($result['duplicate_mode'] ?? 'skip') !== 'update' && ($result['counts']['skipped'] ?? 0) > 0)
                    <div class="ak-alert" style="margin:0 16px 16px; background:#eff6ff; color:#1e3a8a">{{ number_format($result['counts']['skipped']) }} row(s) matched shops that already exist and were left untouched. To change them (for example a new phone or address), tick “Update shops that already exist” and check again.</div>
                @endif
                @if ($result['dry_run'])
                    <div class="ak-alert ak-alert-warn" style="margin:0 16px 16px">Looks right? Choose the same file below, untick “Check only” and import.</div>
                @endif
                @if (count($result['report']))
                    <div class="ak-dt-scroll" tabindex="0" aria-label="Row by row result">
                        <table class="ak-dt">
                            <thead><tr><th scope="col" class="ak-c">Row</th><th scope="col">Result</th><th scope="col">Code</th><th scope="col">Customer</th><th scope="col">Details</th></tr></thead>
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
                    <form method="POST" action="{{ route('customers.import') }}" enctype="multipart/form-data" x-data="{ busy: false }" @submit="busy = true">
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
                            <label style="display:flex; gap:8px; align-items:flex-start; font-size:14px">
                                <input type="checkbox" name="update_existing" value="1" @checked(old('update_existing')) style="width:16px;height:16px; margin-top:3px">
                                <span>Update customers that already exist (same Customer Code) instead of skipping them.
                                    <span class="ak-muted" style="display:block; font-size:12.5px">Only columns filled in the file change; blank cells and missing columns keep the stored value. Check only lists every change as old → new.</span></span>
                            </label>
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
                        <li>Download the template and fill one shop per row.</li>
                        <li>Only <b>Customer Name</b> is required. Leave <b>Customer Code</b> empty to get <code>CUST-0001</code>, <code>CUST-0002</code> …</li>
                        <li>Run with “Check only” first, then import for real.</li>
                    </ol>
                    <p><b>A row is a duplicate</b> when its <b>Customer Code</b> already exists in the system or earlier in the same file. Nothing else is compared, so two shops with the same name but different codes are both added.</p>
                    <p><b>Channel Type:</b> {{ implode(', ', $channelTypes) }}.<br><b>Category:</b> {{ implode(', ', $customerCategories) }}.<br><b>Sales Rep:</b> user name or email. <b>Status:</b> Active / Inactive.</p>
                    <p class="ak-muted">If any value is wrong, only that row is reported; other rows still import.</p>
                </div>
            </section>
        </div>

        <section class="uf-card gh-flow" style="margin-top:20px" aria-labelledby="ci-flow">
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
            <div class="uf-card-head"><h2 class="uf-card-title" id="ci-flow">Flow: how an import runs</h2></div>
            <div class="uf-body" style="overflow-x:auto">
                <p style="font-size:13.5px; margin:0 0 8px">Always run “Check only” first. It runs exactly the same checks as the real import and rolls everything back, so what it shows is what the import will do. New shops, shops that will be updated (old → new) and shops already up to date are listed separately.</p>
                <svg viewBox="0 0 960 150" width="100%" role="img" aria-label="Import process" style="min-width:760px"><defs><marker id="fc-arrow-cp" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box" x="5" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="87.5" y="46" text-anchor="middle">1. Prepare</text><text class="fc-small" x="87.5" y="64" text-anchor="middle">Template or export,</text><text class="fc-small" x="87.5" y="78" text-anchor="middle">one shop per row</text><path class="fc-line" style="marker-end:url(#fc-arrow-cp)" d="M170 57 H192" /><rect class="fc-box" x="194" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="276.5" y="46" text-anchor="middle">2. Check only</text><text class="fc-small" x="276.5" y="64" text-anchor="middle">Upload with “Check only”</text><text class="fc-small" x="276.5" y="78" text-anchor="middle">ticked (nothing saved)</text><path class="fc-line" style="marker-end:url(#fc-arrow-cp)" d="M359 57 H381" /><rect class="fc-box-amber" x="383" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="465.5" y="46" text-anchor="middle">3. Read the result</text><text class="fc-small" x="465.5" y="64" text-anchor="middle">New / Will update /</text><text class="fc-small" x="465.5" y="78" text-anchor="middle">No change / Skipped / Error</text><path class="fc-line" style="marker-end:url(#fc-arrow-cp)" d="M548 57 H570" /><rect class="fc-box" x="572" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="654.5" y="46" text-anchor="middle">4. Fix the file</text><text class="fc-small" x="654.5" y="64" text-anchor="middle">if any row shows Error</text><text class="fc-small" x="654.5" y="78" text-anchor="middle">or a column is not recognised</text><path class="fc-line" style="marker-end:url(#fc-arrow-cp)" d="M737 57 H759" /><rect class="fc-box-green" x="761" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="843.5" y="46" text-anchor="middle">5. Import for real</text><text class="fc-small" x="843.5" y="64" text-anchor="middle">Untick “Check only”,</text><text class="fc-small" x="843.5" y="78" text-anchor="middle">same file, run import</text><path class="fc-line" style="marker-end:url(#fc-arrow-cp)" d="M654.5 94 V122 H276.5 V96" /><text class="fc-small" x="425.5" y="138">repeat until no Error</text></svg>
                <h3 style="margin:18px 0 6px; font-size:14px; font-weight:700; color:var(--ak-navy)">What happens to each row</h3>
                <svg viewBox="0 0 860 550" width="100%" role="img" aria-label="Row decision flow chart" style="min-width:700px;max-width:880px"><defs><marker id="fc-arrow-cu" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="20" y="10" width="330" height="40" rx="9" /><text class="fc-text fc-bold" x="185.0" y="34.0" text-anchor="middle">One row of the file</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M185.0 50 V76" /><text class="fc-small" x="191.0" y="67">yes</text><rect class="fc-box-amber" x="20" y="76" width="330" height="40" rx="9" /><text class="fc-text" x="185.0" y="100.0" text-anchor="middle">Customer Name filled in?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M350 96.0 H418" /><text class="fc-small" x="358" y="91.0">no</text><rect class="fc-box-red" x="420" y="79.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="100.0" text-anchor="middle">ERROR: Customer Name is required</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M185.0 116 V142" /><text class="fc-small" x="191.0" y="133">yes</text><rect class="fc-box-amber" x="20" y="142" width="330" height="46" rx="9" /><text class="fc-text" x="185.0" y="161.0" text-anchor="middle">Values valid? (channel type, category A–D,</text><text class="fc-text" x="185.0" y="177.0" text-anchor="middle">numbers, sales rep found)</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M350 165.0 H418" /><text class="fc-small" x="358" y="160.0">no</text><rect class="fc-box-red" x="420" y="148.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="169.0" text-anchor="middle">ERROR: that row only, others continue</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M185.0 188 V214" /><text class="fc-small" x="191.0" y="205">yes</text><rect class="fc-box-amber" x="20" y="214" width="330" height="40" rx="9" /><text class="fc-text" x="185.0" y="238.0" text-anchor="middle">Customer Code already in the system?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M350 234.0 H418" /><text class="fc-small" x="358" y="229.0">no</text><rect class="fc-box-green" x="420" y="212.0" width="420" height="44" rx="9" /><text class="fc-text" x="630.0" y="230.0" text-anchor="middle">NEW shop: added (code generated if blank);</text><text class="fc-text" x="630.0" y="246.0" text-anchor="middle">in Check only it shows as New</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M185.0 254 V280" /><text class="fc-small" x="191.0" y="271">no</text><rect class="fc-box-amber" x="20" y="280" width="330" height="40" rx="9" /><text class="fc-text" x="185.0" y="304.0" text-anchor="middle">Code belongs to a deleted customer?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M350 300.0 H418" /><text class="fc-small" x="358" y="295.0">yes</text><rect class="fc-box-red" x="420" y="283.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="304.0" text-anchor="middle">ERROR: restore or rename it first</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M185.0 320 V346" /><text class="fc-small" x="191.0" y="337">yes</text><rect class="fc-box-amber" x="20" y="346" width="330" height="40" rx="9" /><text class="fc-text" x="185.0" y="370.0" text-anchor="middle">“If a shop already exists” = Update?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M350 366.0 H418" /><text class="fc-small" x="358" y="361.0">no</text><rect class="fc-box-amber" x="420" y="349.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="370.0" text-anchor="middle">SKIPPED: existing shop left untouched</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M185.0 386 V412" /><text class="fc-small" x="191.0" y="403">yes</text><rect class="fc-box-amber" x="20" y="412" width="330" height="46" rx="9" /><text class="fc-text" x="185.0" y="431.0" text-anchor="middle">Any filled-in value different from</text><text class="fc-text" x="185.0" y="447.0" text-anchor="middle">the stored one?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M350 435.0 H418" /><text class="fc-small" x="358" y="430.0">no</text><rect class="fc-box-amber" x="420" y="418.0" width="420" height="34" rx="9" /><text class="fc-text" x="630.0" y="439.0" text-anchor="middle">NO CHANGE: already up to date</text><path class="fc-line" style="marker-end:url(#fc-arrow-cu)" d="M185.0 458 V484" /><rect class="fc-box-green" x="20" y="484" width="330" height="56" rx="9" /><text class="fc-text fc-bold" x="185.0" y="500.0" text-anchor="middle">UPDATED</text><text class="fc-text" x="185.0" y="516.0" text-anchor="middle">only the differing columns are saved;</text><text class="fc-text" x="185.0" y="532.0" text-anchor="middle">each change listed as old → new</text></svg>
            </div>
        </section>
    </div>
</x-app-layout>
