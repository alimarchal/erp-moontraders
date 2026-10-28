{{-- Expense Details: AMR powder, AMR liquid, advance tax and percentage expense as full-width tables (empty ones included). --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $detailTables = [
        ['title' => 'AMR Powder (5252)', 'rows' => $settlement->amrPowders, 'type' => 'amr', 'empty' => 'No AMR powder recorded'],
        ['title' => 'AMR Liquid (5262)', 'rows' => $settlement->amrLiquids, 'type' => 'amr', 'empty' => 'No AMR liquid recorded'],
        ['title' => 'Advance Tax'.($usesAdvanceTaxIncome ? ' - Income' : '').' Benefits To NTN Customer (1161)', 'rows' => $advanceTaxEntries, 'type' => 'tax', 'empty' => 'No advance tax recorded'],
        ['title' => 'Percentage Expense (5223)', 'rows' => $settlement->percentageExpenses, 'type' => 'pct', 'empty' => 'No percentage expense recorded'],
    ];
@endphp

<div class="space-y-3">
    @foreach ($detailTables as $table)
        @php
            $amountField = $table['type'] === 'tax' ? 'tax_amount' : 'amount';
            $cols = ['amr' => 6, 'tax' => 7, 'pct' => 5][$table['type']];
        @endphp
        <div class="overflow-x-auto">
            <h4 class="font-bold text-sm border-x border-t border-black text-center bg-gray-100 py-0.5">{{ $table['title'] }}
                <span class="font-normal text-xs">{{ $table['rows']->count() }} {{ $table['type'] === 'amr' ? 'items' : 'customers' }} &middot; Rs {{ $money($table['rows']->sum($amountField)) }}</span></h4>
            <table class="report-table w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="text-center w-10">#</th>
                        @if ($table['type'] === 'amr')
                            <th class="text-left">Product</th><th class="text-left">Batch</th><th class="text-right">Qty</th><th class="text-right">Amount</th>
                        @elseif ($table['type'] === 'tax')
                            <th class="text-left">Customer</th><th class="text-left">Invoice #</th><th class="text-right">Sale Amount</th><th class="text-right">Rate %</th><th class="text-right">Tax</th>
                        @else
                            <th class="text-left">Customer</th><th class="text-left">Invoice #</th><th class="text-right">Amount</th>
                        @endif
                        <th class="text-left">Notes</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    @forelse ($table['rows'] as $i => $row)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            @if ($table['type'] === 'amr')
                                <td style="white-space:normal">{{ $row->product->product_name ?? '-' }}</td>
                                <td>{{ $row->batch_code ?: '-' }}</td>
                                <td class="text-right">{{ $money($row->quantity) }}</td>
                                <td class="text-right font-bold">{{ $money($row->amount) }}</td>
                            @elseif ($table['type'] === 'tax')
                                <td style="white-space:normal">{{ $row->customer->customer_name ?? '-' }} @if ($row->customer?->customer_code)({{ $row->customer->customer_code }})@endif</td>
                                <td>{{ $row->invoice_number ?: '-' }}</td>
                                <td class="text-right">{{ $money($row->sale_amount) }}</td>
                                <td class="text-right">{{ $money($row->tax_rate) }}</td>
                                <td class="text-right font-bold">{{ $money($row->tax_amount) }}</td>
                            @else
                                <td style="white-space:normal">{{ $row->customer->customer_name ?? '-' }} @if ($row->customer?->customer_code)({{ $row->customer->customer_code }})@endif</td>
                                <td>{{ $row->invoice_number ?: '-' }}</td>
                                <td class="text-right font-bold">{{ $money($row->amount) }}</td>
                            @endif
                            <td style="white-space:normal" class="text-xs">{{ $row->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $cols }}" class="text-center italic text-gray-500">{{ $table['empty'] }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-bold tabular-nums">
                    <tr>
                        <td colspan="{{ $table['type'] === 'amr' ? 4 : ($table['type'] === 'tax' ? 5 : 3) }}" class="text-right">Total:</td>
                        <td class="text-right">{{ $money($table['rows']->sum($amountField)) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach
</div>
