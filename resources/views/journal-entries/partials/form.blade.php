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

<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
    <div class="p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-label for="entry_date" value="Entry Date" :required="true" />
                <x-input id="entry_date" name="entry_date" type="date" class="mt-1 block w-full"
                    :value="$selectedDate" required />
            </div>

            <div>
                <x-label for="currency_id" value="Currency" :required="true" />
                <select id="currency_id" name="currency_id"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
                    required>
                    <option value="">Select currency</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" {{ (int) $selectedCurrencyId === $currency->id ? 'selected' : '' }}>
                            {{ $currency->currency_code }} · {{ $currency->currency_name }}{{ $currency->is_base_currency ? ' (Base)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="fx_rate_to_base" value="FX Rate To Base" />
                <x-input id="fx_rate_to_base" name="fx_rate_to_base" type="number" step="0.000001" min="0"
                    class="mt-1 block w-full" :value="number_format((float) $fxRate, 6, '.', '')" />
                <p class="text-xs text-gray-500 mt-1">Keep at 1.000000 for base currency entries.</p>
            </div>

            <div>
                <x-label for="reference" value="Reference" />
                <x-input id="reference" name="reference" type="text" maxlength="191" class="mt-1 block w-full"
                    :value="$reference" placeholder="Optional external reference number" />
            </div>

            <div class="md:col-span-2">
                <x-label for="description" value="Description" />
                <textarea id="description" name="description"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                    rows="3" placeholder="Brief description of the journal entry">{{ $description }}</textarea>
            </div>

            <div class="flex items-center space-x-2 md:col-span-2">
                <input type="hidden" name="auto_post" value="0">
                <input id="auto_post" type="checkbox" name="auto_post" value="1"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    {{ filter_var($autoPost, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                <x-label for="auto_post" value="Post immediately after saving" />
            </div>
        </div>

        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Journal Lines</h3>
                <button type="button" id="add-journal-line"
                    class="inline-flex items-center px-3 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Line
                </button>
            </div>

            <div id="journal-line-items" class="space-y-4">
                @foreach ($lineDefaults as $index => $line)
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 journal-line-row" data-index="{{ $index }}">
                        <div class="md:col-span-4">
                            <x-label :for="'line-account-' . $index" value="Account" :required="true" />
                            <select id="line-account-{{ $index }}" name="lines[{{ $index }}][account_id]"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
                                required>
                                <option value="">Select account</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" {{ (int) ($line['account_id'] ?? 0) === $account->id ? 'selected' : '' }}>
                                        {{ $account->account_code }} · {{ $account->account_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <x-label :for="'line-debit-' . $index" value="Debit" />
                            <x-input id="line-debit-{{ $index }}" name="lines[{{ $index }}][debit]" type="number"
                                step="0.01" min="0" class="mt-1 block w-full" :value="$line['debit']" data-role="amount"
                                data-type="debit" />
                        </div>
                        <div class="md:col-span-2">
                            <x-label :for="'line-credit-' . $index" value="Credit" />
                            <x-input id="line-credit-{{ $index }}" name="lines[{{ $index }}][credit]" type="number"
                                step="0.01" min="0" class="mt-1 block w-full" :value="$line['credit']" data-role="amount"
                                data-type="credit" />
                        </div>
                        <div class="md:col-span-3">
                            <x-label :for="'line-cost-center-' . $index" value="Cost Center" />
                            <select id="line-cost-center-{{ $index }}" name="lines[{{ $index }}][cost_center_id]"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                <option value="">Optional</option>
                                @foreach ($costCenters as $costCenter)
                                    <option value="{{ $costCenter->id }}" {{ (int) ($line['cost_center_id'] ?? 0) === $costCenter->id ? 'selected' : '' }}>
                                        {{ $costCenter->code }} · {{ $costCenter->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-1 flex items-end">
                            <button type="button"
                                class="remove-journal-line inline-flex items-center px-3 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150 w-full md:w-auto justify-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Remove
                            </button>
                        </div>
                        <div class="md:col-span-12">
                            <x-label :for="'line-description-' . $index" value="Line Description" />
                            <textarea id="line-description-{{ $index }}" name="lines[{{ $index }}][description]" rows="2"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                placeholder="Optional detail for this line item">{{ $line['description'] }}</textarea>
                        </div>
                    </div>
                @endforeach
            </div>

            <template id="journal-line-template">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 journal-line-row" data-index="__INDEX__">
                    <div class="md:col-span-4">
                        <x-label value="Account" :required="true" />
                        <select name="lines[__INDEX__][account_id]"
                            class="line-account border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
                            required>
                            <option value="">Select account</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_code }} · {{ $account->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <x-label value="Debit" />
                        <input type="number" name="lines[__INDEX__][debit]" step="0.01" min="0"
                            class="line-debit mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            data-role="amount" data-type="debit" value="0.00">
                    </div>
                    <div class="md:col-span-2">
                        <x-label value="Credit" />
                        <input type="number" name="lines[__INDEX__][credit]" step="0.01" min="0"
                            class="line-credit mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            data-role="amount" data-type="credit" value="0.00">
                    </div>
                    <div class="md:col-span-3">
                        <x-label value="Cost Center" />
                        <select name="lines[__INDEX__][cost_center_id]"
                            class="line-cost-center border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                            <option value="">Optional</option>
                            @foreach ($costCenters as $costCenter)
                                <option value="{{ $costCenter->id }}">{{ $costCenter->code }} · {{ $costCenter->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="button"
                            class="remove-journal-line inline-flex items-center px-3 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150 w-full md:w-auto justify-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Remove
                        </button>
                    </div>
                    <div class="md:col-span-12">
                        <x-label value="Line Description" />
                        <textarea name="lines[__INDEX__][description]" rows="2"
                            class="line-description mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            placeholder="Optional detail for this line item"></textarea>
                    </div>
                </div>
            </template>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4 text-sm font-semibold text-gray-700 dark:text-gray-200">
                <div>Total Debit: <span id="journal-total-debit">0.00</span></div>
                <div>Total Credit: <span id="journal-total-credit">0.00</span></div>
                <div>Difference: <span id="journal-total-difference" class="text-green-600">0.00</span></div>
                <div class="text-sm text-gray-500 md:text-right">Debits must equal credits before posting.</div>
            </div>
        </div>
    </div>
</div>

<div class="flex items-center justify-end mt-6">
    <x-button id="submit-btn">
        {{ $submitLabel }}
    </x-button>
</div>

@push('scripts')
    @once
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const container = document.getElementById('journal-line-items');
                if (!container) {
                    return;
                }

                const addButton = document.getElementById('add-journal-line');
                const template = document.getElementById('journal-line-template');
                const totalDebitEl = document.getElementById('journal-total-debit');
                const totalCreditEl = document.getElementById('journal-total-credit');
                const totalDifferenceEl = document.getElementById('journal-total-difference');
                let lineIndex = container.querySelectorAll('.journal-line-row').length;

                const formatAmount = (value) => {
                    const numeric = parseFloat(value);
                    if (Number.isFinite(numeric)) {
                        return numeric.toFixed(2);
                    }

                    return '0.00';
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

                const addLine = (prefill = {}) => {
                    if (!template) {
                        return;
                    }

                    const markup = template.innerHTML.replace(/__INDEX__/g, lineIndex);
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = markup.trim();
                    const newRow = wrapper.firstElementChild;
                    container.appendChild(newRow);

                    if (prefill.account_id) {
                        const accountSelect = newRow.querySelector('.line-account');
                        if (accountSelect) {
                            accountSelect.value = prefill.account_id;
                        }
                    }

                    if (prefill.cost_center_id) {
                        const costSelect = newRow.querySelector('.line-cost-center');
                        if (costSelect) {
                            costSelect.value = prefill.cost_center_id;
                        }
                    }

                    if (prefill.debit !== undefined) {
                        const debitInput = newRow.querySelector('.line-debit');
                        if (debitInput) {
                            debitInput.value = formatAmount(prefill.debit);
                        }
                    }

                    if (prefill.credit !== undefined) {
                        const creditInput = newRow.querySelector('.line-credit');
                        if (creditInput) {
                            creditInput.value = formatAmount(prefill.credit);
                        }
                    }

                    if (prefill.description) {
                        const descriptionInput = newRow.querySelector('.line-description');
                        if (descriptionInput) {
                            descriptionInput.value = prefill.description;
                        }
                    }

                    lineIndex += 1;
                    updateTotals();
                };

                addButton?.addEventListener('click', (event) => {
                    event.preventDefault();
                    addLine();
                });

                container.addEventListener('click', (event) => {
                    const trigger = event.target.closest('.remove-journal-line');
                    if (!trigger) {
                        return;
                    }

                    event.preventDefault();

                    const rows = container.querySelectorAll('.journal-line-row');
                    if (rows.length <= 2) {
                        return;
                    }

                    trigger.closest('.journal-line-row')?.remove();
                    updateTotals();
                });

                container.addEventListener('input', (event) => {
                    const target = event.target;
                    if (!target.matches('input[data-role="amount"]')) {
                        return;
                    }

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
