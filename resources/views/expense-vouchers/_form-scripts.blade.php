@push('scripts')
    <script>
        // One click only: a second submit of the voucher form is ignored and the pressed button shows its busy label.
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (! form.matches('form[data-once-form]') || event.defaultPrevented) { return; }
            if (form.dataset.sending === '1') { event.preventDefault(); return; }
            form.dataset.sending = '1';
            form.setAttribute('aria-busy', 'true');
            const pressed = event.submitter;
            if (pressed && pressed.dataset.busyLabel) { pressed.textContent = pressed.dataset.busyLabel; }
            setTimeout(() => form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = true; }), 0);
        });
        window.addEventListener('pageshow', (event) => {
            if (! event.persisted) { return; }
            document.querySelectorAll('form[data-once-form][data-sending]').forEach((form) => { location.reload(); });
        });

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

        // A shop's usual account goes to the bill lines that have no category yet (handled by evVoucherForm).
        function evSetAccountFromShop(data) {
            if (data.account_id) { window.dispatchEvent(new CustomEvent('ev-shop-account', { detail: String(data.account_id) })); }
        }

        /**
         * The voucher form: who is paid, how, and the bill lines. Each line has a category, an expense account (filled
         * from the category), a description and an amount; the total is only shown here, the server adds it up again.
         */
        function evVoucherForm(start) {
            let next = 0;
            const line = (values = {}) => ({ uid: ++next, category: '', account: start.defaultAccount || '', description: '', amount: '', ...values });

            return {
                payee: start.payee,
                method: start.method,
                amount: start.amount,
                max: start.max,
                errors: start.errors || {},
                lines: (start.lines && start.lines.length ? start.lines : [{}]).map((values) => line(values)),

                total() {
                    const paisa = this.lines.reduce((sum, l) => sum + Math.round((parseFloat(String(l.amount).replace(/,/g, '')) || 0) * 100), 0);
                    return paisa / 100;
                },
                money(value) {
                    return (Number(value) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                err(index, field) {
                    return this.errors['lines.' + index + '.' + field] || '';
                },
                addLine() {
                    if (this.lines.length >= this.max) { return; }
                    this.lines.push(line());
                    this.$nextTick(() => {
                        const rows = this.$root.querySelectorAll('.ev-lines tbody tr');
                        const $category = $(rows[rows.length - 1]).find('.ev-line-category');
                        if ($category.data('select2')) { $category.select2('open'); }
                    });
                },
                removeLine(index) {
                    if (this.lines.length === 1) { return; }
                    this.lines.splice(index, 1);
                    this.errors = {};
                },
                shopAccount(accountId) {
                    this.lines.filter((l) => ! l.category).forEach((l) => { l.account = accountId; });
                    this.$root.querySelectorAll('.ev-lines tbody tr').forEach((row, index) => {
                        const l = this.lines[index];
                        if (l && ! l.category) { $(row).find('.ev-line-account').val(accountId).trigger('change.select2'); }
                    });
                },
                // Searchable category and account dropdowns for one row, kept in step with the line's values.
                initLine(row, l) {
                    const $category = $(row).find('.ev-line-category');
                    const $account = $(row).find('.ev-line-account');
                    if ($category.data('select2')) { return; }
                    $category.select2({ width: '100%', placeholder: $category.data('placeholder'), allowClear: true });
                    $account.select2({ width: '100%', placeholder: $account.data('placeholder') });
                    $category.val(l.category || null).trigger('change.select2');
                    $account.val(l.account || null).trigger('change.select2');
                    $category.on('change', () => { l.category = $category.val() || ''; });
                    $account.on('change', () => { l.account = $account.val() || ''; });
                    $category.on('select2:select', (event) => {
                        const account = $(event.params.data.element).data('account');
                        if (account) { $account.val(String(account)).trigger('change'); }
                    });
                },
            };
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
            const bind = () => { evAjaxSelect('#expense_vendor_id', evSetAccountFromShop); evAjaxSelect('#employee_id'); };
            bind();
            // The payee panels are rendered on demand (x-if), so wire the dropdowns whenever one appears.
            // Pages that share these scripts without payee panels (e.g. supplier create/edit) have no .ev-sections.
            const sections = document.querySelector('.ev-sections');
            if (sections) {
                new MutationObserver(bind).observe(sections, { childList: true, subtree: true });
            }
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
