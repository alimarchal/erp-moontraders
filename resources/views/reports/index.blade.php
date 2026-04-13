<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Reports
        </h2>
    </x-slot>

    <div class="py-6 bg-gray-100 min-h-full">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- ─── Financial Statements ─── --}}
                @canany(['report-financial-general-ledger', 'report-financial-trial-balance', 'report-financial-account-balances', 'report-financial-balance-sheet', 'report-financial-income-statement', 'report-audit-opening-customer-balance'])
                    <div>
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Financial
                            Statements</p>
                        <div
                            class="bg-white rounded-2xl overflow-hidden shadow-2xl hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('report-financial-general-ledger')
                                <x-settings-row href="{{ route('reports.general-ledger.index') }}" label="General Ledger"
                                    description="Complete record of all financial transactions" icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-financial-trial-balance')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.trial-balance.index') }}" label="Trial Balance"
                                    description="Verification of debit and credit balances" icon-bg="bg-green-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-financial-account-balances')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.account-balances.index') }}" label="Account Balances"
                                    description="Summary of all account positions" icon-bg="bg-purple-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-financial-balance-sheet')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.balance-sheet.index') }}" label="Balance Sheet"
                                    description="Assets, liabilities, and equity overview" icon-bg="bg-blue-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-financial-income-statement')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.income-statement.index') }}" label="Income Statement"
                                    description="Revenue, expenses, and net income overview" icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-opening-customer-balance')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.opening-customer-balance.index') }}"
                                    label="Opening Customer Balance" description="Customer opening balances per salesman"
                                    icon-bg="bg-cyan-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- ─── Receivables & Core Reports ─── --}}
                @canany(['report-audit-creditors-ledger', 'report-sales-credit-sales', 'report-audit-cash-detail', 'report-audit-investment-summary', 'report-audit-claim-register', 'report-audit-ledger-register', 'report-audit-cheque-register', 'report-audit-amr-dispose-register'])
                    <div>
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Receivables
                            &amp; Core Reports</p>
                        <div
                            class="bg-white rounded-2xl overflow-hidden shadow-2xl hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('report-audit-creditors-ledger')
                                <x-settings-row href="{{ route('reports.creditors-ledger.index') }}" label="Creditors Ledger"
                                    description="Outstanding balances by creditor" icon-bg="bg-amber-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-sales-credit-sales')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.credit-sales.salesman-history') }}"
                                    label="Salesman Credit History" description="Credit sales history per salesman"
                                    icon-bg="bg-cyan-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-cash-detail')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.cash-detail.index') }}" label="Cash Collection Detail"
                                    description="Daily cash collection breakdown" icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-investment-summary')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.investment-summary.index') }}"
                                    label="Investment Summary" description="Daily investment position summary"
                                    icon-bg="bg-green-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-claim-register')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.claim-register.index') }}" label="Claim Register"
                                    description="Supplier claims and recovery tracking" icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-ledger-register')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.ledger-register.index') }}"
                                    label="Supplier Ledger Register"
                                    description="Supplier transaction ledger with running balance" icon-bg="bg-teal-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-cheque-register')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.cheque-register.index') }}"
                                    label="Cheque Register"
                                    description="Cheque collection and clearing status tracking" icon-bg="bg-amber-600">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-amr-dispose-register')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.amr-dispose-register.index') }}"
                                    label="AMR Dispose Register"
                                    description="AMR liquids & powders disposal tracking by supplier" icon-bg="bg-rose-600">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- ─── Audit & Product Reports ─── --}}
                @canany(['report-sales-fmr-amr-comparison', 'report-audit-sku-fmr-amr', 'report-audit-percentage-expense', 'report-audit-stock-availability', 'report-sales-shop-list', 'report-audit-expense-detail'])
                    <div>
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Audit &amp;
                            Product Reports</p>
                        <div
                            class="bg-white rounded-2xl overflow-hidden shadow-2xl hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('report-sales-fmr-amr-comparison')
                                <x-settings-row href="{{ route('reports.fmr-amr-comparison.index') }}"
                                    label="FMR vs AMR Comparison" description="Factory vs actual movement reconciliation"
                                    icon-bg="bg-rose-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-sku-fmr-amr')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.sku-fmr-amr.index') }}" label="SKU-wise FMR vs AMR"
                                    description="Product-level FMR & AMR breakdown by SKU" icon-bg="bg-pink-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-percentage-expense')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.percentage-expense.index') }}"
                                    label="Percentage Summery" description="Expense percentage and distribution"
                                    icon-bg="bg-violet-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-stock-availability')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.stock-availability.index') }}"
                                    label="Stock Availability" description="Current & historical stock by supplier"
                                    icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-sales-shop-list')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.shop-list.index') }}" label="Shop Directory"
                                    description="Registered retail outlet listings" icon-bg="bg-sky-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-expense-detail')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.expense-detail.index') }}" label="Expense Detail"
                                    description="Track & manage operational expenses by category" icon-bg="bg-red-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- ─── Inventory Management ─── --}}
                @canany(['report-inventory-daily-stock-register', 'report-inventory-salesman-stock-register', 'report-inventory-inventory-ledger', 'report-inventory-van-stock-batch', 'report-inventory-van-stock-ledger'])
                    <div>
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Inventory
                            Management</p>
                        <div
                            class="bg-white rounded-2xl overflow-hidden shadow-2xl hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('report-inventory-daily-stock-register')
                                <x-settings-row href="{{ route('reports.daily-stock-register.index') }}"
                                    label="Daily Stock Register" description="End-of-day stock position summary"
                                    icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-inventory-salesman-stock-register')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.salesman-stock-register.index') }}"
                                    label="Salesman Stock Register" description="Stock allocated per salesman"
                                    icon-bg="bg-purple-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-inventory-van-stock-ledger')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.van-stock-ledger.index') }}" label="Van Stock Ledger"
                                    description="Van stock transaction history" icon-bg="bg-teal-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V13.5m-9-3.75h3.375c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125h-3.375m0 0H6.375c-.621 0-1.125-.504-1.125-1.125v-1.5c0-.621.504-1.125 1.125-1.125h3.375m0 0v-2.25" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-inventory-van-stock-batch')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.van-stock-batch.index') }}" label="Van Stock by Batch"
                                    description="Batch-level van inventory details" icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-inventory-inventory-ledger')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.inventory-ledger.index') }}" label="Inventory Ledger"
                                    description="Double-entry stock movement tracking" icon-bg="bg-violet-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- ─── Distribution & Logistics ─── --}}
                @canany(['report-sales-goods-issue', 'report-sales-daily-sales', 'report-sales-vehicle', 'report-audit-product-price-change-log'])
                    <div>
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Distribution
                            &amp; Logistics</p>
                        <div
                            class="bg-white rounded-2xl overflow-hidden shadow-2xl hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('report-sales-goods-issue')
                                <x-settings-row href="{{ route('reports.goods-issue.index') }}" label="Goods Issue Report"
                                    description="Outward dispatch and delivery records" icon-bg="bg-blue-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M7.5 7.5h-.75A2.25 2.25 0 004.5 9.75v7.5a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25v-7.5a2.25 2.25 0 00-2.25-2.25h-.75m-3-3l-3 3m0 0l3 3m-3-3h11.25" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-sales-daily-sales')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.daily-sales.van-stock') }}" label="Van Stock Status"
                                    description="Current van inventory levels" icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V13.5m-9-3.75h3.375c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125h-3.375m0 0H6.375c-.621 0-1.125-.504-1.125-1.125v-1.5c0-.621.504-1.125 1.125-1.125h3.375m0 0v-2.25" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-sales-vehicle')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.vehicle.index') }}" label="Vehicle Report"
                                    description="Fleet details and assignments" icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V13.5m-9-3.75h3.375c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125h-3.375m0 0H6.375c-.621 0-1.125-.504-1.125-1.125v-1.5c0-.621.504-1.125 1.125-1.125h3.375m0 0v-2.25" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-product-price-change-log')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.product-price-change-log.index') }}"
                                    label="Product Price Change Log"
                                    description="Audit trail of all price changes & impacted batches" icon-bg="bg-blue-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- ─── Supplier Reports ─── --}}
                @canany(['report-audit-invoice-summary', 'report-audit-custom-settlement', 'report-sales-sku-rates', 'report-audit-advance-tax', 'report-audit-advance-tax-sales-register'])
                    <div>
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Supplier Reports</p>
                        <div
                            class="bg-white rounded-2xl overflow-hidden shadow-2xl hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('report-audit-invoice-summary')
                                <x-settings-row href="{{ route('reports.invoice-summary.index') }}" label="Invoice Summary"
                                    description="Supplier invoice summary with tax breakdown" icon-bg="bg-purple-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-custom-settlement')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.custom-settlement.index') }}" label="Custom Settlement"
                                    description="Custom settlement transactions" icon-bg="bg-blue-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-sales-sku-rates')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.sku-rates.index') }}" label="SKU & Pricing"
                                    description="Product codes and pricing schedule" icon-bg="bg-amber-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-advance-tax')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.advance-tax.index') }}" label="Advance Tax Report"
                                    description="Advance tax computation and records" icon-bg="bg-red-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.589-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.589-1.202L5.25 4.971z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-audit-advance-tax-sales-register')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.advance-tax-sales-register.index') }}" label="Advance Tax Sales Register"
                                    description="Daily advance tax register by supplier" icon-bg="bg-red-700">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.589-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.589-1.202L5.25 4.971z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                {{-- ─── Sales & Revenue ─── --}}
                @canany(['report-sales-daily-sales', 'report-sales-credit-sales', 'report-sales-settlement', 'report-sales-roi', 'report-sales-scheme-discount'])
                    <div>
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Sales &amp;
                            Revenue</p>
                        <div
                            class="bg-white rounded-2xl overflow-hidden shadow-2xl hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('report-sales-daily-sales')
                                <x-settings-row href="{{ route('reports.daily-sales.index') }}" label="Daily Sales Summary"
                                    description="Consolidated daily revenue overview" icon-bg="bg-green-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.daily-sales.product-wise') }}" label="Sales by Product"
                                    description="Revenue breakdown by product category" icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.daily-sales.salesman-wise') }}"
                                    label="Sales by Salesman" description="Individual salesman performance analysis"
                                    icon-bg="bg-blue-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-sales-settlement')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.sales-settlement.index') }}" label="Sales Settlement"
                                    description="Settlement and reconciliation records" icon-bg="bg-teal-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-sales-roi')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.roi.index') }}" label="Return on Investment"
                                    description="Profitability and ROI analysis" icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('report-sales-scheme-discount')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row href="{{ route('reports.scheme-discount.index') }}"
                                    label="Schemes &amp; Discounts" description="Promotional scheme and discount tracking"
                                    icon-bg="bg-pink-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

            </div>
        </div>
    </div>
</x-app-layout>