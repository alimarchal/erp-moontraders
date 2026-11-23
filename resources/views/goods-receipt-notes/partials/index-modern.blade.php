{{--
    GRN list for the redesigned layouts (A table, B cards, C by day). Same data, filters, actions
    and permissions as the classic list; adds status tabs with counts and amounts.
    Included by index.blade.php.
--}}
@php
    $filter = (array) request('filter', []);
    $activeStatus = $filter['status'] ?? '';
    $tabs = ['' => 'All', 'draft' => 'Draft', 'posted' => 'Posted', 'reversed' => 'Reversed'];
    $tabCount = fn (string $status) => $status === '' ? (int) $statusTotals->sum('grn_count') : (int) ($statusTotals[$status]->grn_count ?? 0);
    $tabAmount = fn (string $status) => $status === '' ? (float) $statusTotals->sum('amount') : (float) ($statusTotals[$status]->amount ?? 0);
    $filtersOn = collect(\Illuminate\Support\Arr::except($filter, 'status'))->filter(fn ($value) => filled($value))->isNotEmpty();
    $statusChip = ['draft' => 'bg-amber-100 text-amber-900', 'posted' => 'bg-green-100 text-green-800', 'reversed' => 'bg-red-100 text-red-800'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-gray-900">Goods receipt notes</h1>
                <p class="text-sm text-gray-500">{{ $hasDateFilter ? 'Receipts for the dates you picked' : 'Today\'s receipts and every draft · pick dates to see more' }}</p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <x-grn-help-link />
                @can('goods-receipt-note-create')
                    <a href="{{ route('goods-receipt-notes.create') }}" class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-4 text-sm font-bold text-white shadow-sm transition-transform duration-100 hover:bg-blue-700 active:scale-[0.97]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        New GRN
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4 px-3 py-6 sm:px-6 lg:px-8" x-data="{ filtersOpen: {{ $filtersOn ? 'true' : 'false' }} }">
        <x-status-message />

        {{-- Status tabs with counts and amounts --}}
        <nav aria-label="Status" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($tabs as $status => $label)
                @php($tabUrl = route('goods-receipt-notes.index', array_merge(request()->except('page'), ['filter' => array_filter(array_merge($filter, ['status' => $status]), fn ($value) => filled($value))])))
                <a href="{{ $tabUrl }}" @if ($activeStatus === $status) aria-current="page" @endif
                    class="rounded-2xl px-4 py-3 ring-1 transition-transform duration-100 active:scale-[0.98] {{ $activeStatus === $status ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-gray-900 ring-gray-200 hover:bg-gray-50' }}">
                    <span class="flex items-center justify-between text-sm font-semibold">{{ $label }} <span class="tabular-nums {{ $activeStatus === $status ? 'text-slate-300' : 'text-gray-500' }}">{{ number_format($tabCount($status)) }}</span></span>
                    <span class="mt-1 block text-lg font-semibold tabular-nums tracking-tight">Rs {{ number_format($tabAmount($status), 0) }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Filters --}}
        <section class="rounded-2xl bg-white ring-1 ring-gray-200">
            <form method="GET" action="{{ route('goods-receipt-notes.index') }}" class="flex flex-wrap items-center gap-2 p-3">
                @if ($activeStatus)
                    <input type="hidden" name="filter[status]" value="{{ $activeStatus }}">
                @endif
                <label for="grn-index-search" class="sr-only">GRN number</label>
                <input id="grn-index-search" type="search" name="filter[grn_number]" value="{{ $filter['grn_number'] ?? '' }}" placeholder="Search GRN number…"
                    class="h-10 min-w-0 flex-1 rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-xs">
                <button type="button" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen"
                    class="inline-flex h-10 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold ring-1 {{ $filtersOn ? 'bg-blue-50 text-blue-800 ring-blue-200' : 'text-gray-700 ring-gray-300 hover:bg-gray-50' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4" /></svg>
                    Filters{{ $filtersOn ? ' · on' : '' }}
                </button>
                <button type="submit" class="h-10 rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white transition-transform duration-100 hover:bg-gray-800 active:scale-[0.97]">Search</button>
                @if ($filtersOn || $activeStatus)
                    <a href="{{ route('goods-receipt-notes.index') }}" class="text-sm font-semibold text-gray-600 hover:underline">Clear</a>
                @endif

                <div x-show="filtersOpen" x-cloak class="grid w-full grid-cols-1 gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div><label for="f-inv" class="mb-1 block text-xs font-semibold text-gray-600">Supplier invoice</label><input id="f-inv" type="text" name="filter[supplier_invoice_number]" value="{{ $filter['supplier_invoice_number'] ?? '' }}" class="block h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm"></div>
                    <div><label for="f-sup" class="mb-1 block text-xs font-semibold text-gray-600">Supplier</label>
                        <select id="f-sup" name="filter[supplier_id]" class="block h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm"><option value="">All suppliers</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filter['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->supplier_name }}</option>@endforeach</select></div>
                    <div><label for="f-wh" class="mb-1 block text-xs font-semibold text-gray-600">Warehouse</label>
                        <select id="f-wh" name="filter[warehouse_id]" class="block h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm"><option value="">All warehouses</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(($filter['warehouse_id'] ?? '') == $warehouse->id)>{{ $warehouse->warehouse_name }}</option>@endforeach</select></div>
                    <div><label for="f-from" class="mb-1 block text-xs font-semibold text-gray-600">Receipt date from</label><input id="f-from" type="date" name="filter[receipt_date_from]" value="{{ $filter['receipt_date_from'] ?? '' }}" class="block h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm"></div>
                    <div><label for="f-to" class="mb-1 block text-xs font-semibold text-gray-600">Receipt date to</label><input id="f-to" type="date" name="filter[receipt_date_to]" value="{{ $filter['receipt_date_to'] ?? '' }}" class="block h-10 w-full rounded-xl border-gray-300 text-sm shadow-sm"></div>
                </div>
            </form>
        </section>

        @if ($grns->count() === 0)
            <div class="rounded-2xl bg-white px-6 py-14 text-center ring-1 ring-gray-200">
                <p class="text-base font-semibold text-gray-900">No goods receipt notes found</p>
                <p class="mt-1 text-sm text-gray-500">Try other dates or filters.</p>
                @can('goods-receipt-note-create')
                    <a href="{{ route('goods-receipt-notes.create') }}" class="mt-4 inline-flex h-10 items-center rounded-xl bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700">Create a GRN</a>
                @endcan
            </div>
        @else
            @include('goods-receipt-notes.partials.index-list-'.$indexLayout, ['statusChip' => $statusChip])
            <div>{{ $grns->links() }}</div>
        @endif
    </div>

    <x-alpine-confirmation-modal eventName="open-delete-modal" title="Delete GRN" confirmButtonText="Delete"
        confirmButtonClass="bg-red-600 hover:bg-red-700" csrfMethod="DELETE">
        <p class="text-sm text-gray-600">Are you sure you want to delete this GRN? This action cannot be undone.</p>
    </x-alpine-confirmation-modal>
</x-app-layout>
