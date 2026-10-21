@props(['inside' => false])

@php
    $user = auth()->user();
    $pendingApprovals = $user?->can('bank-statement-entry-approve') ? \App\Models\BankStatementEntry::where('status', 'pending_approval')->count() : 0;
    $pendingScans = $user?->can('bank-receipt-scan-list') ? \App\Models\BankReceiptScan::where('status', 'pending')->count() : 0;
    $items = [
        ['label' => 'Register', 'route' => 'bank-statement-entries.index', 'active' => ['bank-statement-entries.*', 'bank-statement-imports.*'], 'can' => 'bank-statement-entry-list'],
        ['label' => 'Approvals', 'route' => 'bank-approvals.index', 'active' => ['bank-approvals.*'], 'can' => 'bank-statement-entry-approve', 'badge' => $pendingApprovals, 'badgeClass' => 'bg-amber-500'],
        ['label' => 'Receipt scans', 'route' => 'bank-receipt-scans.index', 'active' => ['bank-receipt-scans.*'], 'can' => 'bank-receipt-scan-list', 'badge' => $pendingScans, 'badgeClass' => 'bg-sky-600'],
        ['label' => 'Reconciliation', 'route' => 'bank-reconciliations.index', 'active' => ['bank-reconciliations.*'], 'can' => 'bank-reconciliation-list'],
        ['label' => 'Reports', 'route' => 'reports.bank.index', 'active' => ['reports.bank.*'], 'canAny' => \App\Services\BankRegister\BankRegisterPermissions::REPORTS],
        ['label' => 'Ledger accounts', 'route' => 'bank-ledger-accounts.index', 'active' => ['bank-ledger-accounts.*'], 'can' => 'bank-ledger-account-list'],
        ['label' => 'Categories & rules', 'route' => 'bank-entry-categories.index', 'active' => ['bank-entry-categories.*', 'bank-party-aliases.*', 'bank-entry-rules.*', 'bank-cheques.*'], 'can' => 'bank-entry-category-list'],
    ];
@endphp

<nav aria-label="Bank Register sections" class="bank-noprint {{ $inside ? '' : 'max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4' }}">
    <div class="mb-3 flex flex-wrap items-center gap-1.5 rounded-xl bg-white p-1.5 shadow-sm ring-1 ring-gray-200">
        @foreach ($items as $item)
            @continue(isset($item['can']) && ! $user?->can($item['can']))
            @continue(isset($item['canAny']) && ! $user?->canAny($item['canAny']))
            @php($active = request()->routeIs(...$item['active']))
            <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $active ? 'bg-blue-950 text-white shadow' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                {{ $item['label'] }}
                @if (($item['badge'] ?? 0) > 0)
                    <span class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 text-[11px] font-bold leading-5 text-white {{ $item['badgeClass'] }}">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
        <a href="{{ route('bank-register.help') }}" target="_blank" rel="noopener"
            class="ms-auto inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-emerald-800 hover:bg-emerald-50">
            <span aria-hidden="true">?</span> Help <span dir="rtl" lang="ur" class="text-xs">(اردو)</span>
        </a>
    </div>
</nav>
