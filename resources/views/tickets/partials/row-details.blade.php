{{--
    The "Details" cell of the tickets list: what a reviewer needs at a glance for each ticket type.
    Expects $ticket (items loaded), optional $limit (lines shown, default 3) and $lookups (products, batches, warehouses, suppliers keyed by id,
    built once per page by TicketController::detailLookups, so there is no query per row).
--}}
@php
    use App\Enums\TicketStatus;
    use App\Enums\TicketType;
    use Illuminate\Support\Str;

    $money = fn ($value) => number_format((float) $value, 2);
    $signed = fn ($value) => ((float) $value > 0 ? '+' : ((float) $value < 0 ? '−' : '')).number_format(abs((float) $value), 2);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3), '0'), '.');
    $signedQty = fn ($value) => ((float) $value > 0 ? '+' : ((float) $value < 0 ? '−' : '')).$qty(abs((float) $value));
    $tone = fn ($value) => (float) $value > 0 ? 'tk-up' : ((float) $value < 0 ? 'tk-down' : 'tk-flat');
    $product = function ($id) use ($lookups) {
        $found = is_numeric($id) ? ($lookups['products'][(int) $id] ?? null) : null;

        return $found ? ['code' => $found->product_code, 'name' => $found->product_name] : ['code' => '—', 'name' => 'Deleted product'];
    };
    $items = $ticket->items;
    // The list shows the first few lines; the Quick look passes a larger limit to show them all.
    $shown = $limit ?? 3;
@endphp

