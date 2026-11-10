@props(['status'])

@php
    $tones = [
        'unclassified' => 'ak-status-slate', 'classified' => 'ak-status-blue', 'pending_approval' => 'ak-status-amber',
        'rejected' => 'ak-status-red', 'posted' => 'ak-status-green', 'reconciled' => 'ak-status-green',
        'flagged' => 'ak-status-amber', 'reversed' => 'ak-status-red',
    ];
    // The same plain words as the tabs of the register, so a row and its tab never use two names for one step.
    $plain = ['unclassified' => 'To classify', 'classified' => 'Ready to post', 'pending_approval' => 'Waiting for approval'];
    $label = $plain[$status] ?? \App\Models\BankStatementEntry::STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
@endphp

<span {{ $attributes->merge(['class' => 'ak-status '.($tones[$status] ?? 'ak-status-slate')]) }}><i aria-hidden="true"></i>{{ $label }}</span>
