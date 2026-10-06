{{-- Plays a short tone for the first [data-status-sound] element on the page (success / error / warning / info). --}}
@once
@push('scripts')
<script>
    (function () {
        const findTargets = () => document.querySelectorAll('[data-status-sound]');

        const playBeep = (tone = 'success') => {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) {
                return;
            }

            try {
                const ctx = new AudioCtx();
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();

                const settings = {
                    success: { type: 'sine', frequency: 880 },
                    error: { type: 'sawtooth', frequency: 360 },
                    warning: { type: 'triangle', frequency: 660 },
                    info: { type: 'square', frequency: 520 },
                }[tone] || { type: 'sine', frequency: 880 };

                oscillator.type = settings.type;
                oscillator.frequency.value = settings.frequency;
                oscillator.connect(gain);
                gain.connect(ctx.destination);

                const now = ctx.currentTime;
                gain.gain.setValueAtTime(0, now);
                gain.gain.linearRampToValueAtTime(0.18, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);

                oscillator.start(now);
                oscillator.stop(now + 0.4);
            } catch (error) {
                console.warn('Unable to play status sound', error);
            }
        };

        const init = () => {
            const targets = findTargets();
            if (!targets.length) {
                return;
            }

            try {
                const tone = targets[0].dataset.statusSound;
                playBeep(tone);
            } catch (err) {
                console.warn('Status sound failed', err);
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init, { once: true });
        } else {
            init();
        }
    })();
</script>
@endpush
@endonce
