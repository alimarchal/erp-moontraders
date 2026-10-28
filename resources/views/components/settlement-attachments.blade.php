{{-- Shared support for optional settlement attachments: client file registry + preview modal. Include once per page. --}}
@if (\App\Services\SettlementAttachmentService::enabled())
    @once
        @push('scripts')
            <script>
                window.SettlementAttachments = {
                    enabled: true,
                    maxBytes: {{ (int) config('settlement_attachments.max_kb') * 1024 }},
                    allowed: @json(config('settlement_attachments.extensions')),
                    files: {},

                    extension(name) {
                        return (name.split('.').pop() || '').toLowerCase();
                    },

                    /** Validate and remember a chosen file; returns {key, name, ext} or null. */
                    register(input) {
                        const file = input.files && input.files[0];
                        if (!file) return null;

                        const ext = this.extension(file.name);
                        if (!this.allowed.includes(ext)) {
                            alert('Only PDF, JPG, PNG or WEBP files are allowed.');
                            input.value = '';
                            return null;
                        }
                        if (file.size > this.maxBytes) {
                            alert('File is too large. Maximum size is ' + Math.round(this.maxBytes / 1048576) + ' MB.');
                            input.value = '';
                            return null;
                        }

                        const key = 'a' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
                        this.files[key] = file;
                        input.value = '';

                        return { key, name: file.name, ext };
                    },

                    /** Attach a newly chosen file to an entry row, replacing any previous one. */
                    applyToEntry(entry, input) {
                        const picked = this.register(input);
                        if (!picked) return;

                        if (entry.attachment_key) delete this.files[entry.attachment_key];
                        entry.attachment_key = picked.key;
                        entry.attachment_name = picked.name;
                        entry.attachment_ext = picked.ext;
                        entry.attachment = null;
                        entry.attachment_url = null;
                    },

                    clearEntry(entry) {
                        if (entry.attachment_key) delete this.files[entry.attachment_key];
                        entry.attachment_key = null;
                        entry.attachment_name = null;
                        entry.attachment_ext = null;
                        entry.attachment = null;
                        entry.attachment_url = null;
                    },

                    /** Fields to merge into a new entry from the modal's pending file pick. */
                    entryFields(pending) {
                        return pending
                            ? { attachment_key: pending.key, attachment_name: pending.name, attachment_ext: pending.ext }
                            : {};
                    },

                    hasAttachment(entry) {
                        return !!(entry && (entry.attachment_url || (entry.attachment_key && this.files[entry.attachment_key])));
                    },

                    preview(entry) {
                        let url = entry.attachment_url;
                        let ext = entry.attachment_ext || '';
                        if (entry.attachment_key && this.files[entry.attachment_key]) {
                            url = URL.createObjectURL(this.files[entry.attachment_key]);
                        }
                        if (!url) return;
                        window.dispatchEvent(new CustomEvent('open-attachment-preview', {
                            detail: { url, ext, name: entry.attachment_name || 'Attachment' },
                        }));
                    },

                    previewUrl(url, ext, name) {
                        window.dispatchEvent(new CustomEvent('open-attachment-preview', {
                            detail: { url, ext, name: name || 'Attachment' },
                        }));
                    },

                    /** On submit, add a real file input for every file still referenced by an entry. */
                    bindForm(form) {
                        if (!form || form.dataset.attachmentsBound) return;
                        form.dataset.attachmentsBound = '1';
                        form.setAttribute('enctype', 'multipart/form-data');

                        form.addEventListener('submit', () => {
                            form.querySelectorAll('input[data-attachment-file]').forEach(el => el.remove());
                            const referenced = Array.from(form.querySelectorAll('input[type=hidden]'))
                                .map(el => el.value).join(' ');

                            Object.entries(this.files).forEach(([key, file]) => {
                                if (!referenced.includes('"' + key + '"')) return;
                                const el = document.createElement('input');
                                el.type = 'file';
                                el.name = 'attachment_files[' + key + ']';
                                el.dataset.attachmentFile = '1';
                                el.style.display = 'none';
                                const transfer = new DataTransfer();
                                transfer.items.add(file);
                                el.files = transfer.files;
                                form.appendChild(el);
                            });
                        });
                    },
                };

                document.addEventListener('DOMContentLoaded', () => {
                    window.SettlementAttachments.bindForm(document.getElementById('settlementForm'));
                });
            </script>
        @endpush
    @endonce

    @once
        <div x-data="{ open: false, url: '', ext: '', name: '' }"
             x-on:open-attachment-preview.window="url = $event.detail.url; ext = ($event.detail.ext || '').toLowerCase(); name = $event.detail.name; open = true"
             x-on:keydown.escape.window="open = false" x-cloak>
            <div x-show="open" class="fixed inset-0 z-[70]" style="display: none;">
                <div x-show="open"
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 backdrop-blur-none" x-transition:enter-end="opacity-100 backdrop-blur-sm"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 backdrop-blur-sm" x-transition:leave-end="opacity-0 backdrop-blur-none"
                     class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all" @click="open = false"></div>

                <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-2 sm:p-4" @click.self="open = false">
                    <div class="relative w-full max-w-4xl bg-white rounded-lg shadow-xl overflow-hidden flex flex-col max-h-[92vh]" @click.stop>
                        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3 bg-gradient-to-r from-green-600 to-green-700 shrink-0">
                            <h3 class="text-sm sm:text-base font-semibold text-white truncate" x-text="name"></h3>
                            <div class="flex items-center gap-2 shrink-0">
                                <a :href="url" target="_blank" rel="noopener" class="text-xs font-semibold text-white/90 hover:text-white underline">Open in new tab</a>
                                <button type="button" @click="open = false" class="rounded-lg p-1 text-white/80 hover:bg-white/20 hover:text-white transition" aria-label="Close">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex-grow overflow-auto bg-gray-100 flex items-center justify-center p-2">
                            <template x-if="open && ext === 'pdf'">
                                <iframe :src="url" class="w-full h-[75vh] bg-white rounded"></iframe>
                            </template>
                            <template x-if="open && ext !== 'pdf'">
                                <img :src="url" :alt="name" class="max-w-full max-h-[75vh] object-contain rounded">
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endonce

    {{-- Row-level controls live in x-settlement-attachment-cell; nothing else to render here. --}}
@endif