<div class="tk-det">
    @switch($ticket->type)
        @case(TicketType::PriceUpdate)
            @foreach ($items->take($shown) as $item)
                @php $p = $product($item->product_id); $batchCount = count((array) ($item->batch_ids ?? [])); @endphp
                <div class="tk-det-line">
                    <span class="tk-det-sku">{{ $p['code'] }}</span> @if (mb_strtolower(trim($p['name'])) !== mb_strtolower(trim($p['code'])))<span class="tk-det-name" title="{{ $p['name'] }}">{{ Str::limit($p['name'], 38) }}</span>@endif
                    <span class="tk-det-tag">{{ $item->apply_to_all_batches || $batchCount === 0 ? 'All batches' : $batchCount.' '.Str::plural('batch', $batchCount) }}</span>
                    <div class="tk-det-changes">
                        @forelse ($item->priceChanges() as $change)
                            <span class="tk-det-change">
                                <span class="ak-muted">{{ Str::replace(' Price', '', $change['label']) }}</span>
                                {{ $change['old'] === null ? '—' : $money($change['old']) }} → <b>{{ $money($change['new']) }}</b>
                                <span class="{{ $tone($change['difference']) }}" aria-label="{{ $change['difference'] > 0 ? 'up' : ($change['difference'] < 0 ? 'down' : 'no change') }}">{{ $change['difference'] > 0 ? '↑' : ($change['difference'] < 0 ? '↓' : '=') }}</span>
                            </span>
                        @empty
                            <span class="ak-muted">No change</span>
                        @endforelse
                    </div>
                </div>
            @endforeach
            @break

        @case(TicketType::StockAdjustment)
            @foreach ($items as $item)
                @php
                    $data = (array) ($item->payload ?? []);
                    $lines = collect($data['items'] ?? []);
                    $total = $lines->sum(fn ($line) => (float) ($line['adjustment_value'] ?? 0) + (float) ($line['revaluation_value'] ?? 0));
                    $warehouse = is_numeric($data['warehouse_id'] ?? null) ? ($lookups['warehouses'][(int) $data['warehouse_id']] ?? null) : null;
                @endphp
                <div class="tk-det-head">
                    <span class="tk-det-tag">{{ Str::headline($data['adjustment_type'] ?? 'adjustment') }}</span>
                    <span>{{ $warehouse ?? 'Unknown warehouse' }}</span>
                    <span class="ak-muted">· {{ $lines->count() }} {{ Str::plural('line', $lines->count()) }}</span>
                </div>
                <table class="tk-det-table">
                    <tbody>
                        @foreach ($lines->take($shown) as $line)
                            @php
                                $p = $product($line['product_id'] ?? null);
                                $batch = is_numeric($line['stock_batch_id'] ?? null) ? ($lookups['batches'][(int) $line['stock_batch_id']] ?? null) : null;
                                $value = (float) ($line['adjustment_value'] ?? 0) + (float) ($line['revaluation_value'] ?? 0);
                            @endphp
                            <tr>
                                <td><span class="tk-det-sku" title="{{ $p['name'] }}">{{ $p['code'] }}</span> <span class="ak-muted">{{ $batch ?? '—' }}</span></td>
                                <td class="ak-num">{{ $qty($line['system_quantity'] ?? 0) }} → {{ $qty($line['actual_quantity'] ?? 0) }}</td>
                                <td class="ak-num {{ $tone($line['adjustment_quantity'] ?? 0) }}">{{ $signedQty($line['adjustment_quantity'] ?? 0) }}</td>
                                <td class="ak-num {{ $tone($value) }}">{{ $signed($value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="tk-det-foot">
                    @if ($lines->count() > $shown)<span class="ak-muted">+{{ $lines->count() - $shown }} more</span> ·@endif
                    <span>Total value <b class="{{ $tone($total) }}">{{ $signed($total) }}</b></span>
                    @if ($ticket->status === TicketStatus::Approved && ! empty($data['stock_adjustment_id']))
                        ·
                        @can('stock-adjustment-list')
                            <a href="{{ route('stock-adjustments.show', $data['stock_adjustment_id']) }}" class="ak-primary-link">{{ $data['stock_adjustment_number'] ?? 'Posted adjustment' }}</a>
                        @else
                            <span>{{ $data['stock_adjustment_number'] ?? 'Posted' }}</span>
                        @endcan
                    @endif
                </div>
            @endforeach
            @break

        @case(TicketType::NewSku)
            @foreach ($items->take($shown) as $item)
                @php $data = (array) ($item->payload ?? []); @endphp
                <div class="tk-det-line">
                    <span class="tk-det-sku">{{ $data['product_code'] ?? '—' }}</span> <span class="tk-det-name" title="{{ $data['product_name'] ?? '' }}">{{ Str::limit($data['product_name'] ?? '', 38) }}</span>
                    <div class="tk-det-changes">
                        <span class="tk-det-change"><span class="ak-muted">Sell</span> <b>{{ isset($data['unit_sell_price']) ? $money($data['unit_sell_price']) : '—' }}</b></span>
                        <span class="tk-det-change"><span class="ak-muted">Cost</span> <b>{{ isset($data['cost_price']) ? $money($data['cost_price']) : '—' }}</b></span>
                    </div>
                </div>
            @endforeach
            @break

        @case(TicketType::ReactivateSku)
            @foreach ($items->take($shown) as $item)
                @php $p = $product($item->product_id); @endphp
                <div class="tk-det-line">
                    <span class="tk-det-sku">{{ $p['code'] }}</span> @if (mb_strtolower(trim($p['name'])) !== mb_strtolower(trim($p['code'])))<span class="tk-det-name" title="{{ $p['name'] }}">{{ Str::limit($p['name'], 38) }}</span>@endif
                    <span class="ak-muted">→</span> <b class="{{ $item->new_is_active ? 'tk-up' : 'tk-down' }}">{{ $item->new_is_active ? 'Active' : 'Inactive' }}</b>
                </div>
            @endforeach
            @break

        @case(TicketType::ClaimEntry)
        @case(TicketType::LedgerEntry)
            @foreach ($items->take($shown) as $item)
                @php
                    $data = (array) ($item->payload ?? []);
                    $supplier = is_numeric($data['supplier_id'] ?? null) ? ($lookups['suppliers'][(int) $data['supplier_id']] ?? null) : null;
                    $amounts = $ticket->type === TicketType::ClaimEntry
                        ? ['Amount' => $data['amount'] ?? null]
                        : collect(['Invoice' => 'invoice_amount', 'Online' => 'online_amount', 'Expenses' => 'expenses_amount', 'Claim adjust' => 'claim_adjust_amount', '0.50%' => 'za_point_five_percent_amount', 'Opening' => 'opening_balance'])
                            ->map(fn ($key) => $data[$key] ?? null)->filter(fn ($value) => is_numeric($value) && (float) $value != 0)->all();
                @endphp
                <div class="tk-det-line">
                    <span class="tk-det-tag">{{ $ticket->type === TicketType::ClaimEntry ? Str::headline($data['transaction_type'] ?? 'claim') : trim(($data['document_type'] ?? 'Entry').' '.($data['document_number'] ?? '')) }}</span>
                    @foreach ($amounts as $label => $amount)
                        <span class="tk-det-change"><span class="ak-muted">{{ $label }}</span> <b>{{ is_numeric($amount) ? $money($amount) : '—' }}</b></span>
                    @endforeach
                    <div class="ak-muted">{{ $supplier ?? 'No supplier' }}@if (! empty($data['reference_number'])) · Ref {{ $data['reference_number'] }}@endif @if (! empty($data['claim_month'])) · {{ $data['claim_month'] }}@endif</div>
                </div>
            @endforeach
            @break

        @case(TicketType::NewCustomer)
            @foreach ($items->take($shown) as $item)
                @php $data = (array) ($item->payload ?? []); @endphp
                <div class="tk-det-line">
                    <span class="tk-det-sku">{{ $data['customer_code'] ?? '—' }}</span> <span class="tk-det-name">{{ $data['customer_name'] ?? '' }}</span>
                    <div class="ak-muted">{{ collect([($data['business_name'] ?? null) !== ($data['customer_name'] ?? null) ? ($data['business_name'] ?? null) : null, $data['city'] ?? null, $data['channel_type'] ?? null])->filter()->implode(' · ') ?: '—' }}</div>
                </div>
            @endforeach
            @break
    @endswitch

    @if ($ticket->type !== TicketType::StockAdjustment && $items->count() > $shown)
        <div class="tk-det-foot ak-muted">+{{ $items->count() - $shown }} more</div>
    @endif
</div>
