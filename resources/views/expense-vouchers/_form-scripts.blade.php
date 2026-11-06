@push('scripts')
    <script>
        // Plain dropdowns are searchable. Shops and employees are loaded on request while typing, so the form
        // stays light however many exist.
        function evAjaxSelect(selector, onPick) {
            const $el = $(selector);
            if (! $el.length || $el.data('select2')) { return; }
            $el.select2({
                width: '100%', allowClear: true, placeholder: $el.data('placeholder'),
                ajax: { url: $el.data('search-url'), dataType: 'json', delay: 250, data: (params) => ({ q: params.term || '' }), processResults: (data) => data },
            });
            if (onPick) { $el.on('select2:select', (event) => onPick(event.params.data)); }
        }

        function evSetAccountFromShop(data) {
            const $account = $('#expense_account_id');
            if (data.account_id && $account.length) { $account.val(String(data.account_id)).trigger('change'); }
        }

        $(function () {
            $('.ev-select').each(function () {
                const $select = $(this);
                $select.select2({
                    width: '100%',
                    placeholder: $select.data('placeholder') || $select.find('option[value=""]').text() || 'Select',
                    allowClear: !!$select.data('clearable'),
                });
            });
            // Choosing a category fills in its usual expense account (still changeable).
            $('#expense_category_id').on('select2:select', (event) => {
                const account = $(event.params.data.element).data('account');
                if (account) { $('#expense_account_id').val(String(account)).trigger('change'); }
            });
            const bind = () => { evAjaxSelect('#expense_vendor_id', evSetAccountFromShop); evAjaxSelect('#employee_id'); };
            bind();
            // The payee panels are rendered on demand (x-if), so wire the dropdowns whenever one appears.
            new MutationObserver(bind).observe(document.querySelector('.ev-form-grid'), { childList: true, subtree: true });
        });

        function shopModal() {
            const blank = () => ({ name: '', category: 'shop', phone: '', city: '', contact_person: '', address: '' });
            return {
                open: false, busy: false, error: '', duplicate: null, form: blank(),
                async save(confirmDuplicate = false) {
                    this.error = ''; this.busy = true;
                    try {
                        const response = await fetch(@js(route('expense-vendors.store')), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ ...this.form, confirm_duplicate: confirmDuplicate }),
                        });
                        const data = await response.json();
                        if (response.status === 409) { this.duplicate = data; return; }
                        if (! response.ok) { this.error = Object.values(data.errors || {}).flat()[0] || data.message || 'Could not save the shop.'; return; }
                        this.pick(data);
                    } catch (e) {
                        this.error = 'Could not reach the server. Try again.';
                    } finally { this.busy = false; }
                },
                useExisting() { this.pick(this.duplicate.duplicate); },
                pick(shop) {
                    const $select = $('#expense_vendor_id');
                    $select.find('option[value="' + shop.id + '"]').remove();
                    $select.append(new Option(shop.text, shop.id, true, true)).trigger('change');
                    evSetAccountFromShop(shop);
                    this.open = false; this.duplicate = null; this.form = blank();
                },
            };
        }
    </script>
@endpush
