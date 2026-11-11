{{-- The Help button of a Bank Register screen: opens the Help window (x-bank.print-styles) with only the parts of the
     Urdu guide about this screen, and on the Register about the open tab. --}}
@props(['title' => 'Bank Register'])

@php
    // Which sections of the Urdu guide (bank-register/help.blade.php, by section id) each screen's Help window shows.
    // On the Register the open tab decides, so the help is about the step the person is looking at.
    $registerTabTopics = [
        'unclassified' => ['classify', 'register', 'filters'],
        'classified' => ['maker', 'attach', 'register'],
        'pending_approval' => ['checker', 'maker', 'attach'],
        'rejected' => ['checker', 'maker', 'classify'],
        'posted' => ['posting', 'reverse', 'register'],
        'reconciled' => ['recon', 'posting'],
        'reversed' => ['reverse', 'maker'],
    ];
    $helpTopics = match (true) {
        request()->routeIs('bank-statement-entries.index') => $registerTabTopics[request()->input('filter.status')] ?? ['daily', 'register', 'filters', 'lifecycle'],
        request()->routeIs('bank-statement-entries.create') => ['manual', 'attach'],
        request()->routeIs('bank-statement-entries.edit') => ['classify', 'attach', 'maker'],
        request()->routeIs('bank-statement-entries.show') => ['register', 'maker', 'checker', 'reverse'],
        request()->routeIs('bank-statement-imports.*') => ['import', 'daily'],
        request()->routeIs('bank-receipt-scans.*') => ['scans', 'attach'],
        request()->routeIs('bank-reconciliations.*') => ['recon'],
        request()->routeIs('bank-cheques.*') => ['setup-cheques'],
        request()->routeIs('bank-entry-categories.*') => ['setup-categories'],
        request()->routeIs('bank-party-aliases.*') => ['setup-aliases'],
        request()->routeIs('bank-entry-rules.*') => ['setup-rules', 'setup-aliases'],
        request()->routeIs('reports.bank.*') => ['reports', 'print'],
        default => ['what', 'daily', 'faq'],
    };
    $registerTab = request()->routeIs('bank-statement-entries.index') ? request()->input('filter.status') : null;
    $helpTitle = $registerTab ? $title.' › '.(['unclassified' => 'To classify', 'classified' => 'Ready to post', 'pending_approval' => 'Waiting for approval'][$registerTab] ?? \App\Models\BankStatementEntry::STATUSES[$registerTab] ?? $registerTab) : $title;
@endphp

<button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('bk-help', { topics: {{ \Illuminate\Support\Js::from($helpTopics) }}, title: {{ \Illuminate\Support\Js::from($helpTitle) }} })" aria-haspopup="dialog" title="How this screen works, in Urdu">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
    Help <span dir="rtl" lang="ur">(اردو)</span>
</button>
