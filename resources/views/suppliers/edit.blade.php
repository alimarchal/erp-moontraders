<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Supplier: {{ $supplier->supplier_name }}" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="suppliers.index">
            <x-slot name="actions">
                <a href="{{ route('suppliers.help') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 no-print" title="How suppliers and carton entry work">How it works</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    @include('goods-issues.partials.form-style')

    <div class="ak-page gf-page">
        <p class="gf-lede">Change this company's details. Old GRNs are not affected.</p>
        <x-status-message class="mb-4 mt-4 shadow-md" />
        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="ev-form">
            @csrf
            @method('PUT')
            <section class="uf-card gf-card" aria-labelledby="sf-details">
                <header class="uf-card-head">
                    <div>
                        <h2 class="uf-card-title" id="sf-details"><span class="uf-step">1</span> Supplier details</h2>
                        <p class="uf-card-sub">Name, defaults, GRN entry settings and flags.</p>
                    </div>
                </header>
                <div class="uf-body">
                    @include('suppliers.partials.form-fields', ['currencyOptions' => $currencyOptions, 'accountOptions' => $accountOptions])
                </div>
            </section>
            <div class="ev-form-foot">
                <a href="{{ route('suppliers.index') }}" class="ak-btn ak-btn-outline">Cancel <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:15px">منسوخ کریں</span></a>
                <button type="submit" class="ak-btn ak-btn-primary">Save changes <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:15px">تبدیلیاں محفوظ کریں</span></button>
            </div>
        </form>
    </div>
</x-app-layout>
