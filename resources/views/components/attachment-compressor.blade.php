{{-- Shrinks a photo in the user's own browser before it is sent, so the upload is small and quick and the server does
     no picture work at all. PDFs and spreadsheets pass through. Include once per page that has a file input. --}}
@once
    @push('scripts')
        <script>
            window.AttachmentCompressor = {
                maxSide: {{ (int) config('attachment_images.max_px') }},
                quality: {{ max(40, min(95, (int) config('attachment_images.quality'))) / 100 }},
                minBytes: {{ (int) config('attachment_images.min_kb') * 1024 }},
                enabled: {{ config('attachment_images.enabled') ? 'true' : 'false' }},
                // What the server accepts in one save (PHP's own limits), used to warn before sending.
                limits: {
                    postMax: {{ \App\Support\PhpLimits::postMaxBytes() }},
                    files: {{ \App\Support\PhpLimits::maxFileUploads() }},
                    vars: {{ \App\Support\PhpLimits::maxInputVars() }},
                },

                /**
                 * Returns the smaller picture, or the original when it is not a photo, is already small, or cannot be shrunk.
                 * With { webp: true } (only where the server accepts .webp) the picture is saved as WebP, about a third
                 * smaller than JPEG at the same quality; a browser that cannot write WebP falls back to the normal path.
                 */
                async compress(file, { webp = false } = {}) {
                    try {
                        if (!this.enabled || !/^image\/(jpeg|png|webp)$/.test(file.type) || file.size <= this.minBytes || !window.createImageBitmap) {
                            return file;
                        }

                        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                        const scale = Math.min(1, this.maxSide / Math.max(bitmap.width, bitmap.height));
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                        bitmap.close && bitmap.close();

                        if (webp) {
                            const flat = document.createElement('canvas');
                            flat.width = canvas.width;
                            flat.height = canvas.height;
                            const flatContext = flat.getContext('2d');
                            flatContext.fillStyle = '#fff';
                            flatContext.fillRect(0, 0, flat.width, flat.height);
                            flatContext.drawImage(canvas, 0, 0);
                            const small = await new Promise(resolve => flat.toBlob(resolve, 'image/webp', this.quality));

                            if (small && small.type === 'image/webp' && small.size < file.size * 0.9) {
                                return new File([small], file.name.replace(/\.[^.]+$/, '') + '.webp', { type: 'image/webp', lastModified: Date.now() });
                            }
                        }

                        // A PNG (a screenshot, a scanned page) is usually 2 to 4 times smaller as a JPEG; see-through parts become white.
                        if (file.type === 'image/png') {
                            const flat = document.createElement('canvas');
                            flat.width = canvas.width;
                            flat.height = canvas.height;
                            const flatContext = flat.getContext('2d');
                            flatContext.fillStyle = '#fff';
                            flatContext.fillRect(0, 0, flat.width, flat.height);
                            flatContext.drawImage(canvas, 0, 0);
                            const jpeg = await new Promise(resolve => flat.toBlob(resolve, 'image/jpeg', this.quality));

                            if (jpeg && jpeg.size < file.size * 0.7) {
                                return new File([jpeg], file.name.replace(/\.png$/i, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                            }
                        }

                        const type = file.type;
                        const blob = await new Promise(resolve => canvas.toBlob(resolve, type, this.quality));

                        if (!blob || blob.size >= file.size * 0.9) {
                            return file;
                        }

                        return new File([blob], file.name, { type: blob.type, lastModified: Date.now() });
                    } catch (e) {
                        return file;
                    }
                },

                /** Shrinks the photos chosen in a file input marked data-compress, in place; the form waits for it on submit. */
                async shrinkInput(input) {
                    const files = Array.from(input.files || []);
                    if (!files.length) return;

                    const smaller = await Promise.all(files.map(file => this.compress(file)));
                    if (smaller.every((file, i) => file === files[i])) return;

                    const transfer = new DataTransfer();
                    smaller.forEach(file => transfer.items.add(file));
                    input.files = transfer.files;
                },

                /** Plain-words reason this save cannot work on this server, or null when it is fine. */
                tooBig(files, fieldCount) {
                    const bytes = files.reduce((sum, f) => sum + f.size, 0);
                    const mb = n => (n / 1048576).toFixed(1) + ' MB';

                    if (this.limits.postMax > 0 && bytes > this.limits.postMax * 0.8) {
                        return 'The attached files add up to ' + mb(bytes) + ', but this server accepts only about ' + mb(this.limits.postMax * 0.8) + ' in one save. Remove or replace some files, or ask your administrator to raise post_max_size.';
                    }
                    if (files.length > this.limits.files) {
                        return 'You attached ' + files.length + ' files, but this server accepts only ' + this.limits.files + ' in one save (max_file_uploads). Ask your administrator to raise it.';
                    }
                    if (fieldCount + files.length > this.limits.vars * 0.95) {
                        return 'This form has ' + (fieldCount + files.length) + ' fields, close to the ' + this.limits.vars + ' the server accepts in one save (max_input_vars). Part of it would be lost. Ask your administrator to raise max_input_vars to 5000.';
                    }
                    return null;
                },
            };

            // Works for every file input marked data-compress, including ones loaded into a pop-up later.
            document.addEventListener('change', (event) => {
                const input = event.target;
                if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.hasAttribute('data-compress') || !input.form) return;

                const job = window.AttachmentCompressor.shrinkInput(input);
                const form = input.form;
                form.__compressing = Promise.all([form.__compressing, job]).finally(() => { if (form.__compressing === chain) form.__compressing = null; });
                const chain = form.__compressing;
            });

            document.addEventListener('submit', async (event) => {
                const form = event.target;
                if (!form.__compressing) return;

                event.preventDefault();
                event.stopImmediatePropagation();
                await form.__compressing;
                form.requestSubmit(event.submitter || undefined);
            }, true);
        </script>
    @endpush
@endonce
