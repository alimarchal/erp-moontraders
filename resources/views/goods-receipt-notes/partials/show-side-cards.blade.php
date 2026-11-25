{{-- Supplier ledger entry, timeline, record and related links for the redesigned GRN views. --}}
<section aria-labelledby="grn-ledger-heading" class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 {{ $cardShadow ?? '' }}">
    <h2 id="grn-ledger-heading" class="mb-3 text-base font-bold text-gray-900">Supplier ledger entry</h2>
    @if ($ledger)
        <dl class="grid grid-cols-[1fr_auto] gap-y-2 text-sm">
            <dt class="text-gray-600">Status</dt><dd class="text-right"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $ledgerStatus === 'posted' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900' }}">{{ ucfirst($ledgerStatus) }}</span></dd>
            <dt class="text-gray-600">Document</dt><dd class="text-right">{{ $ledger->document_type?->value }} · {{ $ledger->document_number ?: '—' }}</dd>
            <dt class="text-gray-600">Date</dt><dd class="text-right">{{ $ledger->transaction_date?->format('d M Y') }}</dd>
            <dt class="text-gray-600">Invoice amount</dt><dd class="text-right font-bold tabular-nums">Rs {{ $rs($ledger->invoice_amount) }}</dd>
        </dl>
        @if ($ledgerUrl)
            <a href="{{ $ledgerUrl }}" class="mt-3 inline-block text-sm font-semibold text-blue-800 hover:underline">Open in Ledger Register →</a>
        @endif
    @elseif ($isPosted && $ledgerMatches->isEmpty())
        <p class="text-sm text-gray-600">No ledger entry was raised for this GRN.</p>
    @elseif (! $isPosted)
        <p class="text-sm text-gray-600">A draft invoice entry is added to the supplier Ledger Register when this GRN is posted.</p>
    @endif

    @if ($ledgerMatches->isNotEmpty())
        <p class="mb-1 mt-4 text-sm font-semibold">Already in the Ledger Register under invoice no. {{ $grn->supplier_invoice_number }}</p>
        <ul class="divide-y divide-gray-100 text-sm">
            @foreach ($ledgerMatches as $match)
                <li class="flex justify-between gap-2 py-2">
                    <span>{{ $match->transaction_date?->format('d M Y') }} · {{ $match->document_type?->value }} <span class="text-xs {{ $match->isPosted() ? 'text-green-700' : 'text-amber-800' }}">{{ $match->isPosted() ? 'Posted' : 'Draft' }}</span></span>
                    <span class="tabular-nums">{{ $rs($match->invoice_amount) }}</span>
                </li>
            @endforeach
        </ul>
        @if (! $ledger)
            <p class="mt-1 text-xs text-gray-600">No second draft row was raised for this GRN because the invoice is already there.</p>
        @endif
        @if (! $ledgerUrl && auth()->user()->can('report-audit-ledger-register'))
            <a href="{{ route('reports.supplier-ledger-register.index', ['filter' => ['supplier_id' => $grn->supplier_id, 'document_number' => $grn->supplier_invoice_number, 'date_from' => '2020-01-01', 'date_to' => now()->toDateString()]]) }}" class="mt-2 inline-block text-sm font-semibold text-blue-800 hover:underline">Open in Ledger Register →</a>
        @endif
    @endif
</section>

<section aria-labelledby="grn-timeline-heading" class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 {{ $cardShadow ?? '' }} print:hidden">
    <h2 id="grn-timeline-heading" class="mb-3 text-base font-bold text-gray-900">Timeline</h2>
    <ol class="space-y-3">
        @foreach ($timeline as $event)
            <li class="grid grid-cols-[12px_1fr] gap-3 text-sm">
                <span class="mt-1.5 h-2.5 w-2.5 rounded-full bg-blue-900" aria-hidden="true"></span>
                <span>{{ $event['text'] }}<span class="block text-xs text-gray-500">{{ $event['at']->format('d M Y, h:i A') }} · {{ $event['at']->diffForHumans() }}</span></span>
            </li>
        @endforeach
    </ol>
</section>

<section aria-labelledby="grn-details-heading" class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 {{ $cardShadow ?? '' }} print:hidden">
    <h2 id="grn-details-heading" class="mb-3 text-base font-bold text-gray-900">Record</h2>
    <dl class="grid grid-cols-[1fr_auto] gap-y-2 text-sm">
        <dt class="text-gray-600">Created</dt><dd class="text-right">{{ $grn->created_at?->format('d M Y, h:i A') }}</dd>
        <dt class="text-gray-600">Last updated</dt><dd class="text-right">{{ $grn->updated_at?->format('d M Y, h:i A') }}</dd>
        @if ($grn->posted_at)<dt class="text-gray-600">Posted</dt><dd class="text-right">{{ $grn->posted_at->format('d M Y, h:i A') }}</dd>@endif
    </dl>
</section>

<section aria-labelledby="grn-related-heading" class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 {{ $cardShadow ?? '' }} print:hidden">
    <h2 id="grn-related-heading" class="mb-2 text-base font-bold text-gray-900">Related</h2>
    <nav class="divide-y divide-gray-100">
        @foreach ($links as [$label, $url])
            <a href="{{ $url }}" class="flex justify-between py-2 text-sm text-blue-800 hover:underline">{{ $label }} <span aria-hidden="true">→</span></a>
        @endforeach
    </nav>
</section>
