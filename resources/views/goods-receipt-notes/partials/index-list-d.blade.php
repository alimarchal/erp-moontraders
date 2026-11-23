{{-- GRN list, Option D: enterprise grid — dense, striped, every key column, page totals in the footer. --}}
<div class="overflow-hidden rounded-lg border border-gray-300 bg-white">
    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm">
        <span class="font-semibold text-gray-800">Goods receipt notes ({{ number_format($grns->total()) }})</span>
        <span class="text-xs text-gray-500">Rows {{ $grns->firstItem() }}–{{ $grns->lastItem() }} of {{ number_format($grns->total()) }}</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[64rem] text-[13px] tabular-nums">
            <thead class="bg-gray-100 text-xs font-semibold text-gray-600">
                <tr class="[&>th]:border-b [&>th]:border-gray-300 [&>th]:px-2 [&>th]:py-1.5 [&>th]:text-left">
                    <th class="w-10">#</th><th>GRN number</th><th>Receipt date</th><th>Supplier</th><th>Invoice</th><th>Warehouse</th>
                    <th class="!text-right">Quantity</th><th class="!text-right">Amount</th><th>Status</th><th>Ledger</th><th>Received by</th><th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($grns as $grn)
                    <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50 [&>td]:border-b [&>td]:border-gray-200 [&>td]:px-2 [&>td]:py-1.5">
                        <td class="text-gray-500">{{ $grns->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('goods-receipt-notes.show', $grn->id) }}" class="font-semibold text-blue-700 hover:underline">{{ $grn->grn_number }}</a></td>
                        <td>{{ $grn->receipt_date?->format('d M Y') }}</td>
                        <td class="max-w-[14rem] truncate">{{ $grn->supplier->supplier_name }}</td>
                        <td>{{ $grn->supplier_invoice_number ?: '—' }}</td>
                        <td>{{ $grn->warehouse->warehouse_name }}</td>
                        <td class="text-right">{{ number_format((float) $grn->total_quantity, 2) }}</td>
                        <td class="text-right font-semibold">{{ number_format((float) $grn->grand_total, 2) }}</td>
                        <td><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full {{ $grn->status === 'posted' ? 'bg-green-600' : ($grn->status === 'reversed' ? 'bg-red-600' : 'bg-amber-500') }}"></span>{{ ucfirst($grn->status) }}</span></td>
                        <td>{{ $grn->status === 'posted' && $grn->ledgerEntry ? ($grn->ledgerEntry->isPosted() ? 'Posted' : 'Draft') : '—' }}</td>
                        <td class="max-w-[9rem] truncate text-gray-600">{{ $grn->receivedBy->name ?? '—' }}</td>
                        <td>@include('goods-receipt-notes.partials.index-row-actions')</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-100 font-semibold">
                <tr class="[&>td]:border-t [&>td]:border-gray-300 [&>td]:px-2 [&>td]:py-1.5">
                    <td colspan="6">Total on this page</td>
                    <td class="text-right">{{ number_format((float) $grns->getCollection()->sum('total_quantity'), 2) }}</td>
                    <td class="text-right">{{ number_format((float) $grns->getCollection()->sum('grand_total'), 2) }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
