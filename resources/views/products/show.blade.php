{{-- Settings -> Products -> View (/settings/products/{product}). Read only; changes happen on Edit. --}}
@php
    $sell = (float) $product->unit_sell_price;
    $cost = (float) $product->cost_price;
    $margin = $sell > 0 ? round(($sell - $cost) / $sell * 100, 1) : null;
    $uomLabel = fn ($uom) => $uom ? $uom->uom_name.($uom->symbol ? ' ('.$uom->symbol.')' : '') : null;
    $groups = [
        'Product details' => [
            'Product code' => $product->product_code,
            'Product name' => $product->product_name,
            'Category' => $product->category?->name,
            'Supplier' => $product->supplier?->supplier_name,
            'Brand' => $product->brand,
            'Barcode' => $product->barcode,
        ],
        'Units & packaging' => [
            'Base UOM' => $uomLabel($product->uom),
            'Sales UOM' => $uomLabel($product->salesUom) ?? 'Same as base',
            'Conversion factor' => $product->uom_conversion_factor !== null ? rtrim(rtrim((string) $product->uom_conversion_factor, '0'), '.') : '1',
            'Valuation method' => $product->valuation_method,
            'Pack size' => $product->pack_size,
            'Weight' => $product->weight !== null ? $product->weight.' kg' : null,
        ],
        'Pricing & stock' => [
            'Selling price' => number_format($sell, 2),
            'Cost price' => number_format($cost, 2),
            'Expiry price' => number_format((float) $product->expiry_price, 2),
            'Reorder level' => number_format((float) $product->reorder_level, 2),
            'Powder product' => $product->is_powder ? 'Yes' : 'No',
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('products.index') }}">Products</a><span aria-hidden="true">›</span><span>{{ $product->product_code }}</span>
                </nav>
                <h1 class="ak-title">{{ $product->product_name }}</h1>
                <p class="ak-sub">
                    {{ $product->product_code }}@if ($product->category) · {{ $product->category->name }}@endif
                    &middot;
                    @if ($product->is_active)
                        <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                    @else
                        <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Inactive</span>
                    @endif
                    @if ($product->is_powder) <span class="ak-pill">Powder</span> @endif
                </p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('products.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @can('product-edit')
                    <a href="{{ route('products.edit', $product) }}" class="ak-btn ak-btn-primary">Edit product</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />

        <section class="ak-kpis" aria-label="Price summary" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
            <div class="ak-kpi"><span class="ak-kpi-body">
                <span class="ak-kpi-label">Selling price</span>
                <span class="ak-kpi-value">{{ number_format($sell, 2) }}</span>
                <span class="ak-kpi-hint">per {{ $product->salesUom?->uom_name ?? $product->uom?->uom_name ?? 'unit' }}</span>
            </span></div>
            <div class="ak-kpi"><span class="ak-kpi-body">
                <span class="ak-kpi-label">Cost price</span>
                <span class="ak-kpi-value">{{ number_format($cost, 2) }}</span>
                <span class="ak-kpi-hint">{{ $product->valuation_method }} valuation</span>
            </span></div>
            <div class="ak-kpi"><span class="ak-kpi-body">
                <span class="ak-kpi-label">Margin</span>
                <span class="ak-kpi-value">{{ $margin !== null ? $margin.'%' : '—' }}</span>
                <span class="ak-kpi-hint">{{ number_format($sell - $cost, 2) }} per unit</span>
            </span></div>
            <div class="ak-kpi"><span class="ak-kpi-body">
                <span class="ak-kpi-label">Reorder level</span>
                <span class="ak-kpi-value">{{ number_format((float) $product->reorder_level, 0) }}</span>
                <span class="ak-kpi-hint">expiry price {{ number_format((float) $product->expiry_price, 2) }}</span>
            </span></div>
        </section>

        <div class="uf-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr))">
            @foreach ($groups as $title => $rows)
                <section class="uf-card">
                    <div class="uf-card-head"><h2 class="uf-card-title">{{ $title }}</h2></div>
                    <dl style="margin:0; padding:8px 20px 16px">
                        @foreach ($rows as $label => $value)
                            <div style="display:flex; justify-content:space-between; gap:16px; padding:9px 0; border-bottom:1px solid var(--ak-line); font-size:14px">
                                <dt class="ak-muted" style="flex:none">{{ $label }}</dt>
                                <dd style="margin:0; text-align:right; word-break:break-word; color:var(--ak-text)">{{ filled($value) ? $value : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>

        @if (filled($product->description))
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Description</h2></div>
                <p style="margin:0; padding:16px 20px; font-size:14px; white-space:pre-line">{{ $product->description }}</p>
            </section>
        @endif
    </div>
</x-app-layout>
