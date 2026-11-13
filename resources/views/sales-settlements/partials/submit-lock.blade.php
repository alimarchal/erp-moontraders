{{-- One-send lock for the settlement form (create, edit, special edit). The first submit locks every
     [data-submit-lock] button and shows its data-busy-label; any further submit is dropped. When something
     else stops the send (a size warning, a browser alert) the buttons unlock again. While photos are still
     being shrunk the attachments script holds the submit and re-sends it once with data-resubmitting set,
     which this lock lets through. The server refuses a resent form as well (the _once token). --}}
<input type="hidden" name="{{ \App\Support\FormOnce::FIELD }}" value="{{ \App\Support\FormOnce::token() }}">

@once
    @push('scripts')
        <script>
            (() => {
                const setLocked = (form, locked) => {
                    form.querySelectorAll('[data-submit-lock]').forEach((button) => {
                        if (!button.dataset.idleLabel) {
                            button.dataset.idleLabel = button.innerHTML;
                        }
                        button.disabled = locked;
                        button.setAttribute('aria-busy', locked ? 'true' : 'false');
                        button.innerHTML = locked ? (button.dataset.busyLabel || 'Saving…') : button.dataset.idleLabel;
                    });
                };

                const bind = (form) => {
                    if (!form || form.dataset.submitLockBound) {
                        return;
                    }
                    form.dataset.submitLockBound = '1';

                    form.addEventListener('submit', (event) => {
                        if (form.dataset.resubmitting === '1') {
                            delete form.dataset.resubmitting;
                        } else if (form.dataset.sending === '1') {
                            event.preventDefault();
                            event.stopImmediatePropagation();

                            return;
                        }

                        form.dataset.sending = '1';
                        setLocked(form, true);

                        // Decide after every other submit listener has run: a send that was stopped, and is not
                        // waiting for photos, unlocks the form again.
                        setTimeout(() => {
                            const waitingForPhotos = (window.SettlementAttachments?.pending?.size || 0) > 0;
                            if (event.defaultPrevented && !waitingForPhotos && form.dataset.resubmitting !== '1') {
                                delete form.dataset.sending;
                                setLocked(form, false);
                            }
                        }, 0);
                    }, true);

                    // Coming back with the browser's Back button shows the page from cache: unlock it.
                    window.addEventListener('pageshow', (event) => {
                        if (event.persisted) {
                            delete form.dataset.sending;
                            setLocked(form, false);
                        }
                    });
                };

                document.addEventListener('DOMContentLoaded', () => bind(document.getElementById('settlementForm')));
            })();
        </script>
    @endpush
@endonce

@once
    @push('scripts')
        <script>
            // Mirror a figure from the summary (e.g. Short / Excess) into the sticky action bar, so it stays in view.
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-mirror]').forEach((target) => {
                    const source = document.getElementById(target.dataset.mirror);
                    if (!source) {
                        return;
                    }
                    const copy = () => {
                        target.textContent = source.textContent.trim() || '—';
                        target.style.color = getComputedStyle(source).color;
                    };
                    copy();
                    new MutationObserver(copy).observe(source, { childList: true, characterData: true, subtree: true, attributes: true });
                });
            });
        </script>
    @endpush
@endonce
