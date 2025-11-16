@php
    /** @var \App\Models\ProductTaxMapping|null $mapping */
    $mapping = $mapping ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-label for="product_id" value="Product" :required="true" />
        <select id="product_id" name="product_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Product</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" {{ old('product_id', optional($mapping)->product_id) == $product->id ? 'selected' : '' }}>
                    {{ $product->product_code }} - {{ $product->product_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-label for="tax_code_id" value="Tax Code" :required="true" />
        <select id="tax_code_id" name="tax_code_id"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Tax Code</option>
            @foreach ($taxCodes as $taxCode)
                <option value="{{ $taxCode->id }}" {{ old('tax_code_id', optional($mapping)->tax_code_id) == $taxCode->id ? 'selected' : '' }}>
                    {{ $taxCode->tax_code }} - {{ $taxCode->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 mt-4">
    <div>
        <x-label for="transaction_type" value="Transaction Type" :required="true" />
        <select id="transaction_type" name="transaction_type"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
            required>
            <option value="">Select Transaction Type</option>
            <option value="sales" {{ old('transaction_type', optional($mapping)->transaction_type) === 'sales' ? 'selected' : '' }}>
                Sales
            </option>
            <option value="purchase" {{ old('transaction_type', optional($mapping)->transaction_type) === 'purchase' ? 'selected' : '' }}>
                Purchase
            </option>
            <option value="both" {{ old('transaction_type', optional($mapping)->transaction_type ?? 'both') === 'both' ? 'selected' : '' }}>
                Both
            </option>
        </select>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 mt-6">
    <div class="flex items-center">
        <input id="is_active" type="checkbox" name="is_active" value="1"
            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            {{ old('is_active', optional($mapping)->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="ml-2 text-sm text-gray-700">
            Active
        </label>
    </div>
</div>
