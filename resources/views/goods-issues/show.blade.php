<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Goods Issue: {{ $goodsIssue->issue_number }}
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            @if ($goodsIssue->status === 'draft')
            <form action="{{ route('goods-issues.post', $goodsIssue->id) }}" method="POST"
                onsubmit="return confirm('Are you sure you want to post this Goods Issue? This will transfer inventory from warehouse to vehicle.');"
                class="inline-block">
                @csrf
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                    <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Post Issue
                </button>
            </form>
            <a href="{{ route('goods-issues.edit', $goodsIssue->id) }}"
                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                Edit
            </a>
            @endif
            <a href="{{ route('goods-issues.index') }}"
                class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Issue Number</h3>
                            <p class="text-lg font-bold text-gray-900">{{ $goodsIssue->issue_number }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Issue Date</h3>
                            <p class="text-lg text-gray-900">
                                {{ \Carbon\Carbon::parse($goodsIssue->issue_date)->format('d M Y') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Status</h3>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full
                                {{ $goodsIssue->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                {{ $goodsIssue->status === 'issued' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                {{ ucfirst($goodsIssue->status) }}
                            </span>
                        </div>
                    </div>

                    <hr class="my-6 border-gray-200">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Warehouse</h3>
                            <p class="text-base font-semibold text-gray-900">
                                {{ $goodsIssue->warehouse->warehouse_name }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Vehicle</h3>
                            <p class="text-base font-semibold text-gray-900">
                                {{ $goodsIssue->vehicle->vehicle_number }}</p>
                            <p class="text-sm text-gray-600">{{ $goodsIssue->vehicle->vehicle_type }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Salesman</h3>
                            <p class="text-base font-semibold text-gray-900">
                                {{ $goodsIssue->employee->name }}</p>
                            @if($goodsIssue->employee->supplier)
                            <p class="text-sm text-gray-600">{{ $goodsIssue->employee->supplier->company_name }}</p>
                            @endif
                        </div>
                    </div>

                    <hr class="my-6 border-gray-200">

                    <x-detail-table title="Items Issued" :headers="[
                        ['label' => '#', 'align' => 'text-center'],
                        ['label' => 'Product', 'align' => 'text-left'],
                        ['label' => 'Quantity Issued', 'align' => 'text-right'],
                        ['label' => 'UOM', 'align' => 'text-center'],
                        ['label' => 'Batch Breakdown', 'align' => 'text-left'],
                        ['label' => 'Total Value', 'align' => 'text-right'],
                    ]">
                        @foreach ($goodsIssue->items as $item)
                        <tr class="border-b border-gray-200 text-sm">
                            <td class="py-1 px-2 text-center">{{ $item->line_no }}</td>
                            <td class="py-1 px-2">
                                <div class="font-semibold text-gray-900">{{ $item->product->product_code }}</div>
                                <div class="text-xs text-gray-500">{{ $item->product->product_name }}</div>
                            </td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->quantity_issued, 2) }}</td>
                            <td class="py-1 px-2 text-center">{{ $item->uom->uom_name }}</td>
                            <td class="py-1 px-2">
                                @if(isset($item->batch_breakdown) && count($item->batch_breakdown) > 0)
                                @if(count($item->batch_breakdown) === 1)
                                @php $b = $item->batch_breakdown[0]; @endphp
                                <div class="flex items-center space-x-1">
                                    <span class="font-semibold text-green-600">
                                        {{ number_format($b['quantity'], 0) }} × ₨{{
                                        number_format($b['selling_price'], 2) }}
                                    </span>
                                    @if($b['is_promotional'])
                                    <span
                                        class="px-2 py-1 ml-1 text-xs font-semibold rounded bg-orange-100 text-orange-800">
                                        Promotional
                                    </span>
                                    @endif
                                </div>
                                @else
                                <div class="space-y-1">
                                    @foreach($item->batch_breakdown as $b)
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-600">
                                            {{ number_format($b['quantity'], 0) }} × ₨{{
                                            number_format($b['selling_price'], 2) }}
                                            @if($b['is_promotional'])
                                            <span title="Promotional">🎁</span>
                                            @endif
                                        </span>
                                        <span class="font-semibold">= ₨{{ number_format($b['value'], 2)
                                            }}</span>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                                @else
                                <span class="text-gray-400 text-xs">Avg: ₨{{ number_format($item->unit_cost, 2)
                                    }}</span>
                                @endif
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-emerald-600">
                                @if(isset($item->calculated_total))
                                ₨ {{ number_format($item->calculated_total, 2) }}
                                @else
                                ₨ {{ number_format($item->total_value, 2) }}
                                @endif
                            </td>
                        </tr>
                        @endforeach

                        <x-slot name="footer">
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="5" class="py-1 px-2 text-right font-bold text-lg">Grand Total:</td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-emerald-600">
                                    @php
                                    $grandTotal = $goodsIssue->items->sum(function($item) {
                                    return $item->calculated_total ?? $item->total_value;
                                    });
                                    @endphp
                                    ₨ {{ number_format($grandTotal, 2) }}
                                </td>
                            </tr>
                        </x-slot>
                    </x-detail-table>

                    @if ($goodsIssue->notes)
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Notes</h3>
                        <p class="text-sm text-gray-700">{{ $goodsIssue->notes }}</p>
                    </div>
                    @endif

                    @if ($goodsIssue->posted_at)
                    <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                        <p class="text-sm text-green-800">
                            This goods issue was posted on {{ $goodsIssue->posted_at->format('d M Y, h:i A') }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>