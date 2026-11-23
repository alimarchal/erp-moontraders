{{-- The corrections posted after a GRN, for the redesigned GRN views. --}}
@if ($grn->corrections->isNotEmpty())
    <section aria-labelledby="grn-corrections-heading" class="overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 id="grn-corrections-heading" class="text-base font-bold text-gray-900">Corrections</h2>
            <p class="text-xs text-gray-500">Posted after the GRN. Quantity differences are dated on the receipt date.</p>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach ($grn->corrections as $correction)
                <div class="px-4 py-3 text-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <b>{{ $correction->correction_number }}</b>
                        <span class="text-xs text-gray-500">{{ $correction->corrected_at->format('d M Y, h:i A') }} · {{ $correction->correctedBy->name ?? '' }}@if ($correction->journal_entry_id) · Journal #{{ $correction->journal_entry_id }} · COGS {{ number_format((float) $correction->cogs_delta, 2) }}@endif</span>
                    </div>
                    <ul class="mt-2 space-y-1">
                        @foreach ($correction->lines as $line)
                            <li class="rounded-lg bg-amber-50 px-3 py-2 ring-1 ring-amber-200">
                                <span class="font-semibold">Line {{ $line->grnItem->line_no ?? '—' }} · {{ $line->newProduct->product_name ?? '—' }}</span>
                                @if ($line->old_product_id !== $line->new_product_id)
                                    <span class="text-gray-600">(was {{ $line->oldProduct->product_name ?? '—' }})</span>
                                @endif
                                <span class="block tabular-nums text-gray-700">Factor {{ (float) $line->old_conversion_factor }} → {{ (float) $line->new_conversion_factor }} · Qty {{ number_format((float) $line->old_quantity, 2) }} → {{ number_format((float) $line->new_quantity, 2) }} · Unit cost {{ number_format((float) $line->old_unit_cost, 2) }} → {{ number_format((float) $line->new_unit_cost, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-gray-600">Reason: {{ $correction->reason }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
