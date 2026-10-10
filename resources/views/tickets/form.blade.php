@php
    use App\Enums\TicketType;

    $isEdit = $ticket !== null;
    $httpMethod = $httpMethod ?? 'POST';
    $input = 'block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
    $label = 'block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1';
    $existingRows = [];

    if ($isEdit && $type !== TicketType::NewSku) {
        $existingRows = $ticket->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'batch_scope' => $item->apply_to_all_batches ? 'all' : 'selected',
            'batch_ids' => $item->batch_ids ?? [],
            'unit_sell_price' => $item->new_unit_sell_price,
            'cost_price' => $item->new_cost_price,
            'expiry_price' => $item->new_expiry_price,
            'reorder_level' => $item->new_reorder_level,
            'new_is_active' => $item->new_is_active === null ? '1' : (string) (int) $item->new_is_active,
            'remarks' => $item->remarks,
        ])->all();
    }

    $adj = old() && old('warehouse_id') !== null ? old() : ($isEdit && $type === TicketType::StockAdjustment ? ($ticket->items->first()->payload ?? []) : []);
    if ($type === TicketType::StockAdjustment) {
        $existingRows = collect($adj['items'] ?? [])->map(fn ($line) => [
            'product_id' => $line['product_id'], 'stock_batch_id' => $line['stock_batch_id'], 'system_quantity' => $line['system_quantity'] ?? '',
            'actual_quantity' => $line['actual_quantity'], 'unit_cost' => $line['unit_cost'], 'uom_id' => $line['uom_id'],
        ])->all();
    }
    $entry = old('data', $isEdit && $type->isSimpleEntry() ? ($ticket->items->first()->payload ?? []) : []);
    $rows = old('items', $existingRows);
    $sku = old('sku', $isEdit && $type === TicketType::NewSku ? ($ticket->items->first()->payload ?? []) : []);
@endphp

