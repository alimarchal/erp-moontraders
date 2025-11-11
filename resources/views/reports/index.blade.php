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
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-4 intro-y bg-white block">
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
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-4 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">TB</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Trial
                                    Balance</div>
                                <div class="text-xs text-gray-500 mt-1">Database View</div>
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
                        class="transform hover:scale-105 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 lg:col-span-4 intro-y bg-white block">
                        <div class="p-5 flex justify-between">
                            <div>
                                <div class="text-3xl font-bold leading-8 text-gray-800">AB</div>
                                <div class="mt-1 text-base font-extrabold text-gray-700">Account
                                    Balances</div>
                                <div class="text-xs text-gray-500 mt-1">Database View</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="h-16 w-16 text-purple-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                            </svg>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Financial Statements Section -->
            <div>
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
        </div>
    </div>
</x-app-layout>