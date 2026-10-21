@php
    $categoryList = $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type])->values();
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Bank Entry" :showSearch="false" :showRefresh="false" backRoute="bank-statement-entries.index" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />

            @if ($scan)
                <div class="mb-4 flex items-center gap-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                    @if ($scan->isImage())<a href="{{ route('bank-receipt-scans.file', $scan) }}" target="_blank"><img src="{{ route('bank-receipt-scans.file', $scan) }}" alt="" class="h-20 w-28 rounded object-cover border"></a>@endif
                    <div>
                        <p class="font-semibold">Adding an entry for the scan “{{ $scan->file_name }}”</p>
                        <p>It will be attached to the entry as its receipt when you save.</p>
                        <a href="{{ route('bank-receipt-scans.file', $scan) }}" target="_blank" class="text-blue-800 hover:underline">Open the scan</a>
                    </div>
                </div>
            @endif

            @if (session('possible_duplicates'))
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">An entry with the same account, date and amount is already in the register:</p>
                    <ul class="mt-1 list-disc ps-5">
                        @foreach (session('possible_duplicates') as $duplicate)
                            <li><a href="{{ route('bank-statement-entries.show', $duplicate['id']) }}" target="_blank" class="text-blue-800 hover:underline">{{ $duplicate['text'] }}</a></li>
                        @endforeach
                    </ul>
                    <label class="mt-2 inline-flex items-center gap-2">
                        <input type="checkbox" form="entry-form" name="confirm_duplicate" value="1" class="rounded border-gray-300">
                        <span>It is a different transaction — save it anyway</span>
                    </label>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                 x-data="{
                    direction: '{{ $defaults['direction'] === 'debit' ? 'debit' : 'credit' }}',
                    categories: @js($categoryList),
                    category: '{{ old('bank_entry_category_id') }}',
                    get options() { return this.categories.filter(c => c.type === 'both' || c.type === (this.direction === 'credit' ? 'receipt' : 'payment')); },
                    pick(d) { this.direction = d; if (! this.options.some(c => String(c.id) === String(this.category))) { this.category = ''; } }
                 }">
                <x-validation-errors class="mb-4" />

                <form id="entry-form" method="POST" action="{{ route('bank-statement-entries.store') }}" enctype="multipart/form-data" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <input type="hidden" name="direction" :value="direction">
                    @if ($scan)<input type="hidden" name="scan_id" value="{{ $scan->id }}">@endif

                    {{-- 1. Money in or out, and how much --}}
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                        <div class="md:col-span-2">
                            <p class="block font-medium text-sm text-gray-700">Money</p>
                            <div class="mt-1 grid grid-cols-2 rounded-lg border border-gray-300 overflow-hidden text-sm font-semibold">
                                <button type="button" @click="pick('credit')" :class="direction === 'credit' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="px-3 py-2.5 transition">＋ Received</button>
                                <button type="button" @click="pick('debit')" :class="direction === 'debit' ? 'bg-red-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="px-3 py-2.5 transition border-s border-gray-300">− Paid out</button>
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <x-label for="amount" value="Amount (PKR)" :required="true" />
                            <x-input id="amount" name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" class="mt-1 block w-full text-2xl font-bold tabular-nums" required autofocus :value="$defaults['amount']" placeholder="0.00" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <x-label for="bank_account_id" value="Bank account" :required="true" />
                            <select id="bank_account_id" name="bank_account_id" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full" required>
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((int) $defaults['bank_account_id'] === $account->id)>{{ $account->account_name }} ({{ $account->account_number }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-label for="posting_date" value="Date" :required="true" />
                            <x-input id="posting_date" name="posting_date" type="date" max="{{ now()->toDateString() }}" class="mt-1 block w-full" required :value="$defaults['posting_date']" />
                        </div>
                        <div>
                            <x-label for="value_date" value="Value date" />
                            <x-input id="value_date" name="value_date" type="date" class="mt-1 block w-full" :value="old('value_date')" />
                        </div>
                    </div>

                    {{-- 2. Who and what --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <x-label for="party_name_in_statement" value="Party name" />
                            <x-input id="party_name_in_statement" name="party_name_in_statement" type="text" dir="auto" maxlength="191" class="mt-1 block w-full" :value="old('party_name_in_statement')" placeholder="As printed on the statement" />
                            <p class="mt-1 text-xs text-gray-500">If an alias exists for this name, the company is filled in automatically.</p>
                        </div>
                        <div>
                            <x-label for="reference" value="Reference / cheque no." />
                            <x-input id="reference" name="reference" type="text" maxlength="191" class="mt-1 block w-full" :value="old('reference')" placeholder="CHQ Cr. Online 00000264" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <x-label for="company_id" value="Company" />
                            <select id="company_id" name="company_id" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                                <option value="">—</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected((int) old('company_id') === $company->id)>{{ $company->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-label for="bank_entry_category_id" value="Category" />
                            <select id="bank_entry_category_id" name="bank_entry_category_id" x-model="category" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                                <option value="">— decide later —</option>
                                <template x-for="option in options" :key="option.id">
                                    <option :value="option.id" x-text="option.name" :selected="String(option.id) === String(category)"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-label for="narration" value="Narration" />
                        <x-input id="narration" name="narration" type="text" dir="auto" maxlength="2000" class="mt-1 block w-full" :value="old('narration')" />
                    </div>
                    <div class="mt-4">
                        <x-label for="description" value="Note" />
                        <textarea id="description" name="description" rows="2" dir="auto" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full" placeholder="Anything the auditor should know">{{ $defaults['description'] }}</textarea>
                    </div>

                    {{-- 3. Receipt --}}
                    @can('bank-statement-entry-attach')
                      @unless ($scan)
                        <div class="mt-5 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                            <p class="text-sm font-semibold text-gray-800">Receipt / attachment <span class="font-normal text-gray-500">(optional, you can add it later)</span></p>
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-3">
                                <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" class="md:col-span-3 block w-full text-sm">
                                <select name="label" class="rounded-md border-gray-300 text-sm">
                                    @foreach (\App\Models\Attachment::LABELS as $value => $label)<option value="{{ $value }}" @selected($value === 'receipt')>{{ $label }}</option>@endforeach
                                </select>
                                <input name="attachment_description" maxlength="500" dir="auto" placeholder="Description of the file" class="md:col-span-2 rounded-md border-gray-300 text-sm">
                            </div>
                        </div>
                      @endunless
                    @endcan

                    {{-- Buttons --}}
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                        <a href="{{ route('bank-statement-entries.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="after" value="another" :disabled="busy" class="rounded-md border border-blue-950 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-blue-950 hover:bg-blue-50 disabled:opacity-50">Save &amp; add another</button>
                            <button type="submit" name="after" value="save" :disabled="busy" class="rounded-md bg-blue-950 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900 disabled:opacity-50">Save</button>
                            @can('bank-statement-entry-submit')
                                <button type="submit" name="after" value="submit" :disabled="busy" class="rounded-md bg-green-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900 disabled:opacity-50" title="Needs a category. Small amounts are posted straight away, larger ones go to a checker.">Save &amp; submit</button>
                            @endcan
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
