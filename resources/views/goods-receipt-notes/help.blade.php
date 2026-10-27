{{--
    Goods Receipt Notes -> How GRN works (/goods-receipt-notes/help).
    Process guide for people who create GRNs and for super admins. Static page: no data is read.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('goods-receipt-notes.index') }}">Goods Receipt Notes</a><span aria-hidden="true">›</span>
                    <span>How GRN works</span>
                </nav>
                <h1 class="ak-title">How a GRN works</h1>
                <p class="ak-sub">From receiving goods to the supplier ledger: what each step does and where the numbers go.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('goods-receipt-notes.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to GRNs</a>
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
        .gh-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .gh-note { margin-top: 10px; padding: 9px 12px; border-left: 3px solid var(--ak-amber); background: var(--ak-amber-bg); border-radius: 6px; font-size: 13px; }
        .gh-tip { margin-top: 10px; padding: 9px 12px; border-left: 3px solid var(--ak-green); background: var(--ak-green-bg); border-radius: 6px; font-size: 13px; }
        .gh-grid { display: grid; gap: 20px; grid-template-columns: 1fr; }
        @media (min-width: 1024px) { .gh-grid { grid-template-columns: 1fr 1fr; } }
        .gh-scroll { overflow-x: auto; }
        .gh-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; background: var(--ak-slate-bg); padding: 1px 5px; border-radius: 4px; }
        .fc-box { fill: #fff; stroke: var(--ak-navy); stroke-width: 1.4; }
        .fc-box-green { fill: #dcfce7; stroke: #15803d; stroke-width: 1.4; }
        .fc-box-amber { fill: #fef3c7; stroke: #b45309; stroke-width: 1.4; }
        .fc-box-red { fill: #fee2e2; stroke: #b91c1c; stroke-width: 1.4; }
        .fc-box-slate { fill: #f1f5f9; stroke: #64748b; stroke-width: 1.4; }
        .fc-text { font-size: 12.5px; fill: #0f172a; font-family: ui-sans-serif, system-ui, sans-serif; }
        .fc-bold { font-weight: 700; }
        .fc-small { font-size: 11px; fill: #475569; }
        .fc-line { stroke: #475569; stroke-width: 1.4; fill: none; marker-end: url(#fc-arrow); }
        @media print { .gh-card { break-inside: avoid; box-shadow: none; } }
    </style>

    <div class="ak-page">
        <div class="gh-wrap">

            {{-- 1. What it is --}}
            <section class="uf-card gh-card" aria-labelledby="gh-what">
                <header class="uf-card-head"><h2 class="uf-card-title" id="gh-what">1. What a GRN is</h2></header>
                <div class="uf-body">
                    <p>A Goods Receipt Note (GRN) records stock a supplier delivered to a warehouse, together with the supplier's invoice figures (value, discount, FMR allowance, taxes). A GRN stays a <b>draft</b> until it is <b>posted</b>. Posting is the only step that changes stock and the books.</p>
                    <ul>
                        <li><b>Draft</b>: editable, deletable, no effect on stock or accounts.</li>
                        <li><b>Posted</b>: stock is in the warehouse, the journal entry is in the general ledger, and a draft invoice row waits in the supplier Ledger Register.</li>
                        <li><b>Reversed</b>: posting undone by offsetting entries. Nothing is deleted.</li>
                    </ul>
                </div>
            </section>

            {{-- 2. Lifecycle chart --}}
            <section class="uf-card gh-card" aria-labelledby="gh-flow">
                <header class="uf-card-head"><h2 class="uf-card-title" id="gh-flow">2. Lifecycle</h2></header>
                <div class="uf-body gh-scroll">
                    <svg viewBox="0 0 980 330" width="100%" role="img" aria-label="GRN lifecycle flow chart" style="min-width:760px">
                        <defs>
                            <marker id="fc-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker>
                        </defs>

                        <rect class="fc-box" x="10" y="20" width="170" height="64" rx="10" />
                        <text class="fc-text fc-bold" x="95" y="46" text-anchor="middle">Create GRN</text>
                        <text class="fc-small" x="95" y="64" text-anchor="middle">form or Excel import</text>

                        <rect class="fc-box-slate" x="230" y="20" width="170" height="64" rx="10" />
                        <text class="fc-text fc-bold" x="315" y="46" text-anchor="middle">Draft</text>
                        <text class="fc-small" x="315" y="64" text-anchor="middle">edit, review, delete</text>

                        <rect class="fc-box" x="450" y="20" width="200" height="64" rx="10" />
                        <text class="fc-text fc-bold" x="550" y="46" text-anchor="middle">Post to Inventory</text>
                        <text class="fc-small" x="550" y="64" text-anchor="middle">password confirmation</text>

                        <rect class="fc-box-green" x="700" y="20" width="170" height="64" rx="10" />
                        <text class="fc-text fc-bold" x="785" y="46" text-anchor="middle">Posted</text>
                        <text class="fc-small" x="785" y="64" text-anchor="middle">stock + GL + ledger draft</text>

                        <path class="fc-line" d="M180 52 H228" />
                        <path class="fc-line" d="M400 52 H448" />
                        <path class="fc-line" d="M650 52 H698" />

                        <text class="fc-small" x="795" y="104">from Posted you can:</text>
                        <path class="fc-line" d="M785 84 V120 H150 V150" />
                        <path class="fc-line" d="M785 120 V150" />
                        <path class="fc-line" d="M785 120 H470 V150" />

                        <rect class="fc-box-amber" x="40" y="150" width="220" height="74" rx="10" />
                        <text class="fc-text fc-bold" x="150" y="175" text-anchor="middle">Supplier Ledger Register</text>
                        <text class="fc-small" x="150" y="193" text-anchor="middle">edit the draft invoice row</text>
                        <text class="fc-small" x="150" y="208" text-anchor="middle">then Post: DR 2142 / CR 2111</text>

                        <rect class="fc-box-red" x="360" y="150" width="220" height="74" rx="10" />
                        <text class="fc-text fc-bold" x="470" y="175" text-anchor="middle">Reverse GRN</text>
                        <text class="fc-small" x="470" y="193" text-anchor="middle">only while stock is untouched</text>
                        <text class="fc-small" x="470" y="208" text-anchor="middle">offsetting journal + stock out</text>

                        <rect class="fc-box-amber" x="675" y="150" width="220" height="74" rx="10" />
                        <text class="fc-text fc-bold" x="785" y="175" text-anchor="middle">Special Edit (super admin)</text>
                        <text class="fc-small" x="785" y="193" text-anchor="middle">fix pack factor or product</text>
                        <text class="fc-small" x="785" y="208" text-anchor="middle">invoice amount stays fixed</text>

                        <path class="fc-line" d="M470 224 V262" />
                        <rect class="fc-box-red" x="385" y="262" width="170" height="50" rx="10" />
                        <text class="fc-text fc-bold" x="470" y="283" text-anchor="middle">Reversed</text>
                        <text class="fc-small" x="470" y="300" text-anchor="middle">draft ledger row removed</text>
                    </svg>
                </div>
            </section>

            <div class="gh-grid">
                {{-- 3. Creating --}}
                <section class="uf-card gh-card" aria-labelledby="gh-create">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gh-create">3. Creating a GRN</h2></header>
                    <div class="uf-body">
                        <ol>
                            <li>Choose <b>supplier</b>, <b>warehouse</b>, receipt date and the supplier's invoice number and date.</li>
                            <li>Add one line per product: purchase quantity, pack factor (cases to pieces), price, discount, FMR allowance, taxes, batch and expiry details.</li>
                            <li>Save. The GRN gets a number like <span class="gh-code">GRN-2026-0001</span> and stays a draft.</li>
                            <li>Check the draft, then <b>Post to Inventory</b>.</li>
                        </ol>
                        <p style="margin-top:8px">Alternatives: <b>Import Excel</b> fills the lines from the template; <b>Opening stock</b> loads starting stock (see section 9).</p>
                        <h3>How each line is calculated</h3>
                        <table class="gh-table">
                            <tbody>
                                <tr><td>Extended value</td><td>purchase qty × price per case</td></tr>
                                <tr><td>Value before tax</td><td>extended value − discount − <b>FMR allowance</b></td></tr>
                                <tr><td>Sales tax</td><td>value before tax × the supplier's sales tax % (default 18%), editable per line</td></tr>
                                <tr><td>Total with taxes</td><td>value before tax + excise + sales tax + advance income tax + other charges (+ withholding tax, Unilever only)</td></tr>
                                <tr><td>Unit cost</td><td>(total with taxes + FMR allowance) ÷ qty received. For an <b>FMR allowed</b> supplier the FMR is not added back.</td></tr>
                                <tr><td>Total cost</td><td>qty accepted × unit cost. This is what goes into stock.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- 4. Supplier settings --}}
                <section class="uf-card gh-card" aria-labelledby="gh-supplier">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gh-supplier">4. Supplier settings that change a GRN</h2></header>
                    <div class="uf-body">
                        <p>Set on the supplier screen (Settings → Suppliers). Different companies need different settings.</p>
                        <table class="gh-table">
                            <thead><tr><th>Setting</th><th>Effect</th></tr></thead>
                            <tbody>
                                <tr><td><b>FMR allowed</b></td><td>Unit cost <b>excludes</b> the FMR allowance. Off: FMR is added back into unit cost. (Example: Engro is on.)</td></tr>
                                <tr><td><b>Sales tax %</b></td><td>Default rate used to auto-fill sales tax on each GRN line.</td></tr>
                                <tr><td><b>Advance tax income</b></td><td>Used on sales settlements (advance tax collected from customers is shown as income). It does not change the GRN itself.</td></tr>
                                <tr><td><b>Unilever Pakistan</b></td><td>Withholding tax is added on each line using the active WHT rate. Other suppliers: 0.</td></tr>
                                <tr><td><b>Powder / liquid product</b></td><td>The product's powder flag decides which FMR income account is credited: 4220 (powder) or 4210 (liquid).</td></tr>
                            </tbody>
                        </table>
                        <h3>FMR and AMR</h3>
                        <ul>
                            <li><b>FMR allowance</b> is entered on the GRN. It is the amount the supplier allows against market returns. On posting it is credited to <b>4210 FMR Allowance (Liquid)</b> or <b>4220 (Powder)</b>.</li>
                            <li><b>AMR</b> (powder and liquid) is entered on <b>sales settlements</b>, not on the GRN. It is posted as expense to <b>5252 AMR Powder</b> and <b>5262 AMR Liquid</b>. The FMR/AMR comparison and SKU FMR/AMR reports set the two against each other.</li>
                        </ul>
                    </div>
                </section>
            </div>

            {{-- 5. Posting --}}
            <section class="uf-card gh-card" aria-labelledby="gh-post">
                <header class="uf-card-head"><h2 class="uf-card-title" id="gh-post">5. What Post to Inventory does</h2></header>
                <div class="uf-body">
                    <p>All steps run in one transaction. If any step fails, nothing is saved.</p>
                    <div class="gh-scroll">
                        <svg viewBox="0 0 980 120" width="100%" role="img" aria-label="Posting steps" style="min-width:760px">
                            <rect class="fc-box" x="5" y="20" width="150" height="80" rx="10" /><text class="fc-text fc-bold" x="80" y="48" text-anchor="middle">1. Check</text><text class="fc-small" x="80" y="66" text-anchor="middle">draft, line cost =</text><text class="fc-small" x="80" y="80" text-anchor="middle">qty × unit cost</text>
                            <rect class="fc-box" x="180" y="20" width="150" height="80" rx="10" /><text class="fc-text fc-bold" x="255" y="48" text-anchor="middle">2. Stock batch</text><text class="fc-small" x="255" y="66" text-anchor="middle">one new batch</text><text class="fc-small" x="255" y="80" text-anchor="middle">per GRN line</text>
                            <rect class="fc-box" x="355" y="20" width="150" height="80" rx="10" /><text class="fc-text fc-bold" x="430" y="48" text-anchor="middle">3. Stock records</text><text class="fc-small" x="430" y="66" text-anchor="middle">movement, ledger,</text><text class="fc-small" x="430" y="80" text-anchor="middle">valuation, on-hand</text>
                            <rect class="fc-box" x="530" y="20" width="150" height="80" rx="10" /><text class="fc-text fc-bold" x="605" y="48" text-anchor="middle">4. Journal entry</text><text class="fc-small" x="605" y="66" text-anchor="middle">DR 1151 / CR 2142</text><text class="fc-small" x="605" y="80" text-anchor="middle">+ FMR, round off</text>
                            <rect class="fc-box-amber" x="705" y="20" width="150" height="80" rx="10" /><text class="fc-text fc-bold" x="780" y="48" text-anchor="middle">5. Ledger draft</text><text class="fc-small" x="780" y="66" text-anchor="middle">invoice row in the</text><text class="fc-small" x="780" y="80" text-anchor="middle">supplier register</text>
                            <rect class="fc-box-green" x="880" y="20" width="95" height="80" rx="10" /><text class="fc-text fc-bold" x="927" y="56" text-anchor="middle">Posted</text>
                            <path class="fc-line" d="M155 60 H178" /><path class="fc-line" d="M330 60 H353" /><path class="fc-line" d="M505 60 H528" /><path class="fc-line" d="M680 60 H703" /><path class="fc-line" d="M855 60 H878" />
                        </svg>
                    </div>

                    <h3>Tables written</h3>
                    <table class="gh-table">
                        <thead><tr><th>Table</th><th>Written on post</th></tr></thead>
                        <tbody>
                            <tr><td class="gh-code">stock_batches</td><td>New active batch per line (cost, expiry, promo data).</td></tr>
                            <tr><td class="gh-code">stock_movements</td><td>Movement type <span class="gh-code">grn</span> with the accepted quantity.</td></tr>
                            <tr><td class="gh-code">stock_ledger_entries</td><td>Quantity in and running balance.</td></tr>
                            <tr><td class="gh-code">stock_valuation_layers</td><td>FIFO layer: quantity received and remaining, cost.</td></tr>
                            <tr><td class="gh-code">current_stock_by_batch, current_stock</td><td>On-hand quantity and value per batch and per product.</td></tr>
                            <tr><td class="gh-code">inventory_ledger_entries</td><td>Purchase debit (quantity in) for the product and warehouse.</td></tr>
                            <tr><td class="gh-code">journal_entries, journal_entry_details</td><td>The GRN journal, posted automatically.</td></tr>
                            <tr><td class="gh-code">goods_receipt_notes</td><td>Status posted, posted time, journal entry link.</td></tr>
                            <tr><td class="gh-code">supplier_ledger_registers</td><td>Draft invoice row linked to the GRN.</td></tr>
                        </tbody>
                    </table>

                    <h3>The journal entry</h3>
                    <table class="gh-table">
                        <thead><tr><th>Account</th><th>Side</th><th>Amount</th></tr></thead>
                        <tbody>
                            <tr><td>1151 Stock In Hand</td><td>DR</td><td>Total cost of all lines</td></tr>
                            <tr><td>4210 / 4220 FMR Allowance</td><td>CR</td><td>FMR allowance (liquid / powder)</td></tr>
                            <tr><td>2142 Stock Received But Not Billed</td><td>CR</td><td>Invoice value − FMR allowance (what the supplier will invoice)</td></tr>
                            <tr><td>5271 Round Off</td><td>DR or CR</td><td>Whatever balances the entry, shown only when 0.01 or more</td></tr>
                        </tbody>
                    </table>
                    <p style="margin-top:8px">Invoice value = extended value − discounts + sales tax + advance tax + excise. Creditors (2111) are <b>not</b> touched at posting.</p>

                    <h3>Worked example (extended 10,000, discount 500, FMR 300, sales tax 18%, qty 100)</h3>
                    <table class="gh-table">
                        <thead><tr><th>Line</th><th class="num">FMR allowed: off</th><th class="num">FMR allowed: on</th></tr></thead>
                        <tbody>
                            <tr><td>Value before tax (10,000 − 500 − 300)</td><td class="num">9,200</td><td class="num">9,200</td></tr>
                            <tr><td>Sales tax 18%</td><td class="num">1,656</td><td class="num">1,656</td></tr>
                            <tr><td>Total with taxes</td><td class="num">10,856</td><td class="num">10,856</td></tr>
                            <tr><td>Unit cost</td><td class="num">(10,856 + 300) ÷ 100 = 111.56</td><td class="num">10,856 ÷ 100 = 108.56</td></tr>
                            <tr><td>DR 1151 Stock In Hand</td><td class="num">11,156</td><td class="num">10,856</td></tr>
                            <tr><td>CR 4210 FMR Allowance</td><td class="num">300</td><td class="num">300</td></tr>
                            <tr><td>CR 2142 (11,156 − 300)</td><td class="num">10,856</td><td class="num">10,856</td></tr>
                            <tr><td>5271 Round Off</td><td class="num">0</td><td class="num">DR 300</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note">With <b>FMR allowed</b> on, stock is carried without the FMR, so the FMR credit has no matching debit and the difference lands in Round Off. Round Off therefore shows an amount equal to the FMR for these suppliers.</div>
                </div>
            </section>

            <div class="gh-grid">
                {{-- 6. Ledger hand-off --}}
                <section class="uf-card gh-card" aria-labelledby="gh-ledger">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gh-ledger">6. Supplier Ledger Register and payments</h2></header>
                    <div class="uf-body">
                        <p>Posting raises one <b>draft</b> row in <b>Reports → Ledger Register</b> for the same supplier:</p>
                        <ul>
                            <li>Date = supplier invoice date (receipt date if empty), document type <b>DR (Invoices)</b>, document number = supplier invoice number.</li>
                            <li>Invoice amount = the amount credited to 2142 by the GRN. Remarks name the GRN and warehouse.</li>
                            <li>The row is fully editable (date, number, amounts, remarks). Correct it to the real supplier invoice, then <b>Post</b>.</li>
                        </ul>
                        <table class="gh-table">
                            <thead><tr><th>Ledger column</th><th>Journal when posted</th></tr></thead>
                            <tbody>
                                <tr><td>Invoice amount</td><td>DR 2142 Stock Received But Not Billed / CR 2111 Creditors</td></tr>
                                <tr><td>Online amount (payment)</td><td>DR 2111 Creditors / CR 1171 HBL Main Account</td></tr>
                                <tr><td>Expenses</td><td>DR 5210 Admin Expenses / CR 2111 Creditors</td></tr>
                                <tr><td>ZA 0.5%</td><td>DR 2111 Creditors / CR 4240 ZA Incentive Income</td></tr>
                                <tr><td>Claim adjust</td><td>DR 2111 Creditors / CR 1112 Pending Claims Debtors</td></tr>
                            </tbody>
                        </table>
                        <div class="gh-tip">Suppliers are paid through the Ledger Register (online amount). The separate Supplier Payments screen no longer exists, so there is only one place where Creditors are paid down.</div>
                        <p style="margin-top:8px">Reversing a GRN removes its ledger row while the row is still a draft. Once the invoice row is <b>posted</b>, the GRN <b>cannot be reversed</b>: the supplier is owed that amount and may already have been paid. Reverse the ledger entry's journal entry first, then reverse the GRN.</p>
                    </div>
                </section>

                {{-- 7. Reverse --}}
                <section class="uf-card gh-card" aria-labelledby="gh-reverse">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gh-reverse">7. Reverse GRN</h2></header>
                    <div class="uf-body">
                        <p>Button on a posted GRN (permission <span class="gh-code">goods-receipt-note-reverse</span>, password required). It is <b>disabled with the reason as tooltip</b> when any of these is true:</p>
                        <ul>
                            <li>The GRN is not posted (draft or already reversed).</li>
                            <li>Part of the received stock is already issued, sold or adjusted: a batch's on-hand quantity is below what the GRN received.</li>
                            <li>A stock adjustment revalued one of its batches.</li>
                            <li>The GRN has no journal entry of its own, so its value reached the ledger another way.</li>
                            <li>Its supplier invoice is already posted in the Ledger Register (the row raised from the GRN, or a DR row entered by hand under the same invoice number), unless that entry's journal was itself reversed.</li>
                        </ul>
                        <h3>What it does</h3>
                        <ul>
                            <li>Takes the quantity out of every batch, marks batches depleted, writes reversing movements and ledger rows.</li>
                            <li>Posts a mirror journal (<span class="gh-code">REV-&lt;reference&gt;</span>) with debit and credit swapped.</li>
                            <li>Removes the draft Ledger Register row. Sets the GRN to <b>reversed</b>.</li>
                        </ul>
                        <div class="gh-note">If stock was already used, return it to the supplier with a stock adjustment instead.</div>
                    </div>
                </section>
            </div>

            <div class="gh-grid">
                {{-- 8. Special edit --}}
                <section class="uf-card gh-card" aria-labelledby="gh-special">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gh-special">8. Special Edit (super admin only)</h2></header>
                    <div class="uf-body">
                        <p>Fixes a wrong <b>pack factor</b> (cases to pieces) or a wrong <b>product</b> on a <b>posted</b> GRN without reversing it.</p>
                        <ul>
                            <li>The invoice amount (total cost) never changes. New quantity = purchase qty × new factor; unit cost = total cost ÷ new quantity.</li>
                            <li>Updates the GRN line, stock movement, batch, stock ledger, valuation layer, on-hand tables, inventory ledger and header quantity, then recalculates running balances.</li>
                            <li>Stock already issued or sold from the batch is re-costed and the cost difference is posted as an adjusting journal entry.</li>
                            <li>A product can be changed only if the batch is untouched, uses the same stock UOM and has the same powder/liquid class.</li>
                            <li>A unit cost change of more than 10% shows a warning.</li>
                        </ul>
                    </div>
                </section>

                {{-- 9. Import / opening / permissions --}}
                <section class="uf-card gh-card" aria-labelledby="gh-other">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gh-other">9. Import, opening stock and access</h2></header>
                    <div class="uf-body">
                        <ul>
                            <li><b>Import Excel</b>: download the template, fill the lines, upload on the create screen. Needs <span class="gh-code">goods-receipt-note-import</span>.</li>
                            <li><b>Opening stock</b>: separate upload that creates a posted GRN against <b>Opening Balance Equity</b> (DR 1151 / CR equity). It raises no supplier ledger row because no invoice is owed.</li>
                            <li><b>Supplier scoping</b>: a user tied to one supplier only sees and creates that supplier's GRNs.</li>
                        </ul>
                        <table class="gh-table">
                            <thead><tr><th>Permission</th><th>Allows</th></tr></thead>
                            <tbody>
                                <tr><td class="gh-code">goods-receipt-note-list</td><td>View GRNs</td></tr>
                                <tr><td class="gh-code">goods-receipt-note-create</td><td>Create drafts; sees this help page</td></tr>
                                <tr><td class="gh-code">goods-receipt-note-edit</td><td>Edit drafts</td></tr>
                                <tr><td class="gh-code">goods-receipt-note-delete</td><td>Delete drafts</td></tr>
                                <tr><td class="gh-code">goods-receipt-note-post</td><td>Post to inventory</td></tr>
                                <tr><td class="gh-code">goods-receipt-note-reverse</td><td>Reverse a posted GRN</td></tr>
                                <tr><td class="gh-code">goods-receipt-note-import</td><td>Excel import and template</td></tr>
                                <tr><td>super admin</td><td>Everything above, plus Special Edit</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>


            {{-- 10. Decision flows --}}
            <section class="uf-card gh-card" aria-labelledby="gh-dpost">
                <header class="uf-card-head"><h2 class="uf-card-title" id="gh-dpost">10. Decision flow: Post to Inventory</h2></header>
                <div class="uf-body gh-scroll">
                    <p>Amber boxes are checks, red boxes are what the user sees when a check fails, green is the result. Nothing is saved unless every check passes.</p>
<svg viewBox="0 0 800 630" width="100%" role="img" aria-label="Post checks" style="min-width:680px;max-width:860px"><defs><marker id="fc-arrow-802" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="20" y="10" width="310" height="40" rx="9" /><text class="fc-text fc-bold" x="175.0" y="34.0" text-anchor="middle">Click Post to Inventory</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M175.0 50 V76" /><text class="fc-small" x="181.0" y="67">yes</text><rect class="fc-box-amber" x="20" y="76" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="95.0" text-anchor="middle">User has</text><text class="fc-text" x="175.0" y="111.0" text-anchor="middle">goods-receipt-note-post?</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M330 99.0 H408" /><text class="fc-small" x="338" y="94.0">no</text><rect class="fc-box-red" x="410" y="82.0" width="370" height="34" rx="9" /><text class="fc-text" x="595.0" y="103.0" text-anchor="middle">403 Forbidden</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M175.0 122 V148" /><text class="fc-small" x="181.0" y="139">yes</text><rect class="fc-box-amber" x="20" y="148" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="167.0" text-anchor="middle">Password is the user&#x27;s</text><text class="fc-text" x="175.0" y="183.0" text-anchor="middle">current password?</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M330 171.0 H408" /><text class="fc-small" x="338" y="166.0">no</text><rect class="fc-box-red" x="410" y="154.0" width="370" height="34" rx="9" /><text class="fc-text" x="595.0" y="175.0" text-anchor="middle">Provided password does not match</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M175.0 194 V220" /><text class="fc-small" x="181.0" y="211">yes</text><rect class="fc-box-amber" x="20" y="220" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="239.0" text-anchor="middle">GRN belongs to the user&#x27;s supplier</text><text class="fc-text" x="175.0" y="255.0" text-anchor="middle">(if the user is tied to one)?</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M330 243.0 H408" /><text class="fc-small" x="338" y="238.0">no</text><rect class="fc-box-red" x="410" y="226.0" width="370" height="34" rx="9" /><text class="fc-text" x="595.0" y="247.0" text-anchor="middle">403 No permission for this GRN</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M175.0 266 V292" /><text class="fc-small" x="181.0" y="283">yes</text><rect class="fc-box-amber" x="20" y="292" width="310" height="40" rx="9" /><text class="fc-text" x="175.0" y="316.0" text-anchor="middle">GRN is a draft?</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M330 312.0 H408" /><text class="fc-small" x="338" y="307.0">no</text><rect class="fc-box-red" x="410" y="290.0" width="370" height="44" rx="9" /><text class="fc-text" x="595.0" y="308.0" text-anchor="middle">GRN is already posted</text><text class="fc-text" x="595.0" y="324.0" text-anchor="middle">or cancelled</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M175.0 332 V358" /><text class="fc-small" x="181.0" y="349">yes</text><rect class="fc-box-amber" x="20" y="358" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="377.0" text-anchor="middle">Each line: total cost =</text><text class="fc-text" x="175.0" y="393.0" text-anchor="middle">accepted qty × unit cost?</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M330 381.0 H408" /><text class="fc-small" x="338" y="376.0">no</text><rect class="fc-box-red" x="410" y="359.0" width="370" height="44" rx="9" /><text class="fc-text" x="595.0" y="377.0" text-anchor="middle">Post refused: lines listed,</text><text class="fc-text" x="595.0" y="393.0" text-anchor="middle">fix cost or quantity first</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M175.0 404 V430" /><text class="fc-small" x="181.0" y="421">yes</text><rect class="fc-box-amber" x="20" y="430" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="449.0" text-anchor="middle">Accounts 1151, 2142, 4210, 4220</text><text class="fc-text" x="175.0" y="465.0" text-anchor="middle">and cost center CC006 exist?</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M330 453.0 H408" /><text class="fc-small" x="338" y="448.0">no</text><rect class="fc-box-red" x="410" y="431.0" width="370" height="44" rx="9" /><text class="fc-text" x="595.0" y="449.0" text-anchor="middle">Account … is missing from</text><text class="fc-text" x="595.0" y="465.0" text-anchor="middle">the Chart of Accounts</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M175.0 476 V502" /><text class="fc-small" x="181.0" y="493">yes</text><rect class="fc-box-amber" x="20" y="502" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="521.0" text-anchor="middle">Journal accepted: balanced and</text><text class="fc-text" x="175.0" y="537.0" text-anchor="middle">an open accounting period?</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M330 525.0 H408" /><text class="fc-small" x="338" y="520.0">no</text><rect class="fc-box-red" x="410" y="503.0" width="370" height="44" rx="9" /><text class="fc-text" x="595.0" y="521.0" text-anchor="middle">Failed to post GRN: reason</text><text class="fc-text" x="595.0" y="537.0" text-anchor="middle">(everything rolled back)</text><path class="fc-line" style="marker-end:url(#fc-arrow-802)" d="M175.0 548 V574" /><rect class="fc-box-green" x="20" y="574" width="310" height="46" rx="9" /><text class="fc-text fc-bold" x="175.0" y="593.0" text-anchor="middle">Posted: stock written, journal posted,</text><text class="fc-text" x="175.0" y="609.0" text-anchor="middle">draft ledger row raised</text></svg>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="gh-drev">
                <header class="uf-card-head"><h2 class="uf-card-title" id="gh-drev">11. Decision flow: Reverse GRN</h2></header>
                <div class="uf-body gh-scroll">
<svg viewBox="0 0 800 696" width="100%" role="img" aria-label="Reverse checks" style="min-width:680px;max-width:860px"><defs><marker id="fc-arrow-808" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="20" y="10" width="310" height="40" rx="9" /><text class="fc-text fc-bold" x="175.0" y="34.0" text-anchor="middle">Click Reverse GRN</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M175.0 50 V76" /><text class="fc-small" x="181.0" y="67">yes</text><rect class="fc-box-amber" x="20" y="76" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="95.0" text-anchor="middle">User has</text><text class="fc-text" x="175.0" y="111.0" text-anchor="middle">goods-receipt-note-reverse?</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M330 99.0 H408" /><text class="fc-small" x="338" y="94.0">no</text><rect class="fc-box-red" x="410" y="82.0" width="370" height="34" rx="9" /><text class="fc-text" x="595.0" y="103.0" text-anchor="middle">403 Forbidden (button hidden)</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M175.0 122 V148" /><text class="fc-small" x="181.0" y="139">yes</text><rect class="fc-box-amber" x="20" y="148" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="167.0" text-anchor="middle">Password is the user&#x27;s</text><text class="fc-text" x="175.0" y="183.0" text-anchor="middle">current password?</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M330 171.0 H408" /><text class="fc-small" x="338" y="166.0">no</text><rect class="fc-box-red" x="410" y="154.0" width="370" height="34" rx="9" /><text class="fc-text" x="595.0" y="175.0" text-anchor="middle">Invalid password</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M175.0 194 V220" /><text class="fc-small" x="181.0" y="211">yes</text><rect class="fc-box-amber" x="20" y="220" width="310" height="40" rx="9" /><text class="fc-text" x="175.0" y="244.0" text-anchor="middle">GRN is posted?</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M330 240.0 H408" /><text class="fc-small" x="338" y="235.0">no</text><rect class="fc-box-red" x="410" y="223.0" width="370" height="34" rx="9" /><text class="fc-text" x="595.0" y="244.0" text-anchor="middle">Only a posted GRN can be reversed</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M175.0 260 V286" /><text class="fc-small" x="181.0" y="277">yes</text><rect class="fc-box-amber" x="20" y="286" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="305.0" text-anchor="middle">GRN has its own journal entry</text><text class="fc-text" x="175.0" y="321.0" text-anchor="middle">(when it has a value)?</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M330 309.0 H408" /><text class="fc-small" x="338" y="304.0">no</text><rect class="fc-box-red" x="410" y="287.0" width="370" height="44" rx="9" /><text class="fc-text" x="595.0" y="305.0" text-anchor="middle">Value reached the ledger another way:</text><text class="fc-text" x="595.0" y="321.0" text-anchor="middle">reverse it there first</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M175.0 332 V358" /><text class="fc-small" x="181.0" y="349">yes</text><rect class="fc-box-amber" x="20" y="358" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="377.0" text-anchor="middle">Supplier invoice NOT posted in</text><text class="fc-text" x="175.0" y="393.0" text-anchor="middle">the Ledger Register?</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M330 381.0 H408" /><text class="fc-small" x="338" y="376.0">no</text><rect class="fc-box-red" x="410" y="351.0" width="370" height="60" rx="9" /><text class="fc-text" x="595.0" y="369.0" text-anchor="middle">Invoice already posted (supplier may</text><text class="fc-text" x="595.0" y="385.0" text-anchor="middle">be paid): reverse that ledger</text><text class="fc-text" x="595.0" y="401.0" text-anchor="middle">entry&#x27;s journal first</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M175.0 404 V430" /><text class="fc-small" x="181.0" y="421">yes</text><rect class="fc-box-amber" x="20" y="430" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="449.0" text-anchor="middle">All received stock still in</text><text class="fc-text" x="175.0" y="465.0" text-anchor="middle">each batch (not issued or sold)?</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M330 453.0 H408" /><text class="fc-small" x="338" y="448.0">no</text><rect class="fc-box-red" x="410" y="431.0" width="370" height="44" rx="9" /><text class="fc-text" x="595.0" y="449.0" text-anchor="middle">Stock already issued, sold or</text><text class="fc-text" x="595.0" y="465.0" text-anchor="middle">adjusted: use a stock adjustment</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M175.0 476 V502" /><text class="fc-small" x="181.0" y="493">yes</text><rect class="fc-box-amber" x="20" y="502" width="310" height="46" rx="9" /><text class="fc-text" x="175.0" y="521.0" text-anchor="middle">No batch revalued by a</text><text class="fc-text" x="175.0" y="537.0" text-anchor="middle">stock adjustment?</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M330 525.0 H408" /><text class="fc-small" x="338" y="520.0">no</text><rect class="fc-box-red" x="410" y="503.0" width="370" height="44" rx="9" /><text class="fc-text" x="595.0" y="521.0" text-anchor="middle">Batch was revalued: use a</text><text class="fc-text" x="595.0" y="537.0" text-anchor="middle">stock adjustment instead</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M175.0 548 V574" /><text class="fc-small" x="181.0" y="565">yes</text><rect class="fc-box-amber" x="20" y="574" width="310" height="40" rx="9" /><text class="fc-text" x="175.0" y="598.0" text-anchor="middle">Reversing journal accepted?</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M330 594.0 H408" /><text class="fc-small" x="338" y="589.0">no</text><rect class="fc-box-red" x="410" y="572.0" width="370" height="44" rx="9" /><text class="fc-text" x="595.0" y="590.0" text-anchor="middle">Reverse failed: reason</text><text class="fc-text" x="595.0" y="606.0" text-anchor="middle">(everything rolled back)</text><path class="fc-line" style="marker-end:url(#fc-arrow-808)" d="M175.0 614 V640" /><rect class="fc-box-green" x="20" y="640" width="310" height="46" rx="9" /><text class="fc-text fc-bold" x="175.0" y="659.0" text-anchor="middle">Reversed: stock out, mirror journal,</text><text class="fc-text" x="175.0" y="675.0" text-anchor="middle">draft ledger row removed</text></svg>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="gh-roles">
                <header class="uf-card-head"><h2 class="uf-card-title" id="gh-roles">12. Who does what</h2></header>
                <div class="uf-body">
                    <table class="gh-table">
                        <thead><tr><th>Role</th><th>Step</th><th>Needs</th></tr></thead>
                        <tbody>
                            <tr><td>Store / receiving user</td><td>Creates the GRN draft (form or Excel), keeps it correct until posting</td><td><span class="gh-code">goods-receipt-note-create</span>, <span class="gh-code">-edit</span></td></tr>
                            <tr><td>Approver</td><td>Reviews the draft, posts it with a password, can reverse an untouched GRN</td><td><span class="gh-code">goods-receipt-note-post</span>, <span class="gh-code">-reverse</span></td></tr>
                            <tr><td>Accounts</td><td>Opens the draft invoice row in the Ledger Register, matches it to the supplier's invoice, posts it, records payments</td><td>Ledger Register permissions</td></tr>
                            <tr><td>Super admin</td><td>Special Edit for pack-factor or product corrections on a posted GRN</td><td>super admin role</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 13. Checklist --}}
            <section class="uf-card gh-card" aria-labelledby="gh-check">
                <header class="uf-card-head"><h2 class="uf-card-title" id="gh-check">13. Quick checklist before posting</h2></header>
                <div class="uf-body">
                    <ul>
                        <li>Supplier and warehouse are right; supplier invoice number and date are filled (they flow into the ledger row).</li>
                        <li>Every line's quantity, pack factor, discount and FMR match the supplier invoice.</li>
                        <li>The supplier's <b>FMR allowed</b> setting matches how that company treats FMR.</li>
                        <li>After posting: open the journal entry from the GRN page, then edit and post the draft row in the Ledger Register against the real invoice.</li>
                    </ul>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
