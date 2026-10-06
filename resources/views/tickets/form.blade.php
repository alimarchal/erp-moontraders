@php
    use App\Enums\TicketType;

    $isEdit = $ticket !== null;
    $selectClass = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full';
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

        return {
            type: config.type,
            products: config.products,
            rows: (config.rows.length ? config.rows : [{}]).map((r) => Object.assign(blank(), r)),
            batchUrl: config.batchUrl,

            init() {
                this.rows.forEach((row) => {
                    if (row.product_id && row.batch_scope === 'selected') { this.loadBatches(row); }
                });
            },
            options() {
                return this.products.filter((p) => (this.type === 'reactivate_sku' ? !p.active : p.active));
            },
            product(row) { return this.products.find((p) => String(p.id) === String(row.product_id)); },
            current(row, field) { const p = this.product(row); return p ? p[field] : null; },
            diff(row, field) {
                const p = this.product(row);
                if (!p || row[field] === '' || row[field] === null) { return null; }
                return (parseFloat(row[field]) - p[field]);
            },
            fmt(value) { return value === null ? '—' : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            addRow() { this.rows.push(blank()); },
            removeRow(index) { this.rows.splice(index, 1); },
            productChanged(row) { row.batch_ids = []; row.batches = []; if (row.batch_scope === 'selected') { this.loadBatches(row); } },
            async loadBatches(row) {
                if (!row.product_id) { return; }
                row.loading = true;
                try {
                    const response = await fetch(this.batchUrl.replace('__ID__', row.product_id), { headers: { Accept: 'application/json' } });
                    row.batches = response.ok ? await response.json() : [];
                } finally { row.loading = false; }
            },
            scopeChanged(row) { row.batch_ids = []; if (row.batch_scope === 'selected') { this.loadBatches(row); } },
        };
    };
</script>

