{{--
    The receipt details strip for the Option A and Option B GRN forms (create and edit).
    Field ids and names match the classic form, so the shared form script and the controller see the same inputs.
    $grn is the GRN being edited, or null on create.
--}}
@php
    $grn = $grn ?? null;
@endphp
<section aria-label="Receipt details" class="grn-card rounded-2xl bg-white p-4 ring-1 ring-gray-200">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <div class="sm:col-span-2">
            <label for="supplier_id" class="mb-1 block text-xs font-semibold text-gray-600">Supplier <span class="text-red-600">*</span></label>
            <select id="supplier_id" name="supplier_id" required
                class="select2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Select Supplier</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id', $grn?->supplier_id) == $supplier->id)>{{ $supplier->supplier_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="warehouse_id" class="mb-1 block text-xs font-semibold text-gray-600">Warehouse <span class="text-red-600">*</span></label>
            <select id="warehouse_id" name="warehouse_id" required
                class="select2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Select Warehouse</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $grn?->warehouse_id) == $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="receipt_date" class="mb-1 block text-xs font-semibold text-gray-600">Receipt date <span class="text-red-600">*</span></label>
            <input id="receipt_date" name="receipt_date" type="date" required
                value="{{ old('receipt_date', $grn?->receipt_date?->format('Y-m-d') ?? date('Y-m-d')) }}"
                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="supplier_invoice_number" class="mb-1 block text-xs font-semibold text-gray-600">Supplier invoice no.</label>
            <input id="supplier_invoice_number" name="supplier_invoice_number" type="text" placeholder="e.g. INV-2026-001"
                value="{{ old('supplier_invoice_number', $grn?->supplier_invoice_number) }}"
                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="supplier_invoice_date" class="mb-1 block text-xs font-semibold text-gray-600">Invoice date</label>
            <input id="supplier_invoice_date" name="supplier_invoice_date" type="date"
                value="{{ old('supplier_invoice_date', $grn ? $grn->supplier_invoice_date?->format('Y-m-d') : date('Y-m-d')) }}"
                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-2" x-show="isCartonMode || isUnileverPakistan || isFmrAllowed" x-cloak>
        <span x-show="isCartonMode" class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-800 ring-1 ring-blue-200">Carton entry: cartons, loose pieces and rate per TH</span>
        <span x-show="isUnileverPakistan" class="rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-semibold text-purple-800 ring-1 ring-purple-200">Withholding tax applies</span>
        <span x-show="isFmrAllowed" class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">FMR allowed</span>
    </div>

    @error('confirm_unusual_factor')
        <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3" role="alert">
            <p class="text-sm text-amber-800">{{ $message }}</p>
            <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="confirm_unusual_factor" value="1" class="rounded border-gray-300">
                The conversion factor is right
            </label>
        </div>
    @enderror
    @if (old('submitted_form') !== 'import')
        @error('supplier_invoice_number')
            <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3" role="alert">
                <p class="text-sm text-amber-900">{{ $message }}</p>
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="confirm_split_invoice" value="1" class="rounded border-gray-300">
                    Same invoice, delivered in parts
                </label>
            </div>
        @enderror
    @endif
</section>
