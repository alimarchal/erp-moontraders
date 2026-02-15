<div>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ============================================================ --}}
            {{-- PENDING ACTIONS ALERT BAR --}}
            {{-- ============================================================ --}}
            @if (collect($pendingItems)->sum() > 0)
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2.5 bg-amber-100 rounded-xl shadow-inner">
                            <svg class="w-5 h-5 text-amber-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        </div>
                        <h3 class="text-sm font-bold text-amber-800 uppercase tracking-wide">Pending Actions</h3>
                        <span class="ml-auto px-3 py-1 bg-amber-200 text-amber-900 rounded-full text-xs font-bold">{{ collect($pendingItems)->sum() }} Total</span>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if (($pendingItems['draftSettlements'] ?? 0) > 0)
                            <a href="{{ route('sales-settlements.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm text-amber-800 rounded-xl text-sm font-medium hover:bg-white hover:shadow-md transition-all duration-300 border border-amber-100">
                                <span class="w-6 h-6 bg-gradient-to-br from-amber-400 to-amber-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow-sm">{{ $pendingItems['draftSettlements'] }}</span>
                                Draft Settlements
                            </a>
                        @endif
                        @if (($pendingItems['draftGrns'] ?? 0) > 0)
                            <a href="{{ route('goods-receipt-notes.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm text-amber-800 rounded-xl text-sm font-medium hover:bg-white hover:shadow-md transition-all duration-300 border border-amber-100">
                                <span class="w-6 h-6 bg-gradient-to-br from-amber-400 to-amber-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow-sm">{{ $pendingItems['draftGrns'] }}</span>
                                Draft GRNs
                            </a>
                        @endif
                        @if (($pendingItems['draftGoodsIssues'] ?? 0) > 0)
                            <a href="{{ route('goods-issues.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm text-amber-800 rounded-xl text-sm font-medium hover:bg-white hover:shadow-md transition-all duration-300 border border-amber-100">
                                <span class="w-6 h-6 bg-gradient-to-br from-amber-400 to-amber-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow-sm">{{ $pendingItems['draftGoodsIssues'] }}</span>
                                Draft Goods Issues
                            </a>
                        @endif
                        @if (($pendingItems['draftJournalEntries'] ?? 0) > 0)
                            <a href="{{ route('journal-entries.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm text-amber-800 rounded-xl text-sm font-medium hover:bg-white hover:shadow-md transition-all duration-300 border border-amber-100">
                                <span class="w-6 h-6 bg-gradient-to-br from-amber-400 to-amber-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow-sm">{{ $pendingItems['draftJournalEntries'] }}</span>
                                Draft Journal Entries
                            </a>
                        @endif
                        @if (($pendingItems['draftPayments'] ?? 0) > 0)
                            <a href="{{ route('supplier-payments.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm text-amber-800 rounded-xl text-sm font-medium hover:bg-white hover:shadow-md transition-all duration-300 border border-amber-100">
                                <span class="w-6 h-6 bg-gradient-to-br from-amber-400 to-amber-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow-sm">{{ $pendingItems['draftPayments'] }}</span>
                                Draft Payments
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- KPI SUMMARY CARDS - Interactive with hover zoom + shadow --}}
            {{-- ============================================================ --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                @if (isset($kpiCards['totalSalesThisMonth']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-blue-100/50 hover:scale-[1.03] hover:border-blue-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-blue-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-blue-400 to-blue-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-blue-100 rounded-xl transition-all duration-300 group-hover:bg-blue-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sales This Month</span>
                            </div>
                            <p class="text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($kpiCards['totalSalesThisMonth'], 2) }}</p>
                        </div>
                    </div>
                @endif

                @if (isset($kpiCards['grossProfitThisMonth']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-emerald-100/50 hover:scale-[1.03] hover:border-emerald-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-emerald-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-400 to-emerald-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-emerald-100 rounded-xl transition-all duration-300 group-hover:bg-emerald-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Gross Profit</span>
                            </div>
                            <p class="text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($kpiCards['grossProfitThisMonth'], 2) }}</p>
                        </div>
                    </div>
                @endif

                @if (isset($kpiCards['totalPurchasesThisMonth']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-indigo-100/50 hover:scale-[1.03] hover:border-indigo-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-indigo-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-400 to-indigo-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-indigo-100 rounded-xl transition-all duration-300 group-hover:bg-indigo-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Purchases This Month</span>
                            </div>
                            <p class="text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($kpiCards['totalPurchasesThisMonth'], 2) }}</p>
                        </div>
                    </div>
                @endif

                @if (isset($kpiCards['outstandingPayables']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-red-100/50 hover:scale-[1.03] hover:border-red-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-red-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-red-400 to-red-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-red-100 rounded-xl transition-all duration-300 group-hover:bg-red-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Outstanding Payables</span>
                            </div>
                            <p class="text-2xl font-extrabold tabular-nums {{ $kpiCards['outstandingPayables'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($kpiCards['outstandingPayables'], 2) }}</p>
                        </div>
                    </div>
                @endif

                @if (isset($kpiCards['totalInventoryValue']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-orange-100/50 hover:scale-[1.03] hover:border-orange-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-orange-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-orange-400 to-orange-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-orange-100 rounded-xl transition-all duration-300 group-hover:bg-orange-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Inventory Value</span>
                            </div>
                            <p class="text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($kpiCards['totalInventoryValue'], 2) }}</p>
                            @if (isset($kpiCards['productsInStock']))
                                <p class="text-xs text-gray-500 mt-1.5 font-medium">
                                    <span class="text-orange-600 font-bold">{{ $kpiCards['productsInStock'] }}</span> / {{ $kpiCards['totalProducts'] }} products in stock
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                @if (isset($kpiCards['cashCollectedThisMonth']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-teal-100/50 hover:scale-[1.03] hover:border-teal-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-teal-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-teal-400 to-teal-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-teal-100 rounded-xl transition-all duration-300 group-hover:bg-teal-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cash Collected</span>
                            </div>
                            <p class="text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($kpiCards['cashCollectedThisMonth'], 2) }}</p>
                        </div>
                    </div>
                @endif

                @if (isset($kpiCards['creditSalesThisMonth']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-purple-100/50 hover:scale-[1.03] hover:border-purple-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-purple-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-purple-400 to-purple-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-purple-100 rounded-xl transition-all duration-300 group-hover:bg-purple-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Credit Sales</span>
                            </div>
                            <p class="text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($kpiCards['creditSalesThisMonth'], 2) }}</p>
                        </div>
                    </div>
                @endif

                @if (isset($kpiCards['goodsIssuedThisMonth']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-pink-100/50 hover:scale-[1.03] hover:border-pink-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-pink-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-pink-400 to-pink-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-pink-100 rounded-xl transition-all duration-300 group-hover:bg-pink-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Goods Issued</span>
                            </div>
                            <p class="text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($kpiCards['goodsIssuedThisMonth'], 2) }}</p>
                        </div>
                    </div>
                @endif

                @if (isset($kpiCards['draftJournalEntries']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-yellow-100/50 hover:scale-[1.03] hover:border-yellow-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-yellow-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-yellow-100 rounded-xl transition-all duration-300 group-hover:bg-yellow-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Draft Journals</span>
                            </div>
                            <p class="text-2xl font-extrabold tabular-nums {{ $kpiCards['draftJournalEntries'] > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $kpiCards['draftJournalEntries'] }}</p>
                        </div>
                    </div>
                @endif

                @if (isset($kpiCards['paymentsThisMonth']))
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 relative overflow-hidden cursor-default transition-all duration-300 hover:shadow-xl hover:shadow-cyan-100/50 hover:scale-[1.03] hover:border-cyan-200">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-cyan-100/80 to-transparent rounded-bl-full transition-all duration-300 group-hover:w-28 group-hover:h-28"></div>
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-cyan-400 to-cyan-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2.5 bg-cyan-100 rounded-xl transition-all duration-300 group-hover:bg-cyan-200 group-hover:shadow-md group-hover:scale-110">
                                    <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Payments This Month</span>
                            </div>
                            <p class="text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($kpiCards['paymentsThisMonth'], 2) }}</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ============================================================ --}}
            {{-- ROW 1: Monthly Sales Trend + Sales by Payment Method --}}
            {{-- ============================================================ --}}
            @if (!empty($monthlySalesTrend['labels']) || !empty($salesByPaymentMethod['values']))
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    @if (!empty($monthlySalesTrend['labels']))
                        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Monthly Sales & Profit Trend</h3>
                            <p class="text-xs text-gray-400 mb-4">Last 12 months performance overview</p>
                            <div id="chart-monthly-sales" class="w-full" style="min-height: 340px;"></div>
                        </div>
                    @endif

                    @if (!empty($salesByPaymentMethod['values']) && array_sum($salesByPaymentMethod['values']) > 0)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Sales by Payment Method</h3>
                            <p class="text-xs text-gray-400 mb-4">Current month breakdown</p>
                            <div id="chart-payment-method" class="w-full" style="min-height: 340px;"></div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- ROW 2: Revenue vs COGS + Profit Margin Gauge --}}
            {{-- ============================================================ --}}
            @if (!empty($revenueVsCogs['labels']) || !empty($profitMarginGauge))
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    @if (!empty($revenueVsCogs['labels']))
                        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Revenue vs COGS vs Expenses</h3>
                            <p class="text-xs text-gray-400 mb-4">6-month cost analysis</p>
                            <div id="chart-revenue-cogs" class="w-full" style="min-height: 340px;"></div>
                        </div>
                    @endif

                    @if (!empty($profitMarginGauge) && $profitMarginGauge['revenue'] > 0)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Profit Margin</h3>
                            <p class="text-xs text-gray-400 mb-4">Current month profitability</p>
                            <div id="chart-profit-margin" class="w-full" style="min-height: 280px;"></div>
                            <div class="mt-2 grid grid-cols-2 gap-3 text-center">
                                <div class="bg-blue-50 rounded-xl p-3">
                                    <p class="text-xs text-gray-500 font-medium">Revenue</p>
                                    <p class="text-sm font-bold text-blue-700 tabular-nums">{{ number_format($profitMarginGauge['revenue'], 0) }}</p>
                                </div>
                                <div class="bg-emerald-50 rounded-xl p-3">
                                    <p class="text-xs text-gray-500 font-medium">Profit</p>
                                    <p class="text-sm font-bold text-emerald-700 tabular-nums">{{ number_format($profitMarginGauge['profit'], 0) }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- ROW 3: Daily Sales + Cash vs Credit Trend --}}
            {{-- ============================================================ --}}
            @if (!empty($dailySalesTrend['labels']) || !empty($cashVsCreditTrend['labels']))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @if (!empty($dailySalesTrend['labels']))
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Daily Sales (Last 30 Days)</h3>
                            <p class="text-xs text-gray-400 mb-4">Zoom in to explore daily patterns</p>
                            <div id="chart-daily-sales" class="w-full" style="min-height: 340px;"></div>
                        </div>
                    @endif

                    @if (!empty($cashVsCreditTrend['labels']))
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Cash vs Credit vs Cheque Trend</h3>
                            <p class="text-xs text-gray-400 mb-4">6-month payment method breakdown</p>
                            <div id="chart-cash-credit-trend" class="w-full" style="min-height: 340px;"></div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- ROW 4: Sales by Day of Week + Customer Channel Distribution --}}
            {{-- ============================================================ --}}
            @if (!empty($salesByDayOfWeek['labels']) || !empty($customerChannelDistribution['labels']))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @if (!empty($salesByDayOfWeek['labels']) && array_sum($salesByDayOfWeek['values']) > 0)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Sales by Day of Week</h3>
                            <p class="text-xs text-gray-400 mb-4">90-day sales pattern analysis</p>
                            <div id="chart-day-of-week" class="w-full" style="min-height: 360px;"></div>
                        </div>
                    @endif

                    @if (!empty($customerChannelDistribution['labels']))
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Customer Channel Distribution</h3>
                            <p class="text-xs text-gray-400 mb-4">Active customers by channel type</p>
                            <div id="chart-customer-channels" class="w-full" style="min-height: 360px;"></div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- ROW 5: Purchases vs Payments + GRN vs Goods Issue --}}
            {{-- ============================================================ --}}
            @if (!empty($purchasesVsPayments['labels']) || !empty($grnVsGoodsIssueTrend['labels']))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @if (!empty($purchasesVsPayments['labels']))
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Purchases vs Payments</h3>
                            <p class="text-xs text-gray-400 mb-4">6-month supplier obligations</p>
                            <div id="chart-purchases-payments" class="w-full" style="min-height: 340px;"></div>
                        </div>
                    @endif

                    @if (!empty($grnVsGoodsIssueTrend['labels']))
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Goods Receipt vs Goods Issue</h3>
                            <p class="text-xs text-gray-400 mb-4">6-month inbound vs outbound flow</p>
                            <div id="chart-grn-gi" class="w-full" style="min-height: 340px;"></div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- ROW 6: Top Products (Sales) + Top Salesperson --}}
            {{-- ============================================================ --}}
            @if (!empty($topProductsBySales['labels']) || !empty($topSalespersonBySales['labels']))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @if (!empty($topProductsBySales['labels']))
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Top Products by Sales</h3>
                            <p class="text-xs text-gray-400 mb-4">This month's best sellers</p>
                            <div id="chart-top-products-sales" class="w-full" style="min-height: 360px;"></div>
                        </div>
                    @endif

                    @if (!empty($topSalespersonBySales['labels']))
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Top Salespeople</h3>
                            <p class="text-xs text-gray-400 mb-4">This month's top performers</p>
                            <div id="chart-top-salesperson" class="w-full" style="min-height: 360px;"></div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- ROW 7: Warehouse Stock + Stock Movement Breakdown --}}
            {{-- ============================================================ --}}
            @if (!empty($warehouseStockDistribution['labels']) || !empty($stockMovementBreakdown['labels']))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @if (!empty($warehouseStockDistribution['labels']))
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Stock by Warehouse</h3>
                            <p class="text-xs text-gray-400 mb-4">Value distribution across locations</p>
                            <div id="chart-warehouse-stock" class="w-full" style="min-height: 360px;"></div>
                        </div>
                    @endif

                    @if (!empty($stockMovementBreakdown['labels']))
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Stock Movement (Last 30 Days)</h3>
                            <p class="text-xs text-gray-400 mb-4">Inward vs outward flow breakdown</p>
                            <div id="chart-stock-movement" class="w-full" style="min-height: 360px;"></div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- ROW 8: Top Products (Stock Value) + Settlement Status + Journal Status --}}
            {{-- ============================================================ --}}
            @if (!empty($topProductsByStockValue['labels']) || !empty($settlementStatusDistribution['labels']) || !empty($journalEntryStatus['labels']))
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    @if (!empty($topProductsByStockValue['labels']))
                        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                            <h3 class="text-base font-bold text-gray-800 mb-1">Top Products by Stock Value</h3>
                            <p class="text-xs text-gray-400 mb-4">Highest value inventory items</p>
                            <div id="chart-top-stock" class="w-full" style="min-height: 360px;"></div>
                        </div>
                    @endif

                    <div class="space-y-6">
                        @if (!empty($settlementStatusDistribution['labels']) && array_sum($settlementStatusDistribution['values']) > 0)
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                                <h3 class="text-base font-bold text-gray-800 mb-1">Settlement Status</h3>
                                <p class="text-xs text-gray-400 mb-4">All-time distribution</p>
                                <div id="chart-settlement-status" class="w-full" style="min-height: 250px;"></div>
                            </div>
                        @endif

                        @if (!empty($journalEntryStatus['labels']) && array_sum($journalEntryStatus['values']) > 0)
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                                <h3 class="text-base font-bold text-gray-800 mb-1">Journal Entry Status</h3>
                                <p class="text-xs text-gray-400 mb-4">All-time distribution</p>
                                <div id="chart-journal-status" class="w-full" style="min-height: 250px;"></div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- QUICK LINKS --}}
            {{-- ============================================================ --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-lg transition-shadow duration-300">
                <h3 class="text-base font-bold text-gray-800 mb-1">Quick Actions</h3>
                <p class="text-xs text-gray-400 mb-4">Jump to frequently used operations</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @can('goods-receipt-note-create')
                        <a href="{{ route('goods-receipt-notes.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-blue-50 hover:bg-blue-100 hover:shadow-md hover:scale-105 transition-all duration-300 group border border-transparent hover:border-blue-200">
                            <div class="p-2.5 bg-blue-100 rounded-xl group-hover:bg-blue-200 group-hover:shadow-sm transition-all duration-300">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-blue-700 text-center">New GRN</span>
                        </a>
                    @endcan
                    @can('goods-issue-create')
                        <a href="{{ route('goods-issues.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-purple-50 hover:bg-purple-100 hover:shadow-md hover:scale-105 transition-all duration-300 group border border-transparent hover:border-purple-200">
                            <div class="p-2.5 bg-purple-100 rounded-xl group-hover:bg-purple-200 group-hover:shadow-sm transition-all duration-300">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-purple-700 text-center">New Issue</span>
                        </a>
                    @endcan
                    @can('sales-settlement-create')
                        <a href="{{ route('sales-settlements.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-pink-50 hover:bg-pink-100 hover:shadow-md hover:scale-105 transition-all duration-300 group border border-transparent hover:border-pink-200">
                            <div class="p-2.5 bg-pink-100 rounded-xl group-hover:bg-pink-200 group-hover:shadow-sm transition-all duration-300">
                                <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-pink-700 text-center">New Settlement</span>
                        </a>
                    @endcan
                    @can('journal-entry-create')
                        <a href="{{ route('journal-entries.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-yellow-50 hover:bg-yellow-100 hover:shadow-md hover:scale-105 transition-all duration-300 group border border-transparent hover:border-yellow-200">
                            <div class="p-2.5 bg-yellow-100 rounded-xl group-hover:bg-yellow-200 group-hover:shadow-sm transition-all duration-300">
                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-yellow-700 text-center">New Journal</span>
                        </a>
                    @endcan
                    @can('supplier-payment-create')
                        <a href="{{ route('supplier-payments.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-emerald-50 hover:bg-emerald-100 hover:shadow-md hover:scale-105 transition-all duration-300 group border border-transparent hover:border-emerald-200">
                            <div class="p-2.5 bg-emerald-100 rounded-xl group-hover:bg-emerald-200 group-hover:shadow-sm transition-all duration-300">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-emerald-700 text-center">New Payment</span>
                        </a>
                    @endcan
                    @can('inventory-view')
                        <a href="{{ route('inventory.current-stock.index') }}" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-orange-50 hover:bg-orange-100 hover:shadow-md hover:scale-105 transition-all duration-300 group border border-transparent hover:border-orange-200">
                            <div class="p-2.5 bg-orange-100 rounded-xl group-hover:bg-orange-200 group-hover:shadow-sm transition-all duration-300">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-orange-700 text-center">View Stock</span>
                        </a>
                    @endcan
                </div>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const defaultOptions = {
        chart: {
            fontFamily: 'Figtree, sans-serif',
            toolbar: {
                show: true,
                tools: { download: true, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true },
                autoSelected: 'zoom'
            },
            animations: { enabled: true, easing: 'easeinout', speed: 600, dynamicAnimation: { enabled: true, speed: 400 } },
            dropShadow: { enabled: false },
        },
        colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'],
        grid: { borderColor: '#f1f5f9', strokeDashArray: 3, padding: { left: 10, right: 10 } },
        tooltip: { theme: 'light', style: { fontSize: '12px' }, x: { show: true }, marker: { show: true } },
    };

    const noToolbarOpts = {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, toolbar: { show: false } }
    };

    function formatCurrency(val) {
        return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
    }

    function formatCurrencyFull(val) {
        return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
    }

    {{-- ============================================================ --}}
    {{-- MONTHLY SALES TREND --}}
    {{-- ============================================================ --}}
    @if (!empty($monthlySalesTrend['labels']))
    new ApexCharts(document.querySelector('#chart-monthly-sales'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'area', height: 340 },
        series: [
            { name: 'Sales', data: @json($monthlySalesTrend['sales']) },
            { name: 'Gross Profit', data: @json($monthlySalesTrend['profits']) },
        ],
        xaxis: { categories: @json($monthlySalesTrend['labels']), labels: { style: { fontSize: '11px', colors: '#94a3b8' } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 90, 100] } },
        dataLabels: { enabled: false },
        colors: ['#3b82f6', '#10b981'],
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', fontWeight: 600, markers: { radius: 3, width: 10, height: 10 } },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- SALES BY PAYMENT METHOD --}}
    {{-- ============================================================ --}}
    @if (!empty($salesByPaymentMethod['values']) && array_sum($salesByPaymentMethod['values']) > 0)
    new ApexCharts(document.querySelector('#chart-payment-method'), {
        ...noToolbarOpts,
        chart: { ...noToolbarOpts.chart, type: 'donut', height: 340 },
        series: @json($salesByPaymentMethod['values']),
        labels: @json($salesByPaymentMethod['labels']),
        colors: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6'],
        plotOptions: { pie: { donut: { size: '65%', labels: { show: true, name: { fontSize: '14px', fontWeight: 600 }, value: { fontSize: '16px', fontWeight: 700, formatter: formatCurrencyFull }, total: { show: true, label: 'Total', fontSize: '13px', formatter: function(w) { return formatCurrencyFull(w.globals.seriesTotals.reduce((a, b) => a + b, 0)); } } } }, expandOnClick: true }, },
        tooltip: { y: { formatter: formatCurrencyFull } },
        legend: { position: 'bottom', fontSize: '12px', fontWeight: 500, markers: { radius: 3 } },
        dataLabels: { enabled: false },
        stroke: { width: 2, colors: ['#fff'] },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- REVENUE vs COGS vs EXPENSES --}}
    {{-- ============================================================ --}}
    @if (!empty($revenueVsCogs['labels']))
    new ApexCharts(document.querySelector('#chart-revenue-cogs'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'bar', height: 340, stacked: false },
        series: [
            { name: 'Revenue', data: @json($revenueVsCogs['revenue']) },
            { name: 'COGS', data: @json($revenueVsCogs['cogs']) },
            { name: 'Expenses', data: @json($revenueVsCogs['expenses']) },
        ],
        xaxis: { categories: @json($revenueVsCogs['labels']), labels: { style: { fontSize: '11px', colors: '#94a3b8' } } },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%', dataLabels: { position: 'top' } } },
        dataLabels: { enabled: false },
        colors: ['#3b82f6', '#ef4444', '#f59e0b'],
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', fontWeight: 600, markers: { radius: 3 } },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- PROFIT MARGIN GAUGE --}}
    {{-- ============================================================ --}}
    @if (!empty($profitMarginGauge) && $profitMarginGauge['revenue'] > 0)
    new ApexCharts(document.querySelector('#chart-profit-margin'), {
        ...noToolbarOpts,
        chart: { ...noToolbarOpts.chart, type: 'radialBar', height: 280 },
        series: [@json($profitMarginGauge['margin'])],
        plotOptions: {
            radialBar: {
                startAngle: -135,
                endAngle: 135,
                hollow: { size: '65%', margin: 0 },
                track: { background: '#f1f5f9', strokeWidth: '100%', dropShadow: { enabled: true, top: 2, left: 0, blur: 4, opacity: 0.1 } },
                dataLabels: {
                    name: { show: true, fontSize: '14px', fontWeight: 600, color: '#64748b', offsetY: -10 },
                    value: { show: true, fontSize: '32px', fontWeight: 800, color: '#1e293b', offsetY: 5, formatter: function(val) { return val + '%'; } },
                }
            }
        },
        fill: { type: 'gradient', gradient: { shade: 'dark', type: 'horizontal', shadeIntensity: 0.5, gradientToColors: ['#10b981'], stops: [0, 100] } },
        colors: ['#3b82f6'],
        labels: ['Profit Margin'],
        stroke: { lineCap: 'round' },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- DAILY SALES TREND --}}
    {{-- ============================================================ --}}
    @if (!empty($dailySalesTrend['labels']))
    new ApexCharts(document.querySelector('#chart-daily-sales'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'area', height: 340, zoom: { enabled: true, type: 'x', autoScaleYaxis: true }, selection: { enabled: true } },
        series: [{ name: 'Daily Sales', data: @json($dailySalesTrend['sales']) }],
        xaxis: { categories: @json($dailySalesTrend['labels']), labels: { style: { fontSize: '10px', colors: '#94a3b8' }, rotate: -45, rotateAlways: false, hideOverlappingLabels: true }, tickAmount: 10 },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.05, stops: [0, 90, 100] } },
        dataLabels: { enabled: false },
        colors: ['#8b5cf6'],
        markers: { size: 0, hover: { size: 5, sizeOffset: 3 } },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- CASH vs CREDIT TREND --}}
    {{-- ============================================================ --}}
    @if (!empty($cashVsCreditTrend['labels']))
    new ApexCharts(document.querySelector('#chart-cash-credit-trend'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'area', height: 340, stacked: true },
        series: [
            { name: 'Cash', data: @json($cashVsCreditTrend['cash']) },
            { name: 'Credit', data: @json($cashVsCreditTrend['credit']) },
            { name: 'Cheque', data: @json($cashVsCreditTrend['cheque']) },
            { name: 'Bank Transfer', data: @json($cashVsCreditTrend['bank']) },
        ],
        xaxis: { categories: @json($cashVsCreditTrend['labels']), labels: { style: { fontSize: '11px', colors: '#94a3b8' } } },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        stroke: { curve: 'smooth', width: 1.5 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.6, opacityTo: 0.1, stops: [0, 90, 100] } },
        dataLabels: { enabled: false },
        colors: ['#10b981', '#ef4444', '#f59e0b', '#3b82f6'],
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', fontWeight: 600, markers: { radius: 3 } },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- SALES BY DAY OF WEEK --}}
    {{-- ============================================================ --}}
    @if (!empty($salesByDayOfWeek['labels']) && array_sum($salesByDayOfWeek['values']) > 0)
    new ApexCharts(document.querySelector('#chart-day-of-week'), {
        ...noToolbarOpts,
        chart: { ...noToolbarOpts.chart, type: 'radar', height: 360 },
        series: [{ name: 'Sales', data: @json($salesByDayOfWeek['values']) }],
        xaxis: { categories: @json($salesByDayOfWeek['labels']) },
        yaxis: { labels: { formatter: formatCurrency } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        stroke: { width: 2.5 },
        fill: { opacity: 0.25 },
        markers: { size: 5, strokeWidth: 2, strokeColors: '#fff', hover: { size: 8 } },
        colors: ['#6366f1'],
        plotOptions: { radar: { size: 130, polygons: { strokeColors: '#e2e8f0', connectorColors: '#e2e8f0', fill: { colors: ['#f8fafc', '#ffffff'] } } } },
        dataLabels: { enabled: true, background: { enabled: true, borderRadius: 4, padding: 4 }, formatter: function(val) { return formatCurrency(val); }, style: { fontSize: '10px' } },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- CUSTOMER CHANNEL DISTRIBUTION --}}
    {{-- ============================================================ --}}
    @if (!empty($customerChannelDistribution['labels']))
    new ApexCharts(document.querySelector('#chart-customer-channels'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'bar', height: 360 },
        series: [
            { name: 'Customers', data: @json($customerChannelDistribution['counts']) },
            { name: 'Credit Used', data: @json($customerChannelDistribution['credit']) },
        ],
        xaxis: { categories: @json($customerChannelDistribution['labels']), labels: { style: { fontSize: '10px', colors: '#94a3b8' }, rotate: -35, rotateAlways: false } },
        yaxis: [
            { title: { text: 'Customers', style: { fontSize: '11px', color: '#94a3b8' } }, labels: { style: { fontSize: '11px', colors: '#94a3b8' } } },
            { opposite: true, title: { text: 'Credit Used', style: { fontSize: '11px', color: '#94a3b8' } }, labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } }
        ],
        tooltip: { shared: true, intersect: false, y: { formatter: function(val, opts) { return opts.seriesIndex === 1 ? formatCurrencyFull(val) : val; } } },
        plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        colors: ['#06b6d4', '#f97316'],
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', fontWeight: 600, markers: { radius: 3 } },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- PURCHASES vs PAYMENTS --}}
    {{-- ============================================================ --}}
    @if (!empty($purchasesVsPayments['labels']))
    new ApexCharts(document.querySelector('#chart-purchases-payments'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'line', height: 340 },
        series: [
            { name: 'Purchases (GRN)', data: @json($purchasesVsPayments['purchases']) },
            { name: 'Payments', data: @json($purchasesVsPayments['payments']) },
        ],
        xaxis: { categories: @json($purchasesVsPayments['labels']), labels: { style: { fontSize: '11px', colors: '#94a3b8' } } },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        stroke: { curve: 'smooth', width: 3 },
        markers: { size: 6, strokeWidth: 2, strokeColors: '#fff', hover: { size: 9 } },
        colors: ['#ef4444', '#10b981'],
        fill: { type: 'solid', opacity: 1 },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', fontWeight: 600, markers: { radius: 3 } },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- GRN vs GOODS ISSUE --}}
    {{-- ============================================================ --}}
    @if (!empty($grnVsGoodsIssueTrend['labels']))
    new ApexCharts(document.querySelector('#chart-grn-gi'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'bar', height: 340 },
        series: [
            { name: 'Goods Received', data: @json($grnVsGoodsIssueTrend['grn']) },
            { name: 'Goods Issued', data: @json($grnVsGoodsIssueTrend['issues']) },
        ],
        xaxis: { categories: @json($grnVsGoodsIssueTrend['labels']), labels: { style: { fontSize: '11px', colors: '#94a3b8' } } },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
        dataLabels: { enabled: false },
        colors: ['#3b82f6', '#f59e0b'],
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', fontWeight: 600, markers: { radius: 3 } },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- TOP PRODUCTS BY SALES --}}
    {{-- ============================================================ --}}
    @if (!empty($topProductsBySales['labels']))
    new ApexCharts(document.querySelector('#chart-top-products-sales'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'bar', height: 360 },
        series: [{ name: 'Sales', data: @json($topProductsBySales['values']) }],
        xaxis: { categories: @json($topProductsBySales['labels']), labels: { style: { fontSize: '11px', colors: '#94a3b8' }, trim: true, maxWidth: 120 } },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '60%', distributed: true, dataLabels: { position: 'bottom' } } },
        dataLabels: { enabled: true, formatter: formatCurrency, textAnchor: 'start', offsetX: 5, style: { fontSize: '10px', colors: ['#475569'] } },
        colors: ['#ec4899', '#f43f5e', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6'],
        legend: { show: false },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- TOP SALESPERSON --}}
    {{-- ============================================================ --}}
    @if (!empty($topSalespersonBySales['labels']))
    new ApexCharts(document.querySelector('#chart-top-salesperson'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'bar', height: 360 },
        series: [{ name: 'Sales', data: @json($topSalespersonBySales['values']) }],
        xaxis: { categories: @json($topSalespersonBySales['labels']), labels: { style: { fontSize: '11px', colors: '#94a3b8' }, trim: true, maxWidth: 120 } },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '60%', distributed: true } },
        dataLabels: { enabled: true, formatter: formatCurrency, textAnchor: 'start', offsetX: 5, style: { fontSize: '10px', colors: ['#475569'] } },
        colors: ['#6366f1', '#818cf8', '#a78bfa', '#c4b5fd', '#3b82f6', '#60a5fa', '#93c5fd', '#06b6d4'],
        legend: { show: false },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- WAREHOUSE STOCK DISTRIBUTION --}}
    {{-- ============================================================ --}}
    @if (!empty($warehouseStockDistribution['labels']))
    new ApexCharts(document.querySelector('#chart-warehouse-stock'), {
        ...noToolbarOpts,
        chart: { ...noToolbarOpts.chart, type: 'treemap', height: 360 },
        series: [{ data: @json($warehouseStockDistribution['treemap']) }],
        plotOptions: { treemap: { enableShades: true, shadeIntensity: 0.3, distributed: true, colorScale: { ranges: [] } } },
        colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#f97316', '#14b8a6', '#6366f1'],
        tooltip: { y: { formatter: formatCurrencyFull } },
        dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 700 }, formatter: function(text, op) { return [text, formatCurrency(op.value)]; }, offsetY: -4 },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- STOCK MOVEMENT BREAKDOWN --}}
    {{-- ============================================================ --}}
    @if (!empty($stockMovementBreakdown['labels']))
    new ApexCharts(document.querySelector('#chart-stock-movement'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'bar', height: 360, stacked: true },
        series: [
            { name: 'Inward', data: @json($stockMovementBreakdown['inward']) },
            { name: 'Outward', data: @json($stockMovementBreakdown['outward']) },
        ],
        xaxis: { categories: @json($stockMovementBreakdown['labels']), labels: { style: { fontSize: '11px', colors: '#94a3b8' } } },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
        dataLabels: { enabled: false },
        colors: ['#10b981', '#ef4444'],
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', fontWeight: 600, markers: { radius: 3 } },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- TOP PRODUCTS BY STOCK VALUE --}}
    {{-- ============================================================ --}}
    @if (!empty($topProductsByStockValue['labels']))
    new ApexCharts(document.querySelector('#chart-top-stock'), {
        ...defaultOptions,
        chart: { ...defaultOptions.chart, type: 'bar', height: 360 },
        series: [{ name: 'Stock Value', data: @json($topProductsByStockValue['values']) }],
        xaxis: { categories: @json($topProductsByStockValue['labels']), labels: { style: { fontSize: '11px', colors: '#94a3b8' }, trim: true, maxWidth: 120 } },
        yaxis: { labels: { formatter: formatCurrency, style: { fontSize: '11px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: formatCurrencyFull } },
        plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '60%', distributed: true } },
        dataLabels: { enabled: true, formatter: formatCurrency, textAnchor: 'start', offsetX: 5, style: { fontSize: '10px', colors: ['#475569'] } },
        colors: ['#f97316', '#fb923c', '#fdba74', '#fed7aa', '#ea580c', '#c2410c', '#9a3412', '#7c2d12', '#f59e0b', '#d97706'],
        legend: { show: false },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- SETTLEMENT STATUS --}}
    {{-- ============================================================ --}}
    @if (!empty($settlementStatusDistribution['labels']) && array_sum($settlementStatusDistribution['values']) > 0)
    new ApexCharts(document.querySelector('#chart-settlement-status'), {
        ...noToolbarOpts,
        chart: { ...noToolbarOpts.chart, type: 'donut', height: 250 },
        series: @json($settlementStatusDistribution['values']),
        labels: @json($settlementStatusDistribution['labels']),
        colors: ['#94a3b8', '#10b981', '#ef4444'],
        legend: { position: 'bottom', fontSize: '12px', fontWeight: 500, markers: { radius: 3 } },
        plotOptions: { pie: { donut: { size: '55%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px', fontWeight: 600 } } }, expandOnClick: true } },
        dataLabels: { enabled: true, formatter: function(val) { return Math.round(val) + '%'; }, style: { fontSize: '11px', fontWeight: 600 } },
        stroke: { width: 2, colors: ['#fff'] },
    }).render();
    @endif

    {{-- ============================================================ --}}
    {{-- JOURNAL ENTRY STATUS --}}
    {{-- ============================================================ --}}
    @if (!empty($journalEntryStatus['labels']) && array_sum($journalEntryStatus['values']) > 0)
    new ApexCharts(document.querySelector('#chart-journal-status'), {
        ...noToolbarOpts,
        chart: { ...noToolbarOpts.chart, type: 'donut', height: 250 },
        series: @json($journalEntryStatus['values']),
        labels: @json($journalEntryStatus['labels']),
        colors: ['#f59e0b', '#10b981', '#ef4444'],
        legend: { position: 'bottom', fontSize: '12px', fontWeight: 500, markers: { radius: 3 } },
        plotOptions: { pie: { donut: { size: '55%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px', fontWeight: 600 } } }, expandOnClick: true } },
        dataLabels: { enabled: true, formatter: function(val) { return Math.round(val) + '%'; }, style: { fontSize: '11px', fontWeight: 600 } },
        stroke: { width: 2, colors: ['#fff'] },
    }).render();
    @endif
});
</script>
@endpush
