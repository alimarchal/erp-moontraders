{{--
    Expense Vouchers -> How it works (/expense-vouchers/help).
    Static guide: the maker-checker flow, what each step writes, who may do what. No data is read.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('expense-vouchers.index') }}">Expense Vouchers</a><span aria-hidden="true">›</span><span>How it works</span>
                </nav>
                <h1 class="ak-title">How expense vouchers work</h1>
                <p class="ak-sub">The maker and checker steps, what is saved at each step, and what reaches the ledger and the reports.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('expense-vouchers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to vouchers</a>
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

            <section class="uf-card gh-card" aria-labelledby="ev-flow">
                <header class="uf-card-head"><h2 class="uf-card-title" id="ev-flow">1. The flow</h2></header>
                <div class="uf-body gh-scroll">
                    <svg viewBox="0 0 880 330" width="100%" role="img" aria-label="Expense voucher flow" style="min-width:760px"><defs><marker id="fc-arrow-v" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="10" y="20" width="170" height="60" rx="9" /><text class="fc-text fc-bold" x="95.0" y="38.0" text-anchor="middle">1. Maker submits</text><text class="fc-small" x="95.0" y="54.0" text-anchor="middle">status Pending</text><text class="fc-small" x="95.0" y="70.0" text-anchor="middle">expense_vouchers: new row</text><rect class="fc-box-amber" x="230" y="20" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="325.0" y="38.0" text-anchor="middle">2. Waiting for checker</text><text class="fc-small" x="325.0" y="54.0" text-anchor="middle">status Pending</text><text class="fc-small" x="325.0" y="70.0" text-anchor="middle">expense_vouchers: updated</text><rect class="fc-box-amber" x="470" y="20" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="565.0" y="46.0" text-anchor="middle">3. Checker (another user)</text><text class="fc-small" x="565.0" y="62.0" text-anchor="middle">approve or reject?</text><rect class="fc-box-green" x="710" y="20" width="160" height="60" rx="9" /><text class="fc-text fc-bold" x="790.0" y="46.0" text-anchor="middle">4. Approved</text><text class="fc-small" x="790.0" y="62.0" text-anchor="middle">ledger entry posted</text><rect class="fc-box-red" x="470" y="140" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="565.0" y="166.0" text-anchor="middle">Rejected</text><text class="fc-small" x="565.0" y="182.0" text-anchor="middle">reason saved, no ledger entry</text><rect class="fc-box-slate" x="230" y="140" width="190" height="60" rx="9" /><text class="fc-text fc-bold" x="325.0" y="166.0" text-anchor="middle">Maker corrects</text><text class="fc-small" x="325.0" y="182.0" text-anchor="middle">edit, then send again</text><rect class="fc-box-green" x="710" y="140" width="160" height="60" rx="9" /><text class="fc-text fc-bold" x="790.0" y="166.0" text-anchor="middle">5. Print 80mm slip</text><text class="fc-small" x="790.0" y="182.0" text-anchor="middle">thermal printer</text><rect class="fc-box-green" x="710" y="250" width="160" height="60" rx="9" /><text class="fc-text fc-bold" x="790.0" y="268.0" text-anchor="middle">Reports</text><text class="fc-small" x="790.0" y="284.0" text-anchor="middle">Cash Collection Distributor</text><text class="fc-small" x="790.0" y="300.0" text-anchor="middle">Expenses table</text><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M180 50 H228" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M420 50 H468" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M660 50 H708" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M565 80 V138" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M468 170 H422" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M325 140 V82" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M790 80 V138" /><path class="fc-line" style="marker-end:url(#fc-arrow-v)" d="M790 200 V248" /><text class="fc-small" x="668" y="42">yes</text><text class="fc-small" x="572" y="114">no</text></svg>
                    <ol>
                        <li><b>Maker</b> opens New voucher: date, company, party name, expense account, amount, narration, and presses <b>Submit for approval</b>. The voucher gets a number such as EV-2026-0001 and is now <b>Pending approval</b>; it cannot be changed. (A voucher saved with Save changes on the edit page stays a <b>Draft</b>; a draft or rejected voucher can be edited, deleted or submitted again.)</li>
                        <li>A <b>different user</b> with the approve permission opens it. <b>Approve</b> posts the ledger entry; <b>Reject</b> needs a reason and sends it back to the maker, who corrects and sends it again. The person who made the voucher can never approve it, not even a super admin.</li>
                        <li>Only an <b>approved</b> voucher can be printed: an 80 mm thermal slip with the party, account, amount and the maker and checker.</li>
                    </ol>
                    <div class="gh-note">Most expenses belong to Moon Traders itself. An admin leaves the company empty (general expense). A user tied to one company always raises vouchers for that company.</div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="ev-tables">
                <header class="uf-card-head"><h2 class="uf-card-title" id="ev-tables">2. What each step writes to the database</h2></header>
                <div class="uf-body gh-scroll">
                    <table class="gh-table">
                        <thead><tr><th>Step</th><th>Tables written</th><th>Accounting effect</th></tr></thead>
                        <tbody>
                            <tr><td>Submit for approval (or save as draft)</td><td><span class="gh-code">expense_vouchers</span> gets a new row: voucher number, maker, status pending (draft if only saved).</td><td>None.</td></tr>
                            <tr><td>Edit or delete a draft</td><td><span class="gh-code">expense_vouchers</span> row is updated, or soft-deleted.</td><td>None.</td></tr>
                            <tr><td>Send to checker</td><td><span class="gh-code">expense_vouchers</span>: status pending, sent by, sent at.</td><td>None.</td></tr>
                            <tr><td>Reject</td><td><span class="gh-code">expense_vouchers</span>: status rejected, reason, checker, time.</td><td>None.</td></tr>
                            <tr><td><b>Approve</b></td><td><span class="gh-code">journal_entries</span> gets one entry (reference = voucher number, posted); <span class="gh-code">journal_entry_details</span> gets two lines; <span class="gh-code">expense_vouchers</span>: status approved, checker, time, journal entry id. All in one transaction: if posting fails nothing is saved.</td><td><b>Debit</b> the expense account, <b>credit</b> Cash (1121), for the voucher amount, dated on the voucher date.</td></tr>
                            <tr><td>Print slip</td><td><span class="gh-code">expense_vouchers</span>: printed at (first print only).</td><td>None.</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note"><b>Is it a transaction?</b> Only an approved voucher is: it creates a posted journal entry, so General Ledger, Trial Balance, Income Statement and Balance Sheet pick it up through their normal views. There is no separate balance table to update, and a posted entry cannot be changed afterwards. Drafts, pending and rejected vouchers are records only.</div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="ev-reports">
                <header class="uf-card-head"><h2 class="uf-card-title" id="ev-reports">3. Where approved vouchers show up</h2></header>
                <div class="uf-body">
                    <ul>
                        <li><b>Cash Collection Distributor</b> report: the Expenses table lists approved vouchers of the day by party. A single company shows its own; All Companies also shows the general ones. The Cash Summary subtracts them from the cash received to give the cash in hand.</li>
                        <li><b>General Ledger / Trial Balance / Income Statement</b>: through the journal entry, under the expense account chosen on the voucher.</li>
                    </ul>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="ev-perm">
                <header class="uf-card-head"><h2 class="uf-card-title" id="ev-perm">4. Permissions</h2></header>
                <div class="uf-body gh-scroll">
                    <table class="gh-table">
                        <thead><tr><th>Permission</th><th>Allows</th></tr></thead>
                        <tbody>
                            <tr><td><span class="gh-code">expense-voucher-list</span></td><td>See the list, open a voucher and this guide.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-create</span></td><td>Make a voucher and send it to the checker.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-edit</span> / <span class="gh-code">-delete</span></td><td>Change or delete a draft or rejected voucher.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-approve</span></td><td>Act as checker: approve or reject.</td></tr>
                            <tr><td><span class="gh-code">expense-voucher-print</span></td><td>Print the thermal slip of an approved voucher.</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note">Admins and super admins receive all of them automatically. Give the others only what their job needs, and keep the maker and the checker as different people.</div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
