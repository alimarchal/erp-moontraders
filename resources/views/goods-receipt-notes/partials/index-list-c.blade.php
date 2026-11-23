{{-- GRN list, Option C: grouped by receipt day, each day with its count and amount, like a timeline. --}}
<div class="space-y-5">
    @foreach ($grns->getCollection()->groupBy(fn ($grn) => $grn->receipt_date?->toDateString()) as $day => $dayGrns)
        @php($date = $day ? \Carbon\Carbon::parse($day) : null)
        <section aria-label="{{ $date?->format('l, d M Y') }}">
            <div class="mb-2 flex items-baseline gap-3 px-1">
                <h2 class="text-base font-semibold tracking-tight text-gray-900">{{ $date ? ($date->isToday() ? 'Today' : ($date->isYesterday() ? 'Yesterday' : $date->format('l, d M Y'))) : 'No date' }}</h2>
                <span class="text-sm text-gray-500">{{ $dayGrns->count() }} {{ $dayGrns->count() === 1 ? 'GRN' : 'GRNs' }} · Rs {{ number_format((float) $dayGrns->sum('grand_total'), 2) }}</span>
            </div>
            <ol class="relative space-y-2 border-l-2 border-gray-200 pl-5">
                @foreach ($dayGrns as $grn)
                    <li class="relative">
                        <span class="absolute -left-[1.6rem] top-4 h-3 w-3 rounded-full ring-4 ring-gray-100 {{ $grn->status === 'posted' ? 'bg-green-500' : ($grn->status === 'reversed' ? 'bg-red-500' : 'bg-amber-400') }}" aria-hidden="true"></span>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-2xl bg-white px-4 py-3 ring-1 ring-gray-200">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('goods-receipt-notes.show', $grn->id) }}" class="font-semibold text-gray-900 hover:underline">{{ $grn->grn_number }}</a>
                                <span class="ml-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusChip[$grn->status] ?? 'bg-gray-100 text-gray-700' }}">{{ ucfirst($grn->status) }}</span>
                                <p class="truncate text-sm text-gray-600">{{ $grn->supplier->supplier_name }} · {{ $grn->warehouse->warehouse_name }}{{ $grn->supplier_invoice_number ? ' · Inv '.$grn->supplier_invoice_number : '' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold tabular-nums text-gray-900">Rs {{ number_format((float) $grn->grand_total, 2) }}</p>
                                <p class="text-xs tabular-nums text-gray-500">{{ number_format((float) $grn->total_quantity, 2) }} qty</p>
                            </div>
                            <div class="w-full sm:w-auto">@include('goods-receipt-notes.partials.index-row-actions')</div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endforeach
</div>
