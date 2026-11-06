<div class="ev-form-grid">
    <div class="ak-field ev-span-2">
        <label for="name">Shop name *</label>
        <input id="name" type="text" name="name" required maxlength="255" value="{{ old('name', $vendor->name) }}" autofocus>
    </div>
    <div class="ak-field">
        <label for="category">Category</label>
        <select id="category" class="ev-select" name="category">
            @foreach($categories as $key => $label)
                <option value="{{ $key }}" @selected(old('category', $vendor->category) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field"><label for="phone">Phone</label><input id="phone" type="text" name="phone" maxlength="40" value="{{ old('phone', $vendor->phone) }}"></div>
    <div class="ak-field"><label for="city">City</label><input id="city" type="text" name="city" maxlength="80" value="{{ old('city', $vendor->city) }}"></div>
    <div class="ak-field"><label for="contact_person">Contact person</label><input id="contact_person" type="text" name="contact_person" maxlength="255" value="{{ old('contact_person', $vendor->contact_person) }}"></div>
    <div class="ak-field ev-span-2"><label for="address">Address</label><input id="address" type="text" name="address" maxlength="500" value="{{ old('address', $vendor->address) }}"></div>
    <div class="ak-field"><label for="tax_number">NTN / CNIC (optional)</label><input id="tax_number" type="text" name="tax_number" maxlength="60" value="{{ old('tax_number', $vendor->tax_number) }}"></div>
    <div class="ak-field ev-span-2">
        <label for="default_expense_account_id">Usual expense account</label>
        <select id="default_expense_account_id" class="ev-select" name="default_expense_account_id" data-clearable="1" data-placeholder="Filled in automatically when this shop is picked on a voucher">
            <option value=""></option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected((int) old('default_expense_account_id', $vendor->default_expense_account_id) === $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="ak-field ev-span-3"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes', $vendor->notes) }}</textarea></div>
    <div class="ev-span-3" style="display:flex; flex-wrap:wrap; gap:20px">
        @if($vendor->exists)
            <label class="ev-remove"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $vendor->is_active))> Active (shown when making vouchers)</label>
        @endif
        <label class="ev-remove"><input type="checkbox" name="confirm_duplicate" value="1"> Save anyway if a shop with this name already exists</label>
    </div>
</div>
@push('scripts')
    <script>$(function () { $('.ev-select').each(function () { const $s = $(this); $s.select2({ width: '100%', allowClear: !!$s.data('clearable'), placeholder: $s.data('placeholder') || '' }); }); });</script>
@endpush
