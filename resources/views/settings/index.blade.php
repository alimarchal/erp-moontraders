<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block text-black">
            Settings
        </h2>

        <div class="flex justify-center items-center float-right">
            <a href="{{ route('companies.edit', 1) }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                <!-- Access & Identity Section -->
                @canany(['user-list', 'role-list', 'permission-list', 'company-list'])
                    <div x-data="{ open: true }" class="bg-white rounded-lg shadow overflow-hidden">
                        <button @click="open = !open" type="button"
                            class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors duration-150">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-8 rounded-full bg-indigo-500"></div>
                                <h3 class="text-lg font-bold text-gray-800">Access & Identity</h3>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <div class="border-t border-gray-100 divide-y divide-gray-100">
                                @can('user-list')
                                    <a href="{{ route('users.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Users</div>
                                                <div class="text-xs text-gray-500">Manage system users & logins</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\User::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('role-list')
                                    <a href="{{ route('roles.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Roles</div>
                                                <div class="text-xs text-gray-500">Permissions & access levels</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \Spatie\Permission\Models\Role::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('permission-list')
                                    <a href="{{ route('permissions.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center text-orange-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Permissions</div>
                                                <div class="text-xs text-gray-500">Fine-grained access control</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \Spatie\Permission\Models\Permission::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('company-list')
                                    <a href="{{ route('companies.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M15 19.1276C15.8329 19.37 16.7138 19.5 17.625 19.5C19.1037 19.5 20.5025 19.1576 21.7464 18.5478C21.7488 18.4905 21.75 18.4329 21.75 18.375C21.75 16.0968 19.9031 14.25 17.625 14.25C16.2069 14.25 14.956 14.9655 14.2136 16.0552M15 19.1276V19.125C15 18.0121 14.7148 16.9658 14.2136 16.0552M15 19.1276C15 19.1632 14.9997 19.1988 14.9991 19.2343C13.1374 20.3552 10.9565 21 8.625 21C6.29353 21 4.11264 20.3552 2.25092 19.2343C2.25031 19.198 2.25 19.1615 2.25 19.125C2.25 15.6042 5.10418 12.75 8.625 12.75C11.0329 12.75 13.129 14.085 14.2136 16.0552M12 6.375C12 8.23896 10.489 9.75 8.625 9.75C6.76104 9.75 5.25 8.23896 5.25 6.375C5.25 4.51104 6.76104 3 8.625 3C10.489 3 12 4.51104 12 6.375ZM20.25 8.625C20.25 10.0747 19.0747 11.25 17.625 11.25C16.1753 11.25 15 10.0747 15 8.625C15 7.17525 16.1753 6 17.625 6C19.0747 6 20.25 7.17525 20.25 8.625Z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Companies</div>
                                                <div class="text-xs text-gray-500">Legal entities & details</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Company::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endcanany

                <!-- Financial Core Section -->
                @canany(['chart-of-account-list', 'currency-list', 'accounting-period-list', 'bank-account-list', 'cost-center-list'])
                    <div x-data="{ open: true }" class="bg-white rounded-lg shadow overflow-hidden">
                        <button @click="open = !open" type="button"
                            class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors duration-150">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-8 rounded-full bg-green-500"></div>
                                <h3 class="text-lg font-bold text-gray-800">Financial Core</h3>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <div class="border-t border-gray-100 divide-y divide-gray-100">
                                @can('chart-of-account-list')
                                    <a href="{{ route('account-types.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.75 6C3.75 4.75736 4.75736 3.75 6 3.75H8.25C9.49264 3.75 10.5 4.75736 10.5 6V8.25C10.5 9.49264 9.49264 10.5 8.25 10.5H6C4.75736 10.5 3.75 9.49264 3.75 8.25V6Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.75 15.75C3.75 14.5074 4.75736 13.5 6 13.5H8.25C9.49264 13.5 10.5 14.5074 10.5 15.75V18C10.5 19.2426 9.49264 20.25 8.25 20.25H6C4.75736 20.25 3.75 19.2426 3.75 18V15.75Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M13.5 6C13.5 4.75736 14.5074 3.75 15.75 3.75H18C19.2426 3.75 20.25 4.75736 20.25 6V8.25C20.25 9.49264 19.2426 10.5 18 10.5H15.75C14.5074 10.5 13.5 9.49264 13.5 8.25V6Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M13.5 15.75C13.5 14.5074 14.5074 13.5 15.75 13.5H18C19.2426 13.5 20.25 14.5074 20.25 15.75V18C20.25 19.2426 19.2426 20.25 18 20.25H15.75C14.5074 20.25 13.5 19.2426 13.5 18V15.75Z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Account Types</div>
                                                <div class="text-xs text-gray-500">Define categories for your ledger</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\AccountType::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('currency-list')
                                    <a href="{{ route('currencies.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-yellow-50 flex items-center justify-center text-yellow-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 6V18M9 15.1818L9.87887 15.841C11.0504 16.7197 12.9498 16.7197 14.1214 15.841C15.2929 14.9623 15.2929 13.5377 14.1214 12.659C13.5355 12.2196 12.7677 12 11.9999 12C11.275 12 10.5502 11.7804 9.99709 11.341C8.891 10.4623 8.891 9.03772 9.99709 11.341C8.891 10.4623 8.891 9.03772 9.9971 8.15904C11.1032 7.28036 12.8965 7.28036 14.0026 8.15904L14.4175 8.48863M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Currencies</div>
                                                <div class="text-xs text-gray-500">Multi-currency setup & rates</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Currency::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('accounting-period-list')
                                    <a href="{{ route('accounting-periods.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M6.75 3V5.25M17.25 3V5.25M3 18.75V7.5C3 6.25736 4.00736 5.25 5.25 5.25H18.75C19.9926 5.25 21 6.25736 21 7.5V18.75M3 18.75C3 19.9926 4.00736 21 5.25 21H18.75C19.9926 21 21 19.9926 21 18.75M3 18.75V11.25C3 10.0074 4.00736 9 5.25 9H18.75C19.9926 9 21 10.0074 21 11.25V18.75" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Fiscal Periods</div>
                                                <div class="text-xs text-gray-500">Manage financial months & years</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\AccountingPeriod::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('chart-of-account-list')
                                    <a href="{{ route('chart-of-accounts.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center text-green-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.75 3V14.25C3.75 15.4926 4.75736 16.5 6 16.5H8.25M3.75 3H2.25M3.75 3H20.25M20.25 3H21.75M20.25 3V14.25C20.25 15.4926 19.2426 16.5 18 16.5H15.75M8.25 16.5H15.75M8.25 16.5L7.25 19.5M15.75 16.5L16.75 19.5M16.75 19.5L17.25 21M16.75 19.5H7.25M7.25 19.5L6.75 21M7.5 12L10.5 9L12.6476 11.1476C13.6542 9.70301 14.9704 8.49023 16.5 7.60539" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Chart of Accounts</div>
                                                <div class="text-xs text-gray-500">Master list of all ledger accounts</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\ChartOfAccount::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('bank-account-list')
                                    <a href="{{ route('bank-accounts.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M2.25 8.25H21.75M2.25 9H21.75M9.75 15.75H14.25M7.5 12H16.5M6.75 18.75H17.25C18.4926 18.75 19.5 17.7426 19.5 16.5V7.5C19.5 6.25736 18.4926 5.25 17.25 5.25H6.75C5.50736 5.25 4.5 6.25736 4.5 7.5V16.5C4.5 17.7426 5.50736 18.75 6.75 18.75Z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Bank Accounts</div>
                                                <div class="text-xs text-gray-500">Company bank accounts & balances</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\BankAccount::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('cost-center-list')
                                    <a href="{{ route('cost-centers.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center text-purple-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M2.25 21H21.75M3.75 3V21M14.25 3V21M20.25 7.5V21M6.75 6.75H7.5M6.75 9.75H7.5M6.75 12.75H7.5M10.5 6.75H11.25M10.5 9.75H11.25M10.5 12.75H11.25M6.75 21V17.625C6.75 17.0037 7.25368 16.5 7.875 16.5H10.125C10.7463 16.5 11.25 17.0037 11.25 17.625V21M3 3H15M14.25 7.5H21M17.25 11.25H17.2575V11.2575H17.25V11.25ZM17.25 14.25H17.2575V14.2575H17.25V14.25ZM17.25 17.25H17.2575V17.2575H17.25V17.25Z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Cost Centers</div>
                                                <div class="text-xs text-gray-500">Departmental cost tracking</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\CostCenter::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endcanany

                <!-- Tax Configuration Section -->
                @can('tax-list')
                    <div x-data="{ open: true }" class="bg-white rounded-lg shadow overflow-hidden">
                        <button @click="open = !open" type="button"
                            class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors duration-150">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-8 rounded-full bg-red-500"></div>
                                <h3 class="text-lg font-bold text-gray-800">Tax Configuration</h3>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <div class="border-t border-gray-100 divide-y divide-gray-100">
                                <a href="{{ route('tax-codes.index') }}"
                                    class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex-shrink-0 w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center text-red-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15.75 15.75V18M15.75 18V20.25M15.75 18H13.5M15.75 18H18M21 12C21 13.1819 20.7672 14.3522 20.3149 15.4442C19.8626 16.5361 19.1997 17.5282 18.364 18.364C17.5282 19.1997 16.5361 19.8626 15.4442 20.3149C14.3522 20.7672 13.1819 21 12 21C10.8181 21 9.64778 20.7672 8.55585 20.3149C7.46392 19.8626 6.47177 19.1997 5.63604 18.364C4.80031 17.5282 4.13738 16.5361 3.68508 15.4442C3.23279 14.3522 3 13.1819 3 12C3 9.61305 3.94821 7.32387 5.63604 5.63604C7.32387 3.94821 9.61305 3 12 3C14.3869 3 16.6761 3.94821 18.364 5.63604C20.0518 7.32387 21 9.61305 21 12ZM9.75 9.75L14.25 14.25M14.25 9.75L9.75 14.25" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900">Tax Codes</div>
                                            <div class="text-xs text-gray-500">Define VAT, Sales Tax, etc.</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ \App\Models\TaxCode::count() }}
                                        </span>
                                        <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </a>

                                <a href="{{ route('tax-rates.index') }}"
                                    class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex-shrink-0 w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center text-rose-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15.75 10.5V6C15.75 5.17157 15.0784 4.5 14.25 4.5H9.75C8.92157 4.5 8.25 5.17157 8.25 6V10.5M3.75 10.5H20.25M4.5 10.5L5.25 19.5C5.25 20.3284 5.92157 21 6.75 21H17.25C18.0784 21 18.75 20.3284 18.75 19.5L19.5 10.5M9.75 14.25H14.25" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900">Tax Rates</div>
                                            <div class="text-xs text-gray-500">Percentage rates for tax codes</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ \App\Models\TaxRate::count() }}
                                        </span>
                                        <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </a>

                                <a href="{{ route('product-tax-mappings.index') }}"
                                    class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex-shrink-0 w-10 h-10 rounded-lg bg-fuchsia-50 flex items-center justify-center text-fuchsia-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M13.5 6H5.25C4.00736 6 3 7.00736 3 8.25V18.75C3 19.9926 4.00736 21 5.25 21H15.75C16.9926 21 18 19.9926 18 18.75V10.5M13.5 6L18 10.5M13.5 6V10.5H18M9 15.75L10.5 17.25L15 12.75" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900">Tax Mappings</div>
                                            <div class="text-xs text-gray-500">Link products to specific taxes</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ \App\Models\ProductTaxMapping::count() }}
                                        </span>
                                        <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </a>

                                <a href="{{ route('tax-transactions.index') }}"
                                    class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="flex-shrink-0 w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center text-violet-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M12 6V18M15 9.75L12 6.75L9 9.75M15 14.25L12 17.25L9 14.25M3 12H21" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900">Tax Transactions</div>
                                            <div class="text-xs text-gray-500">History of tax applications</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ \App\Models\TaxTransaction::count() }}
                                        </span>
                                        <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                @endcan

                <!-- Partners & Stakeholders Section -->
                @canany(['supplier-list', 'customer-list', 'employee-list'])
                    <div x-data="{ open: true }" class="bg-white rounded-lg shadow overflow-hidden">
                        <button @click="open = !open" type="button"
                            class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors duration-150">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-8 rounded-full bg-orange-500"></div>
                                <h3 class="text-lg font-bold text-gray-800">Partners & Stakeholders</h3>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <div class="border-t border-gray-100 divide-y divide-gray-100">
                                @can('supplier-list')
                                    <a href="{{ route('suppliers.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M5.121 17.804A9 9 0 0112 15a9 9 0 016.879 2.804M15 11a3 3 0 10-6 0 3 3 0 006 0zM21 12a9 9 0 10-18 0 9 9 0 0018 0z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Suppliers</div>
                                                <div class="text-xs text-gray-500">Vendor management & profiles</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Supplier::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('customer-list')
                                    <a href="{{ route('customers.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-pink-50 flex items-center justify-center text-pink-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9.813 15.904C7.995 16.748 7.086 17.17 6.543 17.93C6 18.69 6 19.644 6 21M17.25 12.75A2.25 2.25 0 1121.75 12.75A2.25 2.25 0 0117.25 12.75ZM14.25 21V19.875C14.25 18.839 14.25 18.321 14.405 17.891C14.654 17.195 15.195 16.654 15.891 16.405C16.321 16.25 16.839 16.25 17.875 16.25H18.625C19.661 16.25 20.179 16.25 20.609 16.405C21.305 16.654 21.846 17.195 22.095 17.891C22.25 18.321 22.25 18.839 22.25 19.875V21M7.5 6.75A3.75 3.75 0 1115 6.75A3.75 3.75 0 017.5 6.75ZM3 21V18.75C3 17.093 4.343 15.75 6 15.75H9C10.657 15.75 12 17.093 12 18.75V21" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Customers</div>
                                                <div class="text-xs text-gray-500">Client database & account history</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Customer::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('employee-list')
                                    <a href="{{ route('employees.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-lime-50 flex items-center justify-center text-lime-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M15 19.1276C15.8329 19.37 16.7138 19.5 17.625 19.5C19.1037 19.5 20.5025 19.1576 21.7464 18.5478C21.7488 18.4905 21.75 18.4329 21.75 18.375C21.75 16.0968 19.9031 14.25 17.625 14.25C16.2069 14.25 14.956 14.9655 14.2136 16.0552M15 19.1276V19.125C15 18.0121 14.7148 16.9658 14.2136 16.0552M15 19.1276C15 19.1632 14.9997 19.1988 14.9991 19.2343C13.1374 20.3552 10.9565 21 8.625 21C6.29353 21 4.11264 20.3552 2.25092 19.2343C2.25031 19.198 2.25 19.1615 2.25 19.125C2.25 15.6042 5.10418 12.75 8.625 12.75C11.0329 12.75 13.129 14.085 14.2136 16.0552M12 6.375C12 8.23896 10.489 9.75 8.625 9.75C6.76104 9.75 5.25 8.23896 5.25 6.375C5.25 4.51104 6.76104 3 8.625 3C10.489 3 12 4.51104 12 6.375Z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Employees</div>
                                                <div class="text-xs text-gray-500">Staff records & payroll info</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Employee::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endcanany

                <!-- Inventory & Products Section -->
                @canany(['product-list', 'category-list', 'uom-list'])
                    <div x-data="{ open: true }" class="bg-white rounded-lg shadow overflow-hidden">
                        <button @click="open = !open" type="button"
                            class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors duration-150">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-8 rounded-full bg-indigo-500"></div>
                                <h3 class="text-lg font-bold text-gray-800">Inventory & Products</h3>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <div class="border-t border-gray-100 divide-y divide-gray-100">
                                @can('product-list')
                                    <a href="{{ route('products.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 3.75L3.75 7.5L12 11.25L20.25 7.5L12 3.75Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.75 7.5V12.75L12 16.5M12 16.5V11.25M12 16.5L20.25 12.75V7.5" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.75 12.75V18L12 21.75L20.25 18V12.75" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Products</div>
                                                <div class="text-xs text-gray-500">Item master & specifications</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Product::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('category-list')
                                    <a href="{{ route('categories.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M6 6h.008v.008H6V6z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Categories</div>
                                                <div class="text-xs text-gray-500">Product classifications</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Category::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('uom-list')
                                    <a href="{{ route('uoms.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center text-purple-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9.75 9.75V5.25C9.75 4.00736 10.7574 3 12 3C13.2426 3 14.25 4.00736 14.25 5.25V9.75M5.25 9.75H18.75M6.75 9.75V18.75C6.75 19.9926 7.75736 21 9 21H15C16.2426 21 17.25 19.9926 17.25 18.75V9.75" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Units</div>
                                                <div class="text-xs text-gray-500">Measurement units (KG, PCS, etc)</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Uom::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endcanany

                <!-- Asset Management Section -->
                @canany(['stock-adjustment-list', 'product-recall-list'])
                    <div x-data="{ open: true }" class="bg-white rounded-lg shadow overflow-hidden">
                        <button @click="open = !open" type="button"
                            class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors duration-150">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-8 rounded-full bg-red-500"></div>
                                <h3 class="text-lg font-bold text-gray-800">Inventory Operations</h3>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <div class="border-t border-gray-100 divide-y divide-gray-100">
                                @can('stock-adjustment-list')
                                    <a href="{{ route('stock-adjustments.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center text-red-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Stock Adjustments</div>
                                                <div class="text-xs text-gray-500">Damage, theft, expiry & count variance</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\StockAdjustment::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('product-recall-list')
                                    <a href="{{ route('product-recalls.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center text-orange-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Product Recalls</div>
                                                <div class="text-xs text-gray-500">Supplier recalls & quality issues</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\ProductRecall::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endcanany

                @canany(['vehicle-list', 'warehouse-list', 'warehouse-type-list'])
                    <div x-data="{ open: true }" class="bg-white rounded-lg shadow overflow-hidden">
                        <button @click="open = !open" type="button"
                            class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors duration-150">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-8 rounded-full bg-amber-500"></div>
                                <h3 class="text-lg font-bold text-gray-800">Asset Management</h3>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <div class="border-t border-gray-100 divide-y divide-gray-100">
                                @can('vehicle-list')
                                    <a href="{{ route('vehicles.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M6.857 18.401a3 3 0 114.286-4.201m-4.286 4.201L4 21m2.857-2.599c.02.023.04.046.061.068m-.061-.068C4.476 16.478 3 13.609 3 10.5 3 6.358 6.358 3 10.5 3s7.5 3.358 7.5 7.5c0 3.109-1.476 5.978-3.918 7.901m-2.439-1.201a3 3 0 104.286-4.201m-4.286 4.201L12 21m2.929-2.599c-.02.023-.04.046-.061.068" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Vehicles</div>
                                                <div class="text-xs text-gray-500">Fleet tracking & details</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Vehicle::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('warehouse-list')
                                    <a href="{{ route('warehouses.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center text-orange-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M13.5 21V13.5C13.5 13.0858 13.8358 12.75 14.25 12.75H17.25C17.6642 12.75 18 13.0858 18 13.5V21M13.5 21H2.36088M13.5 21H18M18 21H21.6391M20.25 21V9.34876M3.75 21V9.349M3.75 9.349C4.89729 10.0121 6.38977 9.85293 7.37132 8.87139C7.41594 8.82677 7.45886 8.78109 7.50008 8.73444C8.04979 9.3572 8.85402 9.74998 9.75 9.74998C10.646 9.74998 11.4503 9.35717 12 8.73435C12.5497 9.35717 13.354 9.74998 14.25 9.74998C15.1459 9.74998 15.9501 9.35725 16.4998 8.73456C16.541 8.78114 16.5838 8.82675 16.6284 8.8713C17.61 9.85293 19.1027 10.0121 20.25 9.34876M3.75 9.349C3.52788 9.22062 3.31871 9.06142 3.12868 8.87139C1.95711 7.69982 1.95711 5.80032 3.12868 4.62875L4.31797 3.43946C4.59927 3.15816 4.9808 3.00012 5.37863 3.00012H18.6212C19.019 3.00012 19.4005 3.15816 19.6818 3.43946L20.871 4.62866C22.0426 5.80023 22.0426 7.69973 20.871 8.8713C20.6811 9.06125 20.472 9.2204 20.25 9.34876M6.75 18H10.5C10.9142 18 11.25 17.6642 11.25 17.25V13.5C11.25 13.0858 10.9142 12.75 10.5 12.75H6.75C6.33579 12.75 6 13.0858 6 13.5V17.25C6 17.6642 6.33579 18 6.75 18Z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Warehouses</div>
                                                <div class="text-xs text-gray-500">Storage locations</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\Warehouse::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan

                                @can('warehouse-type-list')
                                    <a href="{{ route('warehouse-types.index') }}"
                                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors duration-150 group">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="flex-shrink-0 w-10 h-10 rounded-lg bg-cyan-50 flex items-center justify-center text-cyan-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 12H15M9 15H15M21 12C21 13.45 19.54 14.8 17.75 15.73C16.1 16.57 14.04 17 12 17C9.96 17 7.9 16.57 6.25 15.73C4.46 14.8 3 13.45 3 12M21 8C21 9.45 19.54 10.8 17.75 11.73C16.1 12.57 14.04 13 12 13C9.96 13 7.9 12.57 6.25 11.73C4.46 10.8 3 9.45 3 8M21 16C21 17.45 19.54 18.8 17.75 19.73C16.1 20.57 14.04 21 12 21C9.96 21 7.9 20.57 6.25 19.73C4.46 18.8 3 17.45 3 16M21 8C21 6.55 19.54 5.2 17.75 4.27C16.1 3.43 14.04 3 12 3C9.96 3 7.9 3.43 6.25 4.27C4.46 5.2 3 6.55 3 8M21 8V16M3 8V16" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">Warehouse Types</div>
                                                <div class="text-xs text-gray-500">Storage facility classifications</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ \App\Models\WarehouseType::count() }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endcanany

            </div>
        </div>
    </div>
</x-app-layout>