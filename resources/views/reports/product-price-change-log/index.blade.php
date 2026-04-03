<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Product Price Change Log" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            @media print {
                @page { margin: 15mm 10mm 20mm 10mm; }
                .no-print { display: none !important; }
                body { margin: 0 !important; padding: 0 !important; }
                .max-w-7xl { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
                .bg-white { margin: 0 !important; padding: 10px !important; box-shadow: none !important; }
                .overflow-x-auto { overflow: visible !important; }
                .print-only { display: block !important; }
            }
            .print-only { display: none; }
        </style>
    @endpush

    <x-filter-section :action="route('reports.product-price-change-log.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Supplier</label>
                <select name="supplier_id" class="select2 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($supplierOptions as $supplier)
                        <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Product</label>
                <select name="product_id" class="select2 w-full">
                    <option value="">All Products</option>
                    @foreach ($productOptions as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                            {{ $product->product_code }} — {{ $product->product_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Price Type</label>
                <select name="price_type" class="select2 w-full">
                    <option value="">All Types</option>
                    @foreach ($priceTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('price_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Changed By</label>
                <select name="changed_by" class="select2 w-full">
                    <option value="">All Users</option>
                    @foreach ($userOptions as $user)
                        <option value="{{ $user->id }}" @selected(request('changed_by') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                    class="w-full border border-gray-300 rounded-md shadow-sm text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500" />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                    class="w-full border border-gray-300 rounded-md shadow-sm text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500" />
            </div>

        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-white shadow rounded-lg overflow-hidden">

            <div class="px-6 py-4 border-b border-gray-200 no-print flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-800">Product Price Change Log</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $logs->total() }} record(s) found</p>
                </div>
                <button onclick="window.print()" class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Print
                </button>
            </div>

            <div class="print-only px-4 pt-4 pb-2 text-center">
                <h2 class="text-lg font-bold">Product Price Change Log</h2>
                <p class="text-sm">Printed on: {{ now()->format('d M Y H:i') }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price Type</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Old Price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">New Price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Change</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Batches Impacted</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Changed By</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            @php
                                $diff = $log->new_price - $log->old_price;
                                $isIncrease = $diff > 0;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ $logs->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                    {{ $log->changed_at->format('d M Y') }}<br>
                                    <span class="text-xs text-gray-400">{{ $log->changed_at->format('H:i:s') }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800">{{ $log->product->product_name ?? '—' }}</div>
                                    <div class="text-xs text-gray-400">{{ $log->product->product_code ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs">
                                    {{ $log->product->supplier->supplier_name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $typeClasses = [
                                            'selling_price' => 'bg-blue-100 text-blue-700',
                                            'expiry_price'  => 'bg-amber-100 text-amber-700',
                                            'cost_price'    => 'bg-purple-100 text-purple-700',
                                        ];
                                        $typeLabels = [
                                            'selling_price' => 'Selling Price',
                                            'expiry_price'  => 'Expiry Price',
                                            'cost_price'    => 'Cost Price',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $typeClasses[$log->price_type] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $typeLabels[$log->price_type] ?? $log->price_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500 line-through">
                                    {{ number_format($log->old_price, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800">
                                    {{ number_format($log->new_price, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-xs font-medium {{ $isIncrease ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $isIncrease ? '+' : '' }}{{ number_format($diff, 2) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($log->price_type === 'selling_price' && $log->impacted_batch_count > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"
                                            title="{{ implode(', ', $log->impacted_batch_ids ?? []) }}">
                                            {{ $log->impacted_batch_count }} batch(es)
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs">
                                    {{ $log->changedBy->name ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-10 text-center text-gray-400 text-sm">No price change records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 no-print">
                    {{ $logs->withQueryString()->links() }}
                </div>
            @endif

        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function () {
                $('.select2').select2({ width: '100%', placeholder: 'Select an option', allowClear: true });
            });
        </script>
    @endpush
</x-app-layout>
