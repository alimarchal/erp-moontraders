{{--
    Suppliers -> View: a summary strip (name, group, status, product and GRN counts), the details as
    inset-grouped rows, and the GRN settings, column order and options beside them. Styles: suppliers.partials.apple-style.
--}}
@php
    $currency = $supplier->defaultCurrency;
    $bankAccount = $supplier->defaultBankAccount;
    $details = [
        ['Supplier name', $supplier->supplier_name, false],
        ['Short name', $supplier->short_name ? mb_strtoupper($supplier->short_name) : null, false],
        ['Group', $supplier->supplier_group, false],
        ['Type', $supplier->supplier_type, false],
        ['Country', $supplier->country, false],
        ['Default currency', $currency ? $currency->currency_code.' · '.$currency->currency_name : null, false],
        ['Default bank account', $bankAccount ? $bankAccount->account_code.' · '.$bankAccount->account_name : null, false],
        ['Default price list', $supplier->default_price_list, false],
        ['Tax ID / NTN', $supplier->tax_id, false],
        ['PAN number', $supplier->pan_number, false],
        ['Website', $supplier->website, false],
        ['Print language', $supplier->print_language, false],
        ['Primary address', $supplier->supplier_primary_address, true],
        ['Primary contact', $supplier->supplier_primary_contact, true],
        ['Additional details', $supplier->supplier_details, true],
    ];
    $options = [
        ['FMR allowed', $supplier->is_fmr_allowed],
        ['Also provides transport', $supplier->is_transporter],
        ['Internal supplier', $supplier->is_internal_supplier],
        ['Advance tax income', $supplier->is_advance_tax_income],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Supplier: '.$supplier->supplier_name" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="suppliers.index">
            <x-slot name="actions">
                <a href="{{ route('suppliers.help') }}" class="sp-head-btn" title="How suppliers and carton entry work">How it works</a>
                @can('supplier-edit')
                    <a href="{{ route('suppliers.edit', $supplier) }}" class="sp-head-btn sp-head-primary" title="Edit supplier">Edit</a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    @include('suppliers.partials.apple-style')

    <div class="sp-page">
        <x-status-message />

        <section class="sp-card sp-hero" aria-label="Summary">
            <div>
                <h2 class="sp-hero-name">{{ $supplier->supplier_name }}</h2>
                <p class="sp-hero-meta">
                    @if ($supplier->short_name) <span class="sp-chip">{{ mb_strtoupper($supplier->short_name) }}</span> @endif
                    <span>{{ collect([$supplier->supplier_group, $supplier->supplier_type, $supplier->country])->filter()->implode(' · ') ?: 'No group set' }}</span>
                    <span class="sp-chip {{ $supplier->disabled ? 'sp-chip-grey' : 'sp-chip-green' }}">{{ $supplier->disabled ? 'Disabled' : 'Active' }}</span>
                </p>
            </div>
            <div class="sp-stats">
                <div class="sp-stat"><b>{{ number_format($productCount) }}</b><span>Products</span></div>
                <div class="sp-stat"><b>{{ number_format($grnCount) }}</b><span>GRNs</span></div>
            </div>
        </section>

        <div class="sp-cols-2">
            <section class="sp-card" aria-labelledby="sp-details">
                <header class="sp-card-head"><h2 class="sp-card-title" id="sp-details">Details</h2></header>
                <div class="sp-card-body">
                    <dl class="sp-dl">
                        @foreach ($details as [$label, $text, $wide])
                            <div @class(['sp-dl-wide' => $wide])>
                                <dt>{{ $label }}</dt>
                                <dd @class(['sp-empty' => blank($text)])>{{ filled($text) ? $text : '—' }}</dd>
                            </div>
                        @endforeach
                        <div><dt>Created</dt><dd>{{ $supplier->created_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                        <div><dt>Last updated</dt><dd>{{ $supplier->updated_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                    </dl>
                </div>
            </section>

            <div class="sp-stack">
                <section class="sp-card" aria-labelledby="sp-grn">
                    <header class="sp-card-head"><h2 class="sp-card-title" id="sp-grn">GRN quantity entry</h2></header>
                    <div class="sp-card-body">
                        <ul class="sp-list">
                            <li class="sp-row">
                                <span class="sp-row-text"><span class="sp-row-title">Entry mode</span>
                                    <span class="sp-row-sub">{{ $supplier->usesCartonEntry() ? 'Cartons + loose pieces and a rate per TH. Stock is saved in pieces.' : 'Quantity is entered directly.' }}</span></span>
                                <span class="sp-chip {{ $supplier->usesCartonEntry() ? 'sp-chip-amber' : '' }}">{{ $supplier->usesCartonEntry() ? 'Carton' : 'Pieces' }}</span>
                            </li>
                            <li class="sp-row">
                                <span class="sp-row-title">Sales tax rate</span>
                                <b style="font-variant-numeric:tabular-nums">{{ number_format((float) $supplier->sales_tax, 2) }}%</b>
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="sp-card" aria-labelledby="sp-order">
                    <header class="sp-card-head">
                        <h2 class="sp-card-title" id="sp-order">GRN line columns</h2>
                        @can('supplier-edit')
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="sp-link-btn">Change order</a>
                        @endcan
                    </header>
                    <div class="sp-card-body sp-order">
                        @foreach (collect($supplier->grnLineColumns())->groupBy('group') as $group => $columns)
                            <div>
                                <h3 class="sp-order-title">{{ \App\Models\Supplier::GRN_LINE_COLUMN_GROUPS[$group]['label'] }}</h3>
                                <ol class="sp-mini-ol">
                                    @foreach ($columns as $column)
                                        <li>{{ $column['label'] }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="sp-card" aria-labelledby="sp-options">
                    <header class="sp-card-head"><h2 class="sp-card-title" id="sp-options">Options</h2></header>
                    <div class="sp-card-body">
                        <ul class="sp-list">
                            @foreach ($options as [$label, $isOn])
                                <li class="sp-row">
                                    <span class="sp-row-title">{{ $label }}</span>
                                    <span class="sp-chip {{ $isOn ? 'sp-chip-blue' : 'sp-chip-grey' }}">{{ $isOn ? 'Yes' : 'No' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
