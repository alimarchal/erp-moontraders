@php
    $isOwnCompanyOnly = $suppliers->count() === 1;
    $isAdmin = auth()->user()->hasAllSupplierAccess();
    $currentOwner = old('owner', $voucher->exists
        ? ($voucher->supplier_id ? 'supplier:'.$voucher->supplier_id : ($voucher->company_id ? 'company:'.$voucher->company_id : ''))
        : ($defaultOwner ?? ''));
    $payee = old('payee_type', $voucher->exists ? $voucher->payee_type : 'vendor');
    $paymentMethod = old('payment_method', $voucher->exists ? ($voucher->payment_method ?: \App\Models\ExpenseVoucher::METHOD_CASH) : \App\Models\ExpenseVoucher::METHOD_CASH);
    $vendorOld = old('expense_vendor_id', $voucher->expense_vendor_id);
    $employeeOld = old('employee_id', $voucher->employee_id);
    $vendorModel = $vendorOld ? \App\Models\ExpenseVendor::withTrashed()->find($vendorOld) : null;
    $employeeModel = $employeeOld ? \App\Models\Employee::withTrashed()->find($employeeOld) : null;
    $methodOptions = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'credit' => 'Credit, pay later (shops only)'];

    // Bill lines: what was just sent back with errors, the saved lines, the voucher's own single line (older
    // vouchers), or one empty line on the usual general account (new lines start on it too).
    $lineOf = fn ($category, $account, $description, $amount) => ['category' => (string) ($category ?? ''), 'account' => (string) ($account ?? ''), 'description' => (string) ($description ?? ''), 'amount' => (string) ($amount ?? '')];
    $oldLines = old('lines');
    if (is_array($oldLines)) {
        $lineStart = collect($oldLines)->values()->map(fn ($line) => $lineOf($line['expense_category_id'] ?? null, $line['expense_account_id'] ?? null, $line['description'] ?? null, $line['amount'] ?? null))->all();
    } elseif ($voucher->exists && $voucher->payee_type !== 'employee') {
        $lineStart = $voucher->lines->isNotEmpty()
            ? $voucher->lines->map(fn ($line) => $lineOf($line->expense_category_id, $line->expense_account_id, $line->description, $line->amount))->all()
            : [$lineOf($voucher->expense_category_id, $voucher->expense_account_id, null, $voucher->amount)];
    } else {
        $lineStart = [$lineOf(null, $defaultAccountId ?? null, null, null)];
    }
    $lineErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'lines'))->map(fn ($messages) => $messages[0])->all();
    $maxLines = \App\Models\ExpenseVoucherLine::MAX_LINES;
    $lineHeaders = [
        ['label' => '#', 'align' => 'text-center', 'width' => '36px'],
        ['label' => 'Category', 'align' => 'text-left', 'width' => '170px'],
        ['label' => 'Expense account', 'align' => 'text-left', 'width' => '220px'],
        ['label' => 'Description', 'align' => 'text-left', 'width' => '180px'],
        ['label' => 'Amount (Rs)', 'align' => 'text-right', 'width' => '120px'],
        ['label' => '<span class="sr-only">Remove</span>', 'align' => 'text-center', 'width' => '44px'],
    ];
