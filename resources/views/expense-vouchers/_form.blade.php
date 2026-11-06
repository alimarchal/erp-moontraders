@php
    $isOwnCompanyOnly = $suppliers->count() === 1;
    $isAdmin = auth()->user()->hasAllSupplierAccess();
    $currentOwner = old('owner', $voucher->exists
        ? ($voucher->supplier_id ? 'supplier:'.$voucher->supplier_id : ($voucher->company_id ? 'company:'.$voucher->company_id : ''))
        : ($defaultOwner ?? ''));
    $payee = old('payee_type', $voucher->exists ? $voucher->payee_type : 'vendor');
    $paymentType = old('payment_type', $voucher->exists && $voucher->payment_type === 'credit' ? 'credit' : 'cash');
    $vendorOld = old('expense_vendor_id', $voucher->expense_vendor_id);
    $employeeOld = old('employee_id', $voucher->employee_id);
    $vendorModel = $vendorOld ? \App\Models\ExpenseVendor::withTrashed()->find($vendorOld) : null;
    $employeeModel = $employeeOld ? \App\Models\Employee::withTrashed()->find($employeeOld) : null;
@endphp
<div class="ev-form-grid" x-data="{ payee: @js($payee), paymentType: @js($paymentType) }">
    <div class="ak-field">
        <label for="voucher_date" class="ev-bi"><span>Voucher date</span><span class="ev-ur" lang="ur" dir="rtl">واؤچر کی تاریخ</span></label>
        <input id="voucher_date" type="date" name="voucher_date" required max="{{ now()->toDateString() }}" value="{{ old('voucher_date', optional($voucher->voucher_date)->format('Y-m-d')) }}">
    </div>
    <div class="ak-field ev-span-2" x-show="payee !== 'employee'">
        <label for="owner" class="ev-bi"><span>Company (distribution)</span><span class="ev-ur" lang="ur" dir="rtl">کمپنی / ڈسٹری بیوشن</span></label>
        <select id="owner" class="ev-select" name="owner" @if($isAdmin) data-clearable="1" data-placeholder="{{ $companies->first()?->company_name }} (general expense)" @else required @endif>
            @if($isAdmin)
                <option value=""></option>
                @foreach($companies as $company)
                    <option value="company:{{ $company->id }}" @selected($currentOwner === 'company:'.$company->id)>{{ $company->company_name }} (general expense)</option>
                @endforeach
            @elseif(! $isOwnCompanyOnly)
                <option value="">Select company</option>
            @endif
            @foreach($suppliers as $supplier)
                <option value="supplier:{{ $supplier->id }}" @selected($currentOwner === 'supplier:'.$supplier->id || ($isOwnCompanyOnly && ! $currentOwner))>{{ $supplier->supplier_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field ev-span-2" x-show="payee === 'employee'" x-cloak>
        <p class="ev-note" style="margin:26px 0 0">An advance is booked to the employee's own company automatically.</p>
    </div>

    {{-- Who is paid --}}
    <fieldset class="ev-span-3 ev-payee" aria-label="Who is paid">
        <legend class="ev-bi" style="width:100%"><span>Who is being paid?</span><span class="ev-ur" lang="ur" dir="rtl">ادائیگی کس کو ہو رہی ہے؟</span></legend>
        <div class="ev-seg" role="radiogroup">
            <label :class="{ 'is-on': payee === 'vendor' }"><input type="radio" name="payee_type" value="vendor" x-model="payee"> Shop / party <span class="ev-ur" lang="ur" dir="rtl">دکان / پارٹی</span></label>
            <label :class="{ 'is-on': payee === 'employee' }"><input type="radio" name="payee_type" value="employee" x-model="payee"> Employee advance <span class="ev-ur" lang="ur" dir="rtl">ملازم کو ایڈوانس</span></label>
            <label :class="{ 'is-on': payee === 'other' }"><input type="radio" name="payee_type" value="other" x-model="payee"> Someone else <span class="ev-ur" lang="ur" dir="rtl">کوئی اور</span></label>
        </div>
    </fieldset>

    {{-- Shop --}}
    <template x-if="payee === 'vendor'">
        <div class="ev-span-3 ev-panel">
            <div class="ev-panel-grid">
                <div class="ak-field ev-span-2">
                    <label for="expense_vendor_id" class="ev-bi"><span>Shop</span><span class="ev-ur" lang="ur" dir="rtl">دکان</span></label>
                    <div class="ev-inline">
                        <select id="expense_vendor_id" name="expense_vendor_id" data-search-url="{{ route('expense-vendors.search') }}" data-placeholder="Type to search a shop by name, phone or city">
                            @if($vendorModel)<option value="{{ $vendorModel->id }}" data-account="{{ $vendorModel->default_expense_account_id }}" selected>{{ $vendorModel->name }}{{ $vendorModel->city ? ' - '.$vendorModel->city : '' }}</option>@endif
                        </select>
                        <button type="button" class="ak-btn ak-btn-outline ev-add-shop" @click="$dispatch('open-shop-modal')">＋ New shop <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit">نئی دکان</span></button>
                    </div>
                    <p class="ak-hint">Shops load as you type. Not in the list? Add it here without leaving the voucher.</p>
                </div>
                <div class="ak-field">
                    <label for="vendor_bill_no" class="ev-bi"><span>Shop bill no. (optional)</span><span class="ev-ur" lang="ur" dir="rtl">بل نمبر</span></label>
                    <input id="vendor_bill_no" type="text" name="vendor_bill_no" maxlength="80" value="{{ old('vendor_bill_no', $voucher->vendor_bill_no) }}">
                </div>
            </div>
            <div class="ak-field" style="margin-top:14px">
                <label class="ev-bi"><span>How is it paid?</span><span class="ev-ur" lang="ur" dir="rtl">ادائیگی کیسے ہوگی؟</span></label>
                <div class="ev-seg" role="radiogroup">
                    <label :class="{ 'is-on': paymentType === 'cash' }"><input type="radio" name="payment_type" value="cash" x-model="paymentType"> Paid in cash today <span class="ev-ur" lang="ur" dir="rtl">آج نقد ادا</span></label>
                    <label :class="{ 'is-on': paymentType === 'credit' }"><input type="radio" name="payment_type" value="credit" x-model="paymentType"> On credit, pay later <span class="ev-ur" lang="ur" dir="rtl">ادھار، بعد میں ادائیگی</span></label>
                </div>
                <p class="ev-note" x-show="paymentType === 'credit'" x-cloak>The expense is booked today, but no cash goes out. The shop's balance grows until you pay it with a <b>Vendor payment</b> (usually at month end).</p>
            </div>
        </div>
    </template>

    {{-- Employee advance --}}
    <template x-if="payee === 'employee'">
        <div class="ev-span-3 ev-panel">
            <div class="ev-panel-grid">
                <div class="ak-field ev-span-2">
                    <label for="employee_id" class="ev-bi"><span>Employee</span><span class="ev-ur" lang="ur" dir="rtl">ملازم</span></label>
                    <select id="employee_id" name="employee_id" data-search-url="{{ route('expense-vouchers.employees.search') }}" data-placeholder="Type a name or employee code">
                        @if($employeeModel)<option value="{{ $employeeModel->id }}" selected>{{ $employeeModel->name }} ({{ $employeeModel->employee_code }})</option>@endif
                    </select>
                </div>
                <div class="ak-field">
                    <label for="advance_installment" class="ev-bi"><span>Monthly instalment (optional)</span><span class="ev-ur" lang="ur" dir="rtl">ماہانہ قسط</span></label>
                    <input id="advance_installment" type="number" step="0.01" min="0.01" name="advance_installment" value="{{ old('advance_installment', $voucher->advance_installment) }}" placeholder="Empty = whole amount from next salary">
                </div>
            </div>
            <p class="ev-note">This is not an expense: it is money the employee owes back. Payroll will take the instalment out of the next salary by itself and keep the balance.</p>
        </div>
    </template>

    {{-- Someone else --}}
    <template x-if="payee === 'other'">
        <div class="ev-span-3 ev-panel">
            <div class="ak-field">
                <label for="party_name" class="ev-bi"><span>Paid to (name)</span><span class="ev-ur" lang="ur" dir="rtl">جسے ادائیگی کی گئی</span></label>
                <input id="party_name" type="text" name="party_name" maxlength="255" autocomplete="off" value="{{ old('party_name', $voucher->payee_type === 'other' ? $voucher->party_name : '') }}" placeholder="Person or company paid  |  شخص یا کمپنی">
            </div>
        </div>
    </template>

    <div class="ak-field" x-show="payee !== 'employee'">
        <label for="expense_category_id" class="ev-bi"><span>Category (what is it for?)</span><span class="ev-ur" lang="ur" dir="rtl">قسم / مد</span></label>
        <select id="expense_category_id" class="ev-select" name="expense_category_id" :disabled="payee === 'employee'" data-placeholder="Toner, grocery, rent, repair…">
            <option value=""></option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" data-account="{{ $category->default_expense_account_id }}" @selected((int) old('expense_category_id', $voucher->expense_category_id) === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @can('expense-category-manage')<p class="ak-hint"><a href="{{ route('expense-categories.index') }}" class="ak-primary-link" target="_blank">Manage categories</a></p>@endcan
    </div>
    <div class="ak-field ev-span-2" x-show="payee !== 'employee'">
        <label for="expense_account_id" class="ev-bi"><span>Expense account</span><span class="ev-ur" lang="ur" dir="rtl">اخراجات کا کھاتہ</span></label>
        <select id="expense_account_id" class="ev-select" name="expense_account_id" :disabled="payee === 'employee'">
            <option value="">Select account</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected((int) old('expense_account_id', $voucher->payee_type === 'employee' ? null : ($voucher->expense_account_id ?? ($defaultAccountId ?? null))) === $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field" x-show="payee === 'vendor'">
        <label for="vehicle_id" class="ev-bi"><span>Vehicle (optional)</span><span class="ev-ur" lang="ur" dir="rtl">گاڑی</span></label>
        <select id="vehicle_id" class="ev-select" name="vehicle_id" data-clearable="1" data-placeholder="For a vehicle expense">
            <option value=""></option>
            @foreach($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}" @selected((int) old('vehicle_id', $voucher->vehicle_id) === $vehicle->id)>{{ $vehicle->vehicle_number }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field">
        <label for="amount" class="ev-bi"><span>Amount (Rs.)</span><span class="ev-ur" lang="ur" dir="rtl">رقم (روپے)</span></label>
        <input id="amount" type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount', $voucher->amount) }}">
    </div>
    <div class="ak-field ev-span-3">
        <label for="narration" class="ev-bi"><span>Narration</span><span class="ev-ur" lang="ur" dir="rtl">تفصیل / بیان</span></label>
        <textarea id="narration" name="narration" rows="3" maxlength="1000" placeholder="What was this expense for?  |  یہ خرچ کس مد میں ہوا؟">{{ old('narration', $voucher->narration) }}</textarea>
    </div>
    @if(\App\Services\ExpenseVoucherAttachmentService::enabled())
    <div class="ak-field ev-span-3" x-data="{ names: [] }">
        <label for="attachments" class="ev-bi"><span>Attachments (optional): receipts, bills, photos</span><span class="ev-ur" lang="ur" dir="rtl">منسلک دستاویزات (اختیاری): رسید، بل یا تصویر</span></label>
        <x-attachment-compressor />
        <input id="attachments" type="file" name="attachments[]" data-compress multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="ev-file" @change="names = [...$event.target.files].map(f => f.name)">
        <p class="ak-hint">You can pick several files together. PDF, JPG, PNG or WEBP, up to {{ round(config('voucher_attachments.max_kb') / 1024) }} MB each. Kept privately under Vouchers / {{ optional($voucher->voucher_date)->format('d-m-Y') ?? 'dd-mm-yyyy of the voucher' }}.</p>
        <ul class="ev-files" x-show="names.length" x-cloak><template x-for="name in names" :key="name"><li x-text="name"></li></template></ul>
        @if($voucher->exists && $voucher->attachments->isNotEmpty())
            <div class="ev-current-file" style="flex-direction:column; align-items:flex-start; gap:8px">
                @foreach($voucher->attachments as $file)
                    <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:center">
                        <a href="{{ $voucher->attachmentUrl($file) }}" target="_blank" rel="noopener">{{ $file->file_name }}</a>
                        <label class="ev-remove"><input type="checkbox" name="remove_attachments[]" value="{{ $file->id }}"> Remove <span class="ev-ur" lang="ur" dir="rtl" style="font-size:15px">ہٹا دیں</span></label>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif
</div>

{{-- New shop, added without leaving the voucher --}}
<div x-data="shopModal()" @open-shop-modal.window="open = true; $nextTick(() => $refs.name.focus())" @keydown.escape.window="open = false">
    <div class="uf-modal" x-show="open" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="shop-modal-title">
        <div class="uf-modal-bg" x-show="open" x-transition.opacity @click="open = false"></div>
        <div class="uf-modal-box" x-show="open" x-transition style="max-width:560px">
            <div class="uf-modal-body" style="display:block">
                <h3 id="shop-modal-title">New shop <span class="ev-ur" lang="ur" dir="rtl">نئی دکان</span></h3>
                <p class="ak-hint" style="margin-bottom:12px">Name is enough to start. The rest helps you find the shop later.</p>
                <div class="ak-alert ak-alert-error" x-show="error" x-text="error" x-cloak style="margin-bottom:10px"></div>
                <div class="ev-modal-grid">
                    <div class="ak-field" style="grid-column:1/-1"><label for="ns_name">Shop name *</label><input id="ns_name" x-ref="name" type="text" x-model="form.name" maxlength="255" @keydown.enter.prevent="save()"></div>
                    <div class="ak-field"><label for="ns_category">Category</label>
                        <select id="ns_category" x-model="form.category">@foreach($vendorCategories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="ak-field"><label for="ns_phone">Phone</label><input id="ns_phone" type="text" x-model="form.phone" maxlength="40"></div>
                    <div class="ak-field"><label for="ns_city">City</label><input id="ns_city" type="text" x-model="form.city" maxlength="80"></div>
                    <div class="ak-field"><label for="ns_contact">Contact person</label><input id="ns_contact" type="text" x-model="form.contact_person" maxlength="255"></div>
                    <div class="ak-field" style="grid-column:1/-1"><label for="ns_address">Address</label><input id="ns_address" type="text" x-model="form.address" maxlength="500"></div>
                </div>
                <div x-show="duplicate" x-cloak class="ev-note" style="margin-top:12px">
                    <span x-text="duplicate && duplicate.message"></span>
                    <div style="display:flex; gap:8px; margin-top:8px">
                        <button type="button" class="ak-btn ak-btn-primary" @click="useExisting()">Use the existing shop</button>
                        <button type="button" class="ak-btn ak-btn-outline" @click="save(true)">It is a different shop, save anyway</button>
                    </div>
                </div>
            </div>
            <div class="uf-modal-foot">
                <button type="button" class="ak-btn ak-btn-outline" @click="open = false">Cancel</button>
                <button type="button" class="ak-btn ak-btn-primary" @click="save()" :disabled="busy"><span x-text="busy ? 'Saving…' : 'Save shop'"></span></button>
            </div>
        </div>
    </div>
</div>
