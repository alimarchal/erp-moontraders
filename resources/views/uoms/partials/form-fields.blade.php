@php
/** @var \App\Models\Uom|null $uom */
$uom = $uom ?? null;
$cancelUrl = $cancelUrl ?? route('uoms.index');
$submitLabel = $submitLabel ?? 'Save';
$val = fn (string $field, $default = null) => old($field, optional($uom)->{$field} ?? $default);
@endphp

<style>
    .ef-stack { display: flex; flex-direction: column; gap: 20px; }
    .ef-wide { grid-column: 1 / -1; }
    .uf-field textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--ak-border-strong); border-radius: 8px; background: #fff; color: var(--ak-text); font-size: 14px; }
    .uf-field textarea:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
    .ef-switch { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--ak-border); border-radius: 10px; background: var(--ak-soft); cursor: pointer; }
    .ef-switch input { width: 18px; height: 18px; accent-color: var(--ak-navy); }
    .ef-switch b { display: block; font-size: 14px; color: var(--ak-text); }
    .ef-switch span { font-size: 12.5px; color: var(--ak-muted); }
</style>

@if ($errors->any())
    <div class="ak-alert ak-alert-error" role="alert">
        <b>Please fix {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('problem', $errors->count()) }}:</b>
        {{ implode(' · ', array_slice($errors->all(), 0, 4)) }}
    </div>
@endif

<div class="ef-stack">
    <section class="uf-card">
        <div class="uf-card-head">
            <div>
                <h2 class="uf-card-title"><span class="uf-step">1</span> Unit details</h2>
                <p class="uf-card-sub">Units used to count stock and to sell products.</p>
            </div>
        </div>
        <div class="uf-body">
            <div class="uf-fields">
                <div class="uf-field">
                    <label for="uom_name">Unit name <span class="uf-req">*</span></label>
                    <input id="uom_name" type="text" name="uom_name" maxlength="191" required value="{{ $val('uom_name') }}" placeholder="Piece">
                    @error('uom_name') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="symbol">Symbol</label>
                    <input id="symbol" type="text" name="symbol" maxlength="191" value="{{ $val('symbol') }}" placeholder="PCS">
                    @error('symbol') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field ef-wide">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Where this unit is used">{{ $val('description') }}</textarea>
                    @error('description') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </section>

    <section class="uf-card">
        <div class="uf-card-head">
            <div><h2 class="uf-card-title"><span class="uf-step">2</span> Options</h2></div>
        </div>
        <div class="uf-body">
            <div class="uf-fields">
                <div>
                    <input type="hidden" name="must_be_whole_number" value="0">
                    <label class="ef-switch" for="must_be_whole_number">
                        <input id="must_be_whole_number" type="checkbox" name="must_be_whole_number" value="1" @checked(old('must_be_whole_number', optional($uom)->must_be_whole_number ?? false))>
                        <div><b>Whole numbers only</b><span>Quantities in this unit cannot have decimals (pieces, cases …).</span></div>
                    </label>
                </div>
                <div>
                    <input type="hidden" name="enabled" value="0">
                    <label class="ef-switch" for="enabled">
                        <input id="enabled" type="checkbox" name="enabled" value="1" @checked(old('enabled', optional($uom)->enabled ?? true))>
                        <div><b>Unit is enabled</b><span>Disabled units stay on record but are hidden from new products.</span></div>
                    </label>
                </div>
            </div>
        </div>
    </section>

    <div class="uf-actions">
        <p>Fields marked <span class="uf-req">*</span> are required.</p>
        <div>
            <a href="{{ $cancelUrl }}" class="ak-btn ak-btn-outline">Cancel</a>
            <button type="submit" class="ak-btn ak-btn-primary">{{ $submitLabel }}</button>
        </div>
    </div>
</div>
