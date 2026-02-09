<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cash Detail Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 14px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;

                    @bottom-center {
                        content: "Page " counter(page) " of " counter(pages);
                    }
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    counter-reset: page 1;
                }

                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 10px !important;
                    box-shadow: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 11px !important;
                    width: 100% !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }

                .text-green-700,
                .text-blue-700,
                .text-orange-700,
                .text-purple-700 {
                    color: #000 !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 8px !important;
                }

                .print-info {
                    font-size: 9px !important;
                    margin-top: 5px !important;
                    margin-bottom: 10px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }

                .page-footer {
                    display: none;
                }
            }

            /* Select2 Styling to match Tailwind Inputs */
            .select2-container .select2-selection--multiple {
                min-height: 42px !important;
                border-color: #d1d5db !important;
                /* gray-300 */
                border-radius: 0.375rem !important;
                /* rounded-md */
                padding-top: 4px !important;
                padding-bottom: 4px !important;
            }

            .select2-container--default.select2-container--focus .select2-selection--multiple {
                border-color: #6366f1 !important;
                /* indigo-500 */
                box-shadow: 0 0 0 1px #6366f1 !important;
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.cash-detail.index')" class="no-print">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Date --}}
            <div>
                <x-label for="date" value="{{ __('Date') }}" />
                <x-input id="date" class="block mt-1 w-full" type="date" name="date" :value="$date" required />
            </div>
            
            {{-- Supplier Filter --}}
             <div>
                <x-label for="supplier_id" value="{{ __('Supplier') }}" />
                <select id="supplier_id" name="supplier_id" class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>
         
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    CASH DETAIL REPORT<br>
                    <span class="text-sm font-semibold">
                        {{ \Carbon\Carbon::parse($date)->format('d-M-Y') }}
                    </span><br>
                     @if($supplierId)
                        <span class="text-sm font-semibold">
                            Supplier: {{ $suppliers->find($supplierId)->supplier_name }}
                        </span><br>
                     @endif
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                {{-- Main Content Grid --}}
                <div class="border-2 border-black rounded-lg px-2 pb-2 mt-4">
                    <h3 class="font-bold text-md text-center text-black pb-1 border-b border-black mb-2">Cash & Bank Summary</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-1 print:flex print:gap-1" style="page-break-inside: avoid; break-inside: avoid;">
                        
                        {{-- 1. Salesman Data --}}
                        <div class="flex flex-col h-full print:w-1/3">
                            <h4 class="font-bold text-sm border-x border-t border-black text-center">Salesman Summary</h4>
                            <table class="report-table w-full flex-grow tabular-nums">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="text-left px-1 py-0.5">Salesman</th>
                                        <th class="text-right px-1 py-0.5">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $totalSalesmanAmount = 0; @endphp
                                    @forelse($salesmanData as $data)
                                        @php $totalSalesmanAmount += $data->amount; @endphp
                                        <tr>
                                            <td class="px-1 py-0.5">{{ $data->salesman_name }}</td>
                                            <td class="text-right font-bold px-1 py-0.5">{{ number_format($data->amount, 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center italic text-gray-500 px-1 py-0.5">No data found</td></tr>
                                    @endforelse
                                    {{-- Filler rows if needed --}}
                                    @for($i = count($salesmanData); $i < 10; $i++)
                                        <tr>
                                            <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                            <td class="text-right px-1 py-0.5 border-none">&nbsp;</td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold">
                                    <tr>
                                        <td class="text-right px-1 py-0.5 border-t border-black">Total:</td>
                                        <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($totalSalesmanAmount, 0) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- 2. Cash Detail --}}
                        <div class="flex flex-col h-full print:w-1/3">
                            <h4 class="font-bold text-sm border-x border-t border-black text-center">Cash Detail</h4>
                            <table class="report-table w-full flex-grow tabular-nums">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="text-center px-1 py-0.5">Denom</th>
                                        <th class="text-center px-1 py-0.5">Qty</th>
                                        <th class="text-right px-1 py-0.5">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php 
                                        $grandTotalCash = 0; 
                                        $denomsList = [5000, 1000, 500, 100, 50, 20, 10];
                                    @endphp
                                    
                                    @foreach($denomsList as $val)
                                        @php 
                                            $qty = $denominations[$val] ?? 0;
                                            $subtotal = $qty * $val;
                                            $grandTotalCash += $subtotal;
                                        @endphp
                                        <tr>
                                            <td class="text-center font-bold px-1 py-0.5">{{ $val }}</td>
                                            <td class="text-center px-1 py-0.5">{{ $qty > 0 ? $qty : '-' }}</td>
                                            <td class="text-right px-1 py-0.5">{{ $qty > 0 ? number_format($subtotal, 0) : '-' }}</td>
                                        </tr>
                                    @endforeach
                                    
                                    {{-- Coins --}}
                                    @php 
                                        $coins = $denominations['coins'] ?? 0;
                                        $grandTotalCash += $coins;
                                    @endphp
                                    <tr>
                                        <td class="text-center font-bold px-1 py-0.5">Coins</td>
                                        <td class="text-center px-1 py-0.5">-</td>
                                        <td class="text-right px-1 py-0.5">{{ $coins > 0 ? number_format($coins, 0) : '-' }}</td>
                                    </tr>
                                    
                                    {{-- Filler rows --}}
                                    @for($i = 0; $i < 2; $i++)
                                        <tr>
                                             <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                             <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                             <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold">
                                    <tr>
                                        <td colspan="2" class="text-right px-1 py-0.5 border-t border-black">Total:</td>
                                        <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($grandTotalCash, 0) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- 3. Bank Slips --}}
                        <div class="flex flex-col h-full print:w-1/3">
                            <h4 class="font-bold text-sm border-x border-t border-black text-center">Bank Slips</h4>
                            <table class="report-table w-full flex-grow tabular-nums">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="text-left px-1 py-0.5">Bank</th>
                                        <th class="text-left px-1 py-0.5">Salesman</th>
                                        <th class="text-right px-1 py-0.5">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $totalBankSlips = 0; @endphp
                                    @forelse($bankSlips as $slip)
                                        @php $totalBankSlips += $slip->amount; @endphp
                                        <tr>
                                            <td class="text-xs text-gray-600 px-1 py-0.5">{{ $slip->bank_name }}</td>
                                            <td class="px-1 py-0.5">{{ $slip->salesman_name }}</td>
                                            <td class="text-right font-bold px-1 py-0.5">{{ number_format($slip->amount, 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center italic text-gray-500 px-1 py-0.5">No bank slips</td></tr>
                                    @endforelse
                                    
                                     {{-- Filler rows --}}
                                    @for($i = count($bankSlips); $i < 10; $i++)
                                        <tr>
                                             <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                             <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                             <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold">
                                    <tr>
                                        <td colspan="2" class="text-right px-1 py-0.5 border-t border-black">Total:</td>
                                        <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($totalBankSlips, 0) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                    </div>
                    
                     {{-- Grand Total Summary --}}
                     <div class="mt-4 border border-black p-2 bg-gray-50 flex justify-between items-center font-bold">
                         <span>Grand Total (Salesman Amount):</span>
                         <span class="text-xl">{{ number_format($totalSalesmanAmount, 0) }}</span>
                     </div>

                </div>
            </div>
        </div>
    </div>

        @push('scripts')
        <script>
            $(document).ready(function () {
                $('#supplier_id').select2({
                    width: '100%',
                    placeholder: 'All Suppliers',
                    allowClear: true
                });

                // Also ensure employee filter is initialized nicely if not already
                $('#supplier_id').select2({
                    width: '100%',
                    placeholder: 'Select Salesmen',
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>
