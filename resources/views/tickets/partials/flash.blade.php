{{-- Flash messages for the ticket pages: success opens a confirmation modal with an OK button, problems stay as alerts. --}}
@if (session('error'))
    <div class="ak-alert ak-alert-error" role="alert" data-status-sound="error">{{ is_array(session('error')) ? session('error.message') : session('error') }}</div>
@endif
@if (session('warning'))
    <div class="ak-alert ak-alert-warn" role="status" data-status-sound="warning">{{ session('warning') }}</div>
@endif
@if (session('success'))
    <div class="uf-modal" x-data="{ open: true }" x-show="open" x-cloak @keydown.escape.window="open = false" role="alertdialog" aria-modal="true" aria-labelledby="tk-flash-title" data-flash-modal data-status-sound="success">
        <div class="uf-modal-bg" x-show="open" x-transition.opacity @click="open = false"></div>
        <div class="uf-modal-box" x-show="open" x-transition>
            <div class="uf-modal-body">
                <span class="uf-modal-icon" style="background:#dcfce7; color:#166534" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </span>
                <div>
                    <h3 id="tk-flash-title">Done</h3>
                    <p style="margin:8px 0 0; font-size:14px; color:#334155">{{ session('success') }}</p>
                </div>
            </div>
            <div class="uf-modal-foot">
                <button type="button" class="ak-btn ak-btn-primary" x-init="$nextTick(() => $el.focus())" @click="open = false">OK</button>
            </div>
        </div>
    </div>
@endif
@if (session('success') || session('error') || session('warning'))
    <x-status-sound-script />
@endif
