{{--
    Reports -> Cash Collection Distributor -> How this report works (/reports/cash-collection-distributor/help).
    Static guide: tables, cheque clearing, expense vouchers and permissions. No data is read.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('reports.cash-collection-distributor.index') }}">Cash Collection Distributor</a><span aria-hidden="true">›</span><span>How it works</span>
                </nav>
                <h1 class="ak-title">How the Cash Collection Distributor report works</h1>
                <p class="ak-sub">What each table shows, how cheque clearing and expense vouchers reach it, and who can see what.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('reports.cash-collection-distributor.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to the report</a>
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

            <section class="uf-card gh-card" aria-labelledby="cc-what">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cc-what">1. What the report shows</h2></header>
                <div class="uf-body">
                    <p>Everything that was collected on one day, grouped by <b>distribution</b> (the company). Open the filter bar, choose a company or <b>All Companies</b>, pick the date and press Apply. Until then the page stays empty.</p>
                    <ul>
                        <li><b>Date</b> is the settlement date. Cheque clearing uses the day the cheque was cleared.</li>
                        <li><b>Designation</b> (Salesman by default) and <b>Salesmen</b> narrow the settlements. They apply to every table, so the totals always agree.</li>
                        <li>The amounts for a single company are the same as that company's row under All Companies.</li>
                        <li>Screen, Print (Ctrl/Cmd+P) and PDF download show the same sheet.</li>
                    </ul>
                    <div class="gh-note">Users without the All Companies permission can open only the company on their own account. Asking for another company, or for All, returns 403.</div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="cc-flow">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cc-flow">2. How the day's money reaches the report</h2></header>
                <div class="uf-body gh-scroll">
                    <svg viewBox="0 0 880 330" width="100%" role="img" aria-label="Daily cash flow into the report" style="min-width:760px"><defs><marker id="fc-arrow-a" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box" x="10" y="10" width="250" height="56" rx="9" /><text class="fc-text fc-bold" x="135.0" y="34.0" text-anchor="middle">Sales settlement of the day</text><text class="fc-small" x="135.0" y="50.0" text-anchor="middle">cash, bank slips, transfers, cheques</text><rect class="fc-box" x="10" y="120" width="250" height="56" rx="9" /><text class="fc-text fc-bold" x="135.0" y="144.0" text-anchor="middle">Cheque register</text><text class="fc-small" x="135.0" y="160.0" text-anchor="middle">cheque stays Pending until the bank pays</text><rect class="fc-box" x="10" y="230" width="250" height="56" rx="9" /><text class="fc-text fc-bold" x="135.0" y="254.0" text-anchor="middle">Expense voucher (maker)</text><text class="fc-small" x="135.0" y="270.0" text-anchor="middle">draft, then sent to the checker</text><rect class="fc-box-amber" x="330" y="120" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="445.0" y="144.0" text-anchor="middle">Cheque cleared on a later day</text><text class="fc-small" x="445.0" y="160.0" text-anchor="middle">cleared date + cleared amount</text><rect class="fc-box-amber" x="330" y="230" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="445.0" y="254.0" text-anchor="middle">Checker approves</text><text class="fc-small" x="445.0" y="270.0" text-anchor="middle">ledger entry is posted</text><rect class="fc-box-green" x="640" y="10" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="755.0" y="34.0" text-anchor="middle">Cash, Bank Slips, Bank Transfers</text><text class="fc-small" x="755.0" y="50.0" text-anchor="middle">and Cheques tables</text><rect class="fc-box-green" x="640" y="120" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="755.0" y="144.0" text-anchor="middle">Cheque Clearing column</text><text class="fc-small" x="755.0" y="160.0" text-anchor="middle">and cash denominations</text><rect class="fc-box-green" x="640" y="230" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="755.0" y="254.0" text-anchor="middle">Expenses table</text><text class="fc-small" x="755.0" y="270.0" text-anchor="middle">single company, by party</text><path class="fc-line" style="marker-end:url(#fc-arrow-a)" d="M260 38 H638" /><path class="fc-line" style="marker-end:url(#fc-arrow-a)" d="M260 148 H328" /><path class="fc-line" style="marker-end:url(#fc-arrow-a)" d="M560 148 H638" /><path class="fc-line" style="marker-end:url(#fc-arrow-a)" d="M260 258 H328" /><path class="fc-line" style="marker-end:url(#fc-arrow-a)" d="M560 258 H638" /><text class="fc-small" x="268" y="32">same date</text><text class="fc-small" x="580" y="142">cleared date</text><text class="fc-small" x="580" y="252">voucher date</text></svg>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="cc-tables">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cc-tables">3. The tables</h2></header>
                <div class="uf-body gh-scroll">
                    <table class="gh-table">
                        <thead><tr><th>Table</th><th>What it shows</th><th>Source</th></tr></thead>
                        <tbody>
                            <tr><td><b>1 - Collection by Distribution</b></td><td>Per company: <b>Cash</b> (the notes counted in the settlement, or the cash collected when no count was entered), <b>Cheque Clearing</b> (cheques cleared that day) and <b>Total Collection</b> (cash + bank slips + bank transfers + cheques of the day's settlements, like the Amount on the paper sheet).</td><td>Settlements, cheque register</td></tr>
                            <tr><td><b>2 - Cash Denominations</b></td><td>Notes by denomination from the settlements, coins, and the <b>Cheque clearing</b> cash a salesman brings back. The total is the cash received that day (settlement cash + cheque clearing).</td><td>Settlement cash counts, cheque register</td></tr>
                            <tr><td><b>3 - Bank Slips &amp; Online From Customer</b></td><td>Per company: <b>bank slips</b> (cash the salesman deposited in the bank), <b>bank transfers / online from customer</b> (the settlement's Bank Transfers section plus recoveries received by bank instead of cash) and their total.</td><td>Settlements</td></tr>
                            <tr><td><b>4 - Cheques</b></td><td>Per company: cheques received in the settlements of the day.</td><td>Settlements</td></tr>
                            <tr><td><b>5 - Bank Slip Details</b></td><td>One row per distribution and salesman, one column per bank account (bank name and account number). A salesman who used two accounts has an amount under each.</td><td>Settlement bank slips</td></tr>
                            <tr><td><b>6 - Expenses</b></td><td>Approved expense vouchers of the day by party. A single company shows its own vouchers; All Companies also shows the general (Moon Traders) ones that belong to no company.</td><td>Expense vouchers</td></tr>
                            <tr><td><b>7 - Cash Summary</b></td><td>Notes and coins + cheque clearing = cash received; less expenses paid = cash in hand; then bank slips, transfers and cheques; and the grand total of the day.</td><td>All of the above</td></tr>
                            <tr><td><b>8 - Salesman-wise Detail</b></td><td>Single company only: cash, slips, transfers and cheques for each salesman.</td><td>Settlements</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note"><b>Grand total</b> = cash received (settlement cash + cheque clearing) + bank slips + bank transfers + cheques. A cheque is counted twice on purpose: once when it is received (table 4) and again, as cash, on the day it is cleared. Expenses paid by voucher come out of the cash received.</div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="cc-cheque">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cc-cheque">4. Cheque clearing</h2></header>
                <div class="uf-body">
                    <p>The salesman goes to the bank with the cheque and brings back cash. That cash is not part of any settlement, so it is recorded in the <b>Cheque Register</b> instead.</p>
                    <ol>
                        <li>Open Reports → Cheque Register and click the status of the cheque.</li>
                        <li>Choose <b>Cleared</b>, enter the <b>cleared date</b> (the day the salesman brought the cash).</li>
                        <li><b>Amount cleared</b> already shows the cheque amount. Change it only if the bank paid a different amount.</li>
                        <li>Add a note if useful and press Update.</li>
                    </ol>
                    <ul>
                        <li>Every change is kept. The clock icon beside the cheque number opens its <b>history</b>: when, by whom, from which status to which, the date, amount and note.</li>
                        <li>Setting a cheque back to Pending or to Bounced removes its cleared date and amount from the cheque, so it no longer appears as cleared cash; the history still shows what happened.</li>
                        <li>Cheques cleared before this feature were given a cleared amount equal to the cheque amount and one history line.</li>
                    </ul>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="cc-voucher">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cc-voucher">5. Expense vouchers (maker and checker)</h2></header>
                <div class="uf-body gh-scroll">
                    <svg viewBox="0 0 880 250" width="100%" role="img" aria-label="Expense voucher maker and checker flow" style="min-width:760px"><defs><marker id="fc-arrow-b" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="10" y="20" width="170" height="56" rx="9" /><text class="fc-text fc-bold" x="95.0" y="44.0" text-anchor="middle">Maker saves</text><text class="fc-small" x="95.0" y="60.0" text-anchor="middle">Draft</text><rect class="fc-box-amber" x="230" y="20" width="190" height="56" rx="9" /><text class="fc-text fc-bold" x="325.0" y="44.0" text-anchor="middle">Sent to checker</text><text class="fc-small" x="325.0" y="60.0" text-anchor="middle">Pending approval</text><rect class="fc-box-amber" x="470" y="20" width="190" height="56" rx="9" /><text class="fc-text fc-bold" x="565.0" y="44.0" text-anchor="middle">Checker (another user)</text><text class="fc-small" x="565.0" y="60.0" text-anchor="middle">approve or reject?</text><rect class="fc-box-green" x="710" y="20" width="160" height="56" rx="9" /><text class="fc-text fc-bold" x="790.0" y="44.0" text-anchor="middle">Approved</text><text class="fc-small" x="790.0" y="60.0" text-anchor="middle">posted to ledger</text><rect class="fc-box-red" x="470" y="150" width="190" height="56" rx="9" /><text class="fc-text fc-bold" x="565.0" y="174.0" text-anchor="middle">Rejected</text><text class="fc-small" x="565.0" y="190.0" text-anchor="middle">reason is written</text><rect class="fc-box-slate" x="230" y="150" width="190" height="56" rx="9" /><text class="fc-text fc-bold" x="325.0" y="174.0" text-anchor="middle">Maker corrects</text><text class="fc-small" x="325.0" y="190.0" text-anchor="middle">edit and send again</text><rect class="fc-box-green" x="710" y="150" width="160" height="56" rx="9" /><text class="fc-text fc-bold" x="790.0" y="174.0" text-anchor="middle">Print 80mm slip</text><text class="fc-small" x="790.0" y="190.0" text-anchor="middle">thermal printer</text><path class="fc-line" style="marker-end:url(#fc-arrow-b)" d="M180 48 H228" /><path class="fc-line" style="marker-end:url(#fc-arrow-b)" d="M420 48 H468" /><path class="fc-line" style="marker-end:url(#fc-arrow-b)" d="M660 48 H708" /><path class="fc-line" style="marker-end:url(#fc-arrow-b)" d="M565 76 V148" /><path class="fc-line" style="marker-end:url(#fc-arrow-b)" d="M468 178 H422" /><path class="fc-line" style="marker-end:url(#fc-arrow-b)" d="M325 150 V78" /><path class="fc-line" style="marker-end:url(#fc-arrow-b)" d="M790 76 V148" /><text class="fc-small" x="663" y="38">yes</text><text class="fc-small" x="572" y="116">reject</text></svg>
                    <ol>
                        <li><b>Maker</b> opens Reports → Expense Vouchers → New Voucher: date, company, party name, expense account, amount and narration. It is saved as a draft with a number like EV-2026-0001.</li>
                        <li>The maker presses <b>Send to checker</b>. The voucher can no longer be changed.</li>
                        <li>A <b>different user</b> with the approve permission opens it and either <b>approves</b> (a ledger entry is posted: expense account debit, Cash 1121 credit) or <b>rejects</b> with a reason. The person who made the voucher can never approve it, not even a super admin.</li>
                        <li>A rejected voucher goes back to the maker, who corrects it and sends it again.</li>
                        <li>Only an <b>approved</b> voucher can be printed: an 80 mm thermal slip with the party, account, amount and the maker and checker names.</li>
                    </ol>
                    <div class="gh-note">Only approved vouchers appear in the Expenses table of this report. Drafts, pending and rejected ones do not. Most expenses belong to Moon Traders itself: an admin leaves the company empty (general expense); a user tied to one company always raises vouchers for that company.</div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="cc-perm">
                <header class="uf-card-head"><h2 class="uf-card-title" id="cc-perm">6. Permissions</h2></header>
                <div class="uf-body gh-scroll">
                    <table class="gh-table">
                        <thead><tr><th>Permission</th><th>Allows</th></tr></thead>
                        <tbody>
                            <tr><td><span class="gh-code">report-audit-cash-collection-distributor</span></td><td>Open the report (own company only).</td></tr>
                            <tr><td><span class="gh-code">report-audit-cash-collection-distributor-all-companies</span></td><td>Choose any company and All Companies.</td></tr>
                            <tr><td><span class="gh-code">report-audit-cheque-register-manage</span></td><td>Clear, bounce or cancel a cheque.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-list</span> / <span class="gh-code">-create</span> / <span class="gh-code">-edit</span> / <span class="gh-code">-delete</span></td><td>See, make, change and delete (draft or rejected) vouchers.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-approve</span></td><td>Act as checker: approve or reject.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-print</span></td><td>Print the thermal slip of an approved voucher.</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note">Admins and super admins receive all of these automatically when the system is updated. Give the others only what their job needs, and keep the maker and the checker as different people.</div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