<script>
    window.ticketForm = function (config) {
        let uid = 0;
        const blank = () => ({
            uid: ++uid, product_id: '', batch_scope: 'all', batch_ids: [], batches: [], loading: false,
            unit_sell_price: '', cost_price: '', expiry_price: '', reorder_level: '',
            new_is_active: '1', remarks: '',
            stock_batch_id: '', system_quantity: '', actual_quantity: '', unit_cost: '', uom_id: '',
        });
        const fields = [['unit_sell_price', 'Selling price'], ['cost_price', 'Cost price'], ['expiry_price', 'Expiry price'], ['reorder_level', 'Reorder level']];

        return {
            type: config.type,
            products: config.products,
            fields,
            rows: (config.rows.length ? config.rows : [{}]).map((r) => Object.assign(blank(), r)),
            batchUrl: config.batchUrl,
            skuName: config.skuName,
            submitting: false,
            adj: config.adj || {},
            adjBatchUrl: config.adjBatchUrl,

            init() {
                if (this.type === 'stock_adjustment') { this.rows.forEach((row) => this.loadAdjBatches(row)); return; }
                this.rows.forEach((row) => {
                    if (row.product_id && row.batch_scope === 'selected') { this.loadBatches(row); }
                });
            },
            options() {
                if (this.type === 'stock_adjustment') { return this.products.filter((p) => p.active && String(p.supplier_id) === String(this.adj.supplier_id)); }
                return this.type === 'reactivate_sku' ? this.products : this.products.filter((p) => p.active);
            },
            // Stock adjustment lines
            setAdj(key, value) {
                this.adj[key] = value;
                if (key === 'supplier_id') { this.rows = [Object.assign(blank(), {})]; }
                if (key === 'warehouse_id') { this.rows.forEach((row) => { row.stock_batch_id = ''; row.system_quantity = ''; row.unit_cost = ''; this.loadAdjBatches(row); }); }
            },
            async loadAdjBatches(row) {
                row.batches = [];
                if (!row.product_id || !this.adj.warehouse_id) { return; }
                row.loading = true;
                try {
                    const url = this.adjBatchUrl.replace('__P__', row.product_id).replace('__W__', this.adj.warehouse_id);
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    row.batches = response.ok ? await response.json() : [];
                    const saved = row.batches.find((b) => String(b.id) === String(row.stock_batch_id));
                    if (saved && row.batch_cost === undefined) { row.batch_cost = Number(saved.unit_cost); }
                } finally { row.loading = false; }
            },
            pickAdjProduct(row, id) {
                this.pick(row, id);
                row.stock_batch_id = ''; row.system_quantity = ''; row.actual_quantity = ''; row.unit_cost = '';
                const p = this.product(row); row.uom_id = p ? p.uom_id : '';
                this.loadAdjBatches(row);
            },
            pickBatch(row, id) {
                const batch = row.batches.find((b) => String(b.id) === String(id));
                row.stock_batch_id = id;
                row.system_quantity = batch ? Number(batch.quantity) : '';
                row.unit_cost = batch ? Number(batch.unit_cost) : '';
                row.batch_cost = row.unit_cost;
                // Counted quantity starts at the system quantity, so changing only the unit cost needs no quantity.
                row.actual_quantity = batch ? Number(batch.quantity) : '';
            },
            costChanged(row) { return row.unit_cost !== '' && row.batch_cost !== undefined && row.batch_cost !== '' && Math.abs(parseFloat(row.unit_cost) - parseFloat(row.batch_cost)) > 0.01; },
            costOnly(row) { return this.adjDiff(row) === 0 && this.costChanged(row); },
            adjDiff(row) { return row.actual_quantity === '' || row.system_quantity === '' ? null : parseFloat(row.actual_quantity) - parseFloat(row.system_quantity); },
            adjValue(row) {
                const d = this.adjDiff(row);
                if (d === null || row.unit_cost === '') { return null; }
                const revaluation = this.costChanged(row) ? parseFloat(row.system_quantity) * (parseFloat(row.unit_cost) - parseFloat(row.batch_cost)) : 0;
                return d * parseFloat(row.unit_cost) + revaluation;
            },
            adjTotal() { return this.rows.reduce((sum, row) => sum + (this.adjValue(row) || 0), 0); },
            product(row) { return this.products.find((p) => String(p.id) === String(row.product_id)); },
            current(row, field) { const p = this.product(row); return p ? p[field] : null; },
            isFilled(row, field) { return row[field] !== '' && row[field] !== null && row[field] !== undefined; },
            diff(row, field) {
                const p = this.product(row);
                return p && this.isFilled(row, field) ? parseFloat(row[field]) - p[field] : null;
            },
            fmt(value) { return value === null || value === undefined ? '—' : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            signed(value) { return (value >= 0 ? '+' : '') + this.fmt(value); },
            changeCount() {
                if (this.type === 'new_sku') { return 1; }
                if (this.type === 'reactivate_sku' || this.type === 'stock_adjustment') { return this.rows.filter((r) => r.product_id).length; }
                return this.rows.reduce((n, row) => n + fields.filter(([f]) => row.product_id && this.diff(row, f) !== null).length, 0);
            },
            productCount() { return this.rows.filter((r) => r.product_id).length; },
            // Searchable product drop-down (Select2, same as the other screens).
            initPicker(el, row) {
                const start = () => {
                    const $el = window.jQuery(el);
                    el.innerHTML = '';
                    el.appendChild(new Option('', '', false, false));
                    this.options().forEach((p) => el.appendChild(new Option(this.type === 'reactivate_sku' ? `${p.label} (${p.active ? 'Active' : 'Inactive'})` : p.label, p.id, false, String(p.id) === String(row.product_id))));
                    $el.select2({ width: '100%', placeholder: this.type === 'reactivate_sku' ? 'Select a SKU…' : 'Select a product…', allowClear: false });
                    $el.on('select2:select', () => (this.type === 'stock_adjustment' ? this.pickAdjProduct(row, el.value) : this.pick(row, el.value)));
                };
                // The layout re-initialises every `.select2` element on DOM ready, so wait until the page has fully loaded.
                if (document.readyState === 'complete' && window.jQuery && window.jQuery.fn.select2) { start(); } else { window.addEventListener('load', start, { once: true }); }
            },
            pick(row, id) { row.product_id = id; if (this.type === 'reactivate_sku') { const p = this.product(row); if (p) { row.new_is_active = p.active ? '0' : '1'; } } row.batch_ids = []; row.batches = []; if (row.batch_scope === 'selected') { this.loadBatches(row); } },
            addRow() { this.rows.push(blank()); },
            removeRow(index) { this.rows.splice(index, 1); },
            async loadBatches(row) {
                if (!row.product_id) { return; }
                row.loading = true;
                try {
                    const response = await fetch(this.batchUrl.replace('__ID__', row.product_id), { headers: { Accept: 'application/json' } });
                    row.batches = response.ok ? await response.json() : [];
                } finally { row.loading = false; }
            },
            setScope(row, scope) { row.batch_scope = scope; row.batch_ids = []; if (scope === 'selected') { this.loadBatches(row); } },
            toggleBatch(row, id) {
                const i = row.batch_ids.map(String).indexOf(String(id));
                i === -1 ? row.batch_ids.push(id) : row.batch_ids.splice(i, 1);
            },
            allBatchesPicked(row) { return row.batches.length > 0 && row.batch_ids.length === row.batches.length; },
            toggleAllBatches(row) { row.batch_ids = this.allBatchesPicked(row) ? [] : row.batches.map((b) => b.id); },
        };
    };
</script>

<form action="{{ $action }}" method="POST" x-data="ticketForm({
        type: @js($type->value),
        products: @js($products),
        rows: @js(array_values($rows)),
        batchUrl: @js(route('tickets.product-batches', ['product' => '__ID__'])),
        skuName: @js($sku['product_name'] ?? ''),
        adj: @js(['supplier_id' => (string) ($adj['supplier_id'] ?? ($suppliers->count() === 1 ? $suppliers->first()->id : '')), 'warehouse_id' => (string) ($adj['warehouse_id'] ?? '')]),
        adjBatchUrl: @js(route('tickets.adjustment-batches', ['product' => '__P__', 'warehouse' => '__W__'])),
    })" id="ticket-form" @submit="if (submitting) { $event.preventDefault(); return; } submitting = true" @pageshow.window="submitting = false">
    @csrf
    @if ($httpMethod !== 'POST')
        @method($httpMethod)
    @endif
    <input type="hidden" name="type" value="{{ $type->value }}">
    @unless ($isEdit)
        <input type="hidden" name="_ticket_token" value="{{ \Illuminate\Support\Str::uuid() }}">
    @endunless

    {{-- Ticket: type, title, description --}}
    <section class="uf-card" aria-label="Ticket details" style="margin-bottom:16px">
        <div class="uf-body tk-top">
            <div>
                <label class="tk-label" for="ticket_type">Ticket type</label>
                @if ($isEdit)
                    <select id="ticket_type" disabled><option>{{ $type->label() }}</option></select>
                @else
                    <select id="ticket_type" class="tk-type-select">
                        @foreach ($types as $option)
                            <option value="{{ route('tickets.create', ['type' => $option->value]) }}" @selected($type === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="uf-field">
                <label for="title">Title <span class="uf-req">*</span></label>
                <input id="title" name="title" type="text" required maxlength="191" autofocus value="{{ old('title', $ticket?->title) }}"
                    placeholder="{{ match ($type) { TicketType::PriceUpdate => 'e.g. Revised trade prices for November', TicketType::NewSku => 'e.g. Add Zeera Biscuit 200g', TicketType::ReactivateSku => 'e.g. Bring back seasonal SKU', TicketType::StockAdjustment => 'e.g. Damaged cartons found in rack 4', TicketType::LedgerEntry => 'e.g. Nestle invoice 4500123 for October', TicketType::ClaimEntry => 'e.g. Price difference claim October', TicketType::NewCustomer => 'e.g. Add Al-Madina General Store' } }}">
            </div>
            <div class="tk-top-wide">
                <label class="tk-label" for="description">Description <span class="ak-muted" style="font-weight:400">(optional)</span></label>
                <textarea id="description" name="description" rows="2" class="tk-textarea" placeholder="Why is this change needed?">{{ old('description', $ticket?->description) }}</textarea>
            </div>
        </div>
    </section>

    @if ($type === TicketType::NewSku)
        <section class="uf-card" aria-labelledby="tk-sku">
            <header class="uf-card-head"><div>
                <h2 class="uf-card-title" id="tk-sku"><span class="uf-step">2</span> New SKU</h2>
                <p class="uf-card-sub">The product is created only when an admin approves this ticket.</p>
            </div></header>
            <div class="uf-body tk-gen4">
                <div><label class="tk-label" for="sku_product_code">SKU code <span class="uf-req">*</span></label>
                    <input id="sku_product_code" name="sku[product_code]" type="text" required value="{{ $sku['product_code'] ?? '' }}" style="text-transform:uppercase"></div>
                <div class="tk-span2"><label class="tk-label" for="sku_product_name">SKU name <span class="uf-req">*</span></label>
                    <input id="sku_product_name" name="sku[product_name]" type="text" required x-model="skuName" value="{{ $sku['product_name'] ?? '' }}"></div>
                <div><label class="tk-label" for="sku_supplier_id">Company / supplier</label>
                    <select id="sku_supplier_id" class="tk-select" name="sku[supplier_id]">
                        @if ($suppliers->count() !== 1)<option value="">Select supplier</option>@endif
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) ($sku['supplier_id'] ?? '') === (string) $supplier->id || $suppliers->count() === 1)>{{ $supplier->supplier_name }}</option>
                        @endforeach
                    </select></div>
                <div><label class="tk-label" for="sku_category_id">Category</label>
                    <select id="sku_category_id" class="tk-select" name="sku[category_id]">
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) ($sku['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select></div>
                <div><label class="tk-label" for="sku_brand">Brand</label>
                    <input id="sku_brand" name="sku[brand]" type="text" value="{{ $sku['brand'] ?? '' }}"></div>
                <div><label class="tk-label" for="sku_pack_size">Pack size</label>
                    <input id="sku_pack_size" name="sku[pack_size]" type="text" placeholder="e.g. 500g" value="{{ $sku['pack_size'] ?? '' }}"></div>
                <div><label class="tk-label" for="sku_uom_id">Base UOM <span class="uf-req">*</span></label>
                    <select id="sku_uom_id" class="tk-select" name="sku[uom_id]" required>
                        <option value="">Select UOM</option>
                        @foreach ($uoms as $uom)
                            <option value="{{ $uom->id }}" @selected((string) ($sku['uom_id'] ?? '') === (string) $uom->id)>{{ $uom->uom_name }} ({{ $uom->symbol }})</option>
                        @endforeach
                    </select></div>
                <div><label class="tk-label" for="sku_sales_uom_id">Sales UOM</label>
                    <select id="sku_sales_uom_id" class="tk-select" name="sku[sales_uom_id]">
                        <option value="">Select UOM</option>
                        @foreach ($uoms as $uom)
                            <option value="{{ $uom->id }}" @selected((string) ($sku['sales_uom_id'] ?? '') === (string) $uom->id)>{{ $uom->uom_name }} ({{ $uom->symbol }})</option>
                        @endforeach
                    </select></div>
                <div><label class="tk-label" for="sku_uom_conversion_factor">Units per sales unit</label>
                    <input id="sku_uom_conversion_factor" name="sku[uom_conversion_factor]" type="number" step="0.001" value="{{ $sku['uom_conversion_factor'] ?? 1 }}"></div>
                <div><label class="tk-label" for="sku_barcode">Barcode</label>
                    <input id="sku_barcode" name="sku[barcode]" type="text" value="{{ $sku['barcode'] ?? '' }}"></div>
                <div><label class="tk-label" for="sku_weight">Weight (kg)</label>
                    <input id="sku_weight" name="sku[weight]" type="number" step="0.001" value="{{ $sku['weight'] ?? '' }}"></div>
                <div><label class="tk-label" for="sku_valuation_method">Valuation</label>
                    <select id="sku_valuation_method" name="sku[valuation_method]">
                        @foreach ($valuationMethods as $method)
                            <option value="{{ $method }}" @selected(($sku['valuation_method'] ?? 'FIFO') === $method)>{{ $method }}</option>
                        @endforeach
                    </select></div>
                @foreach (['unit_sell_price' => 'Selling price', 'cost_price' => 'Cost price', 'expiry_price' => 'Expiry price', 'reorder_level' => 'Reorder level'] as $field => $text)
                    <div><label class="tk-label" for="sku_{{ $field }}">{{ $text }}</label>
                        <input id="sku_{{ $field }}" name="sku[{{ $field }}]" type="number" step="0.01" min="0" value="{{ $sku[$field] ?? '' }}"></div>
                @endforeach
                <div class="tk-check-cell"><label class="tk-check"><input type="hidden" name="sku[is_powder]" value="0"><input type="checkbox" name="sku[is_powder]" value="1" @checked(! empty($sku['is_powder']))> Powder product</label></div>
                <div class="tk-span3"><label class="tk-label" for="sku_description">Product description</label>
                    <input id="sku_description" name="sku[description]" type="text" value="{{ $sku['description'] ?? '' }}"></div>
            </div>
        </section>
    @elseif ($type->isSimpleEntry())
        <section class="uf-card" aria-labelledby="tk-entry">
            <header class="uf-card-head"><div>
                <h2 class="uf-card-title" id="tk-entry"><span class="uf-step">2</span> {{ $type->label() }}</h2>
                <p class="uf-card-sub">{{ $type === TicketType::NewCustomer ? 'The customer is created when an admin approves this ticket.' : 'Only the entry is created when an admin approves — it is not posted.' }}</p>
            </div></header>
            <div class="uf-body tk-gen4">
                @foreach ($entryFields as $field)
                    @php
                        $value = $entry[$field['name']] ?? ($field['default'] ?? '');
                        $id = 'entry_'.$field['name'];
                        $span = $field['span'] ?? 1;
                    @endphp
                    <div @class(['tk-span2' => $span === 2, 'tk-span3' => $span === 3, 'tk-span4' => $span === 4])>
                        <label class="tk-label" for="{{ $id }}">{{ $field['label'] }} @if (! empty($field['required']))<span class="uf-req">*</span>@endif</label>
                        @if ($field['type'] === 'select')
                            <select id="{{ $id }}" class="tk-select" name="data[{{ $field['name'] }}]" @required(! empty($field['required']))>
                                @if (count($field['options']) !== 1 || empty($field['required']))<option value="">Select…</option>@endif
                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue || (count($field['options']) === 1 && ! empty($field['required'])))>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="{{ $id }}" name="data[{{ $field['name'] }}]" type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : 'text') }}"
                                @if ($field['type'] === 'number') step="0.01" @endif @required(! empty($field['required'])) value="{{ $value }}">
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @elseif ($type === TicketType::StockAdjustment)
        <section class="uf-card" aria-labelledby="tk-adj" style="margin-bottom:16px">
            <header class="uf-card-head"><div>
                <h2 class="uf-card-title" id="tk-adj"><span class="uf-step">2</span> Adjustment details</h2>
                <p class="uf-card-sub">When an admin approves, the stock adjustment is created and posted (stock, valuation and journal entry).</p>
            </div></header>
            <div class="uf-body tk-gen4">
                <div><label class="tk-label" for="adj_date">Date <span class="uf-req">*</span></label>
                    <input id="adj_date" name="adjustment_date" type="date" required max="{{ now()->toDateString() }}" value="{{ old('adjustment_date', $adj['adjustment_date'] ?? now()->toDateString()) }}"></div>
                <div><label class="tk-label" for="adj_supplier">Company / supplier <span class="uf-req">*</span></label>
                    <select id="adj_supplier" class="tk-select" name="supplier_id" required>
                        @if ($suppliers->count() !== 1)<option value="">Select supplier</option>@endif
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) ($adj['supplier_id'] ?? '') === (string) $supplier->id || $suppliers->count() === 1)>{{ $supplier->supplier_name }}</option>
                        @endforeach
                    </select></div>
                <div><label class="tk-label" for="adj_warehouse">Warehouse <span class="uf-req">*</span></label>
                    <select id="adj_warehouse" class="tk-select" name="warehouse_id" required>
                        <option value="">Select warehouse</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) ($adj['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                        @endforeach
                    </select></div>
                <div><label class="tk-label" for="adj_type">Adjustment type <span class="uf-req">*</span></label>
                    <select id="adj_type" class="tk-select" name="adjustment_type" required>
                        @foreach ($adjustmentTypes as $adjustmentType)
                            <option value="{{ $adjustmentType }}" @selected(($adj['adjustment_type'] ?? 'damage') === $adjustmentType)>{{ \Illuminate\Support\Str::headline($adjustmentType) }}</option>
                        @endforeach
                    </select></div>
                <div class="tk-span2"><label class="tk-label" for="adj_reason">Reason <span class="uf-req">*</span></label>
                    <input id="adj_reason" name="reason" type="text" required maxlength="1000" value="{{ $adj['reason'] ?? '' }}" placeholder="e.g. Water damage in rack 4"></div>
                <div class="tk-span2"><label class="tk-label" for="adj_notes">Notes</label>
                    <input id="adj_notes" name="notes" type="text" value="{{ $adj['notes'] ?? '' }}"></div>
            </div>
        </section>

        <section class="uf-card" aria-labelledby="tk-lines">
            <header class="uf-card-head">
                <div>
                    <h2 class="uf-card-title" id="tk-lines"><span class="uf-step">3</span> Lines</h2>
                    <p class="uf-card-sub">Pick the product and batch, then enter the counted quantity. To change only the unit cost, leave the counted quantity as it is and type the new unit cost.</p>
                </div>
                <span class="ak-pill" x-text="productCount() + ' line(s) · value ' + signed(adjTotal())"></span>
            </header>
            <div style="overflow-x:auto">
                <table class="tk-grid-table">
                    <thead><tr>
                        <th style="width:36px">#</th><th style="min-width:260px">Product</th><th style="min-width:230px">Batch</th>
                        <th style="width:110px">System qty</th><th style="width:120px">Counted qty</th><th style="width:110px">Difference</th>
                        <th style="width:120px">Unit cost</th><th style="width:130px">Value</th><th style="width:40px"></th>
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
                                    <select class="tk-mini" :name="`items[${index}][stock_batch_id]`" :disabled="!row.product_id || !adj.warehouse_id" @change="pickBatch(row, $event.target.value)">
                                        <option value="" x-text="!adj.warehouse_id ? 'Choose a warehouse first' : (row.loading ? 'Loading…' : (row.product_id && row.batches.length === 0 ? 'No stock in this warehouse' : 'Select batch'))"></option>
                                        <template x-for="batch in row.batches" :key="batch.id">
                                            <option :value="batch.id" :selected="String(batch.id) === String(row.stock_batch_id)" x-text="`${batch.batch_code} (qty ${fmt(batch.quantity)})`"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="tk-cell"><input type="number" step="0.001" readonly tabindex="-1" :name="`items[${index}][system_quantity]`" :value="row.system_quantity" style="background:#f8fafc"></td>
                                <td class="tk-cell"><input type="number" step="0.001" min="0" :disabled="!row.stock_batch_id" :name="`items[${index}][actual_quantity]`" x-model="row.actual_quantity" placeholder="count"></td>
                                <td><span x-show="adjDiff(row) !== null" class="tk-delta" :class="adjDiff(row) > 0 ? 'tk-delta-up' : (adjDiff(row) < 0 ? 'tk-delta-down' : 'tk-delta-flat')" x-text="signed(adjDiff(row))"></span> <small x-show="costOnly(row)" class="ak-muted">cost only</small></td>
                                <td class="tk-cell"><input type="number" step="0.01" min="0" :disabled="!row.stock_batch_id" :name="`items[${index}][unit_cost]`" x-model="row.unit_cost"></td>
                                <td><span x-show="adjValue(row) !== null" class="ak-strong" x-text="signed(adjValue(row))"></span></td>
                                <td><button type="button" class="ak-icon ak-icon-danger" x-show="rows.length > 1" @click="removeRow(index)" title="Remove" aria-label="Remove line"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button></td>
                            </tr>
                        </tbody>
                    </template>
                </table>
            </div>
            <div class="uf-body" style="padding-top:12px"><button type="button" class="tk-add" @click="addRow()" :disabled="!adj.supplier_id">＋ Add another line</button></div>
        </section>
    @else
        <section class="uf-card" aria-labelledby="tk-products">
            <header class="uf-card-head">
                <div>
                    <h2 class="uf-card-title" id="tk-products"><span class="uf-step">2</span> {{ $type === TicketType::ReactivateSku ? 'SKUs to activate or deactivate' : 'Products to update' }}</h2>
                    <p class="uf-card-sub">{{ $type === TicketType::ReactivateSku ? 'Pick a SKU — an Inactive one is offered to Activate, an Active one to Deactivate.' : 'Fill only the values that change — empty means unchanged.' }}</p>
                </div>
                <span class="ak-pill" x-text="productCount() + ' selected'"></span>
            </header>

            <div style="overflow-x:auto">
                <table class="tk-grid-table">
                    <thead>
                        <tr>
                            <th style="width:36px">#</th>
                            <th style="min-width:280px">{{ $type === TicketType::ReactivateSku ? 'SKU' : 'Product' }}</th>
                            @if ($type === TicketType::ReactivateSku)
                                <th style="width:220px">New status</th>
                            @else
                                <template x-for="f in fields" :key="f[0]"><th style="width:128px" x-text="f[1]"></th></template>
                                <th style="width:170px">Selling price applies to</th>
                            @endif
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <template x-for="(row, index) in rows" :key="row.uid">
                        <tbody>
                            <tr>
                                <td class="ak-muted" x-text="index + 1"></td>
                                <td>
                                    <input type="hidden" :name="`items[${index}][product_id]`" :value="row.product_id">
                                    <select class="tk-product-select" x-init="initPicker($el, row)"></select>
                                </td>
                                @if ($type === TicketType::ReactivateSku)
                                    <td>
                                        <input type="hidden" :name="`items[${index}][new_is_active]`" :value="row.new_is_active">
                                        <div class="ak-seg" role="group" aria-label="New status">
                                            <button type="button" @click="row.new_is_active = '1'" :class="row.new_is_active === '1' && 'is-on'">Active</button>
                                            <button type="button" @click="row.new_is_active = '0'" :class="row.new_is_active === '0' && 'is-on'">Inactive</button>
                                        </div>
                                    </td>
                                @else
                                    <template x-for="f in fields" :key="f[0]">
                                        <td class="tk-cell">
                                            <input type="number" step="0.01" min="0" placeholder="unchanged" :disabled="!row.product_id" :name="`items[${index}][${f[0]}]`" x-model="row[f[0]]">
                                            <small x-show="row.product_id">
                                                now <span x-text="fmt(current(row, f[0]))"></span>
                                                <span x-show="diff(row, f[0]) !== null" class="tk-delta"
                                                    :class="diff(row, f[0]) > 0 ? 'tk-delta-up' : (diff(row, f[0]) < 0 ? 'tk-delta-down' : 'tk-delta-flat')" x-text="signed(diff(row, f[0]))"></span>
                                            </small>
                                        </td>
                                    </template>
                                    <td>
                                        <input type="hidden" :name="`items[${index}][batch_scope]`" :value="row.batch_scope">
                                        <select class="tk-mini" :disabled="!row.product_id" @change="setScope(row, $event.target.value)" :value="row.batch_scope">
                                            <option value="all" :selected="row.batch_scope === 'all'">All batches</option>
                                            <option value="selected" :selected="row.batch_scope === 'selected'">Selected batches…</option>
                                        </select>
                                        <small class="ak-muted" x-show="row.batch_scope === 'selected'" x-text="row.batch_ids.length + ' of ' + row.batches.length + ' chosen'"></small>
                                    </td>
                                @endif
                                <td>
                                    <button type="button" class="ak-icon ak-icon-danger" x-show="rows.length > 1" @click="removeRow(index)" title="Remove" aria-label="Remove row">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    </button>
                                </td>
                            </tr>
                            @if ($type === TicketType::PriceUpdate)
                                <tr x-show="row.batch_scope === 'selected' && row.product_id" x-cloak class="tk-sub">
                                    <td></td>
                                    <td colspan="7">
                                        <span class="ak-muted" x-show="row.loading">Loading batches…</span>
                                        <span class="ak-muted" x-show="!row.loading && row.batches.length === 0">No batches with stock for this product.</span>
                                        <div class="tk-batches" x-show="row.batches.length > 0">
                                            <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="toggleAllBatches(row)" x-text="allBatchesPicked(row) ? 'Clear' : 'Select all'"></button>
                                            <template x-for="batch in row.batches" :key="batch.id">
                                                <label class="tk-batch" :class="row.batch_ids.map(String).includes(String(batch.id)) && 'is-on'">
                                                    <input type="checkbox" :name="`items[${index}][batch_ids][]`" :value="batch.id"
                                                        :checked="row.batch_ids.map(String).includes(String(batch.id))" @change="toggleBatch(row, batch.id)">
                                                    <span><b x-text="batch.batch_code"></b>
                                                        <small x-text="`qty ${fmt(batch.quantity)} · ${fmt(batch.selling_price)}` + (batch.expiry_date ? ` · exp ${batch.expiry_date}` : '')"></small></span>
                                                </label>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </template>
                </table>
            </div>
            <div class="uf-body" style="padding-top:12px"><button type="button" class="tk-add" @click="addRow()">＋ Add another {{ $type === TicketType::ReactivateSku ? 'SKU' : 'product' }}</button></div>
        </section>
    @endif

    <div class="uf-actions" style="margin-top:16px">
        <p>
            <b>{{ $type->label() }}</b> &middot;
            @if ($type === TicketType::NewSku)
                <span x-text="skuName || 'new SKU'"></span>
            @elseif ($type->isSimpleEntry())
                one record
            @else
                <span x-text="productCount() + ' {{ $type === TicketType::ReactivateSku ? 'SKU(s)' : 'product(s)' }}'"></span>
                @if ($type === TicketType::PriceUpdate) &middot; <span x-text="changeCount() + ' value(s) changing'"></span> @endif
            @endif
            &middot; {{ auth()->user()->supplier->supplier_name ?? 'all companies' }}
            &middot; nothing changes until an admin approves
        </p>
        <div>
            <a href="{{ $isEdit ? route('tickets.show', $ticket) : route('tickets.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
            <button type="submit" class="ak-btn ak-btn-primary" :disabled="submitting">
                <span x-show="!submitting">{{ $submitLabel }}</span><span x-show="submitting" x-cloak>Please wait…</span>
            </button>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        $(function () {
            // Ticket type: searchable drop-down that switches the form.
            $('#ticket_type.tk-type-select').select2({ width: '100%', minimumResultsForSearch: Infinity })
                .on('select2:select', function (e) { window.location = e.params.data.id; });
            // Stock adjustment: supplier and warehouse drive the product and batch lists.
            const form = document.getElementById('ticket-form');
            $('#adj_supplier').on('select2:select', function () { Alpine.$data(form).setAdj('supplier_id', this.value); });
            $('#adj_warehouse').on('select2:select', function () { Alpine.$data(form).setAdj('warehouse_id', this.value); });
            // Other drop-downs (New SKU form).
            $('.tk-select').each(function () {
                const $s = $(this);
                $s.select2({ width: '100%', placeholder: $s.find('option[value=""]').text() || 'Select', allowClear: $s.find('option[value=""]').length > 0 && ! $s.prop('required') });
            });
        });
    </script>
@endpush
