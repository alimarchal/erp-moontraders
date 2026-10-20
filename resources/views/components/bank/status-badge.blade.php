@props(['status'])

@php
    $styles = [
        'unclassified' => 'bg-gray-100 text-gray-700',
        'classified' => 'bg-blue-100 text-blue-800',
        'pending_approval' => 'bg-amber-100 text-amber-800',
        'rejected' => 'bg-red-100 text-red-700',
        'posted' => 'bg-emerald-100 text-emerald-800',
        'reconciled' => 'bg-teal-100 text-teal-800',
        'flagged' => 'bg-orange-100 text-orange-800',
        'reversed' => 'bg-purple-100 text-purple-800',
    ];
    $label = \App\Models\BankStatementEntry::STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap '.($styles[$status] ?? 'bg-gray-100 text-gray-700')]) }}>
    {{ $label }}
</span>
