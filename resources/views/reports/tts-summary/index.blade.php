<x-app-layout>
    <x-slot name="header">
        <x-page-header title="TTS Summary" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid #000;
                font-size: 13px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid #000;
                padding: 4px 6px;
                word-wrap: break-word;
            }

            .report-table th {
                background-color: #f3f4f6;
                font-weight: 600;
                text-align: center;
            }

            .amount-cell {
                text-align: right;
                font-family: ui-monospace, monospace;
                white-space: nowrap;
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.tts-summary.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="supplier_id" value="Supplier" />
                <select id="supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ (string) $supplierId === (string) $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}@if ($supplier->short_name)
                                ({{ $supplier->short_name }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="start_date" value="Start Date" />
                <x-input id="start_date" name="filter[start_date]" type="date" class="mt-1 block w-full"
                    :value="$startDate->format('Y-m-d')" />
            </div>

            <div>
                <x-label for="end_date" value="End Date" />
                <x-input id="end_date" name="filter[end_date]" type="date" class="mt-1 block w-full"
                    :value="$endDate->format('Y-m-d')" />
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg">
            <div class="text-center font-extrabold mb-4 text-lg">
                Moon Traders<br>
                <span class="text-base">TTS Summery Date:
                    {{ $startDate->format('d-M-Y') }} to {{ $endDate->format('d-M-Y') }}</span>
                @if ($selectedSupplier)
                    <br><span class="text-sm font-semibold">{{ $selectedSupplier->supplier_name }}</span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">Particular</th>
                            <th style="width: 30%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>TTS Received</td>
                            <td class="amount-cell">{{ number_format($ttsReceived, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Promo Received</td>
                            <td class="amount-cell">{{ number_format($promoReceived, 2) }}</td>
                        </tr>
                        <tr class="font-semibold bg-gray-50">
                            <td>Total Received</td>
                            <td class="amount-cell">{{ number_format($totalReceived, 2) }}</td>
                        </tr>
                        <tr>
                            <td>TTS Passed (Account 5292)</td>
                            <td class="amount-cell">{{ number_format($ttsPassed, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Promotion Passed (Account 5287)</td>
                            <td class="amount-cell">{{ number_format($promoPassed, 2) }}</td>
                        </tr>
                        <tr class="font-semibold bg-gray-50">
                            <td>Total Schemed Passed</td>
                            <td class="amount-cell">{{ number_format($totalSchemedPassed, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Percentage Passed (Account 5223)</td>
                            <td class="amount-cell">{{ number_format($percentagePassed, 2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td>Total Balance (Total Received - Total Schemed Passed - Percentage Passed (Account 5223))
                            </td>
                            <td class="amount-cell">{{ number_format($totalBalance, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>