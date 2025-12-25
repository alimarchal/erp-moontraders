<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Trial Balance" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.trial-balance.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Period/Date Selection -->
            <div>
                <x-label for="accounting_period_id" value="Accounting Period" />
                <select id="accounting_period_id" name="accounting_period_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                    onchange="this.form.submit()">
                    <option value="">Custom Date</option>
                    @foreach($accountingPeriods as $period)
                    <option value="{{ $period->id }}" {{ $periodId==$period->id ? 'selected' : '' }}>
                        {{ $period->name }} (As of {{ \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- As Of Date -->
            <div>
                <x-label for="as_of_date" value="As of Date" />
                <x-input id="as_of_date" name="as_of_date" type="date" class="mt-1 block w-full" :value="$asOfDate" />
            </div>
        </div>
    </x-filter-section>

    <!-- Trial Balance Summary -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 mb-4">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="mb-4 text-center">
                <h3 class="text-xl font-bold text-gray-800">Trial Balance</h3>
                <p class="text-gray-600 mt-1">
                    As of {{ \Carbon\Carbon::parse($asOfDate)->format('F d, Y') }}
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600">Total Debits</div>
                    <div class="text-2xl font-bold font-mono text-blue-700">
                        {{ number_format((float) $trialBalance->total_debits, 2) }}
                    </div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600">Total Credits</div>
                    <div class="text-2xl font-bold font-mono text-green-700">
                        {{ number_format((float) $trialBalance->total_credits, 2) }}
                    </div>
                </div>
                <div
                    class="p-4 rounded-lg {{ abs($trialBalance->difference) < 0.01 ? 'bg-emerald-50' : 'bg-red-50' }}">
                    <div class="text-sm text-gray-600">Difference</div>
                    <div
                        class="text-2xl font-bold font-mono {{ abs($trialBalance->difference) < 0.01 ? 'text-emerald-700' : 'text-red-700' }}">
                        {{ number_format((float) $trialBalance->difference, 2) }}
                    </div>
                    @if(abs($trialBalance->difference) < 0.01) <div
                        class="text-xs text-emerald-600 mt-1">✓ Balanced
                </div>
                @else
                <div class="text-xs text-red-600 mt-1">⚠️ Out of Balance</div>
                @endif
            </div>
        </div>
    </div>
    </div>

    
</x-app-layout>
