{{--
    Helpers the Option A and Option B GRN forms add on top of the page's own grnForm():
    line status (error / warning / ok), filters and search, line navigation, totals and the
    check that runs before the form is sent. The calculations themselves stay in grnForm().
--}}
@push('scripts')
    <script>
        function grnLayout(base) {
            const extras = {
                selected: 0,
                lineFilter: 'all',
                lineSearch: '',
                columnSet: 'all',
                productsVersion: 0,
                submitTried: false,
                // Option E steps through details → lines → review; Option G folds the details away.
                wizard: false,
                step: 1,
                headerOpen: true,
                batchFor: null,
                // Option I edits each line in a popup.
                lineModal: false,
                lineModalOpen: false,

                openLine(index) {
                    this.selectLine(index);
                    this.lineModalOpen = true;
                },

                addLineInModal() {
                    this.addItem();
                    this.$nextTick(() => this.openLine(this.items.length - 1));
                },
                headerVersion: 0,

                init() {
                    // Select2 changes don't reach Alpine, so the folded header summary listens itself.
                    const bump = () => this.headerVersion++;
                    if (window.jQuery) {
                        jQuery(document).on('change', '#supplier_id, #warehouse_id', bump);
                    }
                    document.addEventListener('input', (event) => {
                        if (['receipt_date', 'supplier_invoice_number'].includes(event.target.id)) {
                            bump();
                        }
                    });
                },

                openBatch(index) {
                    this.batchFor = index;
                    this.$nextTick(() => document.getElementById('bn_' + index)?.focus());
                },

                hasBatchDetails(item) {
                    return !!(item.batch_number || item.lot_number || item.storage_location || item.manufacturing_date || item.expiry_date || item.must_sell_before
                        || (item.selling_strategy && item.selling_strategy !== 'fifo') || item.priority_order || (item.quality_status && item.quality_status !== 'approved'));
                },

                batchSummary(item) {
                    if (!this.hasBatchDetails(item)) {
                        return 'Not set · FIFO · approved · batch and lot numbers are made for you';
                    }
                    return [
                        item.batch_number ? 'Batch ' + item.batch_number : '',
                        item.lot_number ? 'Lot ' + item.lot_number : '',
                        item.expiry_date ? 'Expires ' + item.expiry_date : '',
                        item.storage_location || '',
                        (item.selling_strategy || 'fifo').toUpperCase().replace('_', ' '),
                        item.quality_status && item.quality_status !== 'approved' ? item.quality_status : '',
                    ].filter(Boolean).join(' · ');
                },

                clearBatch(item) {
                    Object.assign(item, { batch_number: '', lot_number: '', storage_location: '', manufacturing_date: '', expiry_date: '', must_sell_before: '', selling_strategy: 'fifo', priority_order: null, quality_status: 'approved' });
                },

                goStep(step) {
                    if (step > 1 && (!$('#supplier_id').val() || !$('#warehouse_id').val())) {
                        this.step = 1;
                        window.dispatchEvent(new CustomEvent('open-alert-modal', {
                            detail: { title: 'Choose supplier and warehouse', message: '<p>Pick the supplier and the warehouse first.</p>' }
                        }));
                        return;
                    }
                    this.step = step;
                    this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
                },

                headerSummary() {
                    this.headerOpen;
                    this.step;
                    this.headerVersion;
                    const pick = (id) => {
                        const el = document.getElementById(id);
                        return el && el.selectedIndex > 0 ? el.options[el.selectedIndex].text.trim() : '';
                    };
                    const date = document.getElementById('receipt_date')?.value || '';
                    const invoice = document.getElementById('supplier_invoice_number')?.value || '';
                    return [pick('supplier_id') || 'No supplier yet', pick('warehouse_id'), date, invoice ? 'Inv ' + invoice : ''].filter(Boolean).join(' · ');
                },

                // Option A grid columns: the group each belongs to and the column sets it shows in.
                gridColumns: {
                    carton: { group: 'qty', sets: ['all', 'entry'], when: 'carton' },
                    uom: { group: 'qty', sets: ['all', 'entry'] },
                    qtyPurchase: { group: 'qty', sets: ['all', 'entry'] },
                    factor: { group: 'qty', sets: ['all', 'entry'] },
                    qtyStock: { group: 'qty', sets: ['all', 'entry'] },
                    received: { group: 'qty', sets: ['all', 'entry'] },
                    price: { group: 'price', sets: ['all', 'entry'] },
                    extended: { group: 'price', sets: ['all', 'entry'] },
                    discount: { group: 'price', sets: ['all', 'entry'] },
                    fmr: { group: 'price', sets: ['all', 'entry'] },
                    beforeTax: { group: 'price', sets: ['all', 'tax'] },
                    excise: { group: 'tax', sets: ['all', 'tax'] },
                    salesTax: { group: 'tax', sets: ['all', 'tax'] },
                    advanceTax: { group: 'tax', sets: ['all', 'tax'] },
                    other: { group: 'tax', sets: ['all', 'tax'] },
                    withholding: { group: 'tax', sets: ['all', 'tax'], when: 'unilever' },
                    unitCost: { group: 'cost', sets: ['all', 'tax'] },
                    selling: { group: 'cost', sets: ['all', 'entry'] },
                },

                colVisible(key) {
                    const column = this.gridColumns[key];
                    if (!column || !column.sets.includes(this.columnSet)) {
                        return false;
                    }
                    if (column.when === 'carton') {
                        return this.isCartonMode;
                    }
                    if (column.when === 'unilever') {
                        return this.isUnileverPakistan;
                    }
                    return true;
                },

                groupSpan(group) {
                    return Object.keys(this.gridColumns).filter(key => this.gridColumns[key].group === group && this.colVisible(key)).length;
                },

                productOf(item) {
                    this.productsVersion;
                    return (typeof allProducts !== 'undefined' ? allProducts : []).find(p => String(p.id) === String(item.product_id)) || null;
                },

                productLabel(item) {
                    const product = this.productOf(item);
                    if (product) {
                        return product.product_name;
                    }
                    return item.product_id ? 'Product #' + item.product_id : 'New line';
                },

                productCode(item) {
                    const product = this.productOf(item);
                    return product ? product.product_code : '';
                },

                isBlankLine(item) {
                    return !item.product_id && !(parseFloat(item.qty_in_stock_uom) > 0) && !(parseFloat(item.unit_price_per_case) > 0);
                },

                lineIssue(item) {
                    if (this.isBlankLine(item)) {
                        return { level: 'new', text: 'Choose a product to start' };
                    }
                    if (!item.product_id) {
                        return { level: 'error', text: 'Choose a product' };
                    }
                    if (!(parseFloat(item.qty_in_stock_uom) > 0)) {
                        return { level: 'error', text: this.isCartonMode ? 'Enter cartons or loose pieces' : 'Enter a quantity' };
                    }
                    if (!(parseFloat(item.extended_value) > 0)) {
                        return { level: 'error', text: this.isCartonMode ? 'Enter the rate per TH' : 'Enter the price per case' };
                    }
                    const deductions = (parseFloat(item.discount_value) || 0) + (parseFloat(item.fmr_allowance) || 0);
                    if (deductions > (parseFloat(item.extended_value) || 0)) {
                        return { level: 'error', text: 'Discount and FMR are more than the value' };
                    }
                    if (item.manufacturing_date && item.expiry_date && item.expiry_date < item.manufacturing_date) {
                        return { level: 'error', text: 'Expiry is before the manufacturing date' };
                    }
                    const selling = parseFloat(item.selling_price) || 0;
                    const cost = parseFloat(item.unit_cost) || 0;
                    if (!(selling > 0)) {
                        return { level: 'warning', text: 'No selling price' };
                    }
                    if (cost > 0 && selling < cost) {
                        return { level: 'warning', text: 'Selling price is below the unit cost' };
                    }
                    return { level: 'ok', text: '' };
                },

                get issueCounts() {
                    const counts = { error: 0, warning: 0, new: 0 };
                    this.items.forEach(item => {
                        const level = this.lineIssue(item).level;
                        if (counts[level] !== undefined) {
                            counts[level]++;
                        }
                    });
                    return counts;
                },

                trailingBlankCount() {
                    let count = 0;
                    for (let i = this.items.length - 1; i >= 0 && this.isBlankLine(this.items[i]); i--) {
                        count++;
                    }
                    return count;
                },

                get blockingCount() {
                    const counts = this.issueCounts;
                    const trailing = this.trailingBlankCount();
                    const filled = this.items.length - counts.new;
                    return counts.error + (counts.new - trailing) + (filled === 0 ? 1 : 0);
                },

                lineMatches(item, index) {
                    const level = this.lineIssue(item).level;
                    if (this.lineFilter === 'error' && level !== 'error') {
                        return false;
                    }
                    if (this.lineFilter === 'warning' && level !== 'warning') {
                        return false;
                    }
                    const term = this.lineSearch.trim().toLowerCase();
                    if (!term) {
                        return true;
                    }
                    return String(index + 1) === term
                        || this.productLabel(item).toLowerCase().includes(term)
                        || this.productCode(item).toLowerCase().includes(term);
                },

                lineSummary(item) {
                    const issue = this.lineIssue(item);
                    if (issue.level !== 'ok') {
                        return issue.text;
                    }
                    const pieces = this.formatNumber(item.qty_in_stock_uom);
                    if (this.isCartonMode) {
                        return (item.entered_cartons || 0) + ' ctn' + (parseInt(item.entered_loose_pieces) ? ' + ' + item.entered_loose_pieces : '') + ' · ' + pieces + ' pcs';
                    }
                    return this.formatNumber(item.qty_in_purchase_uom) + ' cases · ' + pieces + ' pcs';
                },

                /**
                 * Where a price, deduction or tax input sits in the line: the supplier's column order
                 * (Settings → Suppliers → Edit), ten apart so the worked-out boxes can sit between them.
                 */
                colOrder(key) {
                    const at = (this.lineColumns || []).findIndex((column) => column.key === key);
                    return (at < 0 ? 50 : at) * 10;
                },

                /** "Value before sales tax" follows the last of price, discount and FMR. */
                beforeTaxOrder() {
                    return Math.max(this.colOrder('unit_price_per_case'), this.colOrder('discount_value'), this.colOrder('fmr_allowance')) + 2;
                },

                /**
                 * A column the supplier hid stays hidden only while the line has no amount in it, so no
                 * figure is ever out of sight. Withholding tax only applies to Unilever Pakistan.
                 */
                colShown(item, key) {
                    if (parseFloat(item[key]) > 0) {
                        return true;
                    }
                    if (key === 'withholding_tax' && ! this.isUnileverPakistan) {
                        return false;
                    }
                    const column = (this.lineColumns || []).find((entry) => entry.key === key);
                    return column ? column.visible !== false : true;
                },

                /** Total cases on the GRN (cartons when the supplier is entered in cartons). */
                totalCases() {
                    return this.sumOf(this.isCartonMode ? 'entered_cartons' : 'qty_in_purchase_uom');
                },

                sumOf(field) {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item[field]) || 0), 0);
                },

                selectLine(index) {
                    this.selected = Math.max(0, Math.min(index, this.items.length - 1));
                    this.$nextTick(() => {
                        const row = document.getElementById('grn-line-row-' + this.selected);
                        if (row) {
                            row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        }
                        const entryBar = document.getElementById('grn-entry-bar');
                        if (entryBar && entryBar.getBoundingClientRect().top < 0) {
                            entryBar.scrollIntoView({ block: 'start', behavior: 'smooth' });
                        }
                    });
                },

                nextLine() {
                    if (this.selected >= this.items.length - 1) {
                        this.addItem();
                        this.$nextTick(() => this.selectLine(this.items.length - 1));
                        return;
                    }
                    this.selectLine(this.selected + 1);
                },

                prevLine() {
                    this.selectLine(this.selected - 1);
                },

                addLinesAndSelect(count) {
                    const howMany = parseInt(count) || 1;
                    const firstNew = this.items.length;
                    for (let i = 0; i < howMany; i++) {
                        this.addItem();
                    }
                    this.$nextTick(() => this.selectLine(firstNew));
                },

                removeLine(index) {
                    if (this.items.length <= 1) {
                        return;
                    }
                    this.removeItem(index);
                    this.selectLine(Math.min(index, this.items.length - 1));
                },

                jumpToFirstProblem() {
                    const index = this.items.findIndex(item => ['error', 'new'].includes(this.lineIssue(item).level));
                    if (index >= 0) {
                        this.lineFilter = 'all';
                        if (this.wizard) {
                            this.step = 2;
                        }
                        if (this.lineModal) {
                            this.lineModalOpen = true;
                        }
                        this.selectLine(index);
                        const row = document.getElementById('grn-line-row-' + index);
                        if (row) {
                            row.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        }
                    }
                },

                checkBeforeSubmit(event) {
                    this.submitTried = true;
                    const form = event.target;
                    const trailing = this.items.length > 1 ? Math.min(this.trailingBlankCount(), this.items.length - 1) : 0;

                    if (this.blockingCount > 0) {
                        event.preventDefault();
                        this.jumpToFirstProblem();
                        return;
                    }

                    if (trailing > 0) {
                        // Empty lines at the end are dropped rather than blocking the save; the form
                        // is sent once Alpine has taken their inputs off the page.
                        event.preventDefault();
                        this.items.splice(this.items.length - trailing, trailing);
                        this.selected = 0;
                        this.$nextTick(() => form.submit());
                    }
                },

                moveDown(event) {
                    const cell = event.target.closest('[data-col]');
                    const row = event.target.closest('[data-row]');
                    if (!cell || !row) {
                        return;
                    }
                    const next = document.querySelector('[data-row="' + (parseInt(row.dataset.row) + 1) + '"] [data-col="' + cell.dataset.col + '"] input');
                    if (next) {
                        next.focus();
                        next.select();
                        return;
                    }
                    this.addItem();
                },
            };

            Object.defineProperties(base, Object.getOwnPropertyDescriptors(extras));

            return base;
        }
    </script>
@endpush
