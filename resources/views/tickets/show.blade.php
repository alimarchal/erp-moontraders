@php
    use App\Enums\TicketStatus;
    use App\Enums\TicketType;

    $money = fn ($value) => $value === null ? '—' : number_format((float) $value, 2);
    $signed = fn ($value) => ($value >= 0 ? '+' : '').number_format((float) $value, 2);
    $historyMeta = [
        'created' => ['Submitted', 'bg-indigo-500'],
        'updated' => ['Edited', 'bg-gray-400'],
        'approved' => ['Approved', 'bg-emerald-500'],
        'rejected' => ['Rejected', 'bg-red-500'],
    ];
    $canReview = $ticket->isPending() && auth()->user()->can('ticket-approve');
    $canEdit = $ticket->isPending() && auth()->user()->can('ticket-edit')
        && ($ticket->created_by === auth()->id() || auth()->user()->isTicketAdmin());
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Ticket {{ $ticket->ticket_number }}" backRoute="tickets.index" :showSearch="false" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <x-validation-errors class="mb-4" />

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                <div class="lg:col-span-2 space-y-6">
                    {{-- Title card --}}
                    <div class="bg-white shadow-xl sm:rounded-xl p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ticket->type->iconPath() }}" /></svg>
                                </span>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">{{ $ticket->title }}</h2>
                                    <p class="text-sm text-gray-500">{{ $ticket->type->label() }} · {{ $ticket->supplier->supplier_name ?? 'No company' }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $ticket->status->badgeClasses() }}">{{ $ticket->status->label() }}</span>
                        </div>
                        @if ($ticket->description)
                            <p class="mt-4 whitespace-pre-line rounded-lg bg-gray-50 p-4 text-sm text-gray-700">{{ $ticket->description }}</p>
                        @endif
                    </div>

                    {{-- Requested changes --}}
                    @foreach ($ticket->items as $item)
                        <div class="bg-white shadow-xl sm:rounded-xl overflow-hidden">
                            @if ($ticket->type === TicketType::NewSku)
                                @php $data = $item->new_sku_data; @endphp
                                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                                    <span class="font-semibold text-gray-900">{{ $data['product_code'] ?? '' }}</span>
                                    <span class="text-gray-600">— {{ $data['product_name'] ?? '' }}</span>
                                    <span class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">New SKU</span>
                                </div>
                                <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-4 p-5 text-sm">
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
                                        <div><dt class="text-xs uppercase tracking-wide text-gray-500">{{ $term }}</dt><dd class="mt-0.5 font-medium text-gray-900">{{ $value ?: '—' }}</dd></div>
                                    @endforeach
                                </dl>
                            @elseif ($ticket->type === TicketType::ReactivateSku)
                                <div class="flex flex-wrap items-center justify-between gap-3 p-5">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $item->product->product_code ?? '' }} — {{ $item->product->product_name ?? 'Deleted product' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="rounded-full px-3 py-1 font-semibold {{ $item->old_is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $item->old_is_active ? 'Active' : 'Inactive' }}</span>
                                        <span class="text-gray-400">→</span>
                                        <span class="rounded-full px-3 py-1 font-semibold {{ $item->new_is_active ? 'bg-emerald-600 text-white' : 'bg-gray-700 text-white' }}">{{ $item->new_is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>
                                </div>
                            @else
                                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 font-semibold text-gray-900">
                                    {{ $item->product->product_code ?? '' }} <span class="font-normal text-gray-600">— {{ $item->product->product_name ?? 'Deleted product' }}</span>
                                </div>
                                <table class="w-full text-sm">
                                    <thead class="text-xs uppercase tracking-wide text-gray-500">
                                        <tr><th class="px-5 py-2 text-left">Field</th><th class="px-3 py-2 text-right">Old</th><th class="px-3 py-2 text-right">New</th><th class="px-5 py-2 text-right">Difference</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($item->priceChanges() as $change)
                                            <tr class="border-t border-gray-100">
                                                <td class="px-5 py-2.5 font-medium text-gray-800">{{ $change['label'] }}</td>
                                                <td class="px-3 py-2.5 text-right tabular-nums text-gray-500">{{ $money($change['old']) }}</td>
                                                <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-gray-900">{{ $money($change['new']) }}</td>
                                                <td class="px-5 py-2.5 text-right">
                                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums {{ $change['difference'] > 0 ? 'bg-emerald-50 text-emerald-700' : ($change['difference'] < 0 ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-600') }}">{{ $signed($change['difference']) }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @if ($item->new_unit_sell_price !== null)
                                    <div class="border-t border-gray-100 px-5 py-3 text-sm">
                                        <span class="text-xs uppercase tracking-wide text-gray-500">Selling price applies to</span>
                                        <div class="mt-1 flex flex-wrap gap-2">
                                            @if ($item->apply_to_all_batches)
                                                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">All batches with stock</span>
                                            @else
                                                @foreach ($item->batch_ids as $batchId)
                                                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $batchLabels[$batchId] ?? "Batch #$batchId" }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endif
                            @if ($item->remarks)
                                <p class="border-t border-gray-100 px-5 py-3 text-sm text-gray-600"><span class="font-semibold">Remarks:</span> {{ $item->remarks }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Sidebar --}}
                <aside class="space-y-6 lg:sticky lg:top-6">
                    @if ($canReview)
                        <form method="POST" action="{{ route('tickets.approve', $ticket) }}" class="bg-white shadow-xl sm:rounded-xl p-5 ring-2 ring-indigo-100">
                            @csrf
                            <h3 class="font-semibold text-gray-900">Review</h3>
                            <p class="mt-1 text-xs text-gray-500">Approving applies the change to live data immediately.</p>
                            <textarea name="review_remarks" rows="3" placeholder="Remarks (required to reject)"
                                class="mt-3 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('review_remarks') }}</textarea>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="submit" formaction="{{ route('tickets.reject', $ticket) }}" onclick="return confirm('Reject this ticket?')"
                                    class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Reject</button>
                                <button type="submit" onclick="return confirm('Approve and apply these changes to the system?')"
                                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700">Approve</button>
                            </div>
                        </form>
                    @endif

                    <div class="bg-white shadow-xl sm:rounded-xl p-5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Details</h3>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div><dt class="text-gray-500">Raised by</dt><dd class="font-medium text-gray-900">{{ $ticket->creator->name ?? '—' }}</dd><dd class="text-xs text-gray-500">{{ $ticket->created_at->format('d M Y, H:i') }}</dd></div>
                            @if ($ticket->reviewer)
                                <div><dt class="text-gray-500">{{ $ticket->status->label() }} by</dt><dd class="font-medium text-gray-900">{{ $ticket->reviewer->name }}</dd><dd class="text-xs text-gray-500">{{ $ticket->reviewed_at->format('d M Y, H:i') }}</dd></div>
                            @endif
                            @if ($ticket->review_remarks)
                                <div><dt class="text-gray-500">Review remarks</dt><dd class="text-gray-900">{{ $ticket->review_remarks }}</dd></div>
                            @endif
                        </dl>
                        @if ($canEdit || ($ticket->isPending() && auth()->user()->can('ticket-delete')))
                            <div class="mt-4 flex gap-2 border-t border-gray-100 pt-4">
                                @if ($canEdit)
                                    <a href="{{ route('tickets.edit', $ticket) }}" class="flex-1 rounded-lg border border-gray-300 px-3 py-1.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">Edit</a>
                                @endif
                                @can('ticket-delete')
                                    <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="flex-1" onsubmit="return confirm('Delete this ticket?')">
                                        @csrf @method('DELETE')
                                        <button class="w-full rounded-lg border border-red-200 px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-50">Delete</button>
                                    </form>
                                @endcan
                            </div>
                        @endif
                    </div>

                    <div class="bg-white shadow-xl sm:rounded-xl p-5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ticket history</h3>
                        <ol class="mt-4 space-y-5 border-l-2 border-gray-100 pl-5">
                            @foreach ($ticket->histories as $history)
                                @php [$text, $dot] = $historyMeta[$history->action] ?? [ucfirst($history->action), 'bg-gray-400']; @endphp
                                <li class="relative text-sm">
                                    <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full ring-4 ring-white {{ $dot }}"></span>
                                    <p class="font-semibold text-gray-900">{{ $text }}</p>
                                    <p class="text-xs text-gray-500">{{ $history->user->name ?? 'Unknown' }} · {{ $history->created_at->format('d M Y, H:i') }}</p>
                                    @if ($history->remarks)
                                        <p class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-gray-700">{{ $history->remarks }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