@endphp
<div class="ev-sections" x-data="evVoucherForm({ payee: @js($payee), method: @js($paymentMethod), amount: @js((string) old('amount', $voucher->payee_type === 'employee' ? $voucher->amount : '')), lines: @js($lineStart), errors: @js($lineErrors), max: {{ $maxLines }}, defaultAccount: @js((string) ($defaultAccountId ?? '')) })"
    x-effect="if (payee !== 'vendor' && method === 'credit') { method = 'cash'; }" @ev-shop-account.window="shopAccount($event.detail)">

    {{-- 1. Who is paid --}}
    <section class="uf-card gf-card" aria-labelledby="ev-s-payee">
        <header class="uf-card-head">
            <div>
                <h2 class="uf-card-title" id="ev-s-payee"><span class="uf-step">1</span> Who is paid</h2>
                <p class="uf-card-sub">A shop, an employee taking an advance, or anyone else.</p>
            </div>
        </header>
        <div class="uf-body">
            <div class="ev-seg" role="radiogroup" aria-label="Who is paid">
                <label :class="{ 'is-on': payee === 'vendor' }"><input type="radio" name="payee_type" value="vendor" x-model="payee"> Shop / party</label>
                <label :class="{ 'is-on': payee === 'employee' }"><input type="radio" name="payee_type" value="employee" x-model="payee"> Employee advance</label>
                <label :class="{ 'is-on': payee === 'other' }"><input type="radio" name="payee_type" value="other" x-model="payee"> Someone else</label>
            </div>

            <div class="ev-grid" style="margin-top:14px">
                <template x-if="payee === 'vendor'">
                    <div class="ak-field ev-span-2">
                        <label for="expense_vendor_id">Shop</label>
                        <div class="ev-inline">
                            <select id="expense_vendor_id" name="expense_vendor_id" data-search-url="{{ route('expense-vendors.search') }}" data-placeholder="Type to search a shop by name, phone or city">
                                @if($vendorModel)<option value="{{ $vendorModel->id }}" data-account="{{ $vendorModel->default_expense_account_id }}" selected>{{ $vendorModel->name }}{{ $vendorModel->city ? ' - '.$vendorModel->city : '' }}</option>@endif
                            </select>
                            <button type="button" class="ak-btn ak-btn-ghost ev-add-shop" @click="$dispatch('open-shop-modal')">＋ New shop</button>
                        </div>
                        @error('expense_vendor_id')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </template>

                <template x-if="payee === 'employee'">
                    <div class="ak-field ev-span-2">
                        <label for="employee_id">Employee</label>
                        <select id="employee_id" name="employee_id" data-search-url="{{ route('expense-vouchers.employees.search') }}" data-placeholder="Type a name or employee code">
                            @if($employeeModel)<option value="{{ $employeeModel->id }}" selected>{{ \App\Http\Controllers\ExpenseVoucherController::employeeLabel($employeeModel) }}</option>@endif
                        </select>
                        <p class="ak-hint">Shown as Name · Company. The advance is booked to the employee's own company.</p>
                        @error('employee_id')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </template>
                <template x-if="payee === 'employee'">
                    <div class="ak-field">
                        <label for="advance_installment">Monthly instalment (optional)</label>
                        <input id="advance_installment" type="number" step="0.01" min="0.01" name="advance_installment" inputmode="decimal" value="{{ old('advance_installment', $voucher->advance_installment) }}" placeholder="Empty = all from next salary">
                        @error('advance_installment')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </template>

                <template x-if="payee === 'other'">
                    <div class="ak-field ev-span-2">
                        <label for="party_name">Paid to (name)</label>
                        <input id="party_name" type="text" name="party_name" maxlength="255" autocomplete="off" value="{{ old('party_name', $voucher->payee_type === 'other' ? $voucher->party_name : '') }}" placeholder="Person or company paid">
                        @error('party_name')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </template>

                <div class="ak-field" :class="payee === 'employee' ? 'ev-span-3' : ''" x-show="payee !== 'employee'">
                    <label for="owner">Company (distribution)</label>
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
                    @error('owner')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="ev-note" x-show="payee === 'employee'" x-cloak>This is not an expense: it is money the employee owes back. Payroll takes the instalment out of the next salary and keeps the balance.</p>
        </div>
    </section>

    {{-- 2. Bill lines (or the advance amount) --}}
    <section class="uf-card gf-card" aria-labelledby="ev-s-expense">
        <header class="uf-card-head">
            <div>
                <h2 class="uf-card-title" id="ev-s-expense"><span class="uf-step">2</span> <span x-text="payee === 'employee' ? 'Advance and amount' : 'Bill lines'">Bill lines</span></h2>
                <p class="uf-card-sub" x-show="payee !== 'employee'">One line per item on the bill. A category fills in its usual expense account; you can change it.</p>
            </div>
            <div class="gf-head-stats" x-show="payee !== 'employee'">
                <span class="ak-pill" x-text="lines.length + (lines.length === 1 ? ' line' : ' lines')"></span>
            </div>
        </header>
        <div class="uf-body">
            <div class="ev-grid">
                <div class="ak-field">
                    <label for="voucher_date">Voucher date</label>
                    <input id="voucher_date" type="date" name="voucher_date" required max="{{ now()->toDateString() }}" value="{{ old('voucher_date', optional($voucher->voucher_date)->format('Y-m-d')) }}">
                    @error('voucher_date')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="ak-field" x-show="payee === 'vendor'">
                    <label for="vendor_bill_no">Shop bill no. (optional)</label>
                    <input id="vendor_bill_no" type="text" name="vendor_bill_no" maxlength="80" autocomplete="off" :disabled="payee !== 'vendor'" value="{{ old('vendor_bill_no', $voucher->vendor_bill_no) }}">
                    @error('vendor_bill_no')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="ak-field" x-show="payee === 'vendor'">
                    <label for="vendor_bill_date">Shop bill date (optional)</label>
                    <input id="vendor_bill_date" type="date" name="vendor_bill_date" max="{{ now()->toDateString() }}" :disabled="payee !== 'vendor'" value="{{ old('vendor_bill_date', optional($voucher->vendor_bill_date)->format('Y-m-d')) }}">
                    @error('vendor_bill_date')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="ak-field" x-show="payee === 'vendor'" x-effect="if (payee !== 'vendor' && window.jQuery) { jQuery('#vehicle_id').val(null).trigger('change'); }">
                    <label for="vehicle_id">Vehicle (optional)</label>
                    <select id="vehicle_id" class="ev-select" name="vehicle_id" data-clearable="1" data-placeholder="For a vehicle expense">
                        <option value=""></option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((int) old('vehicle_id', $voucher->vehicle_id) === $vehicle->id)>{{ $vehicle->vehicle_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ak-field" x-show="payee === 'employee'" x-cloak>
                    <label for="amount">Amount (Rs)</label>
                    <input id="amount" class="ev-amount-input" type="text" inputmode="decimal" autocomplete="off" name="amount" :disabled="payee !== 'employee'" :required="payee === 'employee'" x-model="amount" value="{{ old('amount', $voucher->payee_type === 'employee' ? $voucher->amount : '') }}">
                    @error('amount')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- One row per bill line; a card per line on a tablet or phone. A disabled fieldset sends nothing for an advance. --}}
        <fieldset class="ev-lines gf-table" x-show="payee !== 'employee'" :disabled="payee === 'employee'">
            <legend class="sr-only">Bill lines</legend>
            <x-form-table :title="null" :headers="$lineHeaders">
                <tbody>
                    <template x-for="(line, index) in lines" :key="line.uid">
                        <tr x-init="$nextTick(() => initLine($el, line))">
                            <td data-label="#" class="gf-no" x-text="index + 1"></td>
                            <td data-label="Category">
                                <div>
                                    <select class="ev-line-category" :name="`lines[${index}][expense_category_id]`" :aria-label="`Category of line ${index + 1}`" data-placeholder="Toner, repair, tea…">
                                        <option value=""></option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" data-account="{{ $category->default_expense_account_id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="ev-field-error" role="alert" x-show="err(index, 'expense_category_id')" x-text="err(index, 'expense_category_id')"></p>
                                </div>
                            </td>
                            <td data-label="Expense account">
                                <div>
                                    <select class="ev-line-account" :name="`lines[${index}][expense_account_id]`" :aria-label="`Expense account of line ${index + 1}`" data-placeholder="Select account">
                                        <option value=""></option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" @selected($account->id === (int) ($defaultAccountId ?? 0))>{{ $account->account_code }} - {{ $account->account_name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="ev-field-error" role="alert" x-show="err(index, 'expense_account_id')" x-text="err(index, 'expense_account_id')"></p>
                                </div>
                            </td>
                            <td data-label="Description">
                                <div>
                                    <input type="text" maxlength="255" autocomplete="off" :name="`lines[${index}][description]`" x-model="line.description" :aria-label="`Description of line ${index + 1}`" placeholder="What was bought">
                                    <p class="ev-field-error" role="alert" x-show="err(index, 'description')" x-text="err(index, 'description')"></p>
                                </div>
                            </td>
                            <td data-label="Amount (Rs)">
                                <div>
                                    <input type="text" inputmode="decimal" autocomplete="off" class="ev-line-amount" :name="`lines[${index}][amount]`" x-model="line.amount" :aria-label="`Amount of line ${index + 1}`" placeholder="0.00" required>
                                    <p class="ev-field-error" role="alert" x-show="err(index, 'amount')" x-text="err(index, 'amount')"></p>
                                </div>
                            </td>
                            <td data-label="" class="ev-line-remove">
                                <button type="button" class="ev-remove-line" @click="removeLine(index)" :disabled="lines.length === 1" :aria-label="`Remove line ${index + 1}`" title="Remove line">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="ev-total-label">Bill total</td>
                        <td class="ev-total" aria-live="polite" x-text="money(total())">0.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="6">
                            <div class="ev-lines-foot">
                                <button type="button" class="ev-add-line" @click="addLine()" :disabled="lines.length >= max">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                    Add line
                                </button>
                                <span class="ak-hint" x-show="lines.length >= max" x-cloak>At most {{ $maxLines }} lines on one voucher.</span>
                                <p class="ev-field-error" role="alert" x-show="errors.lines" x-text="errors.lines"></p>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </x-form-table>
        </fieldset>
    </section>

    {{-- 3. Payment --}}
    <section class="uf-card gf-card" aria-labelledby="ev-s-payment">
        <header class="uf-card-head">
            <div>
                <h2 class="uf-card-title" id="ev-s-payment"><span class="uf-step">3</span> Payment</h2>
                <p class="uf-card-sub" x-show="method === 'cash'">The checker counts the notes when approving and hands over the cash.</p>
                <p class="uf-card-sub" x-show="method === 'bank_transfer' || method === 'cheque'" x-cloak>Paid from a bank account linked to the chart of accounts.</p>
                <p class="uf-card-sub" x-show="method === 'credit'" x-cloak>Booked today; the shop is paid later with a vendor payment.</p>
            </div>
        </header>
        <div class="uf-body">
            <div class="ev-grid">
                <div class="ak-field">
                    <label for="payment_method">Payment method</label>
                    <select id="payment_method" name="payment_method" x-model="method" required>
                        @foreach($methodOptions as $value => $label)
                            <option value="{{ $value }}" @selected($paymentMethod === $value) @if($value === 'credit') :disabled="payee !== 'vendor'" @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="ak-field ev-span-2" x-show="method === 'bank_transfer' || method === 'cheque'" x-cloak>
                    <label for="bank_account_id">Paid from bank account</label>
                    <select id="bank_account_id" class="ev-select" name="bank_account_id" data-placeholder="Select bank account" :disabled="method !== 'bank_transfer' && method !== 'cheque'">
                        <option value=""></option>
                        @foreach($bankAccounts as $bank)
                            <option value="{{ $bank->id }}" @selected((int) old('bank_account_id', $voucher->bank_account_id) === $bank->id)>{{ $bank->bank_name }} · {{ $bank->account_name }}{{ $bank->account_number ? ' · …'.substr($bank->account_number, -4) : '' }}</option>
                        @endforeach
                    </select>
                    @if($bankAccounts->isEmpty())<p class="ak-hint">No active bank account is linked to the chart of accounts yet.</p>@endif
                    @error('bank_account_id')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="ak-field" x-show="method === 'cheque'" x-cloak>
                    <label for="cheque_number">Cheque number</label>
                    <input id="cheque_number" type="text" name="cheque_number" maxlength="50" autocomplete="off" :disabled="method !== 'cheque'" :required="method === 'cheque'" value="{{ old('cheque_number', $voucher->cheque_number) }}">
                    @error('cheque_number')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="ak-field" x-show="method === 'cheque'" x-cloak>
                    <label for="cheque_date">Cheque date</label>
                    <input id="cheque_date" type="date" name="cheque_date" :disabled="method !== 'cheque'" :required="method === 'cheque'" value="{{ old('cheque_date', optional($voucher->cheque_date)->format('Y-m-d')) }}">
                    @error('cheque_date')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="ak-field" x-show="method === 'bank_transfer' || method === 'cheque'" x-cloak>
                    <label for="transaction_reference">Reference (optional)</label>
                    <input id="transaction_reference" type="text" name="transaction_reference" maxlength="100" autocomplete="off" :disabled="method !== 'bank_transfer' && method !== 'cheque'" placeholder="IBFT / transfer ID" value="{{ old('transaction_reference', $voucher->transaction_reference) }}">
                    @error('transaction_reference')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
    </section>

    {{-- 4. Narration and attachments --}}
    <section class="uf-card gf-card" aria-labelledby="ev-s-notes">
        <header class="uf-card-head">
            <h2 class="uf-card-title" id="ev-s-notes"><span class="uf-step">4</span> Narration and attachments</h2>
        </header>
        <div class="uf-body">
            <div class="ak-field">
                <label for="narration">Narration</label>
                <textarea id="narration" name="narration" rows="2" maxlength="1000" placeholder="What was this expense for?">{{ old('narration', $voucher->narration) }}</textarea>
                @error('narration')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            @if(\App\Services\ExpenseVoucherAttachmentService::enabled())
                <div class="ak-field" x-data="{ names: [] }" style="margin-top:12px">
                    <label for="attachments">Attachments (optional): receipts, bills, photos</label>
                    <x-attachment-compressor />
                    <input id="attachments" type="file" name="attachments[]" data-compress multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="ev-file" @change="names = [...$event.target.files].map(f => f.name)">
                    <p class="ak-hint">Pick several files together. PDF, JPG, PNG or WEBP, up to {{ round(config('voucher_attachments.max_kb') / 1024) }} MB each.</p>
                    @error('attachments')<p class="ev-field-error" role="alert">{{ $message }}</p>@enderror
                    <ul class="ev-files" x-show="names.length" x-cloak><template x-for="name in names" :key="name"><li x-text="name"></li></template></ul>
                    @if($voucher->exists && $voucher->attachments->isNotEmpty())
                        <div class="ev-current-file" style="flex-direction:column; align-items:flex-start; gap:8px">
                            @foreach($voucher->attachments as $file)
                                <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:center">
                                    <a href="{{ $voucher->attachmentUrl($file) }}" target="_blank" rel="noopener">{{ $file->file_name }}</a>
                                    <label class="ev-remove"><input type="checkbox" name="remove_attachments[]" value="{{ $file->id }}"> Remove</label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- Sticky bar: what will be paid on the left, the actions on the right --}}
    <div class="uf-actions gf-actions">
        <div class="gf-totals" aria-live="polite">
            <span>Amount Rs <b x-text="money(payee === 'employee' ? (Number(amount) || 0) : total())">0.00</b></span>
            <span x-text="{ cash: 'Cash', bank_transfer: 'Bank transfer', cheque: 'Cheque', credit: 'Credit, pay later' }[method]"></span>
        </div>
        <div>
            <a href="{{ $voucher->exists ? route('expense-vouchers.show', $voucher) : route('expense-vouchers.index') }}" class="ak-btn ak-btn-ghost">Cancel</a>
            <button type="submit" name="action" value="draft" class="ak-btn ak-btn-ghost" data-busy-label="Saving…">{{ $voucher->exists ? 'Save changes' : 'Save draft' }}</button>
            <button type="submit" name="action" value="submit" class="ak-btn ak-btn-primary gf-submit" data-busy-label="Sending…">Submit for approval</button>
        </div>
    </div>
