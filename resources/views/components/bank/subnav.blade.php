{{-- The Bank Register menu: one panel, opened with the Menu button of the page head (as the Filters panel is),
     instead of a long row of links on every screen. Each person sees only what they may open. --}}
@props(['button' => false])

@php
    $user = auth()->user();
    $pendingScans = $user?->can('bank-receipt-scan-list') ? \App\Models\BankReceiptScan::where('status', 'pending')->count() : 0;
    $onRegister = request()->routeIs('bank-statement-entries.index');
    $groups = [
        'Daily work' => [
            ['label' => 'Register', 'hint' => 'Every bank, cash and wallet line', 'route' => 'bank-statement-entries.index', 'active' => ['bank-statement-entries.*'], 'can' => 'bank-statement-entry-list'],
            ['label' => 'Receipt scans', 'hint' => 'Scanned receipts to link to entries', 'route' => 'bank-receipt-scans.index', 'active' => ['bank-receipt-scans.*'], 'can' => 'bank-receipt-scan-list', 'badge' => $pendingScans],
            ['label' => 'Reconciliation', 'hint' => 'Match the statement with the books', 'route' => 'bank-reconciliations.index', 'active' => ['bank-reconciliations.*'], 'can' => 'bank-reconciliation-list'],
            ['label' => 'Cheques', 'hint' => 'Issued and received cheques', 'route' => 'bank-cheques.index', 'active' => ['bank-cheques.*'], 'can' => 'bank-cheque-list'],
        ],
        'Reports' => [
            ['label' => 'Reports', 'hint' => 'Entries, summary, reconciliation, audit log', 'route' => 'reports.bank.index', 'active' => ['reports.bank.*'], 'canAny' => \App\Services\BankRegister\BankRegisterPermissions::REPORTS],
        ],
        // the master data lives under Settings; this one item takes the person there
        'Setup' => [
            ['label' => 'Bank setup in Settings', 'hint' => 'Categories, party aliases and auto-classify rules', 'route' => 'settings.index', 'fragment' => '#bank-register-setup', 'active' => \App\Services\BankRegister\BankRegisterPermissions::SETUP_ROUTES, 'canAny' => ['bank-entry-category-list', 'bank-party-alias-list', 'bank-entry-rule-list']],
        ],
    ];
    $groups = collect($groups)->map(fn (array $items) => array_values(array_filter($items, fn (array $item) => (! isset($item['can']) || $user?->can($item['can'])) && (! isset($item['canAny']) || $user?->canAny($item['canAny'])))))->filter();
@endphp

<div class="bank-noprint max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{ open: false }" @bk-toggle-menu.window="open = ! open" @keydown.escape.window="open = false">
    @if ($button)
        <div class="bk-menu-standalone">
            <a href="{{ route('bank-statement-entries.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Register</a>
            <x-bank.help-button :title="trim(\Illuminate\Support\Str::headline(str_replace(['bank-', '.index', '.create', '.edit'], '', request()->route()?->getName() ?? '')))" />
            <x-bank.menu-button />
        </div>
    @endif

    <nav id="bk-menu-panel" class="ak-card bk-menu" aria-label="Bank Register menu" x-show="open" x-cloak x-transition style="display:none">
        @foreach ($groups as $title => $items)
            <div class="bk-menu-group">
                <p class="bk-menu-title">{{ $title }}</p>
                @foreach ($items as $item)
                    @php($active = request()->routeIs(...$item['active']))
                    <a href="{{ route($item['route']).($item['fragment'] ?? '') }}" @if ($active) aria-current="page" @endif class="bk-menu-item {{ $active ? 'is-active' : '' }}">
                        <b>{{ $item['label'] }} @if (($item['badge'] ?? 0) > 0)<span class="bk-badge">{{ $item['badge'] }}</span>@endif</b>
                        <span>{{ $item['hint'] }}</span>
                    </a>
                @endforeach
                @if ($title === 'Reports')
                    @if ($onRegister && $user?->can('bank-statement-entry-export'))
                        <a href="{{ route('bank-statement-entries.export', array_filter(['filter' => request()->input('filter')])) }}" class="bk-menu-item"><b>Export to Excel</b><span>The entries listed now, with the same filters</span></a>
                    @endif
                    <button type="button" class="bk-menu-item" @click="open = false; $nextTick(() => window.print())"><b>Print this page</b><span>Black and white, for the laser printer</span></button>
                @endif
                @if ($title === 'Setup')
                    <a href="{{ route('bank-register.help') }}" target="_blank" rel="noopener" class="bk-menu-item"><b>Help <span dir="rtl" lang="ur">(اردو)</span></b><span>How the bank register works</span></a>
                @endif
            </div>
        @endforeach
    </nav>
</div>
