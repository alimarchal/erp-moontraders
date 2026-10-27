{{--
    Settings -> Customers -> How customers work (/settings/customers/help). Static guide.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('customers.index') }}">Customers</a><span aria-hidden="true">›</span><span>How customers work</span>
                </nav>
                <h1 class="ak-title">How customers work</h1>
                <p class="ak-sub">Shops from creation to use in settlements, with import, status and permissions.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('customers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Customers</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>
        .gh-wrap { display: flex; flex-direction: column; gap: 20px; }
        .gh-card p, .gh-card li { font-size: 14px; line-height: 1.55; color: var(--ak-text); }
        .gh-card ul, .gh-card ol { margin: 6px 0 0; padding-left: 22px; }
        .gh-card ul { list-style: disc; }
        .gh-card ol { list-style: decimal; }
        .gh-card li { margin: 3px 0; }
        .gh-card h3 { margin: 14px 0 4px; font-size: 14px; font-weight: 700; color: var(--ak-navy); }
        .gh-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
        .gh-table th { text-align: left; background: var(--ak-slate-bg); color: var(--ak-muted); font-weight: 600; padding: 7px 10px; border-bottom: 1px solid var(--ak-border); }
        .gh-table td { padding: 7px 10px; border-bottom: 1px solid var(--ak-line); vertical-align: top; }
        .gh-note { margin-top: 10px; padding: 9px 12px; border-left: 3px solid var(--ak-amber); background: var(--ak-amber-bg); border-radius: 6px; font-size: 13px; }
        .gh-scroll { overflow-x: auto; }
        .gh-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; background: var(--ak-slate-bg); padding: 1px 5px; border-radius: 4px; }
        .fc-box { fill: #fff; stroke: #172554; stroke-width: 1.4; }
        .fc-box-green { fill: #dcfce7; stroke: #15803d; stroke-width: 1.4; }
        .fc-box-amber { fill: #fef3c7; stroke: #b45309; stroke-width: 1.4; }
        .fc-box-red { fill: #fee2e2; stroke: #b91c1c; stroke-width: 1.4; }
        .fc-box-slate { fill: #f1f5f9; stroke: #64748b; stroke-width: 1.4; }
        .fc-text { font-size: 12.5px; fill: #0f172a; font-family: ui-sans-serif, system-ui, sans-serif; }
        .fc-bold { font-weight: 700; }
        .fc-small { font-size: 11px; fill: #475569; font-family: ui-sans-serif, system-ui, sans-serif; }
        .fc-line { stroke: #475569; stroke-width: 1.4; fill: none; }
        @media print { .gh-card { break-inside: avoid; box-shadow: none; } }
    </style>

    <div class="ak-page">
        <div class="gh-wrap">
            <section class="uf-card gh-card" aria-labelledby="cu-what">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cu-what">1. What a customer is</h2></header>
                <div class="uf-body">
                    <p>A customer is a shop the company sells to. It carries the shop's identity, how it is classified, its credit terms and the accounts used for its balance. Sales settlements record what was sold to it on credit, what was recovered and by which cheque or transfer, so a customer's history lives in those documents and in the customer reports.</p>
                    <ul>
                        <li><b>Active</b>: offered in new sales settlements and lists.</li>
                        <li><b>Inactive</b>: no longer offered in new settlements. Everything already recorded stays and still shows in reports.</li>
                        <li><b>Customers are never deleted.</b> There is no delete button; use Deactivate, and Activate to bring a shop back.</li>
                    </ul>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="cu-flow">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cu-flow">2. Lifecycle</h2></header>
                <div class="uf-body gh-scroll"><svg viewBox="0 0 960 160" width="100%" role="img" aria-label="Customer lifecycle" style="min-width:760px"><defs><marker id="fc-arrow-cl" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box" x="5" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="87.5" y="47.0" text-anchor="middle">1. Add the shop</text><text class="fc-small" x="87.5" y="61.0" text-anchor="middle">form or Excel import</text><text class="fc-small" x="87.5" y="75.0" text-anchor="middle">one record per shop</text><rect class="fc-box-green" x="195" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="277.5" y="47.0" text-anchor="middle">2. Active</text><text class="fc-small" x="277.5" y="61.0" text-anchor="middle">shown in new sales</text><text class="fc-small" x="277.5" y="75.0" text-anchor="middle">settlements and lists</text><rect class="fc-box" x="385" y="20" width="200" height="74" rx="10" /><text class="fc-text fc-bold" x="485.0" y="47.0" text-anchor="middle">3. Used in business</text><text class="fc-small" x="485.0" y="61.0" text-anchor="middle">credit sales, recoveries, cheques,</text><text class="fc-small" x="485.0" y="75.0" text-anchor="middle">bank transfers, advance tax</text><rect class="fc-box-amber" x="610" y="20" width="165" height="74" rx="10" /><text class="fc-text fc-bold" x="692.5" y="47.0" text-anchor="middle">4. Deactivate</text><text class="fc-small" x="692.5" y="61.0" text-anchor="middle">hidden from new settlements</text><text class="fc-small" x="692.5" y="75.0" text-anchor="middle">history and balances stay</text><rect class="fc-box-green" x="800" y="20" width="155" height="74" rx="10" /><text class="fc-text fc-bold" x="877.5" y="54.0" text-anchor="middle">5. Activate</text><text class="fc-small" x="877.5" y="68.0" text-anchor="middle">comes back as Active</text><path class="fc-line" style="marker-end:url(#fc-arrow-cl)" d="M170 57 H193" /><path class="fc-line" style="marker-end:url(#fc-arrow-cl)" d="M360 57 H383" /><path class="fc-line" style="marker-end:url(#fc-arrow-cl)" d="M585 57 H608" /><path class="fc-line" style="marker-end:url(#fc-arrow-cl)" d="M775 57 H798" /><path class="fc-line" style="marker-end:url(#fc-arrow-cl)" d="M877 94 V130 H277 V96" /><text class="fc-small" x="500" y="146">activate again any time (no data is ever deleted)</text></svg></div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="cu-fields">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cu-fields">3. Fields</h2></header>
                <div class="uf-body">
                    <table class="gh-table">
                        <thead><tr><th>Field</th><th>Meaning</th></tr></thead>
                        <tbody>
                            <tr><td><b>Customer Code</b></td><td>Unique code of the shop. Left blank on import, one is generated (CUST-0001, CUST-0002 …). A code is never reused.</td></tr>
                            <tr><td><b>Customer Name</b></td><td>Required. Business name, phone, email, NTN and CNIC are optional identity details.</td></tr>
                            <tr><td>Address, Sub locality, City, State, Country</td><td>Where the shop is. Sub locality and city are used to find and filter shops.</td></tr>
                            <tr><td><b>Channel Type</b></td><td>The kind of shop (for example General Store, Pharmacy). Must be one of the list in the form.</td></tr>
                            <tr><td><b>Category</b></td><td>A, B, C or D: the shop's priority tier.</td></tr>
                            <tr><td>Credit limit, Payment terms</td><td>Credit allowed and the days to pay (0 to 365).</td></tr>
                            <tr><td>Assigned sales rep</td><td>The user who looks after the shop.</td></tr>
                            <tr><td>IT status</td><td>Yes or No flag for income-tax status.</td></tr>
                            <tr><td>Receivable / Payable account</td><td>Chart-of-accounts accounts used for this shop's balance.</td></tr>
                            <tr><td>Status</td><td>Active or Inactive (see section 1).</td></tr>
                        </tbody>
                    </table>
                    <p style="margin-top:8px">Dropdowns (company, channel, category, sales rep, accounts) are searchable: type to find an option.</p>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="cu-import">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cu-import">4. Import from Excel</h2></header>
                <div class="uf-body gh-scroll">
                    <ol>
                        <li>Download the template, fill one shop per row, upload it.</li>
                        <li>Keep <b>Check only</b> ticked first. Nothing is saved; the result lists each row as <b>New</b>, <b>Will update</b> (old → new), <b>No change</b>, <b>Skipped</b> or <b>Error</b>.</li>
                        <li>Tick <b>Update customers that already exist</b> to change shops whose Customer Code is already in the system. Only the columns filled in the file change; blank cells keep the stored value. Without it, existing codes are skipped.</li>
                        <li>Columns the importer does not recognise are listed in the result so a typo is not lost silently.</li>
                        <li>Untick Check only and run the same file to save.</li>
                    </ol>
                    <svg viewBox="0 0 860 534" width="100%" role="img" aria-label="Import row flow chart" style="min-width:700px;max-width:880px"><defs><marker id="fc-arrow-cr" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="20" y="10" width="340" height="40" rx="9" /><text class="fc-text fc-bold" x="190.0" y="34.0" text-anchor="middle">One shop row in the Excel file</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M190.0 50 V76" /><text class="fc-small" x="196.0" y="67">yes</text><rect class="fc-box-amber" x="20" y="76" width="340" height="40" rx="9" /><text class="fc-text" x="190.0" y="100.0" text-anchor="middle">Customer Name filled in?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M360 96.0 H428" /><text class="fc-small" x="368" y="91.0">no</text><rect class="fc-box-red" x="430" y="79.0" width="410" height="34" rx="9" /><text class="fc-text" x="635.0" y="100.0" text-anchor="middle">ERROR: Customer Name is required</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M190.0 116 V142" /><text class="fc-small" x="196.0" y="133">yes</text><rect class="fc-box-amber" x="20" y="142" width="340" height="46" rx="9" /><text class="fc-text" x="190.0" y="161.0" text-anchor="middle">Values valid? (channel type, category A–D,</text><text class="fc-text" x="190.0" y="177.0" text-anchor="middle">numbers, sales rep found)</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M360 165.0 H428" /><text class="fc-small" x="368" y="160.0">no</text><rect class="fc-box-red" x="430" y="148.0" width="410" height="34" rx="9" /><text class="fc-text" x="635.0" y="169.0" text-anchor="middle">ERROR: that row only, others continue</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M190.0 188 V214" /><text class="fc-small" x="196.0" y="205">yes</text><rect class="fc-box-amber" x="20" y="214" width="340" height="40" rx="9" /><text class="fc-text" x="190.0" y="238.0" text-anchor="middle">Customer Code already in the system?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M360 234.0 H428" /><text class="fc-small" x="368" y="229.0">no</text><rect class="fc-box-green" x="430" y="217.0" width="410" height="34" rx="9" /><text class="fc-text" x="635.0" y="238.0" text-anchor="middle">NEW shop (code CUST-0001… generated if blank)</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M190.0 254 V280" /><text class="fc-small" x="196.0" y="271">no</text><rect class="fc-box-amber" x="20" y="280" width="340" height="40" rx="9" /><text class="fc-text" x="190.0" y="304.0" text-anchor="middle">Code belongs to a deleted customer?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M360 300.0 H428" /><text class="fc-small" x="368" y="295.0">yes</text><rect class="fc-box-red" x="430" y="283.0" width="410" height="34" rx="9" /><text class="fc-text" x="635.0" y="304.0" text-anchor="middle">ERROR: restore or rename it first</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M190.0 320 V346" /><text class="fc-small" x="196.0" y="337">yes</text><rect class="fc-box-amber" x="20" y="346" width="340" height="40" rx="9" /><text class="fc-text" x="190.0" y="370.0" text-anchor="middle">“Update customers that already exist” ticked?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M360 366.0 H428" /><text class="fc-small" x="368" y="361.0">no</text><rect class="fc-box-amber" x="430" y="349.0" width="410" height="34" rx="9" /><text class="fc-text" x="635.0" y="370.0" text-anchor="middle">SKIPPED: existing shop left untouched</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M190.0 386 V412" /><text class="fc-small" x="196.0" y="403">yes</text><rect class="fc-box-amber" x="20" y="412" width="340" height="40" rx="9" /><text class="fc-text" x="190.0" y="436.0" text-anchor="middle">Any filled-in value different from the stored one?</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M360 432.0 H428" /><text class="fc-small" x="368" y="427.0">no</text><rect class="fc-box-amber" x="430" y="415.0" width="410" height="34" rx="9" /><text class="fc-text" x="635.0" y="436.0" text-anchor="middle">NO CHANGE: already up to date</text><path class="fc-line" style="marker-end:url(#fc-arrow-cr)" d="M190.0 452 V478" /><rect class="fc-box-green" x="20" y="478" width="340" height="46" rx="9" /><text class="fc-text fc-bold" x="190.0" y="497.0" text-anchor="middle">UPDATED: only differing columns saved,</text><text class="fc-text" x="190.0" y="513.0" text-anchor="middle">each change listed as old → new</text></svg>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="cu-perm">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cu-perm">5. Who can do what</h2></header>
                <div class="uf-body">
                    <table class="gh-table">
                        <thead><tr><th>Permission</th><th>Allows</th></tr></thead>
                        <tbody>
                            <tr><td class="gh-code">customer-list</td><td>See customers and export to Excel</td></tr>
                            <tr><td class="gh-code">customer-create</td><td>Add customers, import from Excel, see this page</td></tr>
                            <tr><td class="gh-code">customer-edit</td><td>Edit a customer, activate or deactivate it, update existing shops from an import</td></tr>
                            <tr><td>super admin</td><td>Everything above</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note">Related reports: Customer Account Statement, Opening Customer Balance and Advance Tax Customer Summary.</div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
