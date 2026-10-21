@php
/** @var \App\Models\Customer|null $customer */
$customer = $customer ?? null;
$channelTypes = $channelTypes ?? [];
$customerCategories = $customerCategories ?? [];
$accountOptions = $accountOptions ?? collect();
$salesRepOptions = $salesRepOptions ?? collect();
$cancelUrl = $cancelUrl ?? route('customers.index');
$submitLabel = $submitLabel ?? 'Save customer';

$val = fn (string $field, $default = null) => old($field, optional($customer)->{$field} ?? $default);
$moneyValue = fn (string $field, $default) => old($field, optional($customer)->{$field} ?? $default);
@endphp

<style>
    .cf-stack { display: flex; flex-direction: column; gap: 20px; }
    .cf-3 { display: grid; gap: 16px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .cf-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .cf-4 { display: grid; gap: 16px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .cf-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .cf-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .cf-wide { grid-column: 1 / -1; }
    .uf-field textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; background: #fff; color: var(--ak-text); font-size: 14px; }
    .uf-field textarea:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .uf-field .cf-hint { margin: 5px 0 0; font-size: 12px; color: var(--ak-muted); }
    .cf-switch { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--ak-border); border-radius: 10px; background: var(--ak-soft); cursor: pointer; }
    .cf-switch input { width: 18px; height: 18px; accent-color: var(--ak-navy); }
    .cf-switch b { display: block; font-size: 14px; color: var(--ak-text); }
    .cf-switch span { font-size: 12.5px; color: var(--ak-muted); }
    details.cf-adv > summary { cursor: pointer; list-style: none; }
    details.cf-adv > summary::-webkit-details-marker { display: none; }
    details.cf-adv[open] .cf-chevron { transform: rotate(180deg); }
    .cf-chevron { transition: transform .15s; width: 18px; height: 18px; color: var(--ak-muted); }
</style>

@if ($errors->any())
    <div class="ak-alert ak-alert-error" role="alert">
        <b>Please fix {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('problem', $errors->count()) }}:</b>
        {{ implode(' · ', array_slice($errors->all(), 0, 4)) }}{{ $errors->count() > 4 ? ' …' : '' }}
    </div>
@endif

