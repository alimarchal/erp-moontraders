@php
    use App\Enums\TicketType;

    $money = fn ($value) => $value === null ? '—' : number_format((float) $value, 2);
    $signed = fn ($value) => ($value >= 0 ? '+' : '').number_format((float) $value, 2);
    $historyLabels = ['created' => 'Submitted', 'updated' => 'Edited', 'approved' => 'Approved', 'rejected' => 'Rejected'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Ticket {{ $ticket->ticket_number }}" backRoute="tickets.index" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <x-validation-errors class="mb-4 mt-4" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $ticket->title }}</h3>
                        <p class="text-sm text-gray-500">{{ $ticket->type->label() }} · {{ $ticket->supplier->supplier_name ?? 'No company' }}</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full {{ $ticket->status->badgeClasses() }}">{{ $ticket->status->label() }}</span>
                </div>
                <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 text-sm">
                    <div><dt class="text-gray-500">Raised by</dt><dd class="font-medium">{{ $ticket->creator->name ?? '—' }} · {{ $ticket->created_at->format('d-m-Y H:i') }}</dd></div>
                    <div><dt class="text-gray-500">Reviewed by</dt><dd class="font-medium">{{ $ticket->reviewer ? $ticket->reviewer->name.' · '.$ticket->reviewed_at->format('d-m-Y H:i') : '—' }}</dd></div>
                    <div><dt class="text-gray-500">Review remarks</dt><dd class="font-medium">{{ $ticket->review_remarks ?: '—' }}</dd></div>
                </dl>
                @if ($ticket->description)
                    <p class="mt-4 text-sm text-gray-700 whitespace-pre-line">{{ $ticket->description }}</p>
                @endif
            </div>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Requested changes</h3>

                @foreach ($ticket->items as $item)
                    <div class="border border-gray-200 rounded-lg p-4 mb-3">
                        @if ($ticket->type === TicketType::NewSku)
                            @php $data = $item->new_sku_data; @endphp
                            <p class="font-semibold">{{ $data['product_code'] ?? '' }} — {{ $data['product_name'] ?? '' }}</p>
                            <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-2 text-sm">
                                <div><dt class="text-gray-500">Supplier</dt><dd>{{ $suppliers[$data['supplier_id'] ?? null] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">Category</dt><dd>{{ $categories[$data['category_id'] ?? null] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">Base UOM</dt><dd>{{ $uoms[$data['uom_id'] ?? null] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">Sales UOM</dt><dd>{{ $uoms[$data['sales_uom_id'] ?? null] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">Pack size</dt><dd>{{ $data['pack_size'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">Brand</dt><dd>{{ $data['brand'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">Barcode</dt><dd>{{ $data['barcode'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">Valuation</dt><dd>{{ $data['valuation_method'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">Selling price</dt><dd>{{ $money($data['unit_sell_price'] ?? null) }}</dd></div>
                                <div><dt class="text-gray-500">Cost price</dt><dd>{{ $money($data['cost_price'] ?? null) }}</dd></div>
                                <div><dt class="text-gray-500">Expiry price</dt><dd>{{ $money($data['expiry_price'] ?? null) }}</dd></div>
                                <div><dt class="text-gray-500">Reorder level</dt><dd>{{ $money($data['reorder_level'] ?? null) }}</dd></div>
                            </dl>
                        @elseif ($ticket->type === TicketType::ReactivateSku)
                            <p class="font-semibold">{{ $item->product->product_code ?? '' }} — {{ $item->product->product_name ?? 'Deleted product' }}</p>
                            <p class="text-sm mt-1">Status: {{ $item->old_is_active ? 'Active' : 'Inactive' }} →
                                <b>{{ $item->new_is_active ? 'Active' : 'Inactive' }}</b></p>
                        @else
                            <p class="font-semibold">{{ $item->product->product_code ?? '' }} — {{ $item->product->product_name ?? 'Deleted product' }}</p>
                            <table class="w-full text-sm mt-2">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-1">Field</th><th class="text-right">Old</th><th class="text-right">New</th><th class="text-right">Difference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($item->priceChanges() as $change)
                                        <tr class="border-t">
                                            <td class="py-1">{{ $change['label'] }}</td>
                                            <td class="text-right">{{ $money($change['old']) }}</td>
                                            <td class="text-right font-semibold">{{ $money($change['new']) }}</td>
                                            <td class="text-right {{ $change['difference'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $signed($change['difference']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($item->new_unit_sell_price !== null)
                                <p class="text-xs text-gray-600 mt-2">
                                    Selling price applies to:
                                    @if ($item->apply_to_all_batches)
                                        <b>all batches</b> with stock
                                    @else
                                        <b>{{ collect($item->batch_ids)->map(fn ($id) => $batchLabels[$id] ?? "Batch #$id")->implode(', ') }}</b>
                                    @endif
                                </p>
                            @endif
                        @endif
                        @if ($item->remarks)
                            <p class="text-xs text-gray-600 mt-2">Remarks: {{ $item->remarks }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($ticket->isPending())
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 flex flex-wrap gap-4 items-start justify-between">
                    <div class="flex gap-2">
                        @can('ticket-edit')
                            @if ($ticket->created_by === auth()->id() || auth()->user()->isTicketAdmin())
                                <a href="{{ route('tickets.edit', $ticket) }}" class="inline-flex items-center px-4 py-2 border rounded-md text-xs font-semibold uppercase">Edit</a>
                            @endif
                        @endcan
                    </div>

                    @can('ticket-approve')
                        <div class="flex-1 min-w-[280px] max-w-xl">
                            <form method="POST" action="{{ route('tickets.approve', $ticket) }}" class="space-y-2" id="review-form">
                                @csrf
                                <x-label for="review_remarks" value="Remarks (required when rejecting)" />
                                <textarea id="review_remarks" name="review_remarks" rows="2" class="block w-full border-gray-300 rounded-md shadow-sm">{{ old('review_remarks') }}</textarea>
                                <div class="flex gap-2 justify-end">
                                    <button type="submit" formaction="{{ route('tickets.reject', $ticket) }}"
                                        onclick="return confirm('Reject this ticket?')"
                                        class="inline-flex items-center px-4 py-2 bg-red-700 rounded-md text-xs font-semibold text-white uppercase">Reject</button>
                                    <button type="submit" onclick="return confirm('Approve and apply these changes to the system?')"
                                        class="inline-flex items-center px-4 py-2 bg-green-700 rounded-md text-xs font-semibold text-white uppercase">Approve</button>
                                </div>
                            </form>
                        </div>
                    @endcan
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Ticket history</h3>
                <ol class="border-l-2 border-gray-200 ml-2 space-y-3">
                    @foreach ($ticket->histories as $history)
                        <li class="ml-4 text-sm">
                            <span class="font-semibold">{{ $historyLabels[$history->action] ?? ucfirst($history->action) }}</span>
                            by {{ $history->user->name ?? 'Unknown' }}
                            <span class="text-gray-500">· {{ $history->created_at->format('d-m-Y H:i') }}</span>
                            @if ($history->from_status !== $history->to_status)
                                <span class="text-gray-500">· {{ ucfirst((string) $history->from_status) }} → {{ ucfirst((string) $history->to_status) }}</span>
                            @endif
                            @if ($history->remarks)
                                <div class="text-gray-600">{{ $history->remarks }}</div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
</x-app-layout>
