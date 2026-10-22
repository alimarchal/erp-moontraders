<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Reports" sub="Company and category summaries, ledgers, reconciliation, receipts, approvals and the audit log." :crumbs="['Reports' => null]">
            <a href="{{ route('reports.index') }}" class="ak-btn ak-btn-outline">All system reports</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page">
        <x-bank.subnav inside />
        <x-bank.print-title title="Bank Statement Reports" />

        <section class="ak-card">
            @if (count($reports))
                <div style="display:grid; gap:12px; padding:16px; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))">
                    @foreach ($reports as $slug => $report)
                        <a href="{{ route('reports.bank.show', $slug) }}" class="ak-kpi" style="display:block; text-decoration:none">
                            <span class="ak-kpi-label" style="font-size:15px; color:var(--ak-text)">{{ $report['title'] }}</span>
                            <span class="ak-kpi-hint" style="display:block; margin-top:4px">{{ $report['description'] }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="ak-empty"><h2>No reports available</h2><p>You do not have access to any bank report.</p></div>
            @endif
        </section>
    </div>
</x-app-layout>
