{{-- A confirm window for one action on a bank entry (reverse, delete, reject). Same look as the Sales Settlement
     windows (recoveries, cheques): blurred page behind, coloured title bar, body, grey footer with Cancel and the action.
     The slot is the body and sits inside the form, so any input in it is sent with the action. --}}
@props([
    'title',
    'sub' => null,
    'action',
    'method' => 'POST',
    'confirm',
    'trigger',
    'triggerClass' => 'ak-btn ak-btn-danger-outline',
    'tone' => 'red',
])

<div x-data="{ open: false, busy: false }" @keydown.escape.window="open = false">
    <button type="button" class="{{ $triggerClass }}" @click="open = true; busy = false">{{ $trigger }}</button>

    <template x-teleport="body">
        <div class="uf-modal bank-noprint bk-action-modal" x-show="open" x-cloak style="display:none" role="dialog" aria-modal="true" aria-label="{{ $title }}">
            <div class="uf-modal-bg" @click="open = false"></div>
            <form method="POST" action="{{ $action }}" class="uf-modal-box bk-modal-box bk-action-box"
                @submit="if (busy) { $event.preventDefault(); return; } busy = true">
                @csrf
                @if (strtoupper($method) !== 'POST') @method($method) @endif

                <div class="bk-modal-head bk-head-{{ $tone }}">
                    <div>
                        <h3>{{ $title }}</h3>
                        @if ($sub)<p>{{ $sub }}</p>@endif
                    </div>
                    <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" @click="open = false">Close</button>
                </div>

                <div class="bk-modal-body bk-stack">{{ $slot }}</div>

                <div class="bk-modal-foot">
                    <button type="button" class="ak-btn ak-btn-outline" @click="open = false">Cancel</button>
                    <button type="submit" class="ak-btn bk-btn-{{ $tone }}" :class="busy && 'is-busy'">{{ $confirm }}</button>
                </div>
            </form>
        </div>
    </template>
</div>
