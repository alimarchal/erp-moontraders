{{-- Settings -> Customers -> Import from Excel (/settings/customers/import). --}}
@php
    $result = session('import_result');
    $labels = ['created' => 'Added', 'updated' => 'Updated', 'skipped' => 'Duplicate', 'error' => 'Error'];
    $tones = ['created' => 'green', 'updated' => 'green', 'skipped' => 'amber', 'error' => 'red'];
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
                <p class="ak-sub">Add many shops at once. Duplicates are detected for you.</p>
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
                        <span class="ak-muted" style="font-weight:400">{{ $result['file'] }}</span>
                    </h2>
                </div>
                <div class="ak-kpis" style="padding:16px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr))">
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ $result['dry_run'] ? 'Will be added' : 'Added' }}</span><span class="ak-kpi-value">{{ number_format($result['counts']['created']) }}</span></span></div>
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ $result['dry_run'] ? 'Will be updated' : 'Updated' }}</span><span class="ak-kpi-value">{{ number_format($result['counts']['updated']) }}</span></span></div>
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Duplicates skipped</span><span class="ak-kpi-value">{{ number_format($result['counts']['skipped']) }}</span></span></div>
                    <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Rows with errors</span><span class="ak-kpi-value">{{ number_format($result['counts']['errors']) }}</span></span></div>
                </div>
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
                            <div class="uf-field">
                                <label for="duplicate_mode">If a shop already exists</label>
                                <select id="duplicate_mode" name="duplicate_mode">
                                    <option value="skip" @selected(old('duplicate_mode', 'skip') === 'skip')>Skip it (keep the existing record)</option>
                                    <option value="update" @selected(old('duplicate_mode') === 'update')>Update it with the filled columns</option>
                                </select>
                            </div>
                            <label style="display:flex; gap:8px; align-items:center; font-size:14px">
                                <input type="checkbox" name="dry_run" value="1" checked style="width:16px;height:16px">
                                Check only — show what would happen without saving
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
    </div>
</x-app-layout>
