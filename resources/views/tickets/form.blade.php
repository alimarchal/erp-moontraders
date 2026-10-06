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
    })" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    @csrf
    @if ($httpMethod !== 'POST')
        @method($httpMethod)
    @endif
    <input type="hidden" name="type" value="{{ $type->value }}">

    {{-- ───────────── Main column ───────────── --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Type picker --}}
        @unless ($isEdit)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach ($types as $option)
                    <a href="{{ route('tickets.create', ['type' => $option->value]) }}"
                        class="group rounded-xl border-2 p-4 transition {{ $type === $option ? 'border-indigo-600 bg-indigo-50/60 shadow' : 'border-gray-200 bg-white hover:border-indigo-300' }}">
                        <span class="flex items-center gap-2 font-semibold {{ $type === $option ? 'text-indigo-700' : 'text-gray-800' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $option->iconPath() }}" /></svg>
                            {{ $option->label() }}
                        </span>
                        <span class="mt-1 block text-xs text-gray-500 leading-snug">{{ $option->description() }}</span>
                    </a>
                @endforeach
            </div>
        @endunless

        {{-- Title + description (issue style) --}}
        <div class="bg-white shadow-xl sm:rounded-xl p-5 space-y-4">
            <div>
                <label for="title" class="{{ $label }}">Title</label>
                <input id="title" name="title" type="text" required maxlength="191" autofocus
                    value="{{ old('title', $ticket?->title) }}"
                    placeholder="{{ match ($type) { TicketType::PriceUpdate => 'e.g. Revised trade price for Nov', TicketType::NewSku => 'e.g. Add Zeera Biscuit 200g', TicketType::ReactivateSku => 'e.g. Bring back seasonal SKU' } }}"
                    class="{{ $input }} text-base font-semibold py-2.5">
            </div>
            <div>
                <label for="description" class="{{ $label }}">Description</label>
                <textarea id="description" name="description" rows="3" placeholder="Why is this change needed? (optional)"
                    class="{{ $input }}">{{ old('description', $ticket?->description) }}</textarea>
            </div>
        </div>

        @if ($type === TicketType::NewSku)
            {{-- ───────────── New SKU ───────────── --}}
            <div class="bg-white shadow-xl sm:rounded-xl p-5">
                <h3 class="font-semibold text-gray-900">Product details</h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $label }}" for="sku_product_code">SKU code *</label>
                        <input id="sku_product_code" name="sku[product_code]" type="text" required value="{{ $sku['product_code'] ?? '' }}" class="{{ $input }} uppercase">
                    </div>
                    <div class="md:col-span-2">
                        <label class="{{ $label }}" for="sku_product_name">SKU name *</label>
                        <input id="sku_product_name" name="sku[product_name]" type="text" required x-model="skuName" value="{{ $sku['product_name'] ?? '' }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}" for="sku_supplier_id">Company / supplier</label>
                        <select id="sku_supplier_id" name="sku[supplier_id]" class="{{ $input }}">
                            @if ($suppliers->count() !== 1)<option value="">Select supplier</option>@endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($sku['supplier_id'] ?? '') === (string) $supplier->id || $suppliers->count() === 1)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}" for="sku_category_id">Category</label>
                        <select id="sku_category_id" name="sku[category_id]" class="{{ $input }}">
                            <option value="">Select category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) ($sku['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}" for="sku_brand">Brand</label>
                        <input id="sku_brand" name="sku[brand]" type="text" value="{{ $sku['brand'] ?? '' }}" class="{{ $input }}">
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-xl sm:rounded-xl p-5">
                <h3 class="font-semibold text-gray-900">Units &amp; packaging</h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $label }}" for="sku_uom_id">Base UOM *</label>
                        <select id="sku_uom_id" name="sku[uom_id]" required class="{{ $input }}">
                            <option value="">Select UOM</option>
                            @foreach ($uoms as $uom)
                                <option value="{{ $uom->id }}" @selected((string) ($sku['uom_id'] ?? '') === (string) $uom->id)>{{ $uom->uom_name }} ({{ $uom->symbol }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}" for="sku_sales_uom_id">Sales UOM</label>
                        <select id="sku_sales_uom_id" name="sku[sales_uom_id]" class="{{ $input }}">
                            <option value="">Select UOM</option>
                            @foreach ($uoms as $uom)
                                <option value="{{ $uom->id }}" @selected((string) ($sku['sales_uom_id'] ?? '') === (string) $uom->id)>{{ $uom->uom_name }} ({{ $uom->symbol }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}" for="sku_uom_conversion_factor">Units per sales unit</label>
                        <input id="sku_uom_conversion_factor" name="sku[uom_conversion_factor]" type="number" step="0.001" value="{{ $sku['uom_conversion_factor'] ?? 1 }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}" for="sku_pack_size">Pack size</label>
                        <input id="sku_pack_size" name="sku[pack_size]" type="text" placeholder="e.g. 500g" value="{{ $sku['pack_size'] ?? '' }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}" for="sku_weight">Weight (kg)</label>
                        <input id="sku_weight" name="sku[weight]" type="number" step="0.001" value="{{ $sku['weight'] ?? '' }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}" for="sku_barcode">Barcode</label>
                        <input id="sku_barcode" name="sku[barcode]" type="text" value="{{ $sku['barcode'] ?? '' }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}" for="sku_valuation_method">Valuation</label>
                        <select id="sku_valuation_method" name="sku[valuation_method]" class="{{ $input }}">
                            @foreach ($valuationMethods as $method)
                                <option value="{{ $method }}" @selected(($sku['valuation_method'] ?? 'FIFO') === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="sku[is_powder]" value="0">
                            <input type="checkbox" name="sku[is_powder]" value="1" class="rounded border-gray-300 text-indigo-600" @checked(! empty($sku['is_powder']))>
                            Powder product
                        </label>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-xl sm:rounded-xl p-5">
                <h3 class="font-semibold text-gray-900">Pricing &amp; stock</h3>
                <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach (['unit_sell_price' => 'Selling price', 'cost_price' => 'Cost price', 'expiry_price' => 'Expiry price', 'reorder_level' => 'Reorder level'] as $field => $text)
                        <div>
                            <label class="{{ $label }}" for="sku_{{ $field }}">{{ $text }}</label>
                            <input id="sku_{{ $field }}" name="sku[{{ $field }}]" type="number" step="0.01" min="0" value="{{ $sku[$field] ?? '' }}" class="{{ $input }}">
                        </div>
                    @endforeach
                    <div class="col-span-2 md:col-span-4">
                        <label class="{{ $label }}" for="sku_description">Product description</label>
                        <textarea id="sku_description" name="sku[description]" rows="2" class="{{ $input }}">{{ $sku['description'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>
        @else
            {{-- ───────────── Product rows ───────────── --}}
            <template x-for="(row, index) in rows" :key="index">
                <div class="bg-white shadow-xl sm:rounded-xl overflow-hidden">
                    <div class="flex items-center gap-3 px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white" x-text="index + 1"></span>

                        {{-- Searchable product picker --}}
                        <div class="relative flex-1" data-product-picker x-data="{ open: false, q: '' }" @click.outside="open = false" @keydown.escape="open = false">
                            <input type="hidden" :name="`items[${index}][product_id]`" :value="row.product_id">
                            <button type="button" @click="open = !open; $nextTick(() => $refs.search && $refs.search.focus())"
                                class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm shadow-sm hover:border-indigo-400">
                                <span x-text="product(row) ? product(row).label : (type === 'reactivate_sku' ? 'Select an inactive SKU…' : 'Select a product…')"
                                    :class="product(row) ? 'font-semibold text-gray-900' : 'text-gray-400'"></span>
                                <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </button>
                            <div x-show="open" x-transition x-cloak class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-xl">
                                <input x-ref="search" x-model="q" type="text" placeholder="Search code or name…"
                                    class="w-full rounded-t-lg border-0 border-b border-gray-200 text-sm focus:ring-0">
                                <ul class="max-h-60 overflow-auto py-1 text-sm">
                                    <template x-for="p in options().filter((p) => p.label.toLowerCase().includes(q.toLowerCase()))" :key="p.id">
                                        <li @click="pick(row, p); open = false; q = ''"
                                            class="cursor-pointer px-3 py-2 hover:bg-indigo-50" x-text="p.label"></li>
                                    </template>
                                    <li x-show="options().filter((p) => p.label.toLowerCase().includes(q.toLowerCase())).length === 0" class="px-3 py-2 text-gray-400">No matching product</li>
                                </ul>
                            </div>
                        </div>

                        <button type="button" x-show="rows.length > 1" @click="removeRow(index)" title="Remove"
                            class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-5 space-y-5" x-show="row.product_id" x-cloak>
                        @if ($type === TicketType::ReactivateSku)
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <span class="{{ $label }}">New status</span>
                                    <div class="inline-flex rounded-lg border border-gray-300 p-0.5 bg-gray-50">
                                        <input type="hidden" :name="`items[${index}][new_is_active]`" :value="row.new_is_active">
                                        <button type="button" @click="row.new_is_active = '1'" class="rounded-md px-4 py-1.5 text-sm font-semibold transition"
                                            :class="row.new_is_active === '1' ? 'bg-emerald-600 text-white shadow' : 'text-gray-600'">Active</button>
                                        <button type="button" @click="row.new_is_active = '0'" class="rounded-md px-4 py-1.5 text-sm font-semibold transition"
                                            :class="row.new_is_active === '0' ? 'bg-gray-700 text-white shadow' : 'text-gray-600'">Inactive</button>
                                    </div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="{{ $label }}">Remarks</label>
                                    <input type="text" :name="`items[${index}][remarks]`" x-model="row.remarks" class="{{ $input }}" placeholder="Optional">
                                </div>
                            </div>
                        @else
                            {{-- Before → after pricing table --}}
                            <div class="overflow-hidden rounded-lg border border-gray-200">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                        <tr><th class="px-3 py-2 text-left">Field</th><th class="px-3 py-2 text-right">Current</th><th class="px-3 py-2 text-left w-44">New value</th><th class="px-3 py-2 text-right">Change</th></tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="f in fields" :key="f[0]">
                                            <tr class="border-t border-gray-100">
                                                <td class="px-3 py-2 font-medium text-gray-800" x-text="f[1]"></td>
                                                <td class="px-3 py-2 text-right text-gray-500 tabular-nums" x-text="fmt(current(row, f[0]))"></td>
                                                <td class="px-3 py-2">
                                                    <input type="number" step="0.01" min="0" placeholder="unchanged" :name="`items[${index}][${f[0]}]`" x-model="row[f[0]]"
                                                        class="w-full rounded-lg border-gray-300 py-1.5 text-sm tabular-nums focus:border-indigo-500 focus:ring-indigo-500">
                                                    <input type="number" step="0.1" placeholder="or ± %" title="Type a percentage to fill the new value" data-percent
                                                        @input="applyPercent(row, f[0], $event.target.value)"
                                                        class="mt-1 w-24 rounded-md border-gray-200 py-0.5 text-xs text-gray-500 focus:border-indigo-400 focus:ring-indigo-400">
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <span x-show="diff(row, f[0]) !== null" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums"
                                                        :class="tone(diff(row, f[0]))">
                                                        <span x-text="signed(diff(row, f[0]))"></span>
                                                        <span x-show="percent(row, f[0]) !== null" class="opacity-70" x-text="'(' + signed(percent(row, f[0])) + '%)'"></span>
                                                    </span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Batch scope --}}
                            <div>
                                <span class="{{ $label }}">Selling price applies to</span>
                                <input type="hidden" :name="`items[${index}][batch_scope]`" :value="row.batch_scope">
                                <div class="inline-flex rounded-lg border border-gray-300 p-0.5 bg-gray-50">
                                    <button type="button" @click="setScope(row, 'all')" class="rounded-md px-4 py-1.5 text-sm font-semibold transition"
                                        :class="row.batch_scope === 'all' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600'">All batches</button>
                                    <button type="button" @click="setScope(row, 'selected')" class="rounded-md px-4 py-1.5 text-sm font-semibold transition"
                                        :class="row.batch_scope === 'selected' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600'">Selected batches</button>
                                </div>

                                <div class="mt-3" x-show="row.batch_scope === 'selected'" x-cloak>
                                    <p class="text-xs text-gray-500" x-show="row.loading">Loading batches…</p>
                                    <p class="text-sm text-gray-500" x-show="!row.loading && row.batches.length === 0">No batches with stock for this product.</p>
                                    <div x-show="row.batches.length > 0">
                                        <button type="button" class="mb-2 text-xs font-semibold text-indigo-600 hover:underline" @click="toggleAllBatches(row)"
                                            x-text="allBatchesPicked(row) ? 'Clear selection' : 'Select all batches'"></button>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            <template x-for="batch in row.batches" :key="batch.id">
                                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm transition"
                                                    :class="row.batch_ids.map(String).includes(String(batch.id)) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'">
                                                    <input type="checkbox" class="rounded border-gray-300 text-indigo-600" :name="`items[${index}][batch_ids][]`" :value="batch.id"
                                                        :checked="row.batch_ids.map(String).includes(String(batch.id))" @change="toggleBatch(row, batch.id)">
                                                    <span class="min-w-0">
                                                        <span class="block font-semibold text-gray-900" x-text="batch.batch_code"></span>
                                                        <span class="block text-xs text-gray-500" x-text="`Qty ${fmt(batch.quantity)} · now ${fmt(batch.selling_price)}` + (batch.expiry_date ? ` · exp ${batch.expiry_date}` : '')"></span>
                                                    </span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-gray-500" x-show="row.batch_scope === 'all'">Applies to the product and every batch that still has stock.</p>
                            </div>

                            <div>
                                <label class="{{ $label }}">Remarks</label>
                                <input type="text" :name="`items[${index}][remarks]`" x-model="row.remarks" class="{{ $input }}" placeholder="Optional note for the approver">
                            </div>
                        @endif
                    </div>
                </div>
            </template>

            <button type="button" @click="addRow()"
                class="w-full rounded-xl border-2 border-dashed border-gray-300 py-3 text-sm font-semibold text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition">
                + Add another {{ $type === TicketType::ReactivateSku ? 'SKU' : 'product' }}
            </button>
        @endif
    </div>

    {{-- ───────────── Sidebar ───────────── --}}
    <aside class="lg:sticky lg:top-6 space-y-4">
        <div class="bg-white shadow-xl sm:rounded-xl p-5">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Summary</h3>
            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex items-center justify-between"><dt class="text-gray-500">Type</dt><dd class="font-semibold text-gray-900">{{ $type->label() }}</dd></div>
                <div class="flex items-center justify-between"><dt class="text-gray-500">Company</dt><dd class="font-semibold text-gray-900">{{ auth()->user()->supplier->supplier_name ?? 'Any' }}</dd></div>
                @if ($type === TicketType::NewSku)
                    <div class="flex items-center justify-between"><dt class="text-gray-500">New SKU</dt><dd class="font-semibold text-gray-900 truncate max-w-[10rem]" x-text="skuName || '—'"></dd></div>
                @else
                    <div class="flex items-center justify-between"><dt class="text-gray-500">Products</dt><dd class="font-semibold text-gray-900" x-text="productCount()"></dd></div>
                    @if ($type === TicketType::PriceUpdate)
                        <div class="flex items-center justify-between"><dt class="text-gray-500">Values changing</dt><dd class="font-semibold text-gray-900" x-text="changeCount()"></dd></div>
                    @endif
                @endif
            </dl>
            <button type="submit"
                class="mt-5 w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ $submitLabel }}
            </button>
            <a href="{{ $isEdit ? route('tickets.show', $ticket) : route('tickets.index') }}" class="mt-2 block text-center text-sm text-gray-500 hover:text-gray-800">Cancel</a>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-relaxed text-amber-800">
            Nothing changes in the system until an admin approves this ticket. You can edit or delete it while it is pending.
        </div>
    </aside>
</form>
