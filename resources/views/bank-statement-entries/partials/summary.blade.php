{{-- The entry an action window is about, so the person sees what they are confirming. --}}
<table class="bk-t">
    <tbody>
        <tr><th>{{ $entry->direction === 'credit' ? 'Received' : 'Paid out' }} (PKR)</th><td class="n b">{{ number_format($entry->amount, 2) }}</td></tr>
        <tr><th>Date</th><td class="n">{{ $entry->posting_date->format('d-m-Y') }}</td></tr>
        <tr><th>Account</th><td class="n">{{ $entry->bankAccount->account_name }}</td></tr>
        <tr><th>Party</th><td class="n" dir="auto">{{ $entry->company?->supplier_name ?? $entry->party_name_in_statement ?? '—' }}</td></tr>
        @if ($entry->receipt_no)
            <tr><th>Receipt no.</th><td class="n">{{ $entry->receipt_no }}</td></tr>
        @endif
    </tbody>
</table>
