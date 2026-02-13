@php
    $entry = $entry ?? null;
    $selectedCurrencyId = old('currency_id', $entry?->currency_id ?? $defaultCurrencyId ?? null);
    $selectedDate = old('entry_date', optional($entry?->entry_date)->format('Y-m-d') ?? now()->toDateString());
    $fxRate = old('fx_rate_to_base', $entry?->fx_rate_to_base ?? 1.0);
    $reference = old('reference', $entry?->reference ?? '');
    $description = old('description', $entry?->description ?? '');
    $autoPost = old('auto_post', false);

    $lineDefaults = collect(old('lines', $entry?->details?->map(function ($detail) {
        return [
            'account_id' => $detail->chart_of_account_id,
            'debit' => number_format((float) $detail->debit, 2, '.', ''),
            'credit' => number_format((float) $detail->credit, 2, '.', ''),
            'description' => $detail->description,
            'cost_center_id' => $detail->cost_center_id,
        ];
    })->toArray() ?? []))->values();

    while ($lineDefaults->count() < 2) {
        $lineDefaults->push([
            'account_id' => null,
            'debit' => '0.00',
            'credit' => '0.00',
            'description' => null,
            'cost_center_id' => null,
        ]);
    }
@endphp

<div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
    <div class="p-6 space-y-6">

        {{-- Header Fields --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <x-label for="entry_date" value="Entry Date" :required="true" />
                <x-input id="entry_date" name="entry_date" type="date" class="mt-1 block w-full"
                    :value="$selectedDate" required />
            </div>

            <div>
                <x-label for="currency_id" value="Currency" :required="true" />
                <select id="currency_id" name="currency_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                    required>
                    <option value="">Select currency</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" {{ (int) $selectedCurrencyId === $currency->id ? 'selected' : '' }}>
                            {{ $currency->currency_code }} &middot; {{ $currency->currency_name }}{{ $currency->is_base_currency ? ' (Base)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="fx_rate_to_base" value="FX Rate To Base" />
                <x-input id="fx_rate_to_base" name="fx_rate_to_base" type="number" step="0.000001" min="0"
                    class="mt-1 block w-full" :value="number_format((float) $fxRate, 6, '.', '')" />
                <p class="text-xs text-gray-500 mt-1">Keep at 1.000000 for base currency.</p>
            </div>

            <div>
                <x-label for="reference" value="Reference" />
                <x-input id="reference" name="reference" type="text" maxlength="191" class="mt-1 block w-full"
                    :value="$reference" placeholder="Invoice #, Check # etc." />
            </div>

            <div class="md:col-span-3">
                <x-label for="description" value="Description" />
                <x-input id="description" name="description" type="text" class="mt-1 block w-full"
                    :value="$description" placeholder="Brief description of this journal entry" />
            </div>

            <div class="flex items-end pb-1">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="auto_post" value="0">
                    <input id="auto_post" type="checkbox" name="auto_post" value="1"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        {{ filter_var($autoPost, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700">Post immediately</span>
                </label>
            </div>
        </div>

        {{-- Journal Lines Table --}}
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="journal-lines-table">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-8">#</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" style="min-width: 250px;">Account <span class="text-red-500">*</span></th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" style="min-width: 180px;">Description</th>
                            <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider" style="min-width: 130px;">Debit</th>
                            <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider" style="min-width: 130px;">Credit</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" style="min-width: 180px;">Cost Center</th>
                            <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-12"></th>
                        </tr>
                    </thead>
                    <tbody id="journal-line-items" class="divide-y divide-gray-100">
                        @foreach ($lineDefaults as $index => $line)
                            <tr class="journal-line-row hover:bg-gray-50/50" data-index="{{ $index }}">
                                <td class="px-3 py-2 text-gray-400 text-center line-number">{{ $index + 1 }}</td>
                                <td class="px-2 py-2">
                                    <select id="line-account-{{ $index }}" name="lines[{{ $index }}][account_id]"
                                        class="select2 line-account border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm"
                                        required>
                                        <option value="">Select account</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}" {{ (int) ($line['account_id'] ?? 0) === $account->id ? 'selected' : '' }}>
                                                {{ $account->account_code }} &middot; {{ $account->account_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    <input type="text" id="line-description-{{ $index }}" name="lines[{{ $index }}][description]"
                                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm"
                                        value="{{ $line['description'] }}" placeholder="Line detail">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="number" id="line-debit-{{ $index }}" name="lines[{{ $index }}][debit]"
                                        step="0.01" min="0"
                                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm text-right"
                                        value="{{ $line['debit'] }}" data-role="amount" data-type="debit">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="number" id="line-credit-{{ $index }}" name="lines[{{ $index }}][credit]"
                                        step="0.01" min="0"
                                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm text-right"
                                        value="{{ $line['credit'] }}" data-role="amount" data-type="credit">
                                </td>
                                <td class="px-2 py-2">
                                    <select id="line-cost-center-{{ $index }}" name="lines[{{ $index }}][cost_center_id]"
                                        class="select2 line-cost-center border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm">
                                        <option value="">--</option>
                                        @foreach ($costCenters as $costCenter)
                                            <option value="{{ $costCenter->id }}" {{ (int) ($line['cost_center_id'] ?? 0) === $costCenter->id ? 'selected' : '' }}>
                                                {{ $costCenter->code }} &middot; {{ $costCenter->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <button type="button"
                                        class="remove-journal-line text-gray-400 hover:text-red-600 transition-colors p-1"
                                        title="Remove line">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                        <tr>
                            <td colspan="3" class="px-3 py-3 text-right text-xs font-bold text-gray-600 uppercase">Totals</td>
                            <td class="px-2 py-3 text-right font-bold text-gray-800">
                                <span id="journal-total-debit">0.00</span>
                            </td>
                            <td class="px-2 py-3 text-right font-bold text-gray-800">
                                <span id="journal-total-credit">0.00</span>
                            </td>
                            <td colspan="2" class="px-3 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-gray-500">Diff:</span>
                                    <span id="journal-total-difference" class="font-bold text-green-600">0.00</span>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Add Line Button --}}
            <div class="px-4 py-3 bg-gray-50/50 border-t border-gray-100">
                <button type="button" id="add-journal-line"
                    class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Another Line
                </button>
            </div>
        </div>

    </div>
</div>

<div class="flex items-center justify-end mt-6">
    <x-button id="submit-btn">
        {{ $submitLabel }}
    </x-button>
</div>

{{-- Hidden template for dynamic line rows --}}
<template id="journal-line-template">
    <tr class="journal-line-row hover:bg-gray-50/50" data-index="__INDEX__">
        <td class="px-3 py-2 text-gray-400 text-center line-number">__NUMBER__</td>
        <td class="px-2 py-2">
            <select name="lines[__INDEX__][account_id]"
                class="select2-dynamic line-account border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm"
                required>
                <option value="">Select account</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->account_code }} &middot; {{ $account->account_name }}</option>
                @endforeach
            </select>
        </td>
        <td class="px-2 py-2">
            <input type="text" name="lines[__INDEX__][description]"
                class="line-description border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm"
                placeholder="Line detail">
        </td>
        <td class="px-2 py-2">
            <input type="number" name="lines[__INDEX__][debit]" step="0.01" min="0"
                class="line-debit border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm text-right"
                data-role="amount" data-type="debit" value="0.00">
        </td>
        <td class="px-2 py-2">
            <input type="number" name="lines[__INDEX__][credit]" step="0.01" min="0"
                class="line-credit border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm text-right"
                data-role="amount" data-type="credit" value="0.00">
        </td>
        <td class="px-2 py-2">
            <select name="lines[__INDEX__][cost_center_id]"
                class="select2-dynamic line-cost-center border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full text-sm">
                <option value="">--</option>
                @foreach ($costCenters as $costCenter)
                    <option value="{{ $costCenter->id }}">{{ $costCenter->code }} &middot; {{ $costCenter->name }}</option>
                @endforeach
            </select>
        </td>
        <td class="px-2 py-2 text-center">
            <button type="button"
                class="remove-journal-line text-gray-400 hover:text-red-600 transition-colors p-1"
                title="Remove line">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </td>
    </tr>
</template>

@push('scripts')
    @once
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const container = document.getElementById('journal-line-items');
                if (!container) return;

                const addButton = document.getElementById('add-journal-line');
                const template = document.getElementById('journal-line-template');
                const totalDebitEl = document.getElementById('journal-total-debit');
                const totalCreditEl = document.getElementById('journal-total-credit');
                const totalDifferenceEl = document.getElementById('journal-total-difference');
                let lineIndex = container.querySelectorAll('.journal-line-row').length;

                const formatAmount = (value) => {
                    const numeric = parseFloat(value);
                    return Number.isFinite(numeric) ? numeric.toFixed(2) : '0.00';
                };

                const updateLineNumbers = () => {
                    container.querySelectorAll('.journal-line-row').forEach((row, i) => {
                        const numCell = row.querySelector('.line-number');
                        if (numCell) numCell.textContent = i + 1;
                    });
                };

                const updateTotals = () => {
                    let totalDebit = 0;
                    let totalCredit = 0;

                    container.querySelectorAll('input[data-type="debit"]').forEach((input) => {
                        totalDebit += parseFloat(input.value) || 0;
                    });

                    container.querySelectorAll('input[data-type="credit"]').forEach((input) => {
                        totalCredit += parseFloat(input.value) || 0;
                    });

                    totalDebitEl.textContent = totalDebit.toFixed(2);
                    totalCreditEl.textContent = totalCredit.toFixed(2);

                    const difference = totalDebit - totalCredit;
                    totalDifferenceEl.textContent = difference.toFixed(2);

                    if (Math.abs(difference) > 0.009) {
                        totalDifferenceEl.classList.add('text-red-600');
                        totalDifferenceEl.classList.remove('text-green-600');
                    } else {
                        totalDifferenceEl.classList.remove('text-red-600');
                        totalDifferenceEl.classList.add('text-green-600');
                    }
                };

                const initSelect2OnRow = (row) => {
                    $(row).find('.select2-dynamic').each(function () {
                        $(this).select2({
                            placeholder: $(this).hasClass('line-account') ? 'Select account' : '--',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $(this).closest('td'),
                        });
                    });
                };

                const addLine = () => {
                    if (!template) return;

                    const rowCount = container.querySelectorAll('.journal-line-row').length;
                    const markup = template.innerHTML
                        .replace(/__INDEX__/g, lineIndex)
                        .replace(/__NUMBER__/g, rowCount + 1);

                    const temp = document.createElement('tbody');
                    temp.innerHTML = markup.trim();
                    const newRow = temp.firstElementChild;
                    container.appendChild(newRow);

                    initSelect2OnRow(newRow);

                    lineIndex += 1;
                    updateTotals();
                };

                addButton?.addEventListener('click', (event) => {
                    event.preventDefault();
                    addLine();
                });

                container.addEventListener('click', (event) => {
                    const trigger = event.target.closest('.remove-journal-line');
                    if (!trigger) return;

                    event.preventDefault();

                    const rows = container.querySelectorAll('.journal-line-row');
                    if (rows.length <= 2) return;

                    const row = trigger.closest('.journal-line-row');
                    // Destroy Select2 instances before removing
                    $(row).find('.select2-hidden-accessible').each(function () {
                        $(this).select2('destroy');
                    });
                    row?.remove();
                    updateLineNumbers();
                    updateTotals();
                });

                container.addEventListener('input', (event) => {
                    const target = event.target;
                    if (!target.matches('input[data-role="amount"]')) return;

                    if (target.value === '') {
                        updateTotals();
                        return;
                    }

                    const formatted = formatAmount(target.value);
                    target.value = formatted;

                    const siblingSelector = target.dataset.type === 'debit'
                        ? 'input[data-type="credit"]'
                        : 'input[data-type="debit"]';

                    const sibling = target.closest('.journal-line-row')?.querySelector(siblingSelector);
                    if (sibling && parseFloat(formatted) > 0) {
                        sibling.value = '0.00';
                    }

                    updateTotals();
                });

                updateTotals();
            });
        </script>
    @endonce
@endpush
