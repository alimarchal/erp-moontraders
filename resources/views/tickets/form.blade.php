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

    $rows = old('items', $existingRows);
    $sku = old('sku', $isEdit && $type === TicketType::NewSku ? ($ticket->items->first()->new_sku_data ?? []) : []);
@endphp

<script>
    window.ticketForm = function (config) {
        const blank = () => ({
            product_id: '', batch_scope: 'all', batch_ids: [], batches: [], loading: false,
            unit_sell_price: '', cost_price: '', expiry_price: '', reorder_level: '',
            new_is_active: '1', remarks: '',
        });
        const fields = [['unit_sell_price', 'Selling price'], ['cost_price', 'Cost price'], ['expiry_price', 'Expiry price'], ['reorder_level', 'Reorder level']];

        return {
            type: config.type,
            products: config.products,
            fields,
            rows: (config.rows.length ? config.rows : [{}]).map((r) => Object.assign(blank(), r)),
            batchUrl: config.batchUrl,
            skuName: config.skuName,

            init() {
                this.rows.forEach((row) => {
                    if (row.product_id && row.batch_scope === 'selected') { this.loadBatches(row); }
                });
            },
            options() { return this.products.filter((p) => (this.type === 'reactivate_sku' ? !p.active : p.active)); },
            product(row) { return this.products.find((p) => String(p.id) === String(row.product_id)); },
            current(row, field) { const p = this.product(row); return p ? p[field] : null; },
            isFilled(row, field) { return row[field] !== '' && row[field] !== null && row[field] !== undefined; },
            diff(row, field) {
                const p = this.product(row);
                return p && this.isFilled(row, field) ? parseFloat(row[field]) - p[field] : null;
            },
            percent(row, field) {
                const base = this.current(row, field);
                const d = this.diff(row, field);
                return base > 0 && d !== null ? (d / base * 100) : null;
            },
            fmt(value) { return value === null || value === undefined ? '—' : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            signed(value) { return (value >= 0 ? '+' : '') + this.fmt(value); },
            tone(value) { return value === 0 ? 'bg-gray-100 text-gray-600' : (value > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'); },
            changeCount() {
                if (this.type === 'new_sku') { return 1; }
                if (this.type === 'reactivate_sku') { return this.rows.filter((r) => r.product_id).length; }
                return this.rows.reduce((n, row) => n + fields.filter(([f]) => row.product_id && this.diff(row, f) !== null).length, 0);
            },
            productCount() { return this.rows.filter((r) => r.product_id).length; },
            applyPercent(row, field, percent) {
                const base = this.current(row, field);
                row[field] = percent === '' || base === null ? '' : (base * (1 + parseFloat(percent) / 100)).toFixed(2);
            },
            pick(row, p) { row.product_id = p.id; row.batch_ids = []; row.batches = []; if (row.batch_scope === 'selected') { this.loadBatches(row); } },
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
    })">
    @csrf
    @if ($httpMethod !== 'POST')
        @method($httpMethod)
    @endif
    <input type="hidden" name="type" value="{{ $type->value }}">

    <div class="uf-grid">
        <div class="tk-stack">

            {{-- 1. Ticket details --}}
            <section class="uf-card" aria-labelledby="tk-details">
                <header class="uf-card-head">
                    <div>
                        <h2 class="uf-card-title" id="tk-details"><span class="uf-step">1</span> Ticket details</h2>
                        <p class="uf-card-sub">What do you want changed, and why?</p>
                    </div>
                </header>
                <div class="uf-body" style="display:flex; flex-direction:column; gap:16px">
                    @unless ($isEdit)
                        <div>
                            <span class="tk-label">Ticket type</span>
                            <div class="tk-types">
                                @foreach ($types as $option)
                                    <a href="{{ route('tickets.create', ['type' => $option->value]) }}" class="tk-type{{ $type === $option ? ' is-on' : '' }}" @if ($type === $option) aria-current="true" @endif>
                                        <b><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $option->iconPath() }}" /></svg>{{ $option->label() }}</b>
                                        <small>{{ $option->description() }}</small>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endunless
                    <div class="uf-field">
                        <label for="title">Title <span class="uf-req">*</span></label>
                        <input id="title" name="title" type="text" required maxlength="191" autofocus value="{{ old('title', $ticket?->title) }}"
                            placeholder="{{ match ($type) { TicketType::PriceUpdate => 'e.g. Revised trade prices for November', TicketType::NewSku => 'e.g. Add Zeera Biscuit 200g', TicketType::ReactivateSku => 'e.g. Bring back seasonal SKU' } }}">
                    </div>
                    <div>
                        <label class="tk-label" for="description">Description <span class="ak-muted" style="font-weight:400">(optional)</span></label>
                        <textarea id="description" name="description" rows="3" class="tk-textarea" placeholder="Why is this change needed?">{{ old('description', $ticket?->description) }}</textarea>
                    </div>
                </div>
            </section>

            @if ($type === TicketType::NewSku)
                <section class="uf-card" aria-labelledby="tk-sku">
                    <header class="uf-card-head"><div>
                        <h2 class="uf-card-title" id="tk-sku"><span class="uf-step">2</span> New SKU</h2>
                        <p class="uf-card-sub">The product is created only when an admin approves this ticket.</p>
                    </div></header>
                    <div class="uf-body tk-gen">
                        <div><label class="tk-label" for="sku_product_code">SKU code <span class="uf-req">*</span></label>
                            <input id="sku_product_code" name="sku[product_code]" type="text" required value="{{ $sku['product_code'] ?? '' }}" style="text-transform:uppercase"></div>
                        <div class="tk-2"><label class="tk-label" for="sku_product_name">SKU name <span class="uf-req">*</span></label>
                            <input id="sku_product_name" name="sku[product_name]" type="text" required x-model="skuName" value="{{ $sku['product_name'] ?? '' }}"></div>
                        <div><label class="tk-label" for="sku_supplier_id">Company / supplier</label>
                            <select id="sku_supplier_id" name="sku[supplier_id]">
                                @if ($suppliers->count() !== 1)<option value="">Select supplier</option>@endif
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((string) ($sku['supplier_id'] ?? '') === (string) $supplier->id || $suppliers->count() === 1)>{{ $supplier->supplier_name }}</option>
                                @endforeach
                            </select></div>
                        <div><label class="tk-label" for="sku_category_id">Category</label>
                            <select id="sku_category_id" name="sku[category_id]">
                                <option value="">Select category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) ($sku['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select></div>
                        <div><label class="tk-label" for="sku_brand">Brand</label>
                            <input id="sku_brand" name="sku[brand]" type="text" value="{{ $sku['brand'] ?? '' }}"></div>
                        <div><label class="tk-label" for="sku_uom_id">Base UOM <span class="uf-req">*</span></label>
                            <select id="sku_uom_id" name="sku[uom_id]" required>
                                <option value="">Select UOM</option>
                                @foreach ($uoms as $uom)
                                    <option value="{{ $uom->id }}" @selected((string) ($sku['uom_id'] ?? '') === (string) $uom->id)>{{ $uom->uom_name }} ({{ $uom->symbol }})</option>
                                @endforeach
                            </select></div>
                        <div><label class="tk-label" for="sku_sales_uom_id">Sales UOM</label>
                            <select id="sku_sales_uom_id" name="sku[sales_uom_id]">
                                <option value="">Select UOM</option>
                                @foreach ($uoms as $uom)
                                    <option value="{{ $uom->id }}" @selected((string) ($sku['sales_uom_id'] ?? '') === (string) $uom->id)>{{ $uom->uom_name }} ({{ $uom->symbol }})</option>
                                @endforeach
                            </select></div>
                        <div><label class="tk-label" for="sku_uom_conversion_factor">Units per sales unit</label>
                            <input id="sku_uom_conversion_factor" name="sku[uom_conversion_factor]" type="number" step="0.001" value="{{ $sku['uom_conversion_factor'] ?? 1 }}"></div>
                        <div><label class="tk-label" for="sku_pack_size">Pack size</label>
                            <input id="sku_pack_size" name="sku[pack_size]" type="text" placeholder="e.g. 500g" value="{{ $sku['pack_size'] ?? '' }}"></div>
                        <div><label class="tk-label" for="sku_weight">Weight (kg)</label>
                            <input id="sku_weight" name="sku[weight]" type="number" step="0.001" value="{{ $sku['weight'] ?? '' }}"></div>
                        <div><label class="tk-label" for="sku_barcode">Barcode</label>
                            <input id="sku_barcode" name="sku[barcode]" type="text" value="{{ $sku['barcode'] ?? '' }}"></div>
                        <div><label class="tk-label" for="sku_valuation_method">Valuation</label>
                            <select id="sku_valuation_method" name="sku[valuation_method]">
                                @foreach ($valuationMethods as $method)
                                    <option value="{{ $method }}" @selected(($sku['valuation_method'] ?? 'FIFO') === $method)>{{ $method }}</option>
                                @endforeach
                            </select></div>
                        <div style="display:flex; align-items:flex-end"><label style="display:inline-flex; gap:8px; align-items:center; font-size:14px">
                            <input type="hidden" name="sku[is_powder]" value="0">
                            <input type="checkbox" name="sku[is_powder]" value="1" style="width:auto; height:auto" @checked(! empty($sku['is_powder']))> Powder product</label></div>
                    </div>
                </section>

                <section class="uf-card" aria-labelledby="tk-sku-price">
                    <header class="uf-card-head"><div>
                        <h2 class="uf-card-title" id="tk-sku-price"><span class="uf-step">3</span> Pricing &amp; stock</h2>
                    </div></header>
                    <div class="uf-body tk-gen" style="grid-template-columns: repeat(2, minmax(0,1fr))">
                        @foreach (['unit_sell_price' => 'Selling price', 'cost_price' => 'Cost price', 'expiry_price' => 'Expiry price', 'reorder_level' => 'Reorder level'] as $field => $text)
                            <div style="grid-column:auto"><label class="tk-label" for="sku_{{ $field }}">{{ $text }}</label>
                                <input id="sku_{{ $field }}" name="sku[{{ $field }}]" type="number" step="0.01" min="0" value="{{ $sku[$field] ?? '' }}"></div>
                        @endforeach
                        <div class="tk-3" style="grid-column:1 / -1"><label class="tk-label" for="sku_description">Product description</label>
                            <textarea id="sku_description" name="sku[description]" rows="2" class="tk-textarea">{{ $sku['description'] ?? '' }}</textarea></div>
                    </div>
                </section>
            @else
                {{-- 2. Products --}}
                <section class="uf-card" aria-labelledby="tk-products">
                    <header class="uf-card-head">
                        <div>
                            <h2 class="uf-card-title" id="tk-products"><span class="uf-step">2</span> {{ $type === TicketType::ReactivateSku ? 'Inactive SKUs' : 'Products' }}</h2>
                            <p class="uf-card-sub">{{ $type === TicketType::ReactivateSku ? 'Only inactive SKUs of your company are listed.' : 'Only active products of your company are listed. Leave a value empty to keep it unchanged.' }}</p>
                        </div>
                        <span class="ak-pill" x-text="productCount() + ' selected'"></span>
                    </header>
                    <div class="uf-body">
                        <template x-for="(row, index) in rows" :key="index">
                            <div class="tk-row">
                                <div class="tk-row-head">
                                    <span class="uf-step" x-text="index + 1"></span>
                                    <div class="tk-pick" data-product-picker x-data="{ open: false, q: '' }" @click.outside="open = false" @keydown.escape="open = false">
                                        <input type="hidden" :name="`items[${index}][product_id]`" :value="row.product_id">
                                        <button type="button" class="tk-pick-btn" @click="open = !open; $nextTick(() => $refs.search && $refs.search.focus())">
                                            <span x-text="product(row) ? product(row).label : (type === 'reactivate_sku' ? 'Select an inactive SKU…' : 'Select a product…')"
                                                :class="product(row) ? 'ak-strong' : 'ak-muted'"></span>
                                            <span aria-hidden="true" class="ak-muted">▾</span>
                                        </button>
                                        <div class="tk-pick-list" x-show="open" x-cloak x-transition>
                                            <input x-ref="search" x-model="q" type="text" placeholder="Search code or name…">
                                            <ul>
                                                <template x-for="p in options().filter((p) => p.label.toLowerCase().includes(q.toLowerCase()))" :key="p.id">
                                                    <li @click="pick(row, p); open = false; q = ''" x-text="p.label"></li>
                                                </template>
                                                <li x-show="options().filter((p) => p.label.toLowerCase().includes(q.toLowerCase())).length === 0" class="ak-muted">No matching product</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <button type="button" class="ak-icon ak-icon-danger" x-show="rows.length > 1" @click="removeRow(index)" title="Remove" aria-label="Remove row">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                <div class="tk-row-body" x-show="row.product_id" x-cloak>
                                    @if ($type === TicketType::ReactivateSku)
                                        <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end">
                                            <div>
                                                <span class="tk-label">New status</span>
                                                <input type="hidden" :name="`items[${index}][new_is_active]`" :value="row.new_is_active">
                                                <div class="ak-seg" role="group" aria-label="New status">
                                                    <button type="button" @click="row.new_is_active = '1'" :class="row.new_is_active === '1' && 'is-on'">Active</button>
                                                    <button type="button" @click="row.new_is_active = '0'" :class="row.new_is_active === '0' && 'is-on'">Inactive</button>
                                                </div>
                                            </div>
                                            <div class="uf-field" style="flex:1; min-width:220px">
                                                <label>Remarks</label>
                                                <input type="text" :name="`items[${index}][remarks]`" x-model="row.remarks" placeholder="Optional">
                                            </div>
                                        </div>
                                    @else
                                        <div style="overflow-x:auto; border:1px solid var(--ak-line); border-radius:8px">
                                            <table class="tk-tbl">
                                                <thead><tr><th>Field</th><th style="text-align:right">Current</th><th>New value</th><th style="text-align:right">Change</th></tr></thead>
                                                <tbody>
                                                    <template x-for="f in fields" :key="f[0]">
                                                        <tr>
                                                            <td class="ak-strong" x-text="f[1]"></td>
                                                            <td class="ak-muted" style="text-align:right; font-variant-numeric:tabular-nums" x-text="fmt(current(row, f[0]))"></td>
                                                            <td>
                                                                <input type="number" step="0.01" min="0" placeholder="unchanged" :name="`items[${index}][${f[0]}]`" x-model="row[f[0]]">
                                                                <br><input type="number" step="0.1" class="tk-pct" placeholder="or ± %" title="Type a percentage to fill the new value" data-percent @input="applyPercent(row, f[0], $event.target.value)">
                                                            </td>
                                                            <td style="text-align:right">
                                                                <span x-show="diff(row, f[0]) !== null" class="tk-delta"
                                                                    :class="diff(row, f[0]) > 0 ? 'tk-delta-up' : (diff(row, f[0]) < 0 ? 'tk-delta-down' : 'tk-delta-flat')">
                                                                    <span x-text="signed(diff(row, f[0]))"></span>
                                                                    <span x-show="percent(row, f[0]) !== null" x-text="'(' + signed(percent(row, f[0])) + '%)'"></span>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div>
                                            <span class="tk-label">Selling price applies to</span>
                                            <input type="hidden" :name="`items[${index}][batch_scope]`" :value="row.batch_scope">
                                            <div class="ak-seg" role="group" aria-label="Batch scope">
                                                <button type="button" @click="setScope(row, 'all')" :class="row.batch_scope === 'all' && 'is-on'">All batches</button>
                                                <button type="button" @click="setScope(row, 'selected')" :class="row.batch_scope === 'selected' && 'is-on'">Selected batches</button>
                                            </div>
                                            <p class="ak-muted" style="margin:8px 0 0; font-size:12.5px" x-show="row.batch_scope === 'all'">Applies to the product and every batch that still has stock.</p>
                                            <div style="margin-top:10px" x-show="row.batch_scope === 'selected'" x-cloak>
                                                <p class="ak-muted" style="font-size:13px" x-show="row.loading">Loading batches…</p>
                                                <p class="ak-muted" style="font-size:13px" x-show="!row.loading && row.batches.length === 0">No batches with stock for this product.</p>
                                                <div x-show="row.batches.length > 0">
                                                    <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" style="margin-bottom:8px" @click="toggleAllBatches(row)"
                                                        x-text="allBatchesPicked(row) ? 'Clear selection' : 'Select all batches'"></button>
                                                    <div class="tk-batches">
                                                        <template x-for="batch in row.batches" :key="batch.id">
                                                            <label class="tk-batch" :class="row.batch_ids.map(String).includes(String(batch.id)) && 'is-on'">
                                                                <input type="checkbox" :name="`items[${index}][batch_ids][]`" :value="batch.id"
                                                                    :checked="row.batch_ids.map(String).includes(String(batch.id))" @change="toggleBatch(row, batch.id)">
                                                                <span>
                                                                    <b x-text="batch.batch_code"></b>
                                                                    <small x-text="`Qty ${fmt(batch.quantity)} · now ${fmt(batch.selling_price)}` + (batch.expiry_date ? ` · exp ${batch.expiry_date}` : '')"></small>
                                                                </span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="uf-field">
                                            <label>Remarks</label>
                                            <input type="text" :name="`items[${index}][remarks]`" x-model="row.remarks" placeholder="Optional note for the approver">
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </template>

                        <button type="button" class="tk-add" @click="addRow()">＋ Add another {{ $type === TicketType::ReactivateSku ? 'SKU' : 'product' }}</button>
                    </div>
                </section>
            @endif
        </div>

        {{-- Side: summary --}}
        <div class="tk-stack">
            <section class="uf-card uf-summary" aria-label="Summary">
                <header class="uf-card-head"><h2 class="uf-card-title">Summary</h2></header>
                <div class="uf-body">
                    <dl>
                        <div><dt>Type</dt><dd>{{ $type->label() }}</dd></div>
                        <div><dt>Company</dt><dd>{{ auth()->user()->supplier->supplier_name ?? 'Any' }}</dd></div>
                        @if ($type === TicketType::NewSku)
                            <div><dt>New SKU</dt><dd x-text="skuName || '—'"></dd></div>
                        @else
                            <div><dt>Products</dt><dd x-text="productCount()"></dd></div>
                            @if ($type === TicketType::PriceUpdate)
                                <div class="uf-total"><dt>Values changing</dt><dd x-text="changeCount()"></dd></div>
                            @endif
                        @endif
                    </dl>
                </div>
            </section>
            <div class="uf-note uf-note-warn">Nothing changes in the system until an admin approves this ticket. You can edit or delete it while it is pending.</div>
        </div>
    </div>

    <div class="uf-actions" style="margin-top:20px">
        <p>{{ $isEdit ? 'Editing '.$ticket->ticket_number.'.' : 'The ticket is sent to an admin for approval.' }}</p>
        <div>
            <a href="{{ $isEdit ? route('tickets.show', $ticket) : route('tickets.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
            <button type="submit" class="ak-btn ak-btn-primary">{{ $submitLabel }}</button>
        </div>
    </div>
</form>
