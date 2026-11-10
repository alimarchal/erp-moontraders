<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Bank Register Reports" sub="Every report of the bank register in one place." :crumbs="['Reports' => null]" />
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <x-status-message />

        <section class="bk-cards" aria-label="Reports">
            @foreach ($reports as $slug => $report)
                <a href="{{ route('reports.bank.show', $slug) }}" class="bk-card-link">
                    <b>{{ $report['title'] }}</b>
                    <span>{{ $report['description'] }}</span>
                </a>
            @endforeach
        </section>
    </div>
</x-app-layout>
