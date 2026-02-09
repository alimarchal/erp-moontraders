<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Cash Detail Report') }}
            </h2>
        </div>
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 13px; /* Slightly larger for readability */
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 4px 6px;
                white-space: nowrap;
            }
            
            .report-table thead th {
                 background-color: #d1d5db; /* gray-300 */
                 font-weight: bold;
                 text-align: center;
            }

            @media print {
                @page {
                    margin: 5mm;
                    size: landscape; /* Suggest landscape for 3 columns */
                }
                
                body {
                     background-color: white !important;
                }
                
                .no-print {
                    display: none !important;
                }
                
                .report-table th,
                .report-table td {
                     border: 1px solid black !important;
                }
            }
        </style>
    @endpush

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">

                {{-- Filters --}}
                <form method="GET" action="{{ route('reports.cash-detail.index') }}" class="mb-6 flex gap-4 items-end no-print">
                    <div>
                        <x-label for="date" value="{{ __('Date') }}" />
                        <x-input id="date" class="block mt-1 w-full" type="date" name="date" :value="$date" required />
                    </div>
                    
                    <div>
                        <x-label for="supplier_id" value="{{ __('Supplier') }}" />
                        <select id="supplier_id" name="supplier_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->supplier_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-button class="mb-0.5">
                            {{ __('Filter') }}
                        </x-button>
                    </div>
                     <div class="ml-auto">
                        <button type="button" onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                            Print
                        </button>
                    </div>
                </form>
                
                {{-- Report Title --}}
                <div class="text-center mb-4">
                     <h1 class="text-2xl font-bold uppercase">Cash Detail</h1>
                     <p class="font-semibold">{{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}</p>
                     @if($supplierId)
                        <p class="text-sm font-semibold text-gray-600">{{ $suppliers->find($supplierId)->supplier_name }}</p>
                     @endif
                </div>

                {{-- Main Content Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-0 border-2 border-black">
                    
                    {{-- 1. Salesman Data --}}
                    <div class="border-r border-black p-0">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th class="text-left">Salesman</th>
                                    <th class="text-right">Amount</th>
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
                                    <tr><td colspan="2" class="text-center italic">No data found</td></tr>
                                @endforelse
                                
                                {{-- Fill empty rows to maintain height if needed, or simplistic approach --}}
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-200 font-bold">
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
                                <tr>
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
                             <tfoot>
                                <tr class="bg-gray-200 font-bold">
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
                                <tr>
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
                                    <tr><td colspan="3" class="text-center italic">No bank slips</td></tr>
                                @endforelse
                            </tbody>
                             <tfoot>
                                <tr class="bg-gray-200 font-bold">
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
</x-app-layout>
