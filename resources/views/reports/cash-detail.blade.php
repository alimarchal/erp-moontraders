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

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-0 border-2 border-black">
                    
                    {{-- 1. Salesman Data --}}
                    <div class="border-r border-black p-0">
                        <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th style="width: 70%" class="text-left">Salesman</th>
                                    <th style="width: 30%" class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalSalesmanAmount = 0; @endphp
                                @forelse($salesmanData as $data)
                                    @php $totalSalesmanAmount += $data->amount; @endphp
                                    <tr>
                                        <td>{{ $data->salesman_name }}</td>
                                        <td class="text-right font-bold">{{ number_format($data->amount, 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center italic text-gray-500">No data found</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td class="text-right">Total :-</td>
                                    <td class="text-right">{{ number_format($totalSalesmanAmount, 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- 2. Cash Detail --}}
                    <div class="border-r border-black p-0">
                        <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-center">CASH</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Value</th>
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
                                        <td class="text-center font-bold">{{ $val }}</td>
                                        <td class="text-center">{{ $qty > 0 ? $qty : '-' }}</td>
                                        <td class="text-right">{{ $qty > 0 ? number_format($subtotal, 0) : '-' }}</td>
                                    </tr>
                                @endforeach
                                
                                {{-- Coins --}}
                                @php 
                                    $coins = $denominations['coins'] ?? 0;
                                    $grandTotalCash += $coins;
                                @endphp
                                <tr>
                                    <td class="text-center font-bold">Coins/Loose</td>
                                    <td class="text-center">-</td>
                                    <td class="text-right">{{ $coins > 0 ? number_format($coins, 0) : '-' }}</td>
                                </tr>
                            </tbody>
                             <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td colspan="2" class="text-right">Total :-</td>
                                    <td class="text-right">{{ number_format($grandTotalCash, 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- 3. Bank Slips --}}
                    <div class="p-0">
                         <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-center">Bank Slips</th>
                                    <th class="text-left">Salesman</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalBankSlips = 0; @endphp
                                @forelse($bankSlips as $slip)
                                    @php $totalBankSlips += $slip->amount; @endphp
                                    <tr>
                                        <td class="text-center text-xs text-gray-600">{{ $slip->bank_name }}</td>
                                        <td>{{ $slip->salesman_name }}</td>
                                        <td class="text-right font-bold">{{ number_format($slip->amount, 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center italic text-gray-500">No bank slips</td></tr>
                                @endforelse
                            </tbody>
                             <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td colspan="2" class="text-right">Total :-</td>
                                    <td class="text-right">{{ number_format($totalBankSlips, 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                </div>
                
                 {{-- Grand Total Summary --}}
                 <div class="mt-0 border-x-2 border-b-2 border-black p-0 grid grid-cols-3">
                     <div class="col-span-1 p-1 font-bold">Others: -</div>
                     <div class="col-span-1"></div>
                     <div class="col-span-1 border-l border-black flex justify-between px-2 py-1 bg-gray-200 font-bold">
                         <span>Grand Total :</span>
                         <span>{{ number_format($totalSalesmanAmount, 0) }}</span>
                     </div>
                 </div>

            </div>
        </div>
    </div>

        @push('scripts')
        <script>
            $(document).ready(function () {
                $('#filter_supplier_id').select2({
                    width: '100%',
                    placeholder: 'All Suppliers',
                    allowClear: true
                });

                // Also ensure employee filter is initialized nicely if not already
                $('#filter_employee_ids').select2({
                    width: '100%',
                    placeholder: 'Select Salesmen',
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>
