<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Classify Bank Entry #{{ $entry->id }}" :showSearch="false" :showRefresh="false" backRoute="bank-statement-entries.index" />
    </x-slot>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <x-validation-errors class="mb-4" />

                @if ($entry->import_id === null)
                    <p class="mb-4 rounded bg-blue-50 border border-blue-200 p-3 text-sm text-blue-900">This entry was typed in by hand, so you can still correct its date, amount and details until it is posted.</p>
                @else
                    <div class="mb-5 rounded-lg bg-gray-50 border border-gray-200 p-4 text-sm">
                        <p class="text-2xl font-bold tabular-nums {{ $entry->direction === 'credit' ? 'text-emerald-700' : 'text-red-700' }}">{{ $entry->direction === 'credit' ? '+' : '−' }} PKR {{ number_format($entry->amount, 2) }}</p>
                        <p class="text-gray-600">{{ $entry->posting_date->format('d-m-Y') }} · {{ $entry->reference }}</p>
                        <p class="mt-1 text-gray-700" dir="auto">{{ $entry->narration }}</p>
                    </div>
                @endif

                @if ($entry->status === 'pending_approval')
                    <p class="mb-4 rounded bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">This entry is waiting for approval. Changing the company or category will withdraw it from the approval queue and the maker must submit it again.</p>
                @endif

                <form method="POST" action="{{ route('bank-statement-entries.update', $entry) }}">
                    @csrf @method('PUT')
                    @if ($entry->import_id === null)
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <div>
                                <x-label for="direction" value="Money" />
                                <select data-allow-clear="false" id="direction" name="direction" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="credit" @selected(old('direction', $entry->direction) === 'credit')>Received</option>
                                    <option value="debit" @selected(old('direction', $entry->direction) === 'debit')>Paid out</option>
                                </select>
                            </div>
                            <div>
                                <x-label for="amount" value="Amount (PKR)" :required="true" />
                                <x-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required :value="old('amount', $entry->amount)" />
                            </div>
                            <div>
                                <x-label for="posting_date" value="Date" :required="true" />
                                <x-input id="posting_date" name="posting_date" type="date" class="mt-1 block w-full" required :value="old('posting_date', $entry->posting_date->format('Y-m-d'))" />
                            </div>
                            <div>
                                <x-label for="value_date" value="Value date" />
                                <x-input id="value_date" name="value_date" type="date" class="mt-1 block w-full" :value="old('value_date', $entry->value_date?->format('Y-m-d'))" />
                            </div>
                            <div class="md:col-span-2">
                                <x-label for="party_name_in_statement" value="Party name" />
                                <x-input id="party_name_in_statement" name="party_name_in_statement" type="text" dir="auto" class="mt-1 block w-full" :value="old('party_name_in_statement', $entry->party_name_in_statement)" />
                            </div>
                            <div class="md:col-span-2">
                                <x-label for="reference" value="Reference / cheque no." />
                                <x-input id="reference" name="reference" type="text" class="mt-1 block w-full" :value="old('reference', $entry->reference)" />
                            </div>
                            <div class="md:col-span-4">
                                <x-label for="narration" value="Narration" />
                                <x-input id="narration" name="narration" type="text" dir="auto" class="mt-1 block w-full" :value="old('narration', $entry->narration)" />
                            </div>
                        </div>
                    @endif
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label for="company_id" value="Company / principal" />
                            <select id="company_id" name="company_id" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                                <option value="">—</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected((int) old('company_id', $entry->company_id) === $company->id)>{{ $company->supplier_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-label for="bank_entry_category_id" value="Category" />
                            <select id="bank_entry_category_id" name="bank_entry_category_id" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                                <option value="">—</option>
                                @foreach ($categories->filter(fn ($c) => in_array($c->type, ['both', $entry->direction === 'credit' ? 'receipt' : 'payment'], true)) as $category)
                                    <option value="{{ $category->id }}" @selected((int) old('bank_entry_category_id', $entry->bank_entry_category_id) === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-label for="description" value="Note / description" />
                        <textarea id="description" name="description" rows="3" dir="auto" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full" placeholder="Anything the auditor should know about this entry">{{ old('description', $entry->description) }}</textarea>
                    </div>
                    <label class="inline-flex items-center gap-2 mt-4">
                        <input type="hidden" name="needs_review" value="0">
                        <input type="checkbox" name="needs_review" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('needs_review', $entry->needs_review))>
                        <span class="text-sm text-gray-700">Needs review</span>
                    </label>
                    <div class="flex items-center justify-between mt-6">
                        <a href="{{ route('bank-statement-entries.show', $entry) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                        <x-button class="bg-blue-950">Save</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
