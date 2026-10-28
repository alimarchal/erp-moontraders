{{-- Sales & Collection Details: four full-width tables (same order as the PDF), empty ones included. --}}
@php
    $exhibits = \App\Services\SettlementAttachmentService::exhibits($settlement);
    $exhibitNo = $exhibits->pluck('no', 'key');
    $attEnabled = \App\Services\SettlementAttachmentService::enabled();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d-M-y') : '-';
    $attCell = function (string $section, $row) use ($exhibitNo, $exhibits) {
        $no = $exhibitNo->get($section.':'.$row->id);
        $exhibit = $no ? $exhibits->firstWhere('no', $no) : null;

        return $exhibit;
    };
    $cols = fn (int $base) => $base + ($attEnabled ? 1 : 0);
@endphp

<div class="border-2 border-black rounded-lg px-2 pb-2 mt-2">
    <h3 class="font-bold text-md text-center text-black pb-1">Sales &amp; Collection Details</h3>

    <div class="space-y-3">
        {{-- 1. Credit sales --}}
        <div class="overflow-x-auto">
            <h4 class="font-bold text-sm border-x border-t border-black text-center bg-gray-100 py-0.5">1 - Credit Sales Breakdown
                <span class="font-normal text-xs">{{ $settlement->creditSales->count() }} customers &middot; Rs {{ $money($settlement->creditSales->sum('sale_amount')) }}</span></h4>
            <table class="report-table w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="text-center w-10">#</th><th class="text-left">Customer</th><th class="text-left">Address</th>
                        <th class="text-right">Prev. Balance</th><th class="text-right">Credit Sale</th><th class="text-right">New Balance</th>
                        <th class="text-left">Notes</th>@if ($attEnabled)<th class="text-center">Attachment</th>@endif
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    @forelse ($settlement->creditSales as $i => $row)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td style="white-space:normal">{{ $row->customer->customer_name ?? '-' }}</td>
                            <td style="white-space:normal">{{ $row->customer->address ?? '-' }}</td>
                            <td class="text-right">{{ $money($row->previous_balance) }}</td>
                            <td class="text-right font-bold">{{ $money($row->sale_amount) }}</td>
                            <td class="text-right">{{ $money($row->new_balance) }}</td>
                            <td style="white-space:normal" class="text-xs">{{ $row->notes ?: '-' }}</td>
                            @if ($attEnabled)<td class="text-center"><x-settlement-attachment-link :row="$row" :exhibit="$attCell('credit_sales', $row)" /></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $cols(7) }}" class="text-center italic text-gray-500">No credit sales recorded</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-bold tabular-nums">
                    <tr><td colspan="4" class="text-right">Total:</td><td class="text-right">{{ $money($settlement->creditSales->sum('sale_amount')) }}</td><td colspan="{{ $cols(2) }}"></td></tr>
                </tfoot>
            </table>
        </div>

        {{-- 2. Recoveries --}}
        <div class="overflow-x-auto">
            <h4 class="font-bold text-sm border-x border-t border-black text-center bg-gray-100 py-0.5">2 - Recoveries Breakdown (Cash + Online Bank Transfer From Customer)
                <span class="font-normal text-xs">{{ $settlement->recoveries->count() }} &middot; Rs {{ $money($settlement->recoveries->sum('amount')) }}</span></h4>
            <table class="report-table w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="text-center w-10">#</th><th class="text-left">Customer</th><th class="text-left">Address</th>
                        <th class="text-right">Prev. Balance</th><th class="text-right">Recovered</th><th class="text-center">Method</th><th class="text-right">New Balance</th>
                        <th class="text-left">Notes</th>@if ($attEnabled)<th class="text-center">Attachment</th>@endif
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    @forelse ($settlement->recoveries as $i => $row)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td style="white-space:normal">{{ $row->customer->customer_name ?? '-' }}</td>
                            <td style="white-space:normal">{{ $row->customer->address ?? '-' }}</td>
                            <td class="text-right">{{ $money($row->previous_balance) }}</td>
                            <td class="text-right font-bold">{{ $money($row->amount) }}</td>
                            <td class="text-center text-xs uppercase">{{ $row->payment_method === 'cash' ? 'Cash' : 'Bank' }}</td>
                            <td class="text-right">{{ $money($row->new_balance) }}</td>
                            <td style="white-space:normal" class="text-xs">{{ $row->notes ?: '-' }}</td>
                            @if ($attEnabled)<td class="text-center"><x-settlement-attachment-link :row="$row" :exhibit="$attCell('recoveries', $row)" /></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $cols(8) }}" class="text-center italic text-gray-500">No recoveries recorded</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-bold tabular-nums">
                    <tr><td colspan="4" class="text-right">Total:</td><td class="text-right">{{ $money($settlement->recoveries->sum('amount')) }}</td><td colspan="{{ $cols(3) }}"></td></tr>
                </tfoot>
            </table>
        </div>

        {{-- 3. Cheques --}}
        <div class="overflow-x-auto">
            <h4 class="font-bold text-sm border-x border-t border-black text-center bg-gray-100 py-0.5">3 - Cheque Payments
                <span class="font-normal text-xs">{{ $settlement->cheques->count() }} &middot; Rs {{ $money($settlement->cheques->sum('amount')) }}</span></h4>
            <table class="report-table w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="text-center w-10">#</th><th class="text-left">Date</th><th class="text-left">Cheque #</th><th class="text-left">Customer</th>
                        <th class="text-left">Bank</th><th class="text-left">Deposit Bank</th><th class="text-right">Amount</th>
                        <th class="text-left">Notes</th>@if ($attEnabled)<th class="text-center">Attachment</th>@endif
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    @forelse ($settlement->cheques as $i => $row)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>{{ $date($row->cheque_date) }}</td>
                            <td>{{ $row->cheque_number ?? '-' }}</td>
                            <td style="white-space:normal">{{ $row->customer->customer_name ?? '-' }}</td>
                            <td style="white-space:normal">{{ $row->bank_name ?? '-' }}</td>
                            <td style="white-space:normal">{{ $row->bankAccount->account_name ?? '-' }}</td>
                            <td class="text-right font-bold">{{ $money($row->amount) }}</td>
                            <td style="white-space:normal" class="text-xs">{{ $row->notes ?: '-' }}</td>
                            @if ($attEnabled)<td class="text-center"><x-settlement-attachment-link :row="$row" :exhibit="$attCell('cheques', $row)" /></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $cols(8) }}" class="text-center italic text-gray-500">No cheques recorded</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-bold tabular-nums">
                    <tr><td colspan="6" class="text-right">Total:</td><td class="text-right">{{ $money($settlement->cheques->sum('amount')) }}</td><td colspan="{{ $cols(1) }}"></td></tr>
                </tfoot>
            </table>
        </div>

        {{-- 4. Bank transfers --}}
        <div class="overflow-x-auto">
            <h4 class="font-bold text-sm border-x border-t border-black text-center bg-gray-100 py-0.5">4 - Bank Transfers / Online From Customer
                <span class="font-normal text-xs">{{ $settlement->bankTransfers->count() }} &middot; Rs {{ $money($settlement->bankTransfers->sum('amount')) }}</span></h4>
            <table class="report-table w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="text-center w-10">#</th><th class="text-left">Date</th><th class="text-left">Customer</th><th class="text-left">Bank Account</th>
                        <th class="text-left">Ref #</th><th class="text-right">Amount</th>
                        <th class="text-left">Notes</th>@if ($attEnabled)<th class="text-center">Attachment</th>@endif
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    @forelse ($settlement->bankTransfers as $i => $row)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>{{ $date($row->transfer_date) }}</td>
                            <td style="white-space:normal">{{ $row->customer->customer_name ?? '-' }}</td>
                            <td style="white-space:normal">{{ $row->bankAccount->account_name ?? '-' }}</td>
                            <td>{{ $row->reference_number ?? '-' }}</td>
                            <td class="text-right font-bold">{{ $money($row->amount) }}</td>
                            <td style="white-space:normal" class="text-xs">{{ $row->notes ?: '-' }}</td>
                            @if ($attEnabled)<td class="text-center"><x-settlement-attachment-link :row="$row" :exhibit="$attCell('bank_transfers', $row)" /></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $cols(7) }}" class="text-center italic text-gray-500">No bank transfers recorded</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-bold tabular-nums">
                    <tr><td colspan="5" class="text-right">Total:</td><td class="text-right">{{ $money($settlement->bankTransfers->sum('amount')) }}</td><td colspan="{{ $cols(1) }}"></td></tr>
                </tfoot>
            </table>
        </div>

        {{-- Attachments list (on screen only; the PDF carries the images) --}}
        @if ($exhibits->isNotEmpty())
            <div class="print:hidden border border-black rounded">
                <h4 class="font-bold text-sm text-center bg-gray-100 py-0.5 border-b border-black">Attachments <span class="font-normal text-xs">{{ $exhibits->count() }} {{ \Illuminate\Support\Str::plural('file', $exhibits->count()) }}</span></h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 p-2">
                    @foreach ($exhibits as $exhibit)
                        <button type="button" onclick="SettlementAttachments.previewUrl(@js($exhibit['url']), @js($exhibit['ext']), @js('Att. '.$exhibit['no'].' - '.$exhibit['label'].': '.$exhibit['title']))"
                            class="flex items-center gap-3 text-left border border-gray-300 rounded p-2 hover:bg-green-50">
                            @if ($exhibit['is_pdf'])
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded bg-red-50 text-xs font-bold text-red-600">PDF</span>
                            @else
                                <img src="{{ $exhibit['url'] }}" alt="Att. {{ $exhibit['no'] }}" loading="lazy" class="h-14 w-14 shrink-0 rounded object-cover border border-gray-200">
                            @endif
                            <span class="min-w-0 text-xs">
                                <span class="block font-bold text-green-800">Att. {{ $exhibit['no'] }} &middot; {{ $exhibit['label'] }}</span>
                                <span class="block truncate">{{ $exhibit['title'] }}</span>
                                <span class="block text-gray-500">{{ $exhibit['reference'] }} &middot; Rs {{ $money($exhibit['amount']) }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
