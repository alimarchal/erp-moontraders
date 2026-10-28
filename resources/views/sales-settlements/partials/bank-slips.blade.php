{{-- Bank slips / deposits by salesman: full-width table with reference, notes and attachment. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $attEnabled = \App\Services\SettlementAttachmentService::enabled();
    $slipExhibits = \App\Services\SettlementAttachmentService::exhibits($settlement)->where('section', 'bank_slips')->keyBy('key');
@endphp
<div class="overflow-x-auto mt-2">
    <h4 class="font-bold text-sm border-x border-t border-black text-center bg-gray-100 py-0.5">Bank Slips / Deposits to Bank From Salesman
        <span class="font-normal text-xs">{{ $settlement->bankSlips->count() }} &middot; Rs {{ $money($settlement->bankSlips->sum('amount')) }}</span></h4>
    <table class="report-table w-full">
        <thead>
            <tr class="bg-gray-100">
                <th class="text-center w-10">#</th><th class="text-left">Bank</th><th class="text-left">Ref #</th><th class="text-center">Date</th>
                <th class="text-right">Amount</th><th class="text-left">Notes</th>@if ($attEnabled)<th class="text-center">Attachment</th>@endif
            </tr>
        </thead>
        <tbody class="tabular-nums">
            @forelse ($settlement->bankSlips as $i => $slip)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td style="white-space:normal">{{ $slip->bankAccount->account_name ?? '-' }}</td>
                    <td>{{ $slip->reference_number ?: '-' }}</td>
                    <td class="text-center">{{ $slip->deposit_date ? \Carbon\Carbon::parse($slip->deposit_date)->format('d-M-y') : '-' }}</td>
                    <td class="text-right font-bold">{{ $money($slip->amount) }}</td>
                    <td style="white-space:normal" class="text-xs">{{ $slip->notes ?: '-' }}</td>
                    @if ($attEnabled)<td class="text-center"><x-settlement-attachment-link :row="$slip" :exhibit="$slipExhibits->get('bank_slips:'.$slip->id)" /></td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $attEnabled ? 7 : 6 }}" class="text-center italic text-gray-500">No bank slips recorded</td></tr>
            @endforelse
        </tbody>
        <tfoot class="bg-gray-50 font-bold tabular-nums">
            <tr><td colspan="4" class="text-right">Total Bank Slips:</td><td class="text-right">{{ $money($settlement->bankSlips->sum('amount')) }}</td><td colspan="{{ $attEnabled ? 2 : 1 }}"></td></tr>
        </tfoot>
    </table>
</div>
