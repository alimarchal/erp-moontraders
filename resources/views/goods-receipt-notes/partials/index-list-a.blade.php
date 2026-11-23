{{-- GRN list, Option A: a clean table. On a phone each row stacks into a compact block. --}}
<div class="overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200">
    <table class="w-full text-sm">
        <thead class="hidden bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-500 md:table-header-group">
            <tr class="[&>th]:px-4 [&>th]:py-2.5">
                <th class="text-left">GRN</th>
                <th class="text-left">Supplier</th>
                <th class="text-left">Warehouse</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Amount</th>
                <th class="text-left">Status</th>
                <th><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($grns as $grn)
                <tr class="block px-4 py-3 hover:bg-gray-50 md:table-row md:p-0 [&>td]:md:px-4 [&>td]:md:py-3">
                    <td class="block md:table-cell">
                        <a href="{{ route('goods-receipt-notes.show', $grn->id) }}" class="font-semibold text-gray-900 hover:underline">{{ $grn->grn_number }}</a>
                        <span class="block text-xs text-gray-500">{{ $grn->receipt_date?->format('d M Y') }}{{ $grn->supplier_invoice_number ? ' · Inv '.$grn->supplier_invoice_number : '' }}</span>
                    </td>
                    <td class="block md:table-cell"><span class="font-medium text-gray-900">{{ $grn->supplier->supplier_name }}</span></td>
                    <td class="hidden text-gray-600 md:table-cell">{{ $grn->warehouse->warehouse_name }}</td>
                    <td class="hidden text-right tabular-nums text-gray-700 md:table-cell">{{ number_format((float) $grn->total_quantity, 2) }}</td>
                    <td class="block font-semibold tabular-nums text-gray-900 md:table-cell md:text-right">Rs {{ number_format((float) $grn->grand_total, 2) }}</td>
                    <td class="mt-1 block md:mt-0 md:table-cell">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusChip[$grn->status] ?? 'bg-gray-100 text-gray-700' }}">{{ ucfirst($grn->status) }}</span>
                        @if ($grn->status === 'posted' && $grn->ledgerEntry)
                            <span class="ml-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $grn->ledgerEntry->isPosted() ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-900' }}">Ledger {{ $grn->ledgerEntry->isPosted() ? 'posted' : 'draft' }}</span>
                        @endif
                    </td>
                    <td class="mt-1 block md:mt-0 md:table-cell">@include('goods-receipt-notes.partials.index-row-actions')</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
