@php
    use App\Enums\TicketType;
    use Illuminate\Support\Str;

    $money = fn ($value) => $value === null ? '—' : number_format((float) $value, 2);
    $signed = fn ($value) => ($value >= 0 ? '+' : '').number_format((float) $value, 2);
    $statusTone = ['pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'rejected' => 'ak-status-red'];
    $historyMeta = ['created' => ['Submitted', ''], 'updated' => ['Edited', 'is-grey'], 'approved' => ['Approved', 'is-green'], 'rejected' => ['Rejected', 'is-red']];
    $authUser = auth()->user();
    $canReview = $ticket->isPending() && $authUser->can('ticket-approve');
    $canEdit = $ticket->isPending() && $authUser->can('ticket-edit') && ($ticket->created_by === $authUser->id || $authUser->isTicketAdmin());
    $canDelete = $ticket->isPending() && $authUser->can('ticket-delete') && ($ticket->created_by === $authUser->id || $authUser->isTicketAdmin());
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('tickets.index') }}">Tickets</a><span aria-hidden="true">›</span><span>{{ $ticket->ticket_number }}</span>
                </nav>
                <div class="ak-person" style="align-items:center; margin-top:6px">
                    <span class="ak-avatar" style="width:48px; height:48px" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ticket->type->iconPath() }}" /></svg>
                    </span>
                    <div>
                        <h1 class="ak-title" style="margin:0">{{ $ticket->title }}</h1>
                        <p class="ak-sub" style="margin-top:2px">
                            {{ $ticket->ticket_number }} &middot; {{ $ticket->type->label() }} &middot; {{ $ticket->supplier->supplier_name ?? 'No company' }}
                            &middot; <span class="ak-status {{ $statusTone[$ticket->status->value] }}"><i aria-hidden="true"></i>{{ $ticket->status->label() }}</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('tickets.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Tickets</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @if ($canEdit)
                    <a href="{{ route('tickets.edit', $ticket) }}" class="ak-btn ak-btn-outline">Edit</a>
                @endif
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('tickets.partials.style')

    <div class="ak-page" x-data="{ confirmDelete: false }">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Ticket {{ $ticket->ticket_number }}</div>
            <table class="ak-print-meta">
                <tr><th>Type</th><td>{{ $ticket->type->label() }}</td><th>Status</th><td>{{ $ticket->status->label() }}</td></tr>
                <tr><th>Raised by</th><td>{{ $ticket->creator->name ?? '—' }} ({{ $ticket->created_at->format('d-M-Y H:i') }})</td><th>Printed</th><td>{{ now()->format('d-M-Y h:i A') }} by {{ $authUser->name }}</td></tr>
            </table>
        </div>

        <x-status-message />
        <x-validation-errors class="mb-4" />

        @if ($ticket->description)
            <div class="uf-note uf-note-info"><b>Description:</b> <span style="white-space:pre-line">{{ $ticket->description }}</span></div>
        @endif

        <div class="tk-grid">
            <div class="tk-stack">
                @foreach ($ticket->items as $item)
                    <section class="uf-card" aria-label="Requested change {{ $loop->iteration }}">
                        @if ($ticket->type === TicketType::NewSku)
                            @php $data = $item->new_sku_data; @endphp
                            <header class="uf-card-head">
                                <h2 class="uf-card-title"><span class="uf-step">{{ $loop->iteration }}</span> {{ $data['product_code'] ?? '' }} — {{ $data['product_name'] ?? '' }}</h2>
                                <span class="ak-pill">New SKU</span>
                            </header>
                            <div class="uf-body">
                                <dl class="tk-list" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); column-gap:24px">
                                    @foreach ([
                                        'Company' => $suppliers[$data['supplier_id'] ?? null] ?? null,
                                        'Category' => $categories[$data['category_id'] ?? null] ?? null,
                                        'Base UOM' => $uoms[$data['uom_id'] ?? null] ?? null,
                                        'Sales UOM' => $uoms[$data['sales_uom_id'] ?? null] ?? null,
                                        'Pack size' => $data['pack_size'] ?? null,
                                        'Brand' => $data['brand'] ?? null,
                                        'Barcode' => $data['barcode'] ?? null,
                                        'Valuation' => $data['valuation_method'] ?? null,
                                        'Selling price' => isset($data['unit_sell_price']) ? $money($data['unit_sell_price']) : null,
                                        'Cost price' => isset($data['cost_price']) ? $money($data['cost_price']) : null,
                                        'Expiry price' => isset($data['expiry_price']) ? $money($data['expiry_price']) : null,
                                        'Reorder level' => isset($data['reorder_level']) ? $money($data['reorder_level']) : null,
                                    ] as $term => $value)
                                        <div><dt>{{ $term }}</dt><dd>{{ $value ?: '—' }}</dd></div>
                                    @endforeach
                                </dl>
                            </div>
                        @elseif ($ticket->type === TicketType::ReactivateSku)
                            <header class="uf-card-head">
                                <h2 class="uf-card-title"><span class="uf-step">{{ $loop->iteration }}</span> {{ $item->product->product_code ?? '' }} — {{ $item->product->product_name ?? 'Deleted product' }}</h2>
                                <span>
                                    <span class="ak-status {{ $item->old_is_active ? 'ak-status-green' : 'ak-status-red' }}"><i aria-hidden="true"></i>{{ $item->old_is_active ? 'Active' : 'Inactive' }}</span>
                                    <span class="ak-muted">→</span>
                                    <span class="ak-status {{ $item->new_is_active ? 'ak-status-green' : 'ak-status-red' }}"><i aria-hidden="true"></i>{{ $item->new_is_active ? 'Active' : 'Inactive' }}</span>
                                </span>
                            </header>
                        @else
                            <header class="uf-card-head">
                                <h2 class="uf-card-title"><span class="uf-step">{{ $loop->iteration }}</span> {{ $item->product->product_code ?? '' }} — {{ $item->product->product_name ?? 'Deleted product' }}</h2>
                            </header>
                            <div class="ak-dt-scroll">
                                <table class="ak-dt">
                                    <thead><tr><th scope="col">Field</th><th scope="col" class="ak-num">Old</th><th scope="col" class="ak-num">New</th><th scope="col" class="ak-num">Difference</th></tr></thead>
                                    <tbody>
                                        @foreach ($item->priceChanges() as $change)
                                            <tr>
                                                <td class="ak-strong">{{ $change['label'] }}</td>
                                                <td class="ak-num ak-muted">{{ $money($change['old']) }}</td>
                                                <td class="ak-num ak-strong">{{ $money($change['new']) }}</td>
                                                <td class="ak-num {{ $change['difference'] > 0 ? 'tk-up' : ($change['difference'] < 0 ? 'tk-down' : 'tk-flat') }}">{{ $signed($change['difference']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($ticket->isPending() && $item->product)
                                @php
                                    $stale = collect($item->priceChanges())->filter(fn ($c) => $c['old'] !== null && round((float) $item->product->{$c['field']}, 2) !== round($c['old'], 2));
                                @endphp
                                @if ($stale->isNotEmpty())
                                    <div class="tk-warn">
                                        <b>Heads up:</b> the product changed after this ticket was raised —
                                        @foreach ($stale as $c)
                                            {{ $c['label'] }} is now {{ $money($item->product->{$c['field']}) }} (ticket was written against {{ $money($c['old']) }}).
                                        @endforeach
                                        Approving will overwrite the current value with the new one.
                                    </div>
                                @endif
                            @endif
                            @if ($item->new_unit_sell_price !== null)
                                <div class="uf-body" style="padding-top:12px; padding-bottom:12px; border-top:1px solid var(--ak-line)">
                                    <span class="ak-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.03em">Selling price applies to</span><br>
                                    @if ($item->apply_to_all_batches)
                                        <span class="tk-chip">All batches with stock</span>
                                    @else
                                        @foreach ($item->batch_ids as $batchId)
                                            <span class="tk-chip">{{ $batchLabels[$batchId] ?? "Batch #$batchId" }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                        @endif
                        @if ($item->remarks)
                            <div class="uf-body" style="padding-top:10px; padding-bottom:10px; border-top:1px solid var(--ak-line); font-size:13px"><b>Remarks:</b> {{ $item->remarks }}</div>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="tk-stack">
                @if ($canReview)
                    <section class="uf-card" aria-label="Review" style="border-color:var(--ak-navy)">
                        <header class="uf-card-head"><h2 class="uf-card-title">Review</h2></header>
                        <form method="POST" action="{{ route('tickets.approve', $ticket) }}" class="uf-body">
                            @csrf
                            <p class="ak-muted" style="margin:0 0 10px; font-size:13px">Approving applies the change to live data immediately, exactly like editing the product.</p>
                            <textarea name="review_remarks" rows="3" class="tk-textarea" placeholder="Remarks (required to reject)">{{ old('review_remarks') }}</textarea>
                            <div style="display:flex; gap:8px; margin-top:12px">
                                <button type="submit" formaction="{{ route('tickets.reject', $ticket) }}" class="ak-btn ak-btn-danger-outline" style="flex:1" onclick="return confirm('Reject this ticket?')">Reject</button>
                                <button type="submit" class="ak-btn ak-btn-success" style="flex:1" onclick="return confirm('Approve and apply these changes to the system?')">Approve</button>
                            </div>
                        </form>
                    </section>
                @endif

                <section class="uf-card" aria-label="Details">
                    <header class="uf-card-head"><h2 class="uf-card-title">Details</h2></header>
                    <div class="uf-body">
                        <dl class="tk-list">
                            <div><dt>Status</dt><dd><span class="ak-status {{ $statusTone[$ticket->status->value] }}"><i aria-hidden="true"></i>{{ $ticket->status->label() }}</span></dd></div>
                            <div><dt>Raised by</dt><dd>{{ $ticket->creator->name ?? '—' }}<small>{{ $ticket->created_at->format('d M Y, h:i A') }}</small></dd></div>
                            @if ($ticket->reviewer)
                                <div><dt>{{ $ticket->status->label() }} by</dt><dd>{{ $ticket->reviewer->name }}<small>{{ $ticket->reviewed_at->format('d M Y, h:i A') }}</small></dd></div>
                            @endif
                            @if ($ticket->review_remarks)
                                <div><dt>Review remarks</dt><dd>{{ $ticket->review_remarks }}</dd></div>
                            @endif
                            <div><dt>Items</dt><dd>{{ $ticket->items->count() }}</dd></div>
                        </dl>
                        @if ($canDelete)
                            <div style="margin-top:14px; padding-top:14px; border-top:1px solid var(--ak-line)">
                                <button type="button" class="ak-btn ak-btn-danger-outline" style="width:100%" @click="confirmDelete = true">Delete ticket</button>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="uf-card" aria-label="Ticket history">
                    <header class="uf-card-head"><h2 class="uf-card-title">Ticket history</h2></header>
                    <div class="uf-body">
                        <ul class="tk-feed">
                            @foreach ($ticket->histories as $history)
                                @php [$text, $tone] = $historyMeta[$history->action] ?? [Str::headline($history->action), 'is-grey']; @endphp
                                <li>
                                    <i class="{{ $tone }}" aria-hidden="true"></i>
                                    <div>
                                        <b>{{ $text }}</b> <span class="ak-muted">by {{ $history->user->name ?? 'Unknown' }}</span>
                                        <small>{{ $history->created_at->format('d M Y, h:i A') }}@if ($history->from_status !== $history->to_status) &middot; {{ ucfirst((string) $history->from_status) }} → {{ ucfirst((string) $history->to_status) }}@endif</small>
                                        @if ($history->remarks)<blockquote>{{ $history->remarks }}</blockquote>@endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </div>
        </div>

        @if ($canDelete)
            <div class="uf-modal" x-show="confirmDelete" x-cloak style="display:none" @keydown.escape.window="confirmDelete = false" role="dialog" aria-modal="true">
                <div class="uf-modal-bg" x-show="confirmDelete" x-transition.opacity @click="confirmDelete = false"></div>
                <div class="uf-modal-box" x-show="confirmDelete" x-transition>
                    <div class="uf-modal-body">
                        <span class="uf-modal-icon ak-pill-red" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg></span>
                        <div><h3>Delete ticket {{ $ticket->ticket_number }}?</h3>
                            <p style="margin:8px 0 0; font-size:14px; color:#334155">The ticket and its history are removed. Nothing in the system was changed by it.</p></div>
                    </div>
                    <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="uf-modal-foot">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="ak-btn ak-btn-outline" @click="confirmDelete = false">Cancel</button>
                        <button type="submit" class="ak-btn ak-btn-danger-outline">Delete ticket</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