</div>

{{-- New shop, added without leaving the voucher --}}
<div x-data="shopModal()" @open-shop-modal.window="open = true; $nextTick(() => $refs.name.focus())" @keydown.escape.window="open = false">
    <div class="uf-modal" x-show="open" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="shop-modal-title">
        <div class="uf-modal-bg" x-show="open" x-transition.opacity @click="open = false"></div>
        <div class="uf-modal-box" x-show="open" x-transition style="max-width:560px">
            <div class="uf-modal-body" style="display:block">
                <h3 id="shop-modal-title">New shop</h3>
                <p class="ak-hint" style="margin-bottom:12px">Name is enough to start. The rest helps you find the shop later.</p>
                <div class="ak-alert ak-alert-error" x-show="error" x-text="error" x-cloak style="margin-bottom:10px"></div>
                <div class="ev-modal-grid">
                    <div class="ak-field" style="grid-column:1/-1"><label for="ns_name">Shop name *</label><input id="ns_name" x-ref="name" type="text" x-model="form.name" maxlength="255" @keydown.enter.prevent="save()"></div>
                    <div class="ak-field"><label for="ns_category">Category</label>
                        <select id="ns_category" x-model="form.category">@foreach($vendorCategories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="ak-field"><label for="ns_phone">Phone</label><input id="ns_phone" type="tel" inputmode="tel" autocomplete="off" x-model="form.phone" maxlength="40"></div>
                    <div class="ak-field"><label for="ns_city">City</label><input id="ns_city" type="text" x-model="form.city" maxlength="80"></div>
                    <div class="ak-field"><label for="ns_contact">Contact person</label><input id="ns_contact" type="text" x-model="form.contact_person" maxlength="255"></div>
                    <div class="ak-field" style="grid-column:1/-1"><label for="ns_address">Address</label><input id="ns_address" type="text" x-model="form.address" maxlength="500"></div>
                </div>
                <div x-show="duplicate" x-cloak class="ev-note" style="margin-top:12px">
                    <span x-text="duplicate && duplicate.message"></span>
                    <div style="display:flex; gap:8px; margin-top:8px">
                        <button type="button" class="ak-btn ak-btn-primary" @click="useExisting()">Use the existing shop</button>
                        <button type="button" class="ak-btn ak-btn-ghost" @click="save(true)">It is a different shop, save anyway</button>
                    </div>
                </div>
            </div>
            <div class="uf-modal-foot">
                <button type="button" class="ak-btn ak-btn-ghost" @click="open = false">Cancel</button>
                <button type="button" class="ak-btn ak-btn-primary" @click="save()" :disabled="busy"><span x-text="busy ? 'Saving…' : 'Save shop'"></span></button>
            </div>
        </div>
    </div>
</div>
