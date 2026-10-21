@php
/** @var \App\Models\Product|null $product */
$product = $product ?? null;
$supplierOptions = $supplierOptions ?? collect();
$categoryOptions = $categoryOptions ?? collect();
$uomOptions = $uomOptions ?? collect();
$valuationMethods = $valuationMethods ?? [];
$cancelUrl = $cancelUrl ?? route('products.index');
$submitLabel = $submitLabel ?? 'Save product';

$val = fn (string $field, $default = null) => old($field, optional($product)->{$field} ?? $default);
@endphp

<style>
    .pf-stack { display: flex; flex-direction: column; gap: 20px; }
    .pf-4 { display: grid; gap: 16px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .pf-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .pf-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .pf-wide { grid-column: 1 / -1; }
    .uf-field textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; background: #fff; color: var(--ak-text); font-size: 14px; }
    .uf-field textarea:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .uf-field .pf-hint { margin: 5px 0 0; font-size: 12px; color: var(--ak-muted); }
    .pf-switches { display: grid; gap: 12px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .pf-switches { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .pf-switch { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--ak-border); border-radius: 10px; background: var(--ak-soft); cursor: pointer; }
    .pf-switch input { width: 18px; height: 18px; accent-color: var(--ak-navy); }
    .pf-switch b { display: block; font-size: 14px; color: var(--ak-text); }
    .pf-switch span { font-size: 12.5px; color: var(--ak-muted); }
</style>

@if ($errors->any())
    <div class="ak-alert ak-alert-error" role="alert">
        <b>Please fix {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('problem', $errors->count()) }}:</b>
        {{ implode(' · ', array_slice($errors->all(), 0, 4)) }}{{ $errors->count() > 4 ? ' …' : '' }}
    </div>
@endif

<div class="pf-stack">
    <section class="uf-card">
        <div class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">1</span> Product details</h2>
                <p class="uf-card-sub">Identity, grouping and supplier.</p>
            </div>
        </div>
        <div class="uf-body">
            <div class="uf-fields">
                <div class="uf-field">
                    <label for="product_code">Product code (SKU) <span class="uf-req">*</span></label>
                    <input id="product_code" type="text" name="product_code" maxlength="191" required style="text-transform:uppercase" value="{{ $val('product_code') }}" placeholder="SKU-001">
                    @error('product_code') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="product_name">Product name <span class="uf-req">*</span></label>
                    <input id="product_name" type="text" name="product_name" maxlength="191" required value="{{ $val('product_name') }}" placeholder="Chocolate Chip Cookies">
                    @error('product_name') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field pf-wide">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="2" placeholder="Key product notes for sales, warehouse and finance">{{ $val('description') }}</textarea>
                    @error('description') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="pf-4" style="margin-top:16px">
                <div class="uf-field">
                    <label for="supplier_id">Preferred supplier</label>
                    <select id="supplier_id" name="supplier_id">
                        <option value="">Not linked</option>
                        @foreach ($supplierOptions as $supplier)
                            <option value="{{ $supplier->id }}" @selected((int) $val('supplier_id', 0) === $supplier->id)>{{ $supplier->supplier_name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select category</option>
                        @foreach ($categoryOptions as $category)
                            <option value="{{ $category->id }}" @selected((int) $val('category_id', 0) === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="brand">Brand</label>
                    <input id="brand" type="text" name="brand" maxlength="120" value="{{ $val('brand') }}" placeholder="Brand name">
                    @error('brand') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="barcode">Barcode</label>
                    <input id="barcode" type="text" name="barcode" maxlength="191" value="{{ $val('barcode') }}" placeholder="EAN/UPC">
                    @error('barcode') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </section>

    <section class="uf-card">
        <div class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">2</span> Units &amp; packaging</h2>
                <p class="uf-card-sub">How stock is counted and how it is sold.</p>
            </div>
        </div>
        <div class="uf-body">
            <div class="pf-4">
                <div class="uf-field">
                    <label for="uom_id">Base UOM (inventory) <span class="uf-req">*</span></label>
                    <select id="uom_id" name="uom_id" required>
                        <option value="">Select base UOM</option>
                        @foreach ($uomOptions as $uom)
                            <option value="{{ $uom->id }}" @selected((int) $val('uom_id', $product ? 0 : 24) === $uom->id)>{{ $uom->uom_name }} {{ $uom->symbol ? '('.$uom->symbol.')' : '' }}</option>
                        @endforeach
                    </select>
                    <p class="pf-hint">Unit for inventory tracking (PCS, KG …)</p>
                    @error('uom_id') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="sales_uom_id">Sales UOM (optional)</label>
                    <select id="sales_uom_id" name="sales_uom_id">
                        <option value="">Same as base UOM</option>
                        @foreach ($uomOptions as $uom)
                            <option value="{{ $uom->id }}" @selected((int) $val('sales_uom_id', $product ? 0 : 33) === $uom->id)>{{ $uom->uom_name }} {{ $uom->symbol ? '('.$uom->symbol.')' : '' }}</option>
                        @endforeach
                    </select>
                    <p class="pf-hint">Unit for invoicing (Case, Box …)</p>
                    @error('sales_uom_id') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="uom_conversion_factor">Conversion factor</label>
                    <input id="uom_conversion_factor" type="number" name="uom_conversion_factor" step="0.001" min="1" value="{{ $val('uom_conversion_factor', 1) }}" placeholder="1">
                    <p class="pf-hint">Base units in 1 sales unit (24 PCS = 1 Case → 24)</p>
                    @error('uom_conversion_factor') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="valuation_method">Valuation method <span class="uf-req">*</span></label>
                    <select id="valuation_method" name="valuation_method" required>
                        @foreach ($valuationMethods as $method)
                            <option value="{{ $method }}" @selected($val('valuation_method', 'FIFO') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                    @error('valuation_method') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="pack_size">Pack size</label>
                    <input id="pack_size" type="text" name="pack_size" maxlength="120" value="{{ $val('pack_size') }}" placeholder="e.g. 12 x 100g">
                    @error('pack_size') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="weight">Weight (kg)</label>
                    <input id="weight" type="number" name="weight" step="0.001" min="0" value="{{ $val('weight') }}" placeholder="0.500">
                    @error('weight') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </section>

    <section class="uf-card">
        <div class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">3</span> Pricing &amp; stock</h2>
                <p class="uf-card-sub">A new selling price is applied to batches that still have stock.</p>
            </div>
        </div>
        <div class="uf-body">
            <div class="pf-4">
                <div class="uf-field">
                    <label for="cost_price">Cost price</label>
                    <input id="cost_price" type="number" name="cost_price" step="0.01" min="0" value="{{ $val('cost_price') }}" placeholder="950">
                    @error('cost_price') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="unit_sell_price">Selling price</label>
                    <input id="unit_sell_price" type="number" name="unit_sell_price" step="0.01" min="0" value="{{ $val('unit_sell_price') }}" placeholder="1200">
                    @error('unit_sell_price') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="expiry_price">Expiry price</label>
                    <input id="expiry_price" type="number" name="expiry_price" step="0.01" min="0" value="{{ $val('expiry_price') }}" placeholder="900">
                    @error('expiry_price') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="reorder_level">Reorder level</label>
                    <input id="reorder_level" type="number" name="reorder_level" step="0.01" min="0" value="{{ $val('reorder_level') }}" placeholder="50">
                    @error('reorder_level') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </section>

    <section class="uf-card">
        <div class="uf-card-head">
            <div><h2 class="uf-card-title"><span class="uf-step">4</span> Status</h2></div>
        </div>
        <div class="uf-body">
            <div class="pf-switches">
                <div>
                    <input type="hidden" name="is_active" value="0">
                    <label class="pf-switch" for="is_active">
                        <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', optional($product)->is_active ?? true))>
                        <div><b>Product is active</b><span>Inactive products stay on record but are hidden from new transactions.</span></div>
                    </label>
                </div>
                <div>
                    <input type="hidden" name="is_powder" value="0">
                    <label class="pf-switch" for="is_powder">
                        <input id="is_powder" type="checkbox" name="is_powder" value="1" @checked(old('is_powder', optional($product)->is_powder ?? false))>
                        <div><b>Powder product</b><span>Tick for powder items that follow powder stock rules.</span></div>
                    </label>
                </div>
            </div>
        </div>
    </section>

    <div class="uf-actions">
        <p>Fields marked <span class="uf-req">*</span> are required.</p>
        <div>
            <a href="{{ $cancelUrl }}" class="ak-btn ak-btn-outline">Cancel</a>
            <button type="submit" class="ak-btn ak-btn-primary">{{ $submitLabel }}</button>
        </div>
    </div>
</div>
