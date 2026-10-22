<x-app-layout>
    <x-slot name="header">
        <x-bank.head :title="'Transaction #'.$entry->id" :sub="$entry->bankAccount->account_name.' · '.$entry->posting_date->format('d M Y')" :crumbs="['Transaction' => null]">
            <a href="{{ route('bank-statement-entries.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page">
        <x-bank.subnav inside />
        <x-bank.print-title title="Bank Entry" :subtitle="'Transaction #'.$entry->id" />
        <x-status-message />
        <section class="ak-card" style="padding:22px; max-width:820px">
            @include('bank-statement-entries.partials.detail')
        </section>
    </div>
</x-app-layout>
