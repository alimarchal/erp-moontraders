{{-- History sheet of the Cheque Register: every status change of a cheque, newest first, with how and where it was
     cleared, its clearing batch, the journals it posted or reversed, a bounce's bank charge and the batch's notes. --}}
<div x-data="chequeHistoryModal()" x-on:open-cheque-history-modal.window="open($event.detail)" x-cloak class="no-print">
    <div x-show="show" x-on:keydown.escape.window="close()" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="crx-scrim fixed inset-0" @click="close()"></div>
        <div class="fixed inset-0 z-10 flex items-end sm:items-center justify-center overflow-y-auto sm:p-4">
            <div class="crx-sheet relative w-full sm:max-w-4xl overflow-hidden bg-white" role="dialog" aria-modal="true" aria-labelledby="crx-history-title" @click.outside="close()">
                <div class="crx-sheet-head">
                    <div>
                        <h3 id="crx-history-title">Status history</h3>
                        <p>Cheque <b x-text="data.cheque_number"></b><span x-show="data.amount"> · Rs <span x-text="data.amount"></span></span></p>
                    </div>
                    <button type="button" @click="close()" class="crx-close" aria-label="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="p-4 max-h-[72vh] overflow-y-auto">
                    <p x-show="loading" class="text-sm" style="color:#6e6e73">Loading…</p>
                    <p x-show="failed" class="crx-warn">The history could not be loaded. Close this window and try again.</p>
                    <p x-show="! loading && ! failed && data.entries.length === 0" class="text-sm" style="color:#6e6e73">No status change has been recorded for this cheque yet.</p>
                    <table x-show="data.entries.length > 0" class="crx-history w-full">
                        <thead>
                            <tr><th>When</th><th>Change</th><th>Cleared · via</th><th>Batch · journals</th><th>By</th><th>Remarks · notes</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="(entry, index) in data.entries" :key="index">
                                <tr>
                                    <td data-label="When" class="whitespace-nowrap" x-text="entry.when"></td>
                                    <td data-label="Change" class="whitespace-nowrap"><span x-text="entry.from"></span> &rarr; <b x-text="entry.to"></b></td>
                                    <td data-label="Cleared">
                                        <span x-text="entry.cleared_date || '—'"></span><span x-show="entry.cleared_amount"> · Rs <span x-text="entry.cleared_amount"></span></span>
                                        <div class="muted" x-show="entry.via" x-text="(entry.via === 'bank' ? 'By bank' : 'Cash back') + (entry.bank ? ' · ' + entry.bank : '')"></div>
                                        <div class="muted" x-show="entry.bank_charge" x-text="'Bank charge Rs ' + entry.bank_charge"></div>
                                    </td>
                                    <td data-label="Batch">
                                        <span x-show="entry.batch" x-text="'Batch #' + entry.batch"></span>
                                        <template x-if="entry.journal_entry">
                                            <div><a :href="journalUrl(entry.journal_entry.id)" target="_blank" x-text="'JE ' + (entry.journal_entry.reference || entry.journal_entry.id)"></a></div>
                                        </template>
                                        <template x-if="entry.reversed_journal_entry">
                                            <div class="muted">reverses <a :href="journalUrl(entry.reversed_journal_entry.id)" target="_blank" x-text="'JE ' + (entry.reversed_journal_entry.reference || entry.reversed_journal_entry.id)"></a></div>
                                        </template>
                                        <span x-show="! entry.batch && ! entry.journal_entry && ! entry.reversed_journal_entry">—</span>
                                    </td>
                                    <td data-label="By" x-text="entry.by || '—'"></td>
                                    <td data-label="Remarks">
                                        <span x-text="entry.notes || ''"></span>
                                        <template x-if="entry.batch_notes">
                                            <div class="muted" x-text="notesLine(entry.batch_notes)"></div>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        function chequeHistoryModal() {
            const journalBase = @js(url('journal-entries'));

            return {
                show: false,
                loading: false,
                failed: false,
                data: { cheque_number: '', amount: '', entries: [] },

                journalUrl(id) { return journalBase + '/' + id; },

                notesLine(batch) {
                    const parts = Object.entries(batch.notes || {}).sort((a, b) => Number(b[0]) - Number(a[0])).map(([note, count]) => Number(note).toLocaleString() + ' × ' + count);
                    if (Number(batch.coins) > 0) {
                        parts.push('coins Rs ' + Number(batch.coins).toLocaleString());
                    }

                    return 'Notes: ' + (parts.join(', ') || 'none') + ' = Rs ' + batch.total;
                },

                async open(detail) {
                    this.data = { cheque_number: '', amount: '', entries: [] };
                    this.failed = false;
                    this.loading = true;
                    this.show = true;
                    try {
                        const response = await fetch(detail.url, { headers: { Accept: 'application/json' } });
                        if (! response.ok) {
                            throw new Error('history ' + response.status);
                        }
                        this.data = await response.json();
                    } catch (error) {
                        this.failed = true;
                    } finally {
                        this.loading = false;
                    }
                },

                close() { this.show = false; },
            };
        }
    </script>
@endpush
