@props([
    'name' => 'denom',
    'model' => 'form.denom',
    'required' => '0',
    'disabled' => 'false',
    'notes' => \App\Support\CashNotes::NOTES,
])

@php($prefix = preg_replace('/[^A-Za-z0-9_-]/', '', $name))

<div
    x-data="{
        notes: @js($notes),
        get counted() {
            let total = Number({{ $model }}.coins) || 0;
            this.notes.forEach(n => { total += n * (Math.max(0, parseInt({{ $model }}[n], 10)) || 0); });
            return total;
        },
        get needed() { return Number({{ $required }}) || 0; },
        get difference() { return Math.round((this.counted - this.needed) * 100) / 100; },
        fmt(v) { return Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }); },
        wipe() { this.notes.forEach(n => { {{ $model }}[n] = ''; }); {{ $model }}.coins = ''; },
    }"
    {{ $attributes->class('cash-notes text-sm') }}
>
    <div class="grid grid-cols-1 sm:grid-cols-2 sm:gap-x-6">
        @foreach($notes as $note)
            <label for="{{ $prefix }}-{{ $note }}" class="flex items-center gap-2 py-1 border-b border-black/5">
                <span class="w-14 shrink-0 font-medium" style="color: var(--apl-label, #1d1d1f)">{{ number_format($note) }}</span>
                <span style="color: var(--apl-label-2, #6e6e73)">×</span>
                <input id="{{ $prefix }}-{{ $note }}" type="number" min="0" step="1" inputmode="numeric" placeholder="0"
                    name="{{ $name }}[{{ $note }}]" x-model.number="{{ $model }}[{{ $note }}]"
                    x-bind:disabled="{{ $disabled }}"
                    class="w-20 h-8 px-2 text-right rounded-md border border-black/10">
                <span class="ml-auto tabular-nums" style="color: var(--apl-label-2, #6e6e73)"
                    x-text="fmt((parseInt({{ $model }}[{{ $note }}], 10) > 0 ? parseInt({{ $model }}[{{ $note }}], 10) : 0) * {{ $note }})"></span>
            </label>
        @endforeach
        <label for="{{ $prefix }}-coins" class="flex items-center gap-2 py-1 border-b border-black/5">
            <span class="w-14 shrink-0 font-medium" style="color: var(--apl-label, #1d1d1f)">Coins</span>
            <span style="color: var(--apl-label-2, #6e6e73)">Rs</span>
            <input id="{{ $prefix }}-coins" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0"
                name="{{ $name }}[coins]" x-model.number="{{ $model }}.coins"
                x-bind:disabled="{{ $disabled }}"
                class="w-20 h-8 px-2 text-right rounded-md border border-black/10">
            <span class="ml-auto tabular-nums" style="color: var(--apl-label-2, #6e6e73)"
                x-text="fmt(Number({{ $model }}.coins) > 0 ? Number({{ $model }}.coins) : 0)"></span>
        </label>
    </div>

    <div class="sticky bottom-0 mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-3 py-2 rounded-lg bg-white/90 backdrop-blur border border-black/10" aria-live="polite">
        <span style="color: var(--apl-label-2, #6e6e73)">
            Counted <b style="color: var(--apl-label, #1d1d1f)">Rs <span x-text="fmt(counted)"></span></b>
            · Required <b style="color: var(--apl-label, #1d1d1f)">Rs <span x-text="fmt(needed)"></span></b>
            ·
            <b x-bind:style="difference === 0 ? 'color: var(--apl-green, #1a8f3c)' : 'color: var(--apl-red, #d92d20)'">
                Difference Rs <span x-text="fmt(difference)"></span>
            </b>
        </span>
        <button type="button" class="text-xs underline" style="color: var(--apl-label-2, #6e6e73)" x-on:click="wipe()" x-bind:disabled="{{ $disabled }}">Clear</button>
    </div>
</div>
