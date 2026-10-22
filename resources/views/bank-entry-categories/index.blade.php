<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bank Entry Categories" :createRoute="route('bank-entry-categories.create')"
            createLabel="Add Category" createPermission="bank-entry-category-create" :showSearch="true" :showRefresh="true"
            backRoute="settings.index">
            <x-slot name="actions">
                <a href="{{ route('bank-entry-categories.export', ['format' => 'pdf'] + request()->query()) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition" title="PDF">PDF</a>
                <a href="{{ route('bank-entry-categories.export', ['format' => 'excel'] + request()->query()) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition" title="Excel">Excel</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @push('header')
        <style>
            @media print {
                @page { margin: 8mm; }
                .no-print, #filters { display: none !important; }
                .bec-table th:last-child, .bec-table td:last-child { display: none !important; }
                .bec-table th { background: #e5e7eb !important; color: #000 !important; }
                .print-only { display: block !important; }
            }
            .print-only { display: none; }
        </style>
    @endpush

    <x-filter-section :action="route('bank-entry-categories.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_name" value="Name" />
                <x-input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full" :value="request('filter.name')" />
            </div>
            <div>
                <x-label for="filter_type" value="Used for" />
                <select id="filter_type" name="filter[type]" class="select2 border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach (\App\Models\BankEntryCategory::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('filter.type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]" class="select2 border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    <option value="1" @selected(request('filter.is_active') === '1')>Active</option>
                    <option value="0" @selected(request('filter.is_active') === '0')>Inactive</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <details class="bg-white shadow-xl sm:rounded-lg">
            <summary class="cursor-pointer px-4 py-3 font-semibold text-sm text-gray-800">؟ مدد — یہ صفحہ کس لیے ہے (اردو)</summary>
            <div dir="rtl" lang="ur" class="px-5 pb-4 text-sm leading-8 text-gray-700" style="font-family:'Noto Nastaliq Urdu','Jameel Noori Nastaleeq',serif">
                <p><b>مقصد:</b> بینک رجسٹر کی ہر اندراج (entry) کو ایک <b>کیٹیگری</b> دی جاتی ہے، مثلاً «Customer Receipt»، «Supplier Payment»، «Salary / Staff»، «Bank Charges»۔ کیٹیگری سے طے ہوتا ہے کہ رقم کس کھاتے (ledger account) میں جائے گی اور رپورٹوں میں کس عنوان کے تحت نظر آئے گی۔</p>
                <ul class="list-disc pr-6">
                    <li><b>Used for:</b> کیٹیگری صرف رقم آنے (Receipt)، رقم جانے (Payment) یا دونوں کے لیے ہے۔ اندراج بناتے وقت فہرست اسی کے مطابق دکھائی جاتی ہے۔</li>
                    <li><b>Default ledger account:</b> اگر کمپنی/پارٹی کا کھاتہ نہ ہو تو رقم اسی کھاتے میں جاتی ہے۔ خالی ہو تو کمپنی کا receivable / payable استعمال ہوتا ہے۔</li>
                    <li><b>Entries:</b> اس کیٹیگری میں اب تک کتنے اندراج ہیں۔</li>
                    <li><b>Add Category:</b> نئی کیٹیگری بنائیں۔ <b>Edit</b> (نارنجی) سے نام، قسم یا کھاتہ بدلیں۔</li>
                    <li><b>Deactivate (سرخ):</b> کیٹیگری ڈیلیٹ نہیں ہوتی، صرف <b>Inactive</b> ہو جاتی ہے، کیونکہ آج یا آئندہ کسی اندراج سے اس کا تعلق ہو سکتا ہے۔ Inactive کیٹیگری نئے اندراج میں نظر نہیں آتی مگر پرانا ریکارڈ اور رپورٹیں ویسی ہی رہتی ہیں۔ دوبارہ چلانے کے لیے Edit میں «Active» لگا دیں۔</li>
                    <li><b>Filter / Print / PDF / Excel:</b> اوپر کے بٹنوں سے فہرست چھانٹیں، پرنٹ کریں یا PDF اور Excel میں ڈاؤن لوڈ کریں (پرنٹ میں Actions کا کالم نہیں آتا)۔</li>
                    <li><b>اجازتیں (Permissions):</b> bank-entry-category-list / create / edit / delete۔ «delete» کی اجازت والا صارف کیٹیگری کو Inactive کر سکتا ہے۔</li>
                </ul>
            </div>
        </details>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pt-4 print-only text-center font-extrabold">
        Moon Traders<br>Bank Entry Categories<br>
        <span class="text-xs font-normal">Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}</span>
    </div>

    <div class="bec-table">
    <x-data-table :items="$categories" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Category'],
        ['label' => 'Used for'],
        ['label' => 'Default ledger account'],
        ['label' => 'Entries', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No categories found." :emptyRoute="route('bank-entry-categories.create')" emptyLinkText="Add a category">
        @foreach ($categories as $index => $category)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">{{ $categories->firstItem() + $index }}</td>
                <td class="py-1 px-2 font-semibold">{{ $category->name }}</td>
                <td class="py-1 px-2">{{ \App\Models\BankEntryCategory::TYPES[$category->type] ?? $category->type }}</td>
                <td class="py-1 px-2 font-normal">{{ $category->defaultAccount ? $category->defaultAccount->account_code.' — '.$category->defaultAccount->account_name : 'Company receivable / payable' }}</td>
                <td class="py-1 px-2 text-right tabular-nums">{{ number_format($category->entries_count) }}</td>
                <td class="py-1 px-2 text-center">
                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $category->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        @can('bank-entry-category-edit')
                            <a href="{{ route('bank-entry-categories.edit', $category) }}" title="Edit"
                                class="inline-flex items-center justify-center w-8 h-8 text-orange-600 hover:text-orange-800 hover:bg-orange-100 rounded-md transition-colors duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </a>
                        @endcan
                        @can('bank-entry-category-delete')
                            @if ($category->is_active)
                                <button type="button" title="Deactivate"
                                    x-data x-on:click="$dispatch('open-deactivate-category-modal', { url: '{{ route('bank-entry-categories.destroy', $category) }}', name: @js($category->name) })"
                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            @endif
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
    </div>

    <x-alpine-confirmation-modal event-name="open-deactivate-category-modal" title="Deactivate Category"
        confirm-button-text="Deactivate" csrf-method="DELETE" :dynamic-action="true">
        <p class="text-sm text-gray-600">Deactivate <b x-text="dynamicData.name"></b>? It stays in past entries and reports but is no longer offered for new entries. You can activate it again from Edit.</p>
    </x-alpine-confirmation-modal>
</x-app-layout>