<div x-data="ticketForm({
        type: @js($type->value),
        products: @js($products),
        rows: @js(array_values($rows)),
        batchUrl: @js(route('tickets.product-batches', ['product' => '__ID__'])),
    })">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <x-label for="type" value="Ticket Type" :required="true" />
            @if ($isEdit)
                <input type="hidden" name="type" value="{{ $type->value }}">
                <x-input type="text" class="mt-1 block w-full bg-gray-100" :value="$type->label()" disabled />
            @else
                <select id="type" name="type" class="{{ $selectClass }}"
                    onchange="window.location = '{{ route('tickets.create') }}?type=' + this.value">
                    @foreach ($types as $option)
                        <option value="{{ $option->value }}" @selected($type === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        <div class="md:col-span-2">
            <x-label for="title" value="Title" :required="true" />
            <x-input id="title" type="text" name="title" class="mt-1 block w-full"
                :value="old('title', $ticket?->title)" required />
        </div>
    </div>

    <div class="mt-4">
        <x-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3"
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $ticket?->description) }}</textarea>
    </div>

    @if ($type === TicketType::NewSku)
        <h3 class="mt-6 mb-2 font-semibold text-gray-800">New SKU details</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="sku_product_code" value="SKU Code" :required="true" />
                <x-input id="sku_product_code" type="text" name="sku[product_code]" class="mt-1 block w-full uppercase" :value="$sku['product_code'] ?? ''" required />
            </div>
            <div class="md:col-span-2">
                <x-label for="sku_product_name" value="SKU Name" :required="true" />
                <x-input id="sku_product_name" type="text" name="sku[product_name]" class="mt-1 block w-full" :value="$sku['product_name'] ?? ''" required />
            </div>
            <div>
                <x-label for="sku_supplier_id" value="Supplier / Company" />
                <select id="sku_supplier_id" name="sku[supplier_id]" class="{{ $selectClass }}">
                    @if ($suppliers->count() !== 1)
                        <option value="">Select supplier</option>
                    @endif
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) ($sku['supplier_id'] ?? '') === (string) $supplier->id || $suppliers->count() === 1)>{{ $supplier->supplier_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="sku_category_id" value="Category" />
                <select id="sku_category_id" name="sku[category_id]" class="{{ $selectClass }}">
                    <option value="">Select category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($sku['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="sku_valuation_method" value="Valuation Method" :required="true" />
                <select id="sku_valuation_method" name="sku[valuation_method]" class="{{ $selectClass }}">
                    @foreach ($valuationMethods as $method)
                        <option value="{{ $method }}" @selected(($sku['valuation_method'] ?? 'FIFO') === $method)>{{ $method }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="sku_uom_id" value="Base UOM" :required="true" />
                <select id="sku_uom_id" name="sku[uom_id]" class="{{ $selectClass }}" required>
                    <option value="">Select UOM</option>
                    @foreach ($uoms as $uom)
                        <option value="{{ $uom->id }}" @selected((string) ($sku['uom_id'] ?? '') === (string) $uom->id)>{{ $uom->uom_name }} ({{ $uom->symbol }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="sku_sales_uom_id" value="Sales UOM" />
                <select id="sku_sales_uom_id" name="sku[sales_uom_id]" class="{{ $selectClass }}">
                    <option value="">Select UOM</option>
                    @foreach ($uoms as $uom)
                        <option value="{{ $uom->id }}" @selected((string) ($sku['sales_uom_id'] ?? '') === (string) $uom->id)>{{ $uom->uom_name }} ({{ $uom->symbol }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="sku_uom_conversion_factor" value="UOM Conversion Factor" />
                <x-input id="sku_uom_conversion_factor" type="number" step="0.001" name="sku[uom_conversion_factor]" class="mt-1 block w-full" :value="$sku['uom_conversion_factor'] ?? 1" />
            </div>
            <div>
                <x-label for="sku_pack_size" value="Pack Size" />
                <x-input id="sku_pack_size" type="text" name="sku[pack_size]" class="mt-1 block w-full" :value="$sku['pack_size'] ?? ''" />
            </div>
            <div>
                <x-label for="sku_brand" value="Brand" />
                <x-input id="sku_brand" type="text" name="sku[brand]" class="mt-1 block w-full" :value="$sku['brand'] ?? ''" />
            </div>
            <div>
                <x-label for="sku_barcode" value="Barcode" />
                <x-input id="sku_barcode" type="text" name="sku[barcode]" class="mt-1 block w-full" :value="$sku['barcode'] ?? ''" />
            </div>
            <div>
                <x-label for="sku_weight" value="Weight (kg)" />
                <x-input id="sku_weight" type="number" step="0.001" name="sku[weight]" class="mt-1 block w-full" :value="$sku['weight'] ?? ''" />
            </div>
            <div>
                <x-label for="sku_unit_sell_price" value="Selling Price" />
                <x-input id="sku_unit_sell_price" type="number" step="0.01" min="0" name="sku[unit_sell_price]" class="mt-1 block w-full" :value="$sku['unit_sell_price'] ?? ''" />
            </div>
            <div>
                <x-label for="sku_cost_price" value="Cost Price" />
                <x-input id="sku_cost_price" type="number" step="0.01" min="0" name="sku[cost_price]" class="mt-1 block w-full" :value="$sku['cost_price'] ?? ''" />
            </div>
            <div>
                <x-label for="sku_expiry_price" value="Expiry Price" />
                <x-input id="sku_expiry_price" type="number" step="0.01" min="0" name="sku[expiry_price]" class="mt-1 block w-full" :value="$sku['expiry_price'] ?? ''" />
            </div>
            <div>
                <x-label for="sku_reorder_level" value="Reorder Level" />
                <x-input id="sku_reorder_level" type="number" step="0.01" min="0" name="sku[reorder_level]" class="mt-1 block w-full" :value="$sku['reorder_level'] ?? ''" />
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center">
                    <input type="hidden" name="sku[is_powder]" value="0">
                    <input type="checkbox" name="sku[is_powder]" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm"
                        @checked(! empty($sku['is_powder']))>
                    <span class="ml-2 text-sm text-gray-600">Powder product</span>
                </label>
            </div>
            <div class="md:col-span-3">
                <x-label for="sku_description" value="Product Description" />
                <textarea id="sku_description" name="sku[description]" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ $sku['description'] ?? '' }}</textarea>
            </div>
        </div>
    @else
        <h3 class="mt-6 mb-2 font-semibold text-gray-800">
            {{ $type === TicketType::ReactivateSku ? 'Inactive SKUs to change' : 'Active products to update' }}
        </h3>

        <template x-for="(row, index) in rows" :key="index">
            <div class="border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
                <div class="flex items-end gap-4">
                    <div class="flex-1">
                        <x-label value="Product" :required="true" />
                        <select :name="`items[${index}][product_id]`" x-model="row.product_id" @change="productChanged(row)"
                            class="{{ $selectClass }}" required>
                            <option value="">Select product</option>
                            <template x-for="p in options()" :key="p.id">
                                <option :value="p.id" x-text="p.label" :selected="String(p.id) === String(row.product_id)"></option>
                            </template>
                        </select>
                    </div>
                    <button type="button" class="text-red-700 hover:underline mb-2" x-show="rows.length > 1" @click="removeRow(index)">Remove</button>
                </div>

                @if ($type === TicketType::ReactivateSku)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <x-label value="Status" :required="true" />
                            <select :name="`items[${index}][new_is_active]`" x-model="row.new_is_active" class="{{ $selectClass }}">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <x-label value="Remarks" />
                            <input type="text" :name="`items[${index}][remarks]`" x-model="row.remarks" class="{{ $selectClass }}">
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4" x-show="row.product_id">
                        <template x-for="field in [['unit_sell_price', 'Selling Price'], ['cost_price', 'Cost Price'], ['expiry_price', 'Expiry Price'], ['reorder_level', 'Reorder Level']]" :key="field[0]">
                            <div>
                                <x-label>
                                    <span x-text="field[1]"></span>
                                </x-label>
                                <div class="text-xs text-gray-500">Old: <span x-text="fmt(current(row, field[0]))"></span></div>
                                <input type="number" step="0.01" min="0" :name="`items[${index}][${field[0]}]`" x-model="row[field[0]]"
                                    class="{{ $selectClass }}" placeholder="New value">
                                <div class="text-xs mt-1" x-show="diff(row, field[0]) !== null"
                                    :class="diff(row, field[0]) >= 0 ? 'text-emerald-700' : 'text-red-700'">
                                    Difference: <span x-text="(diff(row, field[0]) >= 0 ? '+' : '') + fmt(diff(row, field[0]))"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4" x-show="row.product_id">
                        <x-label value="Selling price applies to batch" />
                        <select :name="`items[${index}][batch_scope]`" x-model="row.batch_scope" @change="scopeChanged(row)" class="{{ $selectClass }} md:w-1/3">
                            <option value="all">All batches (with stock)</option>
                            <option value="selected">Selected batches only</option>
                        </select>
                        <div class="mt-2" x-show="row.batch_scope === 'selected'">
                            <p class="text-xs text-gray-500" x-show="row.loading">Loading batches…</p>
                            <p class="text-xs text-gray-500" x-show="!row.loading && row.batches.length === 0">No batches with stock found for this product.</p>
                            <template x-for="batch in row.batches" :key="batch.id">
                                <label class="flex items-center gap-2 text-sm py-0.5">
                                    <input type="checkbox" :name="`items[${index}][batch_ids][]`" :value="batch.id"
                                        :checked="row.batch_ids.map(String).includes(String(batch.id))"
                                        @change="$event.target.checked ? row.batch_ids.push(batch.id) : row.batch_ids = row.batch_ids.filter((id) => String(id) !== String(batch.id))">
                                    <span x-text="`${batch.batch_code} — qty ${fmt(batch.quantity)} — current price ${fmt(batch.selling_price)}${batch.expiry_date ? ' — exp ' + batch.expiry_date : ''}`"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4" x-show="row.product_id">
                        <x-label value="Remarks" />
                        <input type="text" :name="`items[${index}][remarks]`" x-model="row.remarks" class="{{ $selectClass }}">
                    </div>
                @endif
            </div>
        </template>

        <button type="button" @click="addRow()" class="text-blue-800 hover:underline text-sm">+ Add another product</button>
    @endif
</div>
