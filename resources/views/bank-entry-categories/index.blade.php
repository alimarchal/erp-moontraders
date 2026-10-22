<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bank Entry Categories" :createRoute="route('bank-entry-categories.create')"
            createLabel="" createPermission="bank-entry-category-create" :showSearch="true" :showRefresh="true"
            backRoute="settings.index">
            <x-slot name="actions">
                <a href="{{ route('bank-entry-categories.export', ['format' => 'pdf'] + request()->query()) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition" title="PDF" aria-label="PDF"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg></a>
                <a href="{{ route('bank-entry-categories.export', ['format' => 'excel'] + request()->query()) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition" title="Excel" aria-label="Excel"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5"/></svg></a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @push('header')
        <style>
            .ur-help { font-family: 'Noto Naskh Arabic', 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', Tahoma, serif; font-size: 16px; line-height: 2.1; color: #1f2937; text-align: right; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
            .ur-help h3 { margin: 0 0 4px; font-size: 19px; font-weight: 700; color: #1e2a5a; }
            .ur-help p { margin: 0 0 8px; }
            .ur-help dl { display: grid; grid-template-columns: max-content 1fr; column-gap: 20px; row-gap: 4px; margin: 0; }
            .ur-help dt { font-weight: 700; color: #1e2a5a; white-space: nowrap; }
            .ur-help dd { margin: 0; }
            .ur-help .en { direction: ltr; unicode-bidi: isolate; display: inline-block; font-family: Inter, Arial, sans-serif; font-size: 0.82em; line-height: 1.6; background: #eef2ff; border-radius: 5px; padding: 0 6px; }
            @media (max-width: 640px) { .ur-help dl { grid-template-columns: 1fr; } .ur-help dd { margin-bottom: 8px; } }
            @media print { .ur-help { display: none; } }
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
        <section class="ur-help" dir="rtl" lang="ur">
            <h3>مدد: یہ صفحہ کس لیے ہے؟</h3>
            <p>بینک رجسٹر کی ہر انٹری کو ایک <b>کیٹیگری</b> دی جاتی ہے، مثلاً <bdi class="en">Customer Receipt</bdi>، <bdi class="en">Supplier Payment</bdi>، <bdi class="en">Salary / Staff</bdi> یا <bdi class="en">Bank Charges</bdi>۔ کیٹیگری سے طے ہوتا ہے کہ رقم کس کھاتے (<bdi class="en">ledger account</bdi>) میں جائے گی اور رپورٹوں میں کس عنوان کے تحت نظر آئے گی۔</p>
            <dl>
                <dt><bdi class="en">Used for</bdi></dt>
                <dd>کیٹیگری صرف رقم آنے (<bdi class="en">Receipt</bdi>)، صرف رقم جانے (<bdi class="en">Payment</bdi>) یا دونوں کے لیے ہے۔ انٹری بناتے وقت فہرست اسی کے مطابق دکھائی جاتی ہے۔</dd>
                <dt><bdi class="en">Default ledger account</bdi></dt>
                <dd>اگر کمپنی یا پارٹی کا اپنا کھاتہ نہ ہو تو رقم اسی کھاتے میں جاتی ہے۔ خالی ہو تو کمپنی کا <bdi class="en">receivable / payable</bdi> کھاتہ استعمال ہوتا ہے۔</dd>
                <dt><bdi class="en">Entries</bdi></dt>
                <dd>اس کیٹیگری میں اب تک کتنی انٹریاں ہیں۔</dd>
                <dt>نئی کیٹیگری</dt>
                <dd>اوپر <b>+</b> کے بٹن سے بنائیں۔ نام، قسم یا کھاتہ بدلنے کے لیے <b>نارنجی</b> ایڈٹ آئیکن دبائیں۔</dd>
                <dt>غیر فعال کرنا</dt>
                <dd><b>سرخ</b> ✕ آئیکن کیٹیگری کو ڈیلیٹ نہیں کرتا، صرف <bdi class="en">Inactive</bdi> کر دیتا ہے، کیونکہ آج یا آئندہ کسی انٹری سے اس کا تعلق ہو سکتا ہے۔ غیر فعال کیٹیگری نئی انٹری میں نظر نہیں آتی، مگر پرانا ریکارڈ اور رپورٹیں ویسی ہی رہتی ہیں۔ دوبارہ چلانے کے لیے ایڈٹ میں <bdi class="en">Active</bdi> لگا دیں۔</dd>
                <dt>فلٹر، پرنٹ، <bdi class="en">PDF</bdi>، <bdi class="en">Excel</bdi></dt>
                <dd>اوپر کے آئیکنز سے فہرست چھانٹیں، پرنٹ کریں یا ڈاؤن لوڈ کریں۔ پرنٹ میں <bdi class="en">Actions</bdi> کا کالم شامل نہیں ہوتا۔</dd>
                <dt>اجازتیں</dt>
                <dd><bdi class="en">bank-entry-category-list</bdi>، <bdi class="en">create</bdi>، <bdi class="en">edit</bdi> اور <bdi class="en">delete</bdi>۔ <bdi class="en">delete</bdi> کی اجازت والا صارف کیٹیگری کو غیر فعال کر سکتا ہے۔</dd>
            </dl>
        </section>
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
