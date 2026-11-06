{{--
    Stock adjustment form shared by Create and Edit (draft). Same field names as before, so the
    controller is unchanged. Variables: $adjustment (null on create), $action, $suppliers, $warehouses, $submitLabel.
--}}
@php
    $isEdit = $adjustment !== null;
    $adjustmentTypes = ['damage' => 'Damage', 'theft' => 'Theft', 'count_variance' => 'Count variance', 'expiry' => 'Expiry', 'recall' => 'Recall', 'other' => 'Other'];
    $savedRows = $isEdit ? $adjustment->items->map(fn ($item) => [
        'product_id' => (string) $item->product_id, 'stock_batch_id' => (string) $item->stock_batch_id, 'uom_id' => (string) $item->uom_id,
        'system_quantity' => (float) $item->system_quantity, 'actual_quantity' => (float) $item->actual_quantity, 'unit_cost' => (float) $item->unit_cost,
        'batch_code' => $item->stockBatch->batch_code ?? '',
    ])->values()->all() : [];
    $rows = old('items', $savedRows);
    $supplierId = old('supplier_id', $isEdit ? ($adjustment->items->first()->product->supplier_id ?? '') : ($suppliers->count() === 1 ? $suppliers->first()->id : ''));
@endphp

<form method="POST" action="{{ $action }}" id="sa-form" x-data="saForm({
        supplierId: @js((string) $supplierId),
        warehouseId: @js((string) old('warehouse_id', $adjustment->warehouse_id ?? '')),
        rows: @js(array_values($rows)),
        confirmTick: @js((bool) old('confirm_revaluation')),
        productsUrl: @js(route('api.suppliers.stock-adjustment-products', ['supplier' => '__S__'])),
        batchesUrl: @js(route('api.products.batches', ['product' => '__P__', 'warehouse' => '__W__'])),
    })" @submit="guardSubmit($event)" @pageshow.window="submitting = false">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <section class="uf-card" aria-label="Adjustment details" style="margin-bottom:16px">
        <header class="uf-card-head"><div>
            <h2 class="uf-card-title"><span class="uf-step">1</span> Adjustment details</h2>
            <p class="uf-card-sub">Saved as a draft first. Posting moves stock, the ledgers, valuation and the journal.</p>
        </div></header>
        <div class="uf-body tk-gen4">
            <div><label class="tk-label" for="adjustment_date">Date <span class="uf-req">*</span></label>
                <input id="adjustment_date" name="adjustment_date" type="date" required max="{{ now()->toDateString() }}" value="{{ old('adjustment_date', isset($adjustment) ? $adjustment->adjustment_date->format('Y-m-d') : now()->toDateString()) }}"></div>
            <div><label class="tk-label" for="sa_supplier">Company / supplier <span class="uf-req">*</span></label>
                <select id="sa_supplier" class="tk-select" name="supplier_id" required>
                    <option value="">Select supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) $supplierId === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                    @endforeach
                </select></div>
            <div><label class="tk-label" for="sa_warehouse">Warehouse <span class="uf-req">*</span></label>
                <select id="sa_warehouse" class="tk-select" name="warehouse_id" required>
                    <option value="">Select warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $adjustment->warehouse_id ?? '') === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                    @endforeach
                </select></div>
            <div><label class="tk-label" for="sa_type">Adjustment type <span class="uf-req">*</span></label>
                <select id="sa_type" class="tk-select" name="adjustment_type" required>
                    @foreach ($adjustmentTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('adjustment_type', $adjustment->adjustment_type ?? 'damage') === $value)>{{ $label }}</option>
                    @endforeach
                </select></div>
            <div class="tk-span2"><label class="tk-label" for="reason">Reason <span class="uf-req">*</span></label>
                <input id="reason" name="reason" type="text" required maxlength="1000" placeholder="e.g. Water damage in rack 4" value="{{ old('reason', $adjustment->reason ?? '') }}"></div>
            <div class="tk-span2"><label class="tk-label" for="notes">Notes</label>
                <input id="notes" name="notes" type="text" value="{{ old('notes', $adjustment->notes ?? '') }}"></div>
        </div>
    </section>

    <section class="uf-card" aria-labelledby="sa-lines">
        <header class="uf-card-head">
            <div>
                <h2 class="uf-card-title" id="sa-lines"><span class="uf-step">2</span> Lines</h2>
                <p class="uf-card-sub">Pick the product and batch, then enter the counted quantity. To correct only the unit cost, keep the counted quantity and type the new unit cost.</p>
            </div>
            <span class="ak-pill" x-text="lineCount() + ' line(s) · value ' + signed(total())"></span>
        </header>
        <div style="overflow-x:auto">
            <table class="tk-grid-table">
                <thead><tr>
                    <th style="width:36px">#</th><th style="min-width:260px">Product</th><th style="min-width:210px">Batch</th>
                    <th style="width:110px">System qty</th><th style="width:120px">Counted qty</th><th style="width:110px">Difference</th>
                    <th style="width:130px">Unit cost</th><th style="width:130px">Value</th><th style="width:40px"></th>
                </tr></thead>
                <template x-for="(row, index) in rows" :key="row.uid">
                    <tbody>
                        <tr>
                            <td class="ak-muted" x-text="index + 1"></td>
                            <td>
                                <input type="hidden" :name="`items[${index}][product_id]`" :value="row.product_id">
                                <input type="hidden" :name="`items[${index}][uom_id]`" :value="row.uom_id">
                                <select class="tk-product-select" x-init="initPicker($el, row)"></select>
                            </td>
                            <td>
                                <select class="tk-mini" :name="`items[${index}][stock_batch_id]`" :disabled="!row.product_id || !warehouseId" @change="pickBatch(row, $event.target.value)">
                                    <option value="" x-text="!warehouseId ? 'Choose a warehouse first' : (row.loading ? 'Loading…' : (row.product_id && row.batches.length === 0 && !row.stock_batch_id ? 'No stock in this warehouse' : 'Select batch'))"></option>
                                    <template x-if="row.stock_batch_id && !row.batches.some((b) => String(b.id) === String(row.stock_batch_id))">
                                        <option :value="row.stock_batch_id" selected x-text="(row.batch_code || ('#' + row.stock_batch_id)) + ' (saved)'"></option>
                                    </template>
                                    <template x-for="batch in row.batches" :key="batch.id">
                                        <option :value="batch.id" :selected="String(batch.id) === String(row.stock_batch_id)" x-text="`${batch.batch_code} (qty ${fmt(batch.quantity, 3)})`"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="tk-cell"><input type="number" step="0.001" readonly tabindex="-1" :name="`items[${index}][system_quantity]`" :value="row.system_quantity" style="background:#f8fafc"></td>
                            <td class="tk-cell"><input type="number" step="0.001" min="0" :disabled="!row.stock_batch_id" :name="`items[${index}][actual_quantity]`" x-model="row.actual_quantity" placeholder="count"></td>
                            <td><span x-show="diff(row) !== null" class="tk-delta" :class="diff(row) > 0 ? 'tk-delta-up' : (diff(row) < 0 ? 'tk-delta-down' : 'tk-delta-flat')" x-text="signed(diff(row), 3)"></span> <small x-show="costOnly(row)" class="ak-muted">cost only</small></td>
                            <td class="tk-cell"><input type="number" step="0.01" min="0" :disabled="!row.stock_batch_id" :name="`items[${index}][unit_cost]`" x-model="row.unit_cost" :style="costChanged(row) ? 'background:#fef2f2;border-color:#dc2626' : ''" title="Purchase cost of this batch. Changing it revalues the batch."></td>
                            <td><span x-show="value(row) !== null" class="ak-strong" x-text="signed(value(row))"></span>
                                <small x-show="costChanged(row)" class="ak-muted" style="display:block">+ revaluation on posting</small></td>
                            <td><button type="button" class="ak-icon ak-icon-danger" x-show="rows.length > 1" @click="removeRow(index)" title="Remove" aria-label="Remove line"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button></td>
                        </tr>
                    </tbody>
                </template>
            </table>
        </div>
        <div class="uf-body" style="padding-top:12px"><button type="button" class="tk-add" @click="addRow()" :disabled="!supplierId">＋ Add another line</button></div>
    </section>

    <section class="uf-card" x-show="costChangedLines().length" x-cloak :style="needTick ? 'border-color:#dc2626' : ''" style="margin-top:16px">
        <div class="uf-body">
            <p style="margin:0 0 8px; font-weight:700; color:#b91c1c">You changed a unit cost</p>
            <template x-for="row in costChangedLines()" :key="row.uid">
                <p class="ak-muted" style="margin:0 0 4px; font-size:13px" x-text="productLabel(row) + ' — ' + fmt(row.batch_cost) + ' → ' + fmt(row.unit_cost)"></p>
            </template>
            <label style="display:flex; gap:10px; align-items:flex-start; margin-top:8px; font-size:14px; cursor:pointer">
                <input type="checkbox" name="confirm_revaluation" value="1" x-model="confirmTick" style="margin-top:3px; width:18px; height:18px">
                <span>I changed a unit cost on purpose: carry the batch at that cost (revaluation). Leave this unticked so a mistyped cost is caught instead of revaluing the batch.</span>
            </label>
            <p x-show="needTick" x-cloak style="margin:8px 0 0; color:#b91c1c; font-size:13px">Tick the box above to confirm the unit cost change.</p>
        </div>
    </section>

    <div class="tk-footer" style="margin-top:16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:14px 18px; background:#fff; border:1px solid var(--ak-border); border-radius:12px">
        <span class="ak-muted" style="font-size:13px" x-text="lineCount() + ' line(s) · nothing moves until you post'"></span>
        <div style="display:flex; gap:10px">
            <a href="{{ $isEdit ? route('stock-adjustments.show', $adjustment) : route('stock-adjustments.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
            <button type="submit" class="ak-btn ak-btn-primary" :disabled="submitting"><span x-show="!submitting">{{ $submitLabel }}</span><span x-show="submitting" x-cloak>Please wait…</span></button>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        window.saForm = function (cfg) {
            let uid = 0;
            const blank = () => ({ uid: ++uid, product_id: '', uom_id: '', stock_batch_id: '', batches: [], loading: false, system_quantity: '', actual_quantity: '', unit_cost: '', batch_cost: undefined, batch_code: '' });
            const pickers = [];

            return {
                supplierId: cfg.supplierId, warehouseId: cfg.warehouseId, products: [], submitting: false, needTick: false, confirmTick: cfg.confirmTick,
                rows: (cfg.rows.length ? cfg.rows : [{}]).map((r) => Object.assign(blank(), r)),

                async init() {
                    if (this.supplierId) { await this.loadProducts(false); }
                    this.rows.forEach((row) => this.loadBatches(row));
                },
                async setSupplier(id) {
                    this.supplierId = id;
                    this.rows = [blank()];
                    await this.loadProducts(true);
                },
                setWarehouse(id) {
                    this.warehouseId = id;
                    this.rows.forEach((row) => { row.stock_batch_id = ''; row.system_quantity = ''; row.actual_quantity = ''; row.unit_cost = ''; row.batch_cost = undefined; this.loadBatches(row); });
                },
                async loadProducts() {
                    this.products = [];
                    if (!this.supplierId) { this.rebuildPickers(); return; }
                    try {
                        const response = await fetch(cfg.productsUrl.replace('__S__', this.supplierId), { headers: { Accept: 'application/json' } });
                        this.products = response.ok ? await response.json() : [];
                    } catch (e) { this.products = []; }
                    this.rebuildPickers();
                },
                // Searchable product drop-down (Select2, same as the other screens).
                initPicker(el, row) {
                    const start = () => { pickers.push({ el, row }); this.buildPicker(el, row); };
                    if (document.readyState === 'complete' && window.jQuery && window.jQuery.fn.select2) { start(); } else { window.addEventListener('load', start, { once: true }); }
                },
                buildPicker(el, row) {
                    if (!el.isConnected) { return; }
                    const $el = window.jQuery(el);
                    if ($el.data('select2')) { $el.select2('destroy'); }
                    el.innerHTML = '';
                    el.appendChild(new Option('', '', false, false));
                    this.products.forEach((p) => el.appendChild(new Option(p.product_name, p.id, false, String(p.id) === String(row.product_id))));
                    $el.select2({ width: '100%', placeholder: 'Select a product…', allowClear: false });
                    $el.off('select2:select').on('select2:select', () => this.pickProduct(row, el.value));
                },
                rebuildPickers() { pickers.forEach((p) => this.buildPicker(p.el, p.row)); },
                pickProduct(row, id) {
                    row.product_id = id; row.stock_batch_id = ''; row.system_quantity = ''; row.actual_quantity = ''; row.unit_cost = ''; row.batch_cost = undefined;
                    const p = this.products.find((x) => String(x.id) === String(id));
                    row.uom_id = p ? String(p.uom_id) : '';
                    this.loadBatches(row);
                },
                async loadBatches(row) {
                    row.batches = [];
                    if (!row.product_id || !this.warehouseId) { return; }
                    row.loading = true;
                    try {
                        const response = await fetch(cfg.batchesUrl.replace('__P__', row.product_id).replace('__W__', this.warehouseId), { headers: { Accept: 'application/json' } });
                        const list = response.ok ? await response.json() : [];
                        row.batches = list.map((b) => ({
                            id: b.id, batch_code: b.batch_code, unit_cost: parseFloat(b.unit_cost) || 0,
                            quantity: (b.current_stock_by_batch || []).reduce((sum, s) => sum + parseFloat(s.quantity_on_hand || 0), 0),
                        }));
                        const saved = row.batches.find((b) => String(b.id) === String(row.stock_batch_id));
                        if (saved) { row.batch_cost = saved.unit_cost; }
                    } finally { row.loading = false; }
                },
                pickBatch(row, id) {
                    const batch = row.batches.find((b) => String(b.id) === String(id));
                    row.stock_batch_id = id;
                    row.system_quantity = batch ? batch.quantity : '';
                    row.unit_cost = batch ? batch.unit_cost : '';
                    row.batch_cost = batch ? batch.unit_cost : undefined;
                    // Counted quantity starts at the system quantity, so changing only the unit cost needs no quantity.
                    row.actual_quantity = batch ? batch.quantity : '';
                },
                addRow() { this.rows.push(blank()); },
                removeRow(index) { this.rows.splice(index, 1); },

                costChanged(row) { return row.unit_cost !== '' && row.batch_cost !== undefined && Math.abs(parseFloat(row.unit_cost) - parseFloat(row.batch_cost)) > 0.01; },
                costChangedLines() { return this.rows.filter((r) => r.stock_batch_id && this.costChanged(r)); },
                diff(row) { return row.actual_quantity === '' || row.system_quantity === '' ? null : parseFloat(row.actual_quantity) - parseFloat(row.system_quantity); },
                costOnly(row) { return this.diff(row) === 0 && this.costChanged(row); },
                value(row) { const d = this.diff(row); return d === null || row.unit_cost === '' ? null : d * parseFloat(row.unit_cost); },
                total() { return this.rows.reduce((sum, row) => sum + (this.value(row) || 0), 0); },
                lineCount() { return this.rows.filter((r) => r.stock_batch_id).length; },
                productLabel(row) { const p = this.products.find((x) => String(x.id) === String(row.product_id)); return p ? p.product_name : ''; },
                fmt(value, digits = 2) { return value === null || value === undefined || value === '' ? '—' : Number(value).toLocaleString(undefined, { minimumFractionDigits: digits === 3 ? 0 : 2, maximumFractionDigits: digits }); },
                signed(value, digits = 2) { return value === null ? '' : (value > 0 ? '+' : '') + this.fmt(value, digits); },
                guardSubmit(event) {
                    if (this.submitting) { event.preventDefault(); return; }
                    if (this.costChangedLines().length && !this.confirmTick) { event.preventDefault(); this.needTick = true; return; }
                    this.submitting = true;
                },
            };
        };

        $(function () {
            const form = document.getElementById('sa-form');
            $('.tk-select').each(function () {
                $(this).select2({ width: '100%', placeholder: $(this).find('option[value=""]').text() || 'Select', minimumResultsForSearch: this.id === 'sa_type' ? Infinity : 0 });
            });
            $('#sa_supplier').on('select2:select', function () { Alpine.$data(form).setSupplier(this.value); });
            $('#sa_warehouse').on('select2:select', function () { Alpine.$data(form).setWarehouse(this.value); });
        });
    </script>
@endpush
