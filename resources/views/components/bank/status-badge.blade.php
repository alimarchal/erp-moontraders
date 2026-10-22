@props(['status'])

@php
    $tones = [
        'unclassified' => 'ak-status-slate', 'classified' => 'ak-status-blue', 'pending_approval' => 'ak-status-amber',
        'rejected' => 'ak-status-red', 'posted' => 'ak-status-green', 'reconciled' => 'ak-status-green',
        'flagged' => 'ak-status-amber', 'reversed' => 'ak-status-red',
    ];
    $label = \App\Models\BankStatementEntry::STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
@endphp

<span {{ $attributes->merge(['class' => 'ak-status '.($tones[$status] ?? 'ak-status-slate')]) }}><i aria-hidden="true"></i>{{ $label }}</span>
