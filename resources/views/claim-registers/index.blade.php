<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Claim Register" :createRoute="route('claim-registers.create')" createLabel="Add Claim"
            createPermission="claim-register-create" :showSearch="true" :showRefresh="true" backRoute="dashboard">
            <x-slot name="actions">
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: null })" aria-haspopup="dialog" title="How the claim register works, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                    <span class="sr-only">Help</span>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="claims" :has-supplier="true" />

    @if (session('success'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
            <div class="rg-flash rg-flash-ok bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg" role="status">{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
            <div class="rg-flash rg-flash-err bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg" role="alert">{{ session('error') }}</div>
        </div>
    @endif

    <x-filter-section :action="route('claim-registers.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]" data-placeholder="All suppliers"
                    class="select2 rg-supplier-select block mt-1 w-full">
                    <option value=""></option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ (string) $supplierId === (string) $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="filter_transaction_type" value="Transaction Type" />
                <select id="filter_transaction_type" name="filter[transaction_type]" class="rg-select block mt-1 w-full">
                    <option value="">All transactions</option>
                    <option value="claim" {{ request('filter.transaction_type') === 'claim' ? 'selected' : '' }}>Claim (debit)</option>
                    <option value="recovery" {{ request('filter.transaction_type') === 'recovery' ? 'selected' : '' }}>Recovery (credit)</option>
                </select>
            </div>
            <div>
                <x-label for="filter_reference_number" value="Reference Number" />
                <x-input id="filter_reference_number" name="filter[reference_number]" type="text" class="mt-1 block w-full"
                    :value="request('filter.reference_number')" placeholder="e.g. ST-26-014" />
            </div>
            <div>
                <x-label for="filter_claim_month" value="Claim Month" />
                <x-input id="filter_claim_month" name="filter[claim_month]" type="text" class="mt-1 block w-full"
                    :value="request('filter.claim_month')" placeholder="e.g. October 2026" />
            </div>
            <div>
                <x-label for="filter_transaction_date_from" value="Date From" />
                <x-input id="filter_transaction_date_from" name="filter[transaction_date_from]" type="date" class="mt-1 block w-full"
                    :value="request('filter.transaction_date_from')" />
            </div>
            <div>
                <x-label for="filter_transaction_date_to" value="Date To" />
                <x-input id="filter_transaction_date_to" name="filter[transaction_date_to]" type="date" class="mt-1 block w-full"
                    :value="request('filter.transaction_date_to')" />
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 pb-16">
        <div class="rg-card bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg">
            @if ($claims->isEmpty())
                <div class="rg-empty" style="box-shadow:none">
                    <span class="rg-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>
                    </span>
                    <h2>No claims match these filters</h2>
                    <p>Change the filters, or add the first claim for this supplier.</p>
                    @can('claim-register-create')
                        <a href="{{ route('claim-registers.create') }}" class="rg-pill bg-indigo-600 text-white inline-flex items-center">Add claim</a>
                    @endcan
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="report-table rg-table w-full">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 92px;">Date</th>
                                <th class="rg-text" style="width: 90px;">Supplier</th>
                                <th class="rg-text" style="width: 120px;">Reference</th>
                                <th class="rg-text">Description</th>
                                <th class="rg-text" style="width: 112px;">Claim Month</th>
                                <th style="width: 110px;">Opening</th>
                                <th style="width: 110px;">Claim<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'col-debit' })" aria-label="What Claim means" title="What Claim means">i</button></th>
                                <th style="width: 110px;">Recovery<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'col-credit' })" aria-label="What Recovery means" title="What Recovery means">i</button></th>
                                <th style="width: 120px;">Closing<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'col-closing' })" aria-label="What Closing means" title="What Closing means">i</button></th>
                                <th style="width: 110px;" class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $supplierBalances = [];
                            @endphp
                            @if (($totalOpeningBalance ?? 0) != 0)
                                <tr class="bg-yellow-50">
                                    <td colspan="6" class="rg-text"><strong>Balance brought forward</strong></td>
                                    <td class="amount-cell font-semibold {{ $totalOpeningBalance < 0 ? 'text-red-700' : 'text-green-700' }}">{{ number_format($totalOpeningBalance, 2) }}</td>
                                    <td class="amount-cell">-</td>
                                    <td class="amount-cell">-</td>
                                    <td class="amount-cell font-semibold {{ $totalOpeningBalance < 0 ? 'text-red-700' : 'text-green-700' }}">{{ number_format($totalOpeningBalance, 2) }}</td>
                                    <td class="no-print"></td>
                                </tr>
                            @endif
                            @foreach ($claims as $index => $claim)
                                @php
                                    $rowSupplier = $claim->supplier_id;
                                    $supplierBalances[$rowSupplier] ??= $openingBalances[$rowSupplier] ?? 0;
                                    $debit = (float) $claim->debit;
                                    $credit = (float) $claim->credit;
                                    $before = $supplierBalances[$rowSupplier];
                                    $supplierBalances[$rowSupplier] += $debit - $credit;
                                    $after = $supplierBalances[$rowSupplier];
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $claims->firstItem() + $index }}</td>
                                    <td class="text-center rg-nowrap">{{ $claim->transaction_date->format('d-m-Y') }}</td>
                                    <td class="rg-text font-semibold"><span class="rg-clamp" title="{{ $claim->supplier?->supplier_name }}">{{ $claim->supplier?->short_name ?? $claim->supplier?->supplier_name ?? '-' }}</span></td>
                                    <td class="rg-text"><span class="rg-clamp" title="{{ $claim->reference_number }}">{{ $claim->reference_number ?? '-' }}</span></td>
                                    <td class="rg-text"><span class="rg-clamp" title="{{ $claim->description }}">{{ $claim->description ?? '-' }}</span></td>
                                    <td class="rg-text"><span class="rg-clamp" title="{{ $claim->claim_month }}">{{ $claim->claim_month ?? '-' }}</span></td>
                                    <td class="amount-cell {{ $before < 0 ? 'text-red-700' : 'text-gray-600' }}">{{ number_format($before, 2) }}</td>
                                    <td class="amount-cell {{ $debit > 0 ? 'text-green-700' : '' }}">{{ $debit > 0 ? number_format($debit, 2) : '-' }}</td>
                                    <td class="amount-cell {{ $credit > 0 ? 'text-red-700' : '' }}">{{ $credit > 0 ? number_format($credit, 2) : '-' }}</td>
                                    <td class="amount-cell font-semibold {{ $after < 0 ? 'text-red-700' : 'text-green-700' }}">{{ number_format($after, 2) }}</td>
                                    <td class="text-center no-print">
                                        <div class="flex justify-center gap-1">
                                            <a href="{{ route('claim-registers.show', $claim) }}" class="rg-icon rg-blue inline-flex items-center" title="View" aria-label="View claim {{ $claim->reference_number }}">
                                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                            </a>
                                            @if (! $claim->isPosted())
                                                @can('claim-register-edit')
                                                    <a href="{{ route('claim-registers.edit', $claim) }}" class="rg-icon rg-green inline-flex items-center" title="Edit" aria-label="Edit claim {{ $claim->reference_number }}">
                                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                                                    </a>
                                                @endcan
                                                @role('super-admin')
                                                    <form method="POST" action="{{ route('claim-registers.destroy', $claim) }}" onsubmit="return confirm('Delete this claim entry? It has not been posted, so nothing reaches the books.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rg-icon rg-red inline-flex items-center" title="Delete" aria-label="Delete claim {{ $claim->reference_number }}">
                                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                                        </button>
                                                    </form>
                                                @endrole
                                            @else
                                                <span class="rg-posted" title="Posted">Posted</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($claims->hasPages())
                    <div class="mt-4 no-print">{{ $claims->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
