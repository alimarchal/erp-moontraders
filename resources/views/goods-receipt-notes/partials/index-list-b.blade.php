{{-- GRN list, Option B: one card per GRN, three across on a wide screen, one on a phone. --}}
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($grns as $grn)
        <article class="flex flex-col rounded-2xl bg-white p-4 ring-1 ring-gray-200 transition-shadow hover:shadow-md">
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('goods-receipt-notes.show', $grn->id) }}" class="text-base font-semibold tracking-tight text-gray-900 hover:underline">{{ $grn->grn_number }}</a>
                    <p class="truncate text-sm text-gray-600">{{ $grn->supplier->supplier_name }}</p>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusChip[$grn->status] ?? 'bg-gray-100 text-gray-700' }}">{{ ucfirst($grn->status) }}</span>
            </div>
            <p class="mt-3 text-2xl font-semibold tabular-nums tracking-tight text-gray-900">Rs {{ number_format((float) $grn->grand_total, 2) }}</p>
            <dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-xl bg-gray-50 px-2.5 py-2"><dt class="text-gray-500">Received</dt><dd class="font-semibold text-gray-900">{{ $grn->receipt_date?->format('d M Y') }}</dd></div>
                <div class="rounded-xl bg-gray-50 px-2.5 py-2"><dt class="text-gray-500">Quantity</dt><dd class="font-semibold tabular-nums text-gray-900">{{ number_format((float) $grn->total_quantity, 2) }}</dd></div>
                <div class="rounded-xl bg-gray-50 px-2.5 py-2"><dt class="text-gray-500">Warehouse</dt><dd class="truncate font-semibold text-gray-900">{{ $grn->warehouse->warehouse_name }}</dd></div>
                <div class="rounded-xl bg-gray-50 px-2.5 py-2"><dt class="text-gray-500">Invoice</dt><dd class="truncate font-semibold text-gray-900">{{ $grn->supplier_invoice_number ?: '—' }}</dd></div>
            </dl>
            <div class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3">
                @if ($grn->status === 'posted' && $grn->ledgerEntry)
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $grn->ledgerEntry->isPosted() ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-900' }}">Ledger {{ $grn->ledgerEntry->isPosted() ? 'posted' : 'draft' }}</span>
                @endif
                <div class="ml-auto">@include('goods-receipt-notes.partials.index-row-actions')</div>
            </div>
        </article>
    @endforeach
</div>
