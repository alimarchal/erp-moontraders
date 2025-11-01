<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Settings
        </h2>

        <div class="flex justify-center items-center float-right">
            <a href="{{ route('dashboard') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <!-- Arrow Left Icon SVG -->
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
            <div class="grid grid-cols-12 gap-6">

                <!-- Account Types Module Card -->
                <a href="{{ route('account-types.index') }}"
                    class="transform hover:scale-110 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 intro-y bg-white block">
                    <div class="p-5 flex justify-between">
                        <div>
                            <div class="text-3xl font-bold leading-8">{{ \App\Models\AccountType::count() }}</div>
                            <div class="mt-1 text-base font-extrabold text-black">Account Types</div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="h-16 w-16 text-blue-600">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                        </svg>
                    </div>
                </a>

                <!-- Chart of Accounts Module Card -->
                <a href="{{ route('chart-of-accounts.index') }}"
                    class="transform hover:scale-110 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 intro-y bg-white block">
                    <div class="p-5 flex justify-between">
                        <div>
                            <div class="text-3xl font-bold leading-8">{{ \App\Models\ChartOfAccount::count() }}</div>
                            <div class="mt-1 text-base font-extrabold text-black">Chart of Accounts</div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="h-16 w-16 text-green-600">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </a>

                <!-- Cost Centers Module Card -->
                <a href="{{ route('dashboard') }}"
                    class="transform hover:scale-110 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 intro-y bg-white block">
                    <div class="p-5 flex justify-between">
                        <div>
                            <div class="text-3xl font-bold leading-8">{{ \App\Models\CostCenter::count() }}</div>
                            <div class="mt-1 text-base font-extrabold text-black">Cost Centers</div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="h-16 w-16 text-purple-600">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                        </svg>
                    </div>
                </a>

                <!-- Currencies Module Card -->
                <a href="{{ route('currencies.index') }}"
                    class="transform hover:scale-110 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 intro-y bg-white block">
                    <div class="p-5 flex justify-between">
                        <div>
                            <div class="text-3xl font-bold leading-8">{{ \App\Models\Currency::count() }}</div>
                            <div class="mt-1 text-base font-extrabold text-black">Currencies</div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="h-16 w-16 text-yellow-600">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </a>

                <!-- Companies Module Card -->
                <a href="{{ route('dashboard') }}"
                    class="transform hover:scale-110 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 intro-y bg-white block">
                    <div class="p-5 flex justify-between">
                        <div>
                            <div class="text-3xl font-bold leading-8">{{ \App\Models\Company::count() }}</div>
                            <div class="mt-1 text-base font-extrabold text-black">Companies</div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="h-16 w-16 text-indigo-600">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </div>
                </a>

                <!-- Warehouses Module Card -->
                <a href="{{ route('dashboard') }}"
                    class="transform hover:scale-110 transition duration-300 shadow-xl rounded-lg col-span-12 sm:col-span-6 md:col-span-4 intro-y bg-white block">
                    <div class="p-5 flex justify-between">
                        <div>
                            <div class="text-3xl font-bold leading-8">{{ \App\Models\Warehouse::count() }}</div>
                            <div class="mt-1 text-base font-extrabold text-black">Warehouses</div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="h-16 w-16 text-orange-600">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1m1.5.5l-1.5-.5M6.75 7.364V3h-3v18m3-13.636l10.5-3.819" />
                        </svg>
                    </div>
                </a>

            </div>
        </div>
    </div>
</x-app-layout>
