{{-- Registers the x-select2 Alpine directive once per page: <select x-model="form.bank_id" x-select2="form.bank_id">.
     It turns an Alpine-bound <select> into a Select2 box (styled like the other filters), tells Alpine when Select2
     changes the value (a native "change" event, so x-model and @change keep working), and keeps the Select2 box in step
     when Alpine changes the value (a form reset, an edit). Options rendered by x-for are picked up each time it opens. --}}
@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                window.Alpine.directive('select2', (el, { expression }, { evaluateLater, effect, cleanup }) => {
                    const $ = window.jQuery;
                    if (!$ || !$.fn || !$.fn.select2) {
                        return;
                    }

                    const placeholder = el.querySelector('option[value=""]')?.textContent.trim() || 'Select…';
                    const start = () => {
                        if ($(el).data('select2')) {
                            return;
                        }
                        $(el).select2({
                            width: '100%',
                            placeholder,
                            // Inside a modal the list opens in the modal (so it stays above it); elsewhere in the page body, so a card's
                            // rounded, clipped edge cannot cut it off.
                            dropdownParent: $(el.closest('.fixed.inset-0') || document.body),
                            // A short list (cash / bank) needs no search box
                            minimumResultsForSearch: el.options.length > 8 ? 0 : Infinity,
                        });
                        $(el).on('select2:select select2:clear', () => el.dispatchEvent(new Event('change', { bubbles: true })));
                    };
                    queueMicrotask(start);

                    if (expression) {
                        const read = evaluateLater(expression);
                        effect(() => read((value) => {
                            const wanted = value === null || value === undefined ? '' : String(value);
                            queueMicrotask(() => {
                                if ($(el).data('select2') && String($(el).val() ?? '') !== wanted) {
                                    $(el).val(wanted).trigger('change.select2');
                                }
                            });
                        }));
                    }

                    cleanup(() => {
                        if ($(el).data('select2')) {
                            $(el).select2('destroy');
                        }
                    });
                });
            });
        </script>
    @endpush
@endonce