<div class="cf-stack">
    {{-- 1. Identity --}}
    <section class="uf-card">
        <div class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">1</span> Shop details</h2>
                <p class="uf-card-sub">Who the customer is and who looks after them.</p>
            </div>
        </div>
        <div class="uf-body">
            <div class="uf-fields">
                <div class="uf-field">
                    <label for="customer_code">Customer code <span class="uf-req">*</span></label>
                    <input id="customer_code" type="text" name="customer_code" maxlength="191" required style="text-transform:uppercase"
                        value="{{ $val('customer_code') }}" placeholder="CUST-001">
                    @error('customer_code') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="customer_name">Customer / shop name <span class="uf-req">*</span></label>
                    <input id="customer_name" type="text" name="customer_name" maxlength="191" required
                        value="{{ $val('customer_name') }}" placeholder="Al Rehman Store">
                    @error('customer_name') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="business_name">Business / trade name</label>
                    <input id="business_name" type="text" name="business_name" maxlength="191"
                        value="{{ $val('business_name') }}" placeholder="Registered name">
                    @error('business_name') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="sales_rep_id">Assigned sales rep</label>
                    <select id="sales_rep_id" name="sales_rep_id">
                        <option value="">Unassigned</option>
                        @foreach ($salesRepOptions as $salesRep)
                            <option value="{{ $salesRep->id }}" @selected((int) $val('sales_rep_id', 0) === $salesRep->id)>{{ $salesRep->name }}</option>
                        @endforeach
                    </select>
                    @error('sales_rep_id') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </section>

    {{-- 2. Contact & location --}}
    <section class="uf-card">
        <div class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">2</span> Contact &amp; location</h2>
                <p class="uf-card-sub">How to reach the shop and where it is.</p>
            </div>
        </div>
        <div class="uf-body">
            <div class="uf-fields">
                <div class="uf-field">
                    <label for="phone">Phone</label>
                    <input id="phone" type="text" name="phone" maxlength="50" value="{{ $val('phone') }}" placeholder="03XX-XXXXXXX">
                    @error('phone') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" maxlength="191" value="{{ $val('email') }}" placeholder="store@example.com">
                    @error('email') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field cf-wide">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="2" placeholder="Street, market, landmark">{{ $val('address') }}</textarea>
                    @error('address') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="cf-4" style="margin-top:16px">
                <div class="uf-field">
                    <label for="sub_locality">Area / sub locality</label>
                    <input id="sub_locality" type="text" name="sub_locality" maxlength="191" value="{{ $val('sub_locality') }}" placeholder="Satellite Town">
                </div>
                <div class="uf-field">
                    <label for="city">City</label>
                    <input id="city" type="text" name="city" maxlength="191" value="{{ $val('city') }}" placeholder="Muzaffarabad">
                </div>
                <div class="uf-field">
                    <label for="state">State / region</label>
                    <input id="state" type="text" name="state" maxlength="191" value="{{ $val('state', 'Azad Kashmir') }}">
                </div>
                <div class="uf-field">
                    <label for="country">Country</label>
                    <input id="country" type="text" name="country" maxlength="191" value="{{ $val('country', 'Pakistan') }}">
                </div>
            </div>
        </div>
    </section>

    {{-- 3. Classification & credit --}}
    <section class="uf-card">
        <div class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">3</span> Channel &amp; credit</h2>
                <p class="uf-card-sub">How the shop is classified and how much credit it gets.</p>
            </div>
        </div>
        <div class="uf-body">
            <div class="cf-4">
                <div class="uf-field">
                    <label for="channel_type">Channel type <span class="uf-req">*</span></label>
                    <select id="channel_type" name="channel_type" required>
                        @foreach ($channelTypes as $type)
                            <option value="{{ $type }}" @selected($val('channel_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('channel_type') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="customer_category">Category <span class="uf-req">*</span></label>
                    <select id="customer_category" name="customer_category" required>
                        @foreach ($customerCategories as $category)
                            <option value="{{ $category }}" @selected($val('customer_category') === $category)>Category {{ $category }}</option>
                        @endforeach
                    </select>
                    @error('customer_category') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="credit_limit">Credit limit</label>
                    <input id="credit_limit" type="number" name="credit_limit" min="0" step="0.01" value="{{ $moneyValue('credit_limit', 50000) }}">
                    @error('credit_limit') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="payment_terms">Payment terms (days)</label>
                    <input id="payment_terms" type="number" name="payment_terms" min="0" max="365" value="{{ $moneyValue('payment_terms', 30) }}">
                    @error('payment_terms') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </section>

    {{-- 4. Tax & compliance --}}
    <section class="uf-card">
        <div class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">4</span> Tax &amp; identity</h2>
                <p class="uf-card-sub">Optional. Used for FBR / advance tax reports.</p>
            </div>
        </div>
        <div class="uf-body">
            <div class="cf-3">
                <div class="uf-field">
                    <label for="ntn">NTN</label>
                    <input id="ntn" type="text" name="ntn" maxlength="191" value="{{ $val('ntn') }}" placeholder="Tax registration number">
                </div>
                <div class="uf-field">
                    <label for="owner_cnic">Owner CNIC</label>
                    <input id="owner_cnic" type="text" name="owner_cnic" maxlength="191" value="{{ $val('owner_cnic') }}" placeholder="12345-6789012-1">
                </div>
                <div class="uf-field">
                    <label for="it_status">IT status (FBR registered)</label>
                    <select id="it_status" name="it_status">
                        <option value="0" @selected((string) (int) $val('it_status', 0) === '0')>No</option>
                        <option value="1" @selected((string) (int) $val('it_status', 0) === '1')>Yes</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    {{-- 5. Accounting (collapsed) --}}
    <details class="uf-card cf-adv" @if ($errors->hasAny(['receivable_account_id', 'payable_account_id', 'credit_used', 'last_sale_date'])) open @endif>
        <summary class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">5</span> Accounting links <span class="ak-muted" style="font-weight:400">(optional)</span></h2>
                <p class="uf-card-sub">Ledger accounts and usage figures. Most shops can leave these alone.</p>
            </div>
            <svg class="cf-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
        </summary>
        <div class="uf-body">
            <div class="uf-fields">
                <div class="uf-field">
                    <label for="receivable_account_id">Receivable account (AR)</label>
                    <select id="receivable_account_id" name="receivable_account_id">
                        <option value="">Not linked</option>
                        @foreach ($accountOptions as $account)
                            <option value="{{ $account->id }}" @selected((int) $val('receivable_account_id', 0) === $account->id)>{{ $account->account_code }} — {{ $account->account_name }}</option>
                        @endforeach
                    </select>
                    @error('receivable_account_id') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="payable_account_id">Payable account (AP)</label>
                    <select id="payable_account_id" name="payable_account_id">
                        <option value="">Not linked</option>
                        @foreach ($accountOptions as $account)
                            <option value="{{ $account->id }}" @selected((int) $val('payable_account_id', 0) === $account->id)>{{ $account->account_code }} — {{ $account->account_name }}</option>
                        @endforeach
                    </select>
                    @error('payable_account_id') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="credit_used">Credit used</label>
                    <input id="credit_used" type="number" name="credit_used" min="0" step="0.01" value="{{ $moneyValue('credit_used', 0) }}">
                    @error('credit_used') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="last_sale_date">Last sale date</label>
                    <input id="last_sale_date" type="date" name="last_sale_date" value="{{ old('last_sale_date', optional(optional($customer)->last_sale_date)->format('Y-m-d')) }}">
                    @error('last_sale_date') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </details>

    {{-- 6. Notes & status --}}
    <section class="uf-card">
        <div class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">6</span> Notes &amp; status</h2>
            </div>
        </div>
        <div class="uf-body">
            <div class="uf-fields">
                <div class="uf-field cf-wide">
                    <label for="notes">Internal notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Delivery window, credit preference, contact person …">{{ $val('notes') }}</textarea>
                </div>
                <div class="cf-wide">
                    <input type="hidden" name="is_active" value="0">
                    <label class="cf-switch" for="is_active">
                        <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', optional($customer)->is_active ?? true))>
                        <div><b>Customer is active</b><span>Inactive customers stay on record but are hidden from new sales.</span></div>
                    </label>
                </div>
            </div>
        </div>
    </section>

    <div class="uf-actions">
        <p>Fields marked <span class="uf-req">*</span> are required.</p>
        <div>
            <a href="{{ $cancelUrl }}" class="ak-btn ak-btn-outline">Cancel</a>
            <button type="submit" class="ak-btn ak-btn-primary">{{ $submitLabel }}</button>
        </div>
    </div>
</div>
