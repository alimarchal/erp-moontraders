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

    <div class="py-6 bg-[#F2F1F5] min-h-full">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div x-data="{
                    query: '',
                    matches(el) {
                        const q = this.query.trim().toLowerCase();
                        return q === '' || q.split(/\s+/).every(word => el.dataset.search.includes(word));
                    },
                    visibleCount() {
                        return Array.from(this.$root.querySelectorAll('[data-settings-item]')).filter(el => this.matches(el)).length;
                    },
                    sectionVisible(section) {
                        return Array.from(section.querySelectorAll('[data-settings-item]')).some(el => this.matches(el));
                    },
                }"
                x-on:keydown.window="if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) { event.preventDefault(); $refs.search.focus(); }">

                <style>
                    .settings-group > a:not([hidden]) ~ a:not([hidden]) { border-top: 1px solid #f3f4f6; }
                </style>

                {{-- Search --}}
                <div class="mb-6 max-w-xl mx-auto">
                    <label for="settings-search" class="sr-only">Search settings</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                        </svg>
                        <input id="settings-search" x-ref="search" x-model="query" type="search" autocomplete="off"
                            placeholder="Search settings…  (press / to focus)"
                            x-on:keydown.escape="query = ''; $refs.search.blur()"
                            class="w-full rounded-xl border-0 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-900 shadow-md ring-1 ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <p x-show="query.trim() !== ''" x-cloak class="mt-2 px-1 text-xs text-gray-500"
                        x-text="visibleCount() + ' result' + (visibleCount() === 1 ? '' : 's')"></p>
                </div>

                <div class="columns-1 md:columns-2 lg:columns-3 gap-6">
                @canany(['category-list', 'product-list', 'uom-list'])
                    <section x-show="sectionVisible($el)" data-settings-section class="mb-6 break-inside-avoid">
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Product Catalog</p>
                        <div class="settings-group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('product-list')
                                <x-settings-row data-settings-item data-search="products item master and specifications product catalog items, categories and units" x-bind:hidden="! matches($el)" href="{{ route('products.index') }}" label="Products"
                                    description="Item master & specifications" :count="$productCount" icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('category-list')
                                <x-settings-row data-settings-item data-search="categories product classifications product catalog items, categories and units" x-bind:hidden="! matches($el)" href="{{ route('categories.index') }}" label="Categories"
                                    description="Product classifications" :count="\App\Models\Category::count()"
                                    icon-bg="bg-teal-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('uom-list')
                                <x-settings-row data-settings-item data-search="units of measure kg, pcs, litre, etc. product catalog items, categories and units" x-bind:hidden="! matches($el)" href="{{ route('uoms.index') }}" label="Units of Measure"
                                    description="KG, PCS, Litre, etc." :count="\App\Models\Uom::count()"
                                    icon-bg="bg-purple-500">
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
                    </section>
                @endcanany

                @canany(['vehicle-list', 'warehouse-list', 'warehouse-type-list'])
                    <section x-show="sectionVisible($el)" data-settings-section class="mb-6 break-inside-avoid">
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Warehousing &amp; Fleet</p>
                        <div class="settings-group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('warehouse-list')
                                <x-settings-row data-settings-item data-search="warehouses storage locations and facilities warehousing and fleet storage locations and vehicles" x-bind:hidden="! matches($el)" href="{{ route('warehouses.index') }}" label="Warehouses"
                                    description="Storage locations & facilities" :count="\App\Models\Warehouse::count()"
                                    icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.5 21V13.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('warehouse-type-list')
                                <x-settings-row data-settings-item data-search="warehouse types storage facility classifications warehousing and fleet storage locations and vehicles" x-bind:hidden="! matches($el)" href="{{ route('warehouse-types.index') }}" label="Warehouse Types"
                                    description="Storage facility classifications" :count="\App\Models\WarehouseType::count()"
                                    icon-bg="bg-cyan-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('vehicle-list')
                                <x-settings-row data-settings-item data-search="vehicles fleet tracking and details warehousing and fleet storage locations and vehicles" x-bind:hidden="! matches($el)" href="{{ route('vehicles.index') }}" label="Vehicles"
                                    description="Fleet tracking & details" :count="\App\Models\Vehicle::count()"
                                    icon-bg="bg-amber-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </section>
                @endcanany

                @canany(['claim-register-list', 'product-recall-list', 'stock-adjustment-list'])
                    <section x-show="sectionVisible($el)" data-settings-section class="mb-6 break-inside-avoid">
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Stock Control</p>
                        <div class="settings-group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('stock-adjustment-list')
                                <x-settings-row data-settings-item data-search="stock adjustments damage, theft, expiry and variance stock control adjustments, recalls and claims" x-bind:hidden="! matches($el)" href="{{ route('stock-adjustments.index') }}" label="Stock Adjustments"
                                    description="Damage, theft, expiry & variance" :count="\App\Models\StockAdjustment::count()"
                                    icon-bg="bg-red-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('product-recall-list')
                                <x-settings-row data-settings-item data-search="product recalls supplier recalls and quality issues stock control adjustments, recalls and claims" x-bind:hidden="! matches($el)" href="{{ route('product-recalls.index') }}" label="Product Recalls"
                                    description="Supplier recalls & quality issues" :count="\App\Models\ProductRecall::count()"
                                    icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('claim-register-list')
                                <x-settings-row data-settings-item data-search="claim register supplier and damage claims log stock control adjustments, recalls and claims" x-bind:hidden="! matches($el)" href="{{ route('claim-registers.index') }}" label="Claim Register"
                                    description="Supplier & damage claims log" :count="\App\Models\ClaimRegister::count()"
                                    icon-bg="bg-rose-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </section>
                @endcanany

                @canany(['company-list', 'permission-list', 'role-list', 'user-list'])
                    <section x-show="sectionVisible($el)" data-settings-section class="mb-6 break-inside-avoid">
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Organization &amp; Access</p>
                        <div class="settings-group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('company-list')
                                <x-settings-row data-settings-item data-search="companies legal entities and details organization and access companies, users, roles and permissions" x-bind:hidden="! matches($el)" href="{{ route('companies.index') }}" label="Companies"
                                    description="Legal entities & details" :count="\App\Models\Company::count()"
                                    icon-bg="bg-violet-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('user-list')
                                <x-settings-row data-settings-item data-search="users manage system users and logins organization and access companies, users, roles and permissions" x-bind:hidden="! matches($el)" href="{{ route('users.index') }}" label="Users"
                                    description="Manage system users & logins" :count="\App\Models\User::count()"
                                    icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('role-list')
                                <x-settings-row data-settings-item data-search="roles permissions and access levels organization and access companies, users, roles and permissions" x-bind:hidden="! matches($el)" href="{{ route('roles.index') }}" label="Roles"
                                    description="Permissions & access levels" :count="\Spatie\Permission\Models\Role::count()"
                                    icon-bg="bg-blue-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('permission-list')
                                <x-settings-row data-settings-item data-search="permissions fine-grained access control organization and access companies, users, roles and permissions" x-bind:hidden="! matches($el)" href="{{ route('permissions.index') }}" label="Permissions"
                                    description="Fine-grained access control"
                                    :count="\Spatie\Permission\Models\Permission::count()" icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </section>
                @endcanany

                @canany(['category-revenue-list', 'profit-category-list', 'tax-list'])
                    <section x-show="sectionVisible($el)" data-settings-section class="mb-6 break-inside-avoid">
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Tax &amp; Profitability</p>
                        <div class="settings-group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('tax-list')
                                <x-settings-row data-settings-item data-search="tax codes define vat, sales tax, etc. tax and profitability tax rules, expenses and profit categories" x-bind:hidden="! matches($el)" href="{{ route('tax-codes.index') }}" label="Tax Codes"
                                    description="Define VAT, Sales Tax, etc." :count="\App\Models\TaxCode::count()"
                                    icon-bg="bg-red-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('tax-list')
                                <x-settings-row data-settings-item data-search="tax rates percentage rates for tax codes tax and profitability tax rules, expenses and profit categories" x-bind:hidden="! matches($el)" href="{{ route('tax-rates.index') }}" label="Tax Rates"
                                    description="Percentage rates for tax codes" :count="\App\Models\TaxRate::count()"
                                    icon-bg="bg-rose-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('category-revenue-list')
                                <x-settings-row data-settings-item data-search="category revenue supplier-wise revenue category setup tax and profitability tax rules, expenses and profit categories" x-bind:hidden="! matches($el)" href="{{ route('category-revenue.index') }}" label="Category Revenue"
                                    description="Supplier-wise revenue category setup"
                                    :count="\App\Models\RevenueCategory::count()" icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('profit-category-list')
                                <x-settings-row data-settings-item data-search="profit categories supplier-wise profit after taxation category setup tax and profitability tax rules, expenses and profit categories" x-bind:hidden="! matches($el)" href="{{ route('profit-categories.index') }}" label="Profit Categories"
                                    description="Supplier-wise profit after taxation category setup"
                                    :count="\App\Models\ProfitCategory::count()" icon-bg="bg-cyan-600">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </section>
                @endcanany

                @canany(['company-target-list', 'customer-list', 'employee-list', 'opening-customer-balance-list', 'promotional-campaign-list', 'supplier-list', 'supplier-payment-list'])
                    <section x-show="sectionVisible($el)" data-settings-section class="mb-6 break-inside-avoid">
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Business Partners</p>
                        <div class="settings-group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('supplier-list')
                                <x-settings-row data-settings-item data-search="suppliers vendor management and profiles business partners suppliers, customers, staff and targets" x-bind:hidden="! matches($el)" href="{{ route('suppliers.index') }}" label="Suppliers"
                                    description="Vendor management & profiles" :count="\App\Models\Supplier::count()"
                                    icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('supplier-payment-list')
                                <x-settings-row data-settings-item data-search="supplier payments payment vouchers and records business partners suppliers, customers, staff and targets" x-bind:hidden="! matches($el)" href="{{ route('supplier-payments.index') }}" label="Supplier Payments"
                                    description="Payment vouchers & records" :count="\App\Models\SupplierPayment::count()"
                                    icon-bg="bg-amber-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('customer-list')
                                <x-settings-row data-settings-item data-search="customers client database and account history business partners suppliers, customers, staff and targets" x-bind:hidden="! matches($el)" href="{{ route('customers.index') }}" label="Customers"
                                    description="Client database & account history" :count="\App\Models\Customer::count()"
                                    icon-bg="bg-pink-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('opening-customer-balance-list')
                                <x-settings-row data-settings-item data-search="opening balances customer opening balance entries business partners suppliers, customers, staff and targets" x-bind:hidden="! matches($el)" href="{{ route('opening-customer-balances.index') }}" label="Opening Balances"
                                    description="Customer opening balance entries"
                                    :count="\App\Models\CustomerEmployeeAccountTransaction::count()" icon-bg="bg-blue-400">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.589-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.589-1.202L5.25 4.971z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('employee-list')
                                <x-settings-row data-settings-item data-search="employees staff records and payroll info business partners suppliers, customers, staff and targets" x-bind:hidden="! matches($el)" href="{{ route('employees.index') }}" label="Employees"
                                    description="Staff records & payroll info" :count="\App\Models\Employee::count()"
                                    icon-bg="bg-lime-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('company-target-list')
                                <x-settings-row data-settings-item data-search="company targets monthly salesman sales targets per company business partners suppliers, customers, staff and targets" x-bind:hidden="! matches($el)" href="{{ route('company-targets.index') }}" label="Company Targets"
                                    description="Monthly salesman sales targets per company"
                                    :count="\App\Models\CompanyTarget::count()" icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('promotional-campaign-list')
                                <x-settings-row data-settings-item data-search="promotions campaigns and discount schemes business partners suppliers, customers, staff and targets" x-bind:hidden="! matches($el)" href="{{ route('promotional-campaigns.index') }}" label="Promotions"
                                    description="Campaigns & discount schemes" :count="\App\Models\PromotionalCampaign::count()"
                                    icon-bg="bg-amber-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </section>
                @endcanany

                @canany(['account-type-list', 'accounting-period-list', 'bank-account-list', 'chart-of-account-list', 'cost-center-list', 'currency-list', 'investment-opening-balance-list', 'journal-entry-list'])
                    <section x-show="sectionVisible($el)" data-settings-section class="mb-6 break-inside-avoid">
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Accounting Setup</p>
                        <div class="settings-group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('chart-of-account-list')
                                <x-settings-row data-settings-item data-search="chart of accounts master list of all ledger accounts accounting setup ledger structure, periods and banking" x-bind:hidden="! matches($el)" href="{{ route('chart-of-accounts.index') }}" label="Chart of Accounts"
                                    description="Master list of all ledger accounts"
                                    :count="\App\Models\ChartOfAccount::count()" icon-bg="bg-green-600">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 3V14.25C3.75 15.4926 4.75736 16.5 6 16.5H8.25M3.75 3H2.25M3.75 3H20.25M20.25 3H21.75M20.25 3V14.25C20.25 15.4926 19.2426 16.5 18 16.5H15.75M8.25 16.5H15.75M8.25 16.5L7.25 19.5M15.75 16.5L16.75 19.5M16.75 19.5L17.25 21M16.75 19.5H7.25M7.25 19.5L6.75 21M7.5 12L10.5 9L12.6476 11.1476C13.6542 9.70301 14.9704 8.49023 16.5 7.60539" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('account-type-list')
                                <x-settings-row data-settings-item data-search="account types define categories for your ledger accounting setup ledger structure, periods and banking" x-bind:hidden="! matches($el)" href="{{ route('account-types.index') }}" label="Account Types"
                                    description="Define categories for your ledger" :count="\App\Models\AccountType::count()"
                                    icon-bg="bg-blue-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
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
                            @can('accounting-period-list')
                                <x-settings-row data-settings-item data-search="fiscal periods manage financial months and years accounting setup ledger structure, periods and banking" x-bind:hidden="! matches($el)" href="{{ route('accounting-periods.index') }}" label="Fiscal Periods"
                                    description="Manage financial months & years" :count="\App\Models\AccountingPeriod::count()"
                                    icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6.75 3V5.25M17.25 3V5.25M3 18.75V7.5C3 6.25736 4.00736 5.25 5.25 5.25H18.75C19.9926 5.25 21 6.25736 21 7.5V18.75M3 18.75C3 19.9926 4.00736 21 5.25 21H18.75C19.9926 21 21 19.9926 21 18.75M3 18.75V11.25C3 10.0074 4.00736 9 5.25 9H18.75C19.9926 9 21 10.0074 21 11.25V18.75" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('currency-list')
                                <x-settings-row data-settings-item data-search="currencies multi-currency setup and rates accounting setup ledger structure, periods and banking" x-bind:hidden="! matches($el)" href="{{ route('currencies.index') }}" label="Currencies"
                                    description="Multi-currency setup & rates" :count="\App\Models\Currency::count()"
                                    icon-bg="bg-yellow-400">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6V18M9 15.1818L9.87887 15.841C11.0504 16.7197 12.9498 16.7197 14.1214 15.841C15.2929 14.9623 15.2929 13.5377 14.1214 12.659C13.5355 12.2196 12.7677 12 11.9999 12C11.275 12 10.5502 11.7804 9.99709 11.341C8.891 10.4623 8.891 9.03772 9.9971 8.15904C11.1032 7.28036 12.8965 7.28036 14.0026 8.15904L14.4175 8.48863M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('cost-center-list')
                                <x-settings-row data-settings-item data-search="cost centers department and project cost tracking accounting setup ledger structure, periods and banking" x-bind:hidden="! matches($el)" href="{{ route('cost-centers.index') }}" label="Cost Centers"
                                    description="Department & project cost tracking" :count="\App\Models\CostCenter::count()"
                                    icon-bg="bg-fuchsia-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('bank-account-list')
                                <x-settings-row data-settings-item data-search="bank accounts company bank accounts and balances accounting setup ledger structure, periods and banking" x-bind:hidden="! matches($el)" href="{{ route('bank-accounts.index') }}" label="Bank Accounts"
                                    description="Company bank accounts & balances" :count="\App\Models\BankAccount::count()"
                                    icon-bg="bg-teal-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('investment-opening-balance-list')
                                <x-settings-row data-settings-item data-search="investment balances supplier-wise opening investment amounts accounting setup ledger structure, periods and banking" x-bind:hidden="! matches($el)" href="{{ route('investment-opening-balances.index') }}"
                                    label="Investment Balances" description="Supplier-wise opening investment amounts"
                                    :count="\App\Models\InvestmentOpeningBalance::count()" icon-bg="bg-orange-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('journal-entry-list')
                                <x-settings-row data-settings-item data-search="journal entries manual gl postings and adjustments accounting setup ledger structure, periods and banking" x-bind:hidden="! matches($el)" href="{{ route('journal-entries.index') }}" label="Journal Entries"
                                    description="Manual GL postings & adjustments" :count="\App\Models\JournalEntry::count()"
                                    icon-bg="bg-violet-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </section>
                @endcanany


                @canany(['bank-statement-entry-list', 'bank-entry-category-list', 'bank-party-alias-list', 'bank-entry-rule-list', 'bank-cheque-list', 'bank-reconciliation-list', 'bank-receipt-scan-list', 'bank-ledger-account-list'])
                    <section x-show="sectionVisible($el)" data-settings-section class="mb-6 break-inside-avoid">
                        <p class="px-4 mb-1 text-[11px] font-semibold uppercase tracking-widest text-gray-500">Bank Register</p>
                        <div class="settings-group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-[0_20px_60px_-10px_rgba(0,0,0,0.15)] hover:-translate-y-0.5 transition-all duration-200">
                            @can('bank-statement-entry-list')
                                <x-settings-row data-settings-item data-search="bank statement register import statements, tag, approve & post bank register bank statement" x-bind:hidden="! matches($el)" href="{{ route('bank-statement-entries.index') }}" label="Bank Statement Register"
                                    description="Import statements, tag, approve & post" icon-bg="bg-teal-600">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('bank-entry-category-list')
                            <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row data-settings-item data-search="bank entry categories receipt / payment categories bank register bank statement" x-bind:hidden="! matches($el)" href="{{ route('bank-entry-categories.index') }}" label="Bank Entry Categories"
                                    description="Receipt / payment categories" :count="\App\Models\BankEntryCategory::count()" icon-bg="bg-cyan-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('bank-party-alias-list')
                            <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row data-settings-item data-search="party aliases merge different spellings of a party bank register bank statement" x-bind:hidden="! matches($el)" href="{{ route('bank-party-aliases.index') }}" label="Party Aliases"
                                    description="Merge different spellings of a party" :count="\App\Models\BankPartyAlias::count()" icon-bg="bg-sky-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('bank-entry-rule-list')
                            <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row data-settings-item data-search="auto-classify rules tag entries by text in the narration bank register bank statement" x-bind:hidden="! matches($el)" href="{{ route('bank-entry-rules.index') }}" label="Auto-classify Rules"
                                    description="Tag entries by text in the narration" :count="\App\Models\BankEntryRule::count()" icon-bg="bg-indigo-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('bank-cheque-list')
                            <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row data-settings-item data-search="bank cheque register issued & received cheques, auto-cleared bank register bank statement" x-bind:hidden="! matches($el)" href="{{ route('bank-cheques.index') }}" label="Bank Cheque Register"
                                    description="Issued & received cheques, auto-cleared" :count="\App\Models\BankCheque::count()" icon-bg="bg-emerald-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('bank-ledger-account-list')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row data-settings-item data-search="register ledger accounts the register's own coa accounts (6000+), kept apart from live accounts bank register bank statement" x-bind:hidden="! matches($el)" href="{{ route('bank-ledger-accounts.index') }}" label="Register Ledger Accounts"
                                    description="The register's own COA accounts (6000+), kept apart from live accounts" icon-bg="bg-slate-600">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('bank-receipt-scan-list')
                                <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row data-settings-item data-search="receipt scan inbox scan receipts daily, link them to bank entries bank register bank statement" x-bind:hidden="! matches($el)" href="{{ route('bank-receipt-scans.index') }}" label="Receipt Scan Inbox"
                                    description="Scan receipts daily, link them to bank entries" :count="\App\Models\BankReceiptScan::where('status', 'pending')->count()" icon-bg="bg-sky-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                            @can('bank-reconciliation-list')
                            <div class="ml-[58px] h-px bg-gray-100"></div>
                                <x-settings-row data-settings-item data-search="bank reconciliations match statement with the books & lock a period bank register bank statement" x-bind:hidden="! matches($el)" href="{{ route('bank-reconciliations.index') }}" label="Bank Reconciliations"
                                    description="Match statement with the books & lock a period" icon-bg="bg-teal-500">
                                    <x-slot name="icon">
                                        <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                                        </svg>
                                    </x-slot>
                                </x-settings-row>
                            @endcan
                        </div>
                    </section>
                @endcanany

                </div>

                <div x-show="query.trim() !== '' && visibleCount() === 0" x-cloak
                    class="py-16 text-center text-sm text-gray-500" data-settings-empty>
                    No settings match "<span x-text="query"></span>".
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
