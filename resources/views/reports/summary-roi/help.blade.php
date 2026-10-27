{{--
    Reports -> Summary ROI -> How this report works (/reports/summary-roi/help).
    Static guide: formulas, sources and rules. No data is read.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('reports.summary-roi.index') }}">Summary ROI</a><span aria-hidden="true">›</span><span>How it is calculated</span>
                </nav>
                <h1 class="ak-title">How the Summary ROI report is calculated</h1>
                <p class="ak-sub">Where every figure comes from, which filters change it, and how the totals are built.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('reports.summary-roi.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to the report</a>
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

            <section class="uf-card gh-card" aria-labelledby="sr-what">
                <header class="uf-card-head"><h2 class="uf-card-title" id="sr-what">1. What the report shows</h2></header>
                <div class="uf-body">
                    <p>A one-page profit statement for <b>one company</b> and one period: what was sold, what that earned, what came in besides sales, what was spent, and what is left before and after taxation. Left side: sales and revenue. Right side: expenses and the result.</p>
                    <ul>
                        <li><b>Nothing is calculated until a company is chosen.</b> Opening the page only shows the filters.</li>
                        <li>Admins and super admins can pick any company. Everyone else sees only the company on their own account, and the company box is locked.</li>
                        <li>Default period is the current month. Only <b>posted</b> sales settlements count unless the Status filter says draft.</li>
                    </ul>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="sr-company">
                <header class="uf-card-head"><h2 class="uf-card-title" id="sr-company">2. Which company a user gets</h2></header>
                <div class="uf-body gh-scroll">
                    <svg viewBox="0 0 860 396" width="100%" role="img" aria-label="Company rule flow chart" style="min-width:700px;max-width:880px"><defs><marker id="fc-arrow-co" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box-slate" x="20" y="10" width="340" height="40" rx="9" /><text class="fc-text fc-bold" x="190.0" y="34.0" text-anchor="middle">Open Reports → Summary ROI</text><path class="fc-line" style="marker-end:url(#fc-arrow-co)" d="M190.0 50 V76" /><text class="fc-small" x="196.0" y="67">yes</text><rect class="fc-box-amber" x="20" y="76" width="340" height="40" rx="9" /><text class="fc-text" x="190.0" y="100.0" text-anchor="middle">Has the user report-sales-summary-roi?</text><path class="fc-line" style="marker-end:url(#fc-arrow-co)" d="M360 96.0 H428" /><text class="fc-small" x="368" y="91.0">no</text><rect class="fc-box-red" x="430" y="79.0" width="410" height="34" rx="9" /><text class="fc-text" x="635.0" y="100.0" text-anchor="middle">403 Forbidden</text><path class="fc-line" style="marker-end:url(#fc-arrow-co)" d="M190.0 116 V142" /><text class="fc-small" x="196.0" y="133">no</text><rect class="fc-box-amber" x="20" y="142" width="340" height="40" rx="9" /><text class="fc-text" x="190.0" y="166.0" text-anchor="middle">Admin or super admin?</text><path class="fc-line" style="marker-end:url(#fc-arrow-co)" d="M360 162.0 H428" /><text class="fc-small" x="368" y="157.0">yes</text><rect class="fc-box-amber" x="430" y="140.0" width="410" height="44" rx="9" /><text class="fc-text" x="635.0" y="158.0" text-anchor="middle">Company list shows every company;</text><text class="fc-text" x="635.0" y="174.0" text-anchor="middle">nothing loads until one is chosen and Search is pressed</text><path class="fc-line" style="marker-end:url(#fc-arrow-co)" d="M190.0 182 V208" /><text class="fc-small" x="196.0" y="199">yes</text><rect class="fc-box-amber" x="20" y="208" width="340" height="40" rx="9" /><text class="fc-text" x="190.0" y="232.0" text-anchor="middle">Company on the user&#x27;s own account?</text><path class="fc-line" style="marker-end:url(#fc-arrow-co)" d="M360 228.0 H428" /><text class="fc-small" x="368" y="223.0">no</text><rect class="fc-box-red" x="430" y="206.0" width="410" height="44" rx="9" /><text class="fc-text" x="635.0" y="224.0" text-anchor="middle">“No company is assigned to your account”</text><text class="fc-text" x="635.0" y="240.0" text-anchor="middle">nothing is calculated</text><path class="fc-line" style="marker-end:url(#fc-arrow-co)" d="M190.0 248 V274" /><text class="fc-small" x="196.0" y="265">no</text><rect class="fc-box-amber" x="20" y="274" width="340" height="40" rx="9" /><text class="fc-text" x="190.0" y="298.0" text-anchor="middle">Asked for a different company in the URL?</text><path class="fc-line" style="marker-end:url(#fc-arrow-co)" d="M360 294.0 H428" /><text class="fc-small" x="368" y="289.0">yes</text><rect class="fc-box-red" x="430" y="277.0" width="410" height="34" rx="9" /><text class="fc-text" x="635.0" y="298.0" text-anchor="middle">403: only the own company can be opened</text><path class="fc-line" style="marker-end:url(#fc-arrow-co)" d="M190.0 314 V340" /><rect class="fc-box-green" x="20" y="340" width="340" height="46" rx="9" /><text class="fc-text fc-bold" x="190.0" y="359.0" text-anchor="middle">Report loads for the user&#x27;s own company</text><text class="fc-text" x="190.0" y="375.0" text-anchor="middle">(company box is locked)</text></svg>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="sr-flow">
                <header class="uf-card-head"><h2 class="uf-card-title" id="sr-flow">3. Where the figures come from</h2></header>
                <div class="uf-body gh-scroll">
                    <p>Left: the records read. Middle: the report blocks. Right: the totals they feed.</p>
                    <svg viewBox="0 0 870 510" width="100%" role="img" aria-label="Where the report figures come from" style="min-width:760px"><defs><marker id="fc-arrow-df" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#475569" /></marker></defs><rect class="fc-box" x="5" y="10" width="200" height="52" rx="9" /><text class="fc-text fc-bold" x="105.0" y="26.0" text-anchor="middle">Posted sales settlements</text><text class="fc-small" x="105.0" y="40.0" text-anchor="middle">items: sales value,</text><text class="fc-small" x="105.0" y="54.0" text-anchor="middle">COGS, qty</text><rect class="fc-box" x="5" y="72" width="200" height="52" rx="9" /><text class="fc-text fc-bold" x="105.0" y="95.0" text-anchor="middle">Scheme Received</text><text class="fc-small" x="105.0" y="109.0" text-anchor="middle">entries</text><rect class="fc-box" x="5" y="134" width="200" height="52" rx="9" /><text class="fc-text fc-bold" x="105.0" y="157.0" text-anchor="middle">Posted GRN lines</text><text class="fc-small" x="105.0" y="171.0" text-anchor="middle">FMR allowance</text><rect class="fc-box" x="5" y="196" width="200" height="52" rx="9" /><text class="fc-text fc-bold" x="105.0" y="219.0" text-anchor="middle">Ledger Register</text><text class="fc-small" x="105.0" y="233.0" text-anchor="middle">ZA 0.5% column</text><rect class="fc-box" x="5" y="258" width="200" height="52" rx="9" /><text class="fc-text fc-bold" x="105.0" y="281.0" text-anchor="middle">Revenue Detail</text><text class="fc-small" x="105.0" y="295.0" text-anchor="middle">posted entries</text><rect class="fc-box" x="5" y="320" width="200" height="52" rx="9" /><text class="fc-text fc-bold" x="105.0" y="343.0" text-anchor="middle">Expense Detail</text><text class="fc-small" x="105.0" y="357.0" text-anchor="middle">Distribution &amp; Selling</text><rect class="fc-box" x="5" y="382" width="200" height="52" rx="9" /><text class="fc-text fc-bold" x="105.0" y="405.0" text-anchor="middle">Settlement expenses</text><text class="fc-small" x="105.0" y="419.0" text-anchor="middle">by GL account</text><rect class="fc-box" x="5" y="444" width="200" height="52" rx="9" /><text class="fc-text fc-bold" x="105.0" y="467.0" text-anchor="middle">Profit Category Detail</text><text class="fc-small" x="105.0" y="481.0" text-anchor="middle">posted: taxation etc.</text><rect class="fc-box-amber" x="300" y="10" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="415.0" y="35.0" text-anchor="middle">Category Wise Sale</text><text class="fc-small" x="415.0" y="49.0" text-anchor="middle">+ Total Sale</text><rect class="fc-box-amber" x="300" y="80" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="415.0" y="105.0" text-anchor="middle">Profit From Sale</text><text class="fc-small" x="415.0" y="119.0" text-anchor="middle">sale − COGS</text><rect class="fc-box-amber" x="300" y="150" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="415.0" y="175.0" text-anchor="middle">Scheme / FMR / Cash Discount</text><text class="fc-small" x="415.0" y="189.0" text-anchor="middle">+ other revenue rows</text><rect class="fc-box-amber" x="300" y="250" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="415.0" y="275.0" text-anchor="middle">Distribution &amp; Selling</text><text class="fc-small" x="415.0" y="289.0" text-anchor="middle">Expenses</text><rect class="fc-box-amber" x="300" y="340" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="415.0" y="365.0" text-anchor="middle">Other Operating Expenses</text><text class="fc-small" x="415.0" y="379.0" text-anchor="middle">fixed list of accounts</text><rect class="fc-box-amber" x="300" y="430" width="230" height="56" rx="9" /><text class="fc-text fc-bold" x="415.0" y="455.0" text-anchor="middle">Taxation rows</text><text class="fc-small" x="415.0" y="469.0" text-anchor="middle">profit categories</text><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M205 36 C250 36 250 38 298 38" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M205 36 C250 36 250 108 298 108" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M205 98 C250 98 250 178 298 178" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M205 160 C250 160 250 178 298 178" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M205 222 C250 222 250 178 298 178" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M205 284 C250 284 250 178 298 178" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M205 346 C250 346 250 278 298 278" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M205 408 C250 408 250 368 298 368" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M205 470 C250 470 250 458 298 458" /><rect class="fc-box-green" x="620" y="40" width="235" height="70" rx="9" /><text class="fc-text fc-bold" x="737.5" y="65.0" text-anchor="middle">Grand Revenue</text><text class="fc-small" x="737.5" y="79.0" text-anchor="middle">Profit From Sale + Scheme</text><text class="fc-small" x="737.5" y="93.0" text-anchor="middle">+ FMR + Cash Discount + revenue rows</text><rect class="fc-box-red" x="620" y="300" width="235" height="70" rx="9" /><text class="fc-text fc-bold" x="737.5" y="325.0" text-anchor="middle">Grand Total Expenses</text><text class="fc-small" x="737.5" y="339.0" text-anchor="middle">Distribution &amp; Selling</text><text class="fc-small" x="737.5" y="353.0" text-anchor="middle">+ Other Operating</text><rect class="fc-box-green" x="620" y="440" width="235" height="56" rx="9" /><text class="fc-text fc-bold" x="737.5" y="465.0" text-anchor="middle">Profit after Taxation</text><text class="fc-small" x="737.5" y="479.0" text-anchor="middle">Grand Revenue − Expenses − taxation</text><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M530 38 C580 38 580 75 618 75" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M530 108 C580 108 580 75 618 75" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M530 188 C580 188 580 75 618 75" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M530 278 C580 278 580 335 618 335" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M530 368 C580 368 580 335 618 335" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M737 110 V438" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M737 370 V438" /><path class="fc-line" style="marker-end:url(#fc-arrow-df)" d="M530 458 C580 458 580 468 618 468" /></svg>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="sr-left">
                <header class="uf-card-head"><h2 class="uf-card-title" id="sr-left">4. Left table: Category Wise Sale and Profit From Sale &amp; Other Revenue</h2></header>
                <div class="uf-body">
                    <p>The “How calculated” column on the report repeats these rules next to each figure.</p>
                    <table class="gh-table">
                        <thead><tr><th>Line</th><th>How it is calculated</th><th>Source</th></tr></thead>
                        <tbody>
                            <tr><td>Category Wise Sale (each category)</td><td>Sum of the sales value of the settlement items of products in that category, inside the period and filters.</td><td>Sales settlement items</td></tr>
                            <tr><td>Total Sale</td><td>Sum of the category lines.</td><td>—</td></tr>
                            <tr><td><b>Profit From Sale</b></td><td>
                                <b>Average = No (default):</b> Σ(qty sold × product list price) − Σ(qty sold × product standard cost).<br>
                                <b>Average = Yes:</b> Σ(actual sales value) − Σ(actual COGS at average cost).<br>
                                Added up over all categories.</td><td>Settlement items, product prices</td></tr>
                            <tr><td>Scheme Received</td><td>Sum of Scheme Received entries of the company dated in the period.</td><td>Scheme Received</td></tr>
                            <tr><td>FMR Received</td><td>Sum of the FMR allowance on lines of posted GRNs received in the period.</td><td>GRN items</td></tr>
                            <tr><td>Cash Discount from Invoices (0.5%)</td><td>Sum of the ZA 0.5% column of the supplier Ledger Register in the period.</td><td>Ledger Register</td></tr>
                            <tr><td>Other Allowance from Company, Rate Increase Profit</td><td>No source yet. Always shown as —.</td><td>—</td></tr>
                            <tr><td>Other revenue rows</td><td>Posted Revenue Detail entries per revenue category in the period (entries without a company use the category's company).</td><td>Revenue Detail</td></tr>
                            <tr><td><b>Grand Revenue</b></td><td>Profit From Sale + Scheme Received + FMR Received + Cash Discount + other revenue rows.</td><td>—</td></tr>
                        </tbody>
                    </table>
                    <div class="gh-note">Category Wise Sale, Profit From Sale and Other Operating Expenses follow the Salesman, Vehicle, Warehouse and Status filters. Scheme, FMR, Cash Discount, the revenue rows, Distribution &amp; Selling Expenses and the taxation rows follow the company and the dates only.</div>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="sr-right">
                <header class="uf-card-head"><h2 class="uf-card-title" id="sr-right">5. Right table: expenses and the result</h2></header>
                <div class="uf-body">
                    <table class="gh-table">
                        <thead><tr><th>Line</th><th>How it is calculated</th><th>Source</th></tr></thead>
                        <tbody>
                            <tr><td>Distribution &amp; Selling Expenses</td><td>One line per Expense Detail record of the company in the period, grouped by category (Stationery, TCS, Tonner &amp; IT, Salaries, Fuel, Van Work, Printing, Salesflo Fee, Administrative). A category with no record shows —. Total = sum of the amounts.</td><td>Expense Detail</td></tr>
                            <tr><td>Other Operating Expenses</td><td>Expenses entered on the settlements, summed per GL account: AMR Powder 5252, AMR Liquid 5262, Scheme Discount 5292, Advance Tax 1161, Discount to Trade 5223, Promotion Off 5288, Food/Salesman/Loader 5282, Toll Tax/Labor 5272, Miscellaneous 5221, Administrative 5210, Short Amount 5293, then any other account used. Follows the same filters as the sales.</td><td>Settlement expenses</td></tr>
                            <tr><td>Engro only</td><td>Short Amount line = Excess Amount − Short Amount (excess is taken from the settlements' excess amounts, and 4250 is not shown separately).</td><td>Settlement excess amounts</td></tr>
                            <tr><td>Grand Total Expenses</td><td>Distribution &amp; Selling total + Other Operating total.</td><td>—</td></tr>
                            <tr><td><b>Profit before Taxation</b></td><td>Grand Revenue − Grand Total Expenses.</td><td>—</td></tr>
                            <tr><td>Taxation rows</td><td>Posted Profit Category Detail entries of the company in the period, one line per profit category.</td><td>Profit Category Detail</td></tr>
                            <tr><td><b>Profit after Taxation</b></td><td>Profit before Taxation − the taxation rows.</td><td>—</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="uf-card gh-card" aria-labelledby="sr-top">
                <header class="uf-card-head"><h2 class="uf-card-title" id="sr-top">6. Cards, charts, filters and printing</h2></header>
                <div class="uf-body">
                    <ul>
                        <li><b>Gross Inflow</b> = Total Sale + Scheme + FMR + Cash Discount + other revenue. <b>Total Expenses</b> = Grand Total Expenses. <b>Net Position</b> = Gross Inflow − Total Expenses. These cards are a quick view and differ from Profit before Taxation, which starts from Profit From Sale instead of Total Sale.</li>
                        <li><b>Charts:</b> Inflow Composition (sale, scheme, FMR, cash discount, other revenue) and Expense Comparison (Distribution &amp; Selling against Other Operating). Both print.</li>
                        <li><b>Filters:</b> period, salesman, vehicle, warehouse, status (posted by default), Average (Yes/No, see section 4) and company.</li>
                        <li><b>Printing:</b> Print the page. The filter bar and cards are hidden, charts and both tables stay, and the “How calculated” column prints in a smaller font.</li>
                    </ul>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
