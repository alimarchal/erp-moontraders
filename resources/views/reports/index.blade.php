<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Reports
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Core Reports Section -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Core Reports</h3>
                <div class="grid grid-cols-12 gap-6">
                    <!-- General Ledger Report Card -->
                    <a href="{{ route('reports.general-ledger.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">GL</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">General
                                    Ledger</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-blue-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                            </svg>
                        </div>
                    </a>

                    <!-- Trial Balance Report Card -->
                    <a href="{{ route('reports.trial-balance.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">TB</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Trial
                                    Balance</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-green-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                            </svg>
                        </div>
                    </a>

                    <!-- Account Balances Report Card -->
                    <a href="{{ route('reports.account-balances.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">AB</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Account
                                    Balances</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-purple-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                            </svg>
                        </div>
                    </a>



                    <!-- Shop List Report Card -->
                    <a href="{{ route('reports.shop-list.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">SL</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Shop List</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-sky-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                            </svg>
                        </div>
                    </a>

                    <!-- SKU & Rates Report Card -->
                    <a href="{{ route('reports.sku-rates.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">SKU</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">SKU & Rates</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-amber-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                            </svg>
                        </div>
                    </a>

                    <!-- Sale Settlement Report Card -->
                    <a href="{{ route('reports.sales-settlement.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">SSR</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Sale Settlement</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-teal-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Financial Statements Section -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Financial Statements</h3>
                <div class="grid grid-cols-12 gap-6">
                    <!-- Balance Sheet Report Card -->
                    <a href="{{ route('reports.balance-sheet.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-6 lg:col-span-6 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">BS</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Balance
                                    Sheet</div>
                                <div class="text-xs text-gray-500 mt-1">Database View</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-indigo-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" />
                            </svg>
                        </div>
                    </a>

                    <!-- Income Statement Report Card -->
                    <a href="{{ route('reports.income-statement.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-6 lg:col-span-6 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">IS</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Income
                                    Statement</div>
                                <div class="text-xs text-gray-500 mt-1">Database View</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-emerald-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Goods Issue Reports Section (Moved from just being in Sales/Distribution?) - Actually sticking to Sales & Distribution -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Sales & Distribution Reports</h3>
                <div class="grid grid-cols-12 gap-6">
                    <!-- Goods Issue Report Card -->
                    <a href="{{ route('reports.goods-issue.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">GIR</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Goods Issue</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-indigo-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V13.5m-9-3.75h3.375c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125h-3.375m0 0H6.375c-.621 0-1.125-.504-1.125-1.125v-1.5c0-.621.504-1.125 1.125-1.125h3.375m0 0v-2.25" />
                            </svg>
                        </div>
                    </a>

                    <!-- ROI Report Card -->
                    <a href="{{ route('reports.roi.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">ROI</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">ROI Report</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-green-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                            </svg>
                        </div>
                    </a>


                    <!-- FMR vs AMR Comparison Report Card -->
                    <a href="{{ route('reports.fmr-amr-comparison.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">FMR</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">FMR vs AMR</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-rose-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                        </div>
                    </a>


                    <!-- Daily Sales Report Card -->
                    <a href="{{ route('reports.daily-sales.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">📊</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Daily Sales</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-blue-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                            </svg>
                        </div>
                    </a>

                    <!-- Product-Wise Sales Report Card -->
                    <a href="{{ route('reports.daily-sales.product-wise') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">📦</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Product-Wise</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-green-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                    </a>

                    <!-- Salesman-Wise Report Card -->
                    <a href="{{ route('reports.daily-sales.salesman-wise') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">👥</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Salesman-Wise</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-purple-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                        </div>
                    </a>

                    <!-- Van Stock Report Card -->
                    <a href="{{ route('reports.daily-sales.van-stock') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">🚚</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Van Stock</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-orange-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V13.5m-9-3.75h3.375c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125h-3.375m0 0H6.375c-.621 0-1.125-.504-1.125-1.125v-1.5c0-.621.504-1.125 1.125-1.125h3.375m0 0v-2.25" />
                            </svg>
                        </div>
                    </a>

                    <!-- Scheme Discount Report Card -->
                    <a href="{{ route('reports.scheme-discount.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">SD</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Scheme Discount</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-pink-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                            </svg>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Inventory & Stock Reports Section -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Inventory & Stock Reports</h3>
                <div class="grid grid-cols-12 gap-6">
                    <!-- Van Stock Ledger Report Card -->
                    <a href="{{ route('reports.van-stock-ledger.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-4 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">VSL</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Van Stock Ledger</div>
                                <div class="text-xs text-gray-500 mt-1">Stock Movements</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-teal-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V13.5m-9-3.75h3.375c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125h-3.375m0 0H6.375c-.621 0-1.125-.504-1.125-1.125v-1.5c0-.621.504-1.125 1.125-1.125h3.375m0 0v-2.25" />
                            </svg>
                        </div>
                    </a>

                    <!-- Van Stock by Batch Report Card -->
                    <a href="{{ route('reports.van-stock-batch.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-4 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">📦</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Van Stock by Batch</div>
                                <div class="text-xs text-gray-500 mt-1">Batch-level Details</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-indigo-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Accounts Receivable Section -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Accounts Receivable</h3>
                <div class="grid grid-cols-12 gap-6">
                    <!-- Creditors Ledger Report Card -->
                    <a href="{{ route('reports.creditors-ledger.index') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">CL</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Creditors Ledger</div>
                                <div class="text-xs text-gray-500 mt-1">Accounts Receivable</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-amber-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                            </svg>
                        </div>
                    </a>

                    <!-- Customer Credit Sales History -->
                    <a href="{{ route('reports.credit-sales.customer-history') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">CS</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Customer Credit</div>
                                <div class="text-xs text-gray-500 mt-1">Credit Sales by Customer</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-rose-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                    </a>

                    <!-- Salesman Credit Sales History -->
                    <a href="{{ route('reports.credit-sales.salesman-history') }}"
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-3 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">SS</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Salesman Credit</div>
                                <div class="text-xs text-gray-500 mt-1">Credit Sales by Salesman</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-cyan-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>