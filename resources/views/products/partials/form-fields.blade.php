@php
/** @var \App\Models\Product|null $product */
$product = $product ?? null;
$categoryOptions = $categoryOptions ?? collect();
$supplierOptions = $supplierOptions ?? collect();
$uomOptions = $uomOptions ?? collect();
$accountOptions = $accountOptions ?? collect();
$valuationMethods = $valuationMethods ?? [];
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="product_code" value="Product Code" :required="true" />
        <x-input id="product_code" type="text" name="product_code" maxlength="191" class="mt-1 block w-full uppercase"
            required :value="old('product_code', optional($product)->product_code)" placeholder="SKU-001" />
    </div>

    <div>
        <x-label for="product_name" value="Product Name" :required="true" />
        <x-input id="product_name" type="text" name="product_name" maxlength="191" class="mt-1 block w-full" required
            :value="old('product_name', optional($product)->product_name)" placeholder="Chocolate Chip Cookies" />
    </div>
</div>

<div class="mt-4">
    <x-label for="description" value="Description" />
    <textarea id="description" name="description" rows="3"
        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
        placeholder="Key product notes for sales, warehouse and finance">{{ old('description', optional($product)->description) }}</textarea>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
    <div>
        <x-label for="category_id" value="Category" />
        <select id="category_id" name="category_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Uncategorized</option>
            @foreach ($categoryOptions as $category)
            <option value="{{ $category->id }}" {{ (int) old('category_id', optional($product)->category_id) ===
                $category->id ? 'selected' : '' }}>
                {{ $category->category_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="supplier_id" value="Preferred Supplier" />
        <select id="supplier_id" name="supplier_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Not linked</option>
            @foreach ($supplierOptions as $supplier)
            <option value="{{ $supplier->id }}" {{ (int) old('supplier_id', optional($product)->supplier_id) ===
                $supplier->id ? 'selected' : '' }}>
                {{ $supplier->supplier_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="uom_id" value="Base UOM (Inventory)" :required="true" />
        <select id="uom_id" name="uom_id" required
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Select Base UOM</option>
            @foreach ($uomOptions as $uom)
            <option value="{{ $uom->id }}" {{ (int) old('uom_id', optional($product)->uom_id) === $uom->id ? 'selected'
                : '' }}>
                {{ $uom->uom_name }} {{ $uom->symbol ? '(' . $uom->symbol . ')' : '' }}
            </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">Unit for inventory tracking (e.g., PCS, KG)</p>
    </div>

    <div>
        <x-label for="valuation_method" value="Valuation Method" :required="true" />
        <select id="valuation_method" name="valuation_method" required
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            @foreach ($valuationMethods as $method)
            <option value="{{ $method }}" {{ old('valuation_method', optional($product)->valuation_method ?? 'FIFO') ===
                $method ? 'selected' : '' }}>
                {{ $method }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <x-label for="sales_uom_id" value="Sales UOM (Optional)" />
        <select id="sales_uom_id" name="sales_uom_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Same as Base UOM</option>
            @foreach ($uomOptions as $uom)
            <option value="{{ $uom->id }}" {{ (int) old('sales_uom_id', optional($product)->sales_uom_id) === $uom->id ?
                'selected' : '' }}>
                {{ $uom->uom_name }} {{ $uom->symbol ? '(' . $uom->symbol . ')' : '' }}
            </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">Unit for sales/invoicing (e.g., Cases, Boxes, Cartons)</p>
    </div>

    <div>
        <x-label for="uom_conversion_factor" value="Conversion Factor" />
        <x-input id="uom_conversion_factor" type="number" name="uom_conversion_factor" step="0.001" min="1"
            class="mt-1 block w-full"
            :value="old('uom_conversion_factor', optional($product)->uom_conversion_factor ?? 1)" placeholder="1" />
        <p class="text-xs text-gray-500 mt-1">How many base units in 1 sales unit (e.g., 24 PCS = 1 Case → enter 24)</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
    <div>
        <x-label for="brand" value="Brand" />
        <x-input id="brand" type="text" name="brand" maxlength="120" class="mt-1 block w-full"
            :value="old('brand', optional($product)->brand)" placeholder="Brand name" />
    </div>

    <div>
        <x-label for="barcode" value="Barcode" />
        <x-input id="barcode" type="text" name="barcode" maxlength="191" class="mt-1 block w-full"
            :value="old('barcode', optional($product)->barcode)" placeholder="EAN/UPC" />
    </div>

    <div>
        <x-label for="pack_size" value="Pack Size" />
        <x-input id="pack_size" type="text" name="pack_size" maxlength="120" class="mt-1 block w-full"
            :value="old('pack_size', optional($product)->pack_size)" placeholder="e.g., 12 x 100g" />
    </div>

    <div>
        <x-label for="weight" value="Weight (kg)" />
        <x-input id="weight" type="number" name="weight" step="0.001" min="0" class="mt-1 block w-full"
            :value="old('weight', optional($product)->weight)" placeholder="0.500" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="reorder_level" value="Reorder Level" />
        <x-input id="reorder_level" type="number" name="reorder_level" step="0.01" min="0" class="mt-1 block w-full"
            :value="old('reorder_level', optional($product)->reorder_level)" placeholder="50" />
    </div>

    <div>
        <x-label for="unit_price" value="Selling Price" />
        <x-input id="unit_price" type="number" name="unit_price" step="0.01" min="0" class="mt-1 block w-full"
            :value="old('unit_price', optional($product)->unit_price)" placeholder="1200" />
    </div>

    <div>
        <x-label for="cost_price" value="Cost Price" />
        <x-input id="cost_price" type="number" name="cost_price" step="0.01" min="0" class="mt-1 block w-full"
            :value="old('cost_price', optional($product)->cost_price)" placeholder="950" />
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
    <div>
        <x-label for="inventory_account_id" value="Inventory Account" />
        <select id="inventory_account_id" name="inventory_account_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Default</option>
            @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" {{ (int) old('inventory_account_id', optional($product)->
                inventory_account_id) === $account->id ? 'selected' : '' }}>
                {{ $account->account_code }} — {{ $account->account_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="cogs_account_id" value="COGS Account" />
        <select id="cogs_account_id" name="cogs_account_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Default</option>
            @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" {{ (int) old('cogs_account_id', optional($product)->cogs_account_id) ===
                $account->id ? 'selected' : '' }}>
                {{ $account->account_code }} — {{ $account->account_name }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="sales_revenue_account_id" value="Sales Revenue Account" />
        <select id="sales_revenue_account_id" name="sales_revenue_account_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">Default</option>
            @foreach ($accountOptions as $account)
            <option value="{{ $account->id }}" {{ (int) old('sales_revenue_account_id', optional($product)->
                sales_revenue_account_id) === $account->id ? 'selected' : '' }}>
                {{ $account->account_code }} — {{ $account->account_name }}
            </option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-4">
    <div class="flex items-center">
        <input type="hidden" name="is_active" value="0">
        <input id="is_active" type="checkbox" name="is_active" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" {{
            old('is_active', optional($product)->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="ml-2 text-sm text-gray-700">
            Product is active
        </label>
    </div>
</div>