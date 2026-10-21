@php
/** @var \App\Models\Category|null $category */
$category = $category ?? null;
$cancelUrl = $cancelUrl ?? route('categories.index');
$submitLabel = $submitLabel ?? 'Save';
$val = fn (string $field, $default = null) => old($field, optional($category)->{$field} ?? $default);
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
                <h2 class="uf-card-title"><span class="uf-step">1</span> Category details</h2>
                <p class="uf-card-sub">Product groups used for reporting and filtering.</p>
            </div>
        </div>
        <div class="uf-body">
            <div class="uf-fields">
                <div class="uf-field">
                    <label for="name">Category name <span class="uf-req">*</span></label>
                    <input id="name" type="text" name="name" maxlength="191" required value="{{ $val('name') }}" placeholder="Cooking Oil">
                    @error('name') <p class="uf-error">{{ $message }}</p> @enderror
                </div>
                <div class="uf-field">
                    <label for="slug">Slug (optional)</label>
                    <input id="slug" type="text" name="slug" maxlength="191" value="{{ $val('slug') }}" placeholder="cooking-oil">
                    @error('slug') <p class="uf-error">{{ $message }}</p> @enderror
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
                    <input type="hidden" name="is_active" value="0">
                    <label class="ef-switch" for="is_active">
                        <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', optional($category)->is_active ?? true))>
                        <div><b>Category is active</b><span>Inactive categories stay on record but are hidden from new products.</span></div>
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
