<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Van Stock Reconciliation (VRS Fix)
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-8">

                {{-- Summary header --}}
                <div class="flex items-center gap-3 mb-6">
                    <div class="flex-shrink-0">
                        @if ($fixedCount > 0)
                            <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                            </div>
                        @else
                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            @if ($fixedCount > 0)
                                {{ $fixedCount }} discrepanc{{ $fixedCount === 1 ? 'y' : 'ies' }} found and fixed
                            @else
                                All van stock records are in sync
                            @endif
                        </h3>
                        <p class="text-sm text-gray-500">
                            {{ $okCount }} OK &nbsp;&bull;&nbsp;
                            {{ $fixedCount }} Fixed &nbsp;&bull;&nbsp;
                            {{ $okCount + $fixedCount }} total vehicle-product pairs checked
                        </p>
                    </div>
                </div>

                @if (count($rows) === 0)
                    <div class="text-center py-10 text-gray-400 text-sm">
                        No van stock records found. Nothing to reconcile.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-2 pr-3 font-medium text-gray-600 w-6">#</th>
                                    <th class="text-left py-2 pr-3 font-medium text-gray-600">Vehicle</th>
                                    <th class="text-left py-2 pr-3 font-medium text-gray-600">Product</th>
                                    <th class="text-right py-2 pr-3 font-medium text-gray-600">Ledger (Truth)</th>
                                    <th class="text-right py-2 pr-3 font-medium text-gray-600">Was (Batches)</th>
                                    <th class="text-right py-2 pr-3 font-medium text-gray-600">Was (Agg)</th>
                                    <th class="text-left py-2 font-medium text-gray-600">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $i => $row)
                                    <tr class="{{ $row['status'] === 'FIXED' ? 'bg-yellow-50' : '' }}">
                                        <td class="py-2 pr-3 text-gray-400 font-mono text-xs">{{ $i + 1 }}</td>
                                        <td class="py-2 pr-3 font-mono text-xs text-gray-800">{{ $row['vehicle'] }}</td>
                                        <td class="py-2 pr-3 text-gray-700 text-xs">{{ $row['product'] }}</td>
                                        <td class="py-2 pr-3 text-right font-mono text-xs text-blue-700 font-semibold">
                                            {{ number_format($row['ledger'], 3) }}
                                        </td>
                                        <td class="py-2 pr-3 text-right font-mono text-xs {{ $row['status'] === 'FIXED' ? 'text-red-600 line-through' : 'text-gray-500' }}">
                                            {{ number_format($row['was_vsb'], 3) }}
                                        </td>
                                        <td class="py-2 pr-3 text-right font-mono text-xs {{ $row['status'] === 'FIXED' ? 'text-red-600 line-through' : 'text-gray-500' }}">
                                            {{ $row['was_agg'] !== null ? number_format($row['was_agg'], 3) : '—' }}
                                        </td>
                                        <td class="py-2">
                                            @if ($row['status'] === 'OK')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    OK
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Fixed
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-400">
                        Ledger (Truth) = <code class="font-mono">SUM(debit_qty - credit_qty)</code> from <code class="font-mono">inventory_ledger_entries</code>.
                        Re-run at any time — this operation is safe and idempotent.
                    </p>
                    <a href="{{ route('vrs-fix') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-gray-800 rounded hover:bg-gray-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Run Again
                    </a>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
