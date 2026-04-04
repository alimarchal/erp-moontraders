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

                <!-- Access & Identity Section — iOS Settings style -->
                @canany(['user-list', 'role-list', 'permission-list', 'company-list'])
                    <div class="rounded-2xl overflow-hidden bg-gray-50 border border-gray-200 shadow-md">
                        <!-- Section Header -->
                        <div class="px-4 pt-4 pb-2 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-indigo-500 flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Access & Identity</h3>
                        </div>

                        <!-- Items grouped card -->
                        <div
                            class="mx-3 mb-3 bg-white rounded-xl overflow-hidden divide-y divide-gray-100 border border-gray-100">
                            @can('user-list')
                                <x-settings-row href="{{ route('users.index') }}" label="Users"
                                    description="Manage system users & logins" :count="\App\Models\User::count()"
                                    icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('role-list')
                                <x-settings-row href="{{ route('roles.index') }}" label="Roles"
                                    description="Permissions & access levels" :count="\Spatie\Permission\Models\Role::count()"
                                    icon-bg="bg-blue-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('permission-list')
                                <x-settings-row href="{{ route('permissions.index') }}" label="Permissions"
                                    description="Fine-grained access control"
                                    :count="\Spatie\Permission\Models\Permission::count()" icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('company-list')
                                <x-settings-row href="{{ route('companies.index') }}" label="Companies"
                                    description="Legal entities & details" :count="\App\Models\Company::count()"
                                    icon-bg="bg-violet-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('bank-account-list')
                                <x-settings-row href="{{ route('bank-accounts.index') }}" label="Bank Accounts"
                                    description="Company bank accounts & balances" :count="\App\Models\BankAccount::count()"
                                    icon-bg="bg-teal-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                <!-- Financial Core Section — iOS Settings style -->
                @canany(['account-type-list', 'chart-of-account-list', 'currency-list', 'accounting-period-list', 'investment-opening-balance-list'])
                    <div class="rounded-2xl overflow-hidden bg-gray-50 border border-gray-200 shadow-md">
                        <!-- Section Header -->
                        <div class="px-4 pt-4 pb-2 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-green-500 flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                            </div>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Financial Core</h3>
                        </div>

                        <!-- Items grouped card -->
                        <div
                            class="mx-3 mb-3 bg-white rounded-xl overflow-hidden divide-y divide-gray-100 border border-gray-100">
                            @can('account-type-list')
                                <x-settings-row href="{{ route('account-types.index') }}" label="Account Types"
                                    description="Define categories for your ledger" :count="\App\Models\AccountType::count()"
                                    icon-bg="bg-blue-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
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
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('currency-list')
                                <x-settings-row href="{{ route('currencies.index') }}" label="Currencies"
                                    description="Multi-currency setup & rates" :count="\App\Models\Currency::count()"
                                    icon-bg="bg-yellow-400">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6V18M9 15.1818L9.87887 15.841C11.0504 16.7197 12.9498 16.7197 14.1214 15.841C15.2929 14.9623 15.2929 13.5377 14.1214 12.659C13.5355 12.2196 12.7677 12 11.9999 12C11.275 12 10.5502 11.7804 9.99709 11.341C8.891 10.4623 8.891 9.03772 9.9971 8.15904C11.1032 7.28036 12.8965 7.28036 14.0026 8.15904L14.4175 8.48863M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('accounting-period-list')
                                <x-settings-row href="{{ route('accounting-periods.index') }}" label="Fiscal Periods"
                                    description="Manage financial months & years" :count="\App\Models\AccountingPeriod::count()"
                                    icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6.75 3V5.25M17.25 3V5.25M3 18.75V7.5C3 6.25736 4.00736 5.25 5.25 5.25H18.75C19.9926 5.25 21 6.25736 21 7.5V18.75M3 18.75C3 19.9926 4.00736 21 5.25 21H18.75C19.9926 21 21 19.9926 21 18.75M3 18.75V11.25C3 10.0074 4.00736 9 5.25 9H18.75C19.9926 9 21 10.0074 21 11.25V18.75" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('investment-opening-balance-list')
                                <x-settings-row href="{{ route('investment-opening-balances.index') }}"
                                    label="Investment Opening Balances" description="Supplier-wise opening investment amounts"
                                    :count="\App\Models\InvestmentOpeningBalance::count()" icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('chart-of-account-list')
                                <x-settings-row href="{{ route('chart-of-accounts.index') }}" label="Chart of Accounts"
                                    description="Master list of all ledger accounts"
                                    :count="\App\Models\ChartOfAccount::count()" icon-bg="bg-green-600">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 3V14.25C3.75 15.4926 4.75736 16.5 6 16.5H8.25M3.75 3H2.25M3.75 3H20.25M20.25 3H21.75M20.25 3V14.25C20.25 15.4926 19.2426 16.5 18 16.5H15.75M8.25 16.5H15.75M8.25 16.5L7.25 19.5M15.75 16.5L16.75 19.5M16.75 19.5L17.25 21M16.75 19.5H7.25M7.25 19.5L6.75 21M7.5 12L10.5 9L12.6476 11.1476C13.6542 9.70301 14.9704 8.49023 16.5 7.60539" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                        </div>
                    </div>
                @endcanany

                <!-- Tax Configuration Section — iOS Settings style -->
                @canany(['tax-list', 'expense-detail-list'])
                    <div class="rounded-2xl overflow-hidden bg-gray-50 border border-gray-200 shadow-md">
                        <div class="px-4 pt-4 pb-2 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-red-500 flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                </svg>
                            </div>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Tax Configuration</h3>
                        </div>
                        <div
                            class="mx-3 mb-3 bg-white rounded-xl overflow-hidden divide-y divide-gray-100 border border-gray-100">
                            @can('tax-list')
                                <x-settings-row href="{{ route('tax-codes.index') }}" label="Tax Codes"
                                    description="Define VAT, Sales Tax, etc." :count="\App\Models\TaxCode::count()"
                                    icon-bg="bg-red-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>

                                <x-settings-row href="{{ route('tax-rates.index') }}" label="Tax Rates"
                                    description="Percentage rates for tax codes" :count="\App\Models\TaxRate::count()"
                                    icon-bg="bg-rose-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>

                                <x-settings-row href="{{ route('product-tax-mappings.index') }}" label="Tax Mappings"
                                    description="Link products to specific taxes"
                                    :count="\App\Models\ProductTaxMapping::count()" icon-bg="bg-fuchsia-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>

                                <x-settings-row href="{{ route('tax-transactions.index') }}" label="Tax Transactions"
                                    description="History of tax applications" :count="\App\Models\TaxTransaction::count()"
                                    icon-bg="bg-violet-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('expense-detail-list')
                                <x-settings-row href="{{ route('expense-details.index') }}" label="Expense Details"
                                    description="Category-based expense records" :count="\App\Models\ExpenseDetail::count()"
                                    icon-bg="bg-pink-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                <!-- Partners & Stakeholders Section — iOS Settings style -->
                @canany(['supplier-list', 'customer-list', 'employee-list'])
                    <div class="rounded-2xl overflow-hidden bg-gray-50 border border-gray-200 shadow-md">
                        <div class="px-4 pt-4 pb-2 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-orange-500 flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                            </div>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Partners &
                                Stakeholders</h3>
                        </div>
                        <div
                            class="mx-3 mb-3 bg-white rounded-xl overflow-hidden divide-y divide-gray-100 border border-gray-100">
                            @can('supplier-list')
                                <x-settings-row href="{{ route('suppliers.index') }}" label="Suppliers"
                                    description="Vendor management & profiles" :count="\App\Models\Supplier::count()"
                                    icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('customer-list')
                                <x-settings-row href="{{ route('customers.index') }}" label="Customers"
                                    description="Client database & account history" :count="\App\Models\Customer::count()"
                                    icon-bg="bg-pink-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('employee-list')
                                <x-settings-row href="{{ route('employees.index') }}" label="Employees"
                                    description="Staff records & payroll info" :count="\App\Models\Employee::count()"
                                    icon-bg="bg-lime-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                <!-- Inventory & Products Section — iOS Settings style -->
                @canany(['product-list', 'category-list', 'uom-list'])
                    <div class="rounded-2xl overflow-hidden bg-gray-50 border border-gray-200 shadow-md">
                        <div class="px-4 pt-4 pb-2 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-indigo-500 flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                </svg>
                            </div>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Inventory & Products
                            </h3>
                        </div>
                        <div
                            class="mx-3 mb-3 bg-white rounded-xl overflow-hidden divide-y divide-gray-100 border border-gray-100">
                            @can('product-list')
                                <x-settings-row href="{{ route('products.index') }}" label="Products"
                                    description="Item master & specifications" :count="\App\Models\Product::count()"
                                    icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('category-list')
                                <x-settings-row href="{{ route('categories.index') }}" label="Categories"
                                    description="Product classifications" :count="\App\Models\Category::count()"
                                    icon-bg="bg-teal-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('uom-list')
                                <x-settings-row href="{{ route('uoms.index') }}" label="Units of Measure"
                                    description="KG, PCS, Litre, etc." :count="\App\Models\Uom::count()"
                                    icon-bg="bg-purple-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.589-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.589-1.202L5.25 4.971z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                <!-- Inventory Operations Section — iOS Settings style -->
                @canany(['stock-adjustment-list', 'product-recall-list', 'supplier-payment-list', 'warehouse-type-list'])
                    <div class="rounded-2xl overflow-hidden bg-gray-50 border border-gray-200 shadow-md">
                        <div class="px-4 pt-4 pb-2 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-red-600 flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                                </svg>
                            </div>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Inventory Operations
                            </h3>
                        </div>
                        <div
                            class="mx-3 mb-3 bg-white rounded-xl overflow-hidden divide-y divide-gray-100 border border-gray-100">
                            @can('stock-adjustment-list')
                                <x-settings-row href="{{ route('stock-adjustments.index') }}" label="Stock Adjustments"
                                    description="Damage, theft, expiry & variance" :count="\App\Models\StockAdjustment::count()"
                                    icon-bg="bg-red-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('product-recall-list')
                                <x-settings-row href="{{ route('product-recalls.index') }}" label="Product Recalls"
                                    description="Supplier recalls & quality issues" :count="\App\Models\ProductRecall::count()"
                                    icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('supplier-payment-list')
                                <x-settings-row href="{{ route('supplier-payments.index') }}" label="Supplier Payments"
                                    description="Payment vouchers & records" :count="\App\Models\SupplierPayment::count()"
                                    icon-bg="bg-amber-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('warehouse-type-list')
                                <x-settings-row href="{{ route('warehouse-types.index') }}" label="Warehouse Types"
                                    description="Storage facility classifications" :count="\App\Models\WarehouseType::count()"
                                    icon-bg="bg-cyan-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </div>
                @endcanany

                <!-- Asset Management Section — iOS Settings style -->
                @canany(['vehicle-list', 'warehouse-list'])
                    <div class="rounded-2xl overflow-hidden bg-gray-50 border border-gray-200 shadow-md">
                        <div class="px-4 pt-4 pb-2 flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-amber-500 flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                </svg>
                            </div>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500">Asset Management</h3>
                        </div>
                        <div
                            class="mx-3 mb-3 bg-white rounded-xl overflow-hidden divide-y divide-gray-100 border border-gray-100">
                            @can('vehicle-list')
                                <x-settings-row href="{{ route('vehicles.index') }}" label="Vehicles"
                                    description="Fleet tracking & details" :count="\App\Models\Vehicle::count()"
                                    icon-bg="bg-amber-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan

                            @can('warehouse-list')
                                <x-settings-row href="{{ route('warehouses.index') }}" label="Warehouses"
                                    description="Storage locations & facilities" :count="\App\Models\Warehouse::count()"
                                    icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.5 21V13.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
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