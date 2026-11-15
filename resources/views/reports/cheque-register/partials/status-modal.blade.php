{{-- Status sheet of the Cheque Register: one cheque (pending, cleared, bounced or cancelled) or a bulk clearing of the
     selected pending cheques. A cash clearing counts the notes with <x-cash-notes>; a bulk cash clearing must be one
     company. A bounce asks for its date and an optional bank charge, with the deposit bank when a charge is entered. --}}
<div x-data="chequeStatusModal()" x-on:open-cheque-status-modal.window="open($event.detail)" x-cloak class="no-print">
    <div x-show="show" x-on:keydown.escape.window="if (show) { close() }" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div x-show="show" x-transition.opacity.duration.200ms class="crx-scrim fixed inset-0" @click="close()"></div>

        <div class="fixed inset-0 z-10 flex items-end sm:items-center justify-center overflow-y-auto sm:p-4">
            <div x-show="show" x-transition:enter="duration-300 [transition-timing-function:cubic-bezier(.23,1,.32,1)]"
                x-transition:enter-start="opacity-0 translate-y-3 scale-[.97]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="ease-out duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[.98]"
                class="crx-sheet relative transform overflow-hidden bg-white text-left w-full sm:max-w-2xl"
                role="dialog" aria-modal="true" aria-labelledby="crx-status-title" @click.outside="close()">

                <div class="crx-sheet-head">
                    <div>
                        <h3 id="crx-status-title" x-text="title()"></h3>
                        <p>
                            <span class="crx-tag" x-show="clearing()" x-text="byCash() ? 'Cash back' : 'By bank'"></span>
                            <template x-if="bulk"><span><b x-text="cheques.length"></b> cheques</span></template>
                            <template x-if="! bulk"><span>Cheque <b x-text="form.number"></b> · <span x-text="form.company"></span></span></template>
                            · Rs <span x-text="money(form.amount)"></span>
                        </p>
                    </div>
                    <button type="button" @click="close()" class="crx-close" aria-label="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form :action="formAction" method="POST" class="p-5 sm:p-6 space-y-5 max-h-[78vh] overflow-y-auto" @submit="submit($event)">
                    @csrf
                    <template x-for="cheque in cheques" :key="cheque.id"><input type="hidden" name="cheque_ids[]" :value="cheque.id"></template>

                    <div x-show="bulk">
                        <span class="crx-label">Selected cheques</span>
                        <ul class="crx-picked">
                            <template x-for="cheque in cheques" :key="cheque.id">
                                <li><span class="n" x-text="cheque.number"></span><span class="c" x-text="cheque.company"></span><span class="a" x-text="'Rs ' + money(cheque.amount)"></span></li>
                            </template>
                        </ul>
                        <p class="crx-warn" role="alert" x-show="byCash() && mixedCompanies()" x-text="'Cash clearing must be one company at a time. Selected: ' + companies().join(', ') + '. Clear each company separately, or choose Deposited in bank.'"></p>
                    </div>

                    <div x-show="! bulk">
                        <span class="crx-label">Status</span>
                        <div class="crx-seg" role="radiogroup" aria-label="Cheque status">
                            <template x-for="option in [['pending','Pending'],['cleared','Cleared'],['bounced','Bounced'],['cancelled','Cancelled']]" :key="option[0]">
                                <label class="crx-seg-option" :class="[form.status === option[0] ? 'is-on' : '', 'tone-' + option[0]]">
                                    <input type="radio" name="status" :value="option[0]" x-model="form.status" class="sr-only" :disabled="bulk">
                                    <span x-text="option[1]"></span>
                                </label>
                            </template>
                        </div>
                        <p class="crx-info" x-show="original === 'cleared' && form.status !== 'cleared'">A cleared cheque can be changed only on the day it was cleared. If it was cleared with other cheques, the whole clearing reopens.</p>
                    </div>

                    <div x-show="clearing()" class="space-y-5">
                        <div>
                            <span class="crx-label">How was it cleared?</span>
                            <div class="crx-seg" style="grid-template-columns: repeat(2, minmax(0, 1fr))" role="radiogroup" aria-label="How the cheque was cleared">
                                <label class="crx-seg-option tone-cleared" :class="form.via === 'cash' && 'is-on'">
                                    <input type="radio" name="cleared_via" value="cash" x-model="form.via" class="sr-only" :disabled="! clearing()">
                                    <span>Cash brought back</span>
                                </label>
                                <label class="crx-seg-option tone-cleared" :class="form.via === 'bank' && 'is-on'">
                                    <input type="radio" name="cleared_via" value="bank" x-model="form.via" class="sr-only" :disabled="! clearing()">
                                    <span>Deposited in bank</span>
                                </label>
                            </div>
                            <p class="crx-help" x-text="byCash() ? 'The salesman brought the cash and took the cheque back. Booked: Cash ← Cheques in Hand.' : 'The cheque was deposited and the bank cleared it. Booked: Bank ← Cheques in Hand.'"></p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="crx-label" for="crx-cleared-date">Cleared date</label>
                                <input id="crx-cleared-date" type="date" name="cleared_date" x-model="form.cleared_date" max="{{ now()->toDateString() }}" :disabled="! clearing()" x-bind:required="clearing()">
                            </div>
                            <div>
                                <span class="crx-label">Amount cleared</span>
                                <div class="crx-readonly" aria-live="polite"><span>Rs <span x-text="money(form.amount)"></span></span><small x-text="bulk ? 'all selected cheques' : 'full cheque amount'"></small></div>
                            </div>
                        </div>

                        <div x-show="! byCash()">
                            <label class="crx-label" for="crx-bank">Bank it was deposited in</label>
                            <select id="crx-bank" name="cleared_bank_account_id" x-model="form.bank_id" :disabled="! clearing() || byCash()" x-bind:required="clearing() && ! byCash()">
                                <option value="">Choose a bank…</option>
                                @foreach ($depositBanks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->account_name }}{{ $bank->account_number ? ' · '.$bank->account_number : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="crx-notes" x-show="byCash()">
                            <p class="text-[15px] font-semibold" style="color:#1d1d1f">Cash notes <span class="cr-required">*</span></p>
                            <p class="crx-help mb-2" x-text="bulk ? 'Count all the notes brought back for these cheques together. The total must equal the cheques. They are stored once on this clearing and added to that day\'s Cash Collection report.' : 'Count every note brought back. The total must equal the cheque. These notes are added to the Cash Collection report.'"></p>
                            <x-cash-notes name="denom" model="form.denom" required="form.amount" disabled="! (clearing() && byCash())" />
                            <p class="crx-warn" x-show="notesError && ! notesMatch()" x-cloak>The notes must add up to Rs <span x-text="money(form.amount)"></span> before you can save.</p>
                        </div>
                    </div>

                    <div x-show="bouncing()" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="crx-label" for="crx-bounce-date">Bounce date</label>
                                <input id="crx-bounce-date" type="date" name="status_date" x-model="form.status_date" max="{{ now()->toDateString() }}" :disabled="! bouncing()" x-bind:required="bouncing()">
                            </div>
                            <div>
                                <label class="crx-label" for="crx-bank-charge">Bank charge (optional, Rs)</label>
                                <input id="crx-bank-charge" type="number" min="0" step="0.01" inputmode="decimal" name="bank_charge" x-model="form.bank_charge" placeholder="0" :disabled="! bouncing()">
                            </div>
                        </div>
                        <div x-show="hasBankCharge()">
                            <label class="crx-label" for="crx-bounce-bank">Deposit bank that charged the fee</label>
                            <select id="crx-bounce-bank" name="bank_account_id" x-model="form.cheque_bank_id" :disabled="! hasBankCharge()" x-bind:required="hasBankCharge()">
                                <option value="">Choose a bank…</option>
                                @foreach ($depositBanks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->account_name }}{{ $bank->account_number ? ' · '.$bank->account_number : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p class="crx-help">The cheque goes back onto the customer: Debtors ← Cheques in Hand. A bank charge is added to the customer and taken from the deposit bank.</p>
                    </div>

                    <div>
                        <label class="crx-label" for="crx-notes-text">Remarks (optional)</label>
                        <input id="crx-notes-text" type="text" name="notes" maxlength="500" x-model="form.notes">
                    </div>

                    <div class="crx-foot -mx-5 sm:-mx-6 -mb-5 sm:-mb-6 sticky bottom-[-24px]">
                        <button type="button" @click="close()" class="crx-btn crx-btn-secondary">Cancel</button>
                        <button type="submit" class="crx-btn crx-btn-primary" :class="clearing() && 'is-cleared'" :disabled="busy || (bulk && byCash() && mixedCompanies())"
                            x-text="busy ? 'Saving…' : (bulk ? 'Clear ' + cheques.length + ' cheques' : (clearing() ? 'Save cleared cheque' : 'Update status'))">Update status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        function chequeStatusModal() {
            return {
                show: false,
                bulk: false,
                busy: false,
                cheques: [],
                original: 'pending',
                formAction: '',
                form: { status: 'pending', via: 'cash', bank_id: '', cheque_bank_id: '', cleared_date: '', status_date: '', bank_charge: '', amount: 0, number: '', company: '', notes: '', denom: {} },
                notes: @js(\App\Support\CashNotes::NOTES),
                notesError: false,

                clearing() { return this.bulk || this.form.status === 'cleared'; },
                bouncing() { return ! this.bulk && this.form.status === 'bounced'; },
                byCash() { return this.form.via !== 'bank'; },
                hasBankCharge() { return this.bouncing() && Number(this.form.bank_charge) > 0; },
                companies() { return [...new Set(this.cheques.map((cheque) => cheque.company))]; },
                mixedCompanies() { return new Set(this.cheques.map((cheque) => cheque.supplierId)).size > 1; },

                title() {
                    if (this.bulk) {
                        return this.byCash() ? 'Clear selected cheques: count the cash' : 'Clear selected cheques by bank';
                    }
                    if (this.bouncing()) {
                        return 'Cheque bounced';
                    }

                    return this.clearing() ? (this.byCash() ? 'Cheque cleared: count the cash' : 'Cheque cleared by bank') : 'Update cheque status';
                },

                money(value) { return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 }); },

                counted() {
                    return this.notes.reduce((sum, note) => sum + note * (Math.max(0, parseInt(this.form.denom[note], 10)) || 0), 0) + (Number(this.form.denom.coins) || 0);
                },

                notesMatch() { return this.counted() > 0 && Math.abs(this.counted() - Number(this.form.amount || 0)) < 0.01; },

                submit(event) {
                    if (this.clearing() && this.byCash() && (! this.notesMatch() || (this.bulk && this.mixedCompanies()))) {
                        event.preventDefault();
                        this.notesError = true;

                        return;
                    }
                    this.busy = true;
                },

                open(detail) {
                    const today = new Date().toLocaleDateString('en-CA');
                    this.bulk = !! detail.bulk;
                    this.cheques = this.bulk ? detail.cheques : [];
                    this.formAction = detail.url;
                    this.original = this.bulk ? 'pending' : (detail.status || 'pending');
                    this.form = {
                        status: this.bulk ? 'cleared' : this.original,
                        via: detail.via || 'cash',
                        bank_id: detail.bankId || '',
                        cheque_bank_id: detail.chequeBankId || '',
                        cleared_date: detail.clearedDate || today,
                        status_date: today,
                        bank_charge: '',
                        amount: this.bulk ? Math.round(this.cheques.reduce((sum, cheque) => sum + cheque.amount, 0) * 100) / 100 : Number(detail.amount || 0),
                        number: detail.number || '',
                        company: detail.company || '',
                        notes: '',
                        denom: {},
                    };
                    this.notesError = false;
                    this.busy = false;
                    this.show = true;
                },

                close() { this.show = false; },
            };
        }
    </script>
@endpush
