{{-- Categories tab: the allowances (earnings) and deductions a payslip can carry. New ones can be added any time. --}}
@php
    $calcLabels = \App\Models\SalaryComponent::calculationLabels();
    $groups = [
        \App\Models\SalaryComponent::TYPE_EARNING => ['Earnings', 'Allowances and other pay', 'pr-col-earn'],
        \App\Models\SalaryComponent::TYPE_DEDUCTION => ['Deductions', 'Cuts from the salary', 'pr-col-ded'],
    ];
    $canManage = auth()->user()->can('payroll-salary-manage');
    $editing = old('_category_id');
    $oldCategory = old('name') !== null ? [
        'id' => $editing ? (int) $editing : null, 'name' => old('name'), 'type' => old('type', 'earning'), 'calculation' => old('calculation', 'fixed'),
        'default_value' => old('default_value'), 'account_id' => old('account_id'), 'is_active' => old('_category_id') ? (bool) old('is_active') : true,
    ] : null;
    $openNew = request()->boolean('new');
@endphp

<div>
    <div class="pr-split" style="margin-top:2px">
        @foreach ($groups as $type => [$title, $hint, $tone])
            @php $list = $categories->where('type', $type); @endphp
            <section class="pr-card {{ $tone }}" style="padding:16px" aria-labelledby="pr-cat-{{ $type }}">
                <div class="pr-col-head">
                    <div><h4 id="pr-cat-{{ $type }}" style="margin:0; font-size:17px">{{ $title }}<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: @js($type === \App\Models\SalaryComponent::TYPE_EARNING ? 'fixed-part' : 'deductions') })" aria-label="Help: {{ strtolower($title) }}" title="Help: {{ strtolower($title) }}">i</button></h4><span class="pr-label">{{ $hint }} · {{ $list->where('is_active', true)->count() }} in use @if ($type === \App\Models\SalaryComponent::TYPE_EARNING) · every month or decided each month<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'variable-part' })" aria-label="Help: monthly variable pay" title="Help: monthly variable pay">i</button>@endif</span></div>
                    @if ($canManage)
                        <button type="button" class="pr-btn pr-btn-sm" @click="$dispatch('pr-category', { type: @js($type) })">+ Add</button>
                    @endif
                </div>
                @forelse ($list as $category)
                    @php $used = (int) ($usage[$category->id] ?? 0); @endphp
                    <div style="display:flex; align-items:center; gap:12px; padding:11px 0; border-top:1px solid var(--pr-sep); {{ $category->is_active ? '' : 'opacity:.6' }}">
                        <div style="flex:1; min-width:0">
                            <div style="font-weight:600; color:var(--pr-text)">{{ $category->name }}
                                @unless ($category->is_active)<span class="pr-cap pr-cap-grey" style="margin-left:4px">Off</span>@endunless
                            </div>
                            <div style="font-size:12.5px; color:var(--pr-muted); margin-top:2px">
                                {{ $calcLabels[$category->calculation] ?? $category->calculation }}
                                · {{ $category->isStanding() ? 'every month' : 'decided each month' }}
                                @if ($category->isStanding()) · {{ $used }} {{ \Illuminate\Support\Str::plural('employee', $used) }}@endif
                                @if ($category->account) · {{ $category->account->account_code }} {{ $category->account->account_name }}@endif
                            </div>
                        </div>
                        @if ($canManage)
                            <button type="button" class="pr-btn pr-btn-sm" aria-label="Edit {{ $category->name }}"
                                @click="$dispatch('pr-category', @js(['id' => $category->id, 'name' => $category->name, 'type' => $category->type, 'calculation' => $category->calculation, 'default_value' => (float) $category->default_value, 'account_id' => $category->account_id, 'is_active' => (bool) $category->is_active]))">Edit</button>
                        @endif
                    </div>
                @empty
                    <p class="pr-empty" style="padding:24px 0">None yet.</p>
                @endforelse
            </section>
        @endforeach
    </div>

    <p class="pr-label" style="margin:12px 4px 0">Switched-off categories (such as EOBI) stay on payslips already made but are no longer offered for new salaries.</p>

    @if ($canManage)
        <div x-data="{
                open: false, busy: false,
                form: { id: null, name: '', type: 'earning', calculation: 'fixed', default_value: '', account_id: '', is_active: true },
                show(detail) {
                    const d = detail || {};
                    this.form = { id: d.id ?? null, name: d.name ?? '', type: d.type ?? 'earning', calculation: d.calculation ?? 'fixed',
                        default_value: d.default_value ?? '', account_id: d.account_id ? String(d.account_id) : '', is_active: d.is_active ?? true };
                    this.busy = false; this.open = true;
                    this.$nextTick(() => this.$refs.name.focus());
                },
                get action() { return this.form.id ? @js(url('payroll/components')) + '/' + this.form.id : @js(route('payroll.components.store')); },
            }"
            x-init="@if ($oldCategory) show(@js($oldCategory)) @elseif ($openNew) show(null) @endif"
            @pr-category.window="show($event.detail)" @keydown.escape.window="open = false">
            <div class="pr-sheet-wrap" x-show="open" x-cloak role="dialog" aria-modal="true" aria-labelledby="pr-cat-title">
                <div class="pr-scrim" @click="open = false" x-show="open" x-transition.opacity></div>
                <form method="POST" :action="action" class="pr-sheet" x-show="open" @submit="busy = true"
                    x-transition:enter="pr-enter" x-transition:enter-start="pr-from" x-transition:enter-end="pr-to" x-transition:leave="pr-leave" x-transition:leave-start="pr-to" x-transition:leave-end="pr-from">
                    @csrf
                    <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="_category_id" :value="form.id ?? ''">
                    <div class="pr-sheet-head">
                        <div><h3 id="pr-cat-title" x-text="form.id ? 'Edit category' : 'New category'"></h3><p>An allowance or a deduction that can go on a payslip.</p></div>
                        <button type="button" class="pr-x" @click="open = false" aria-label="Close">✕</button>
                    </div>
                    <div class="pr-sheet-body" style="display:grid; gap:14px">
                        <label class="pr-field"><span>Name</span><input type="text" name="name" x-ref="name" x-model="form.name" required maxlength="120" class="pr-input" placeholder="e.g. Fuel Allowance, Death Insurance"></label>
                        <div class="pr-field"><span>Kind</span>
                            <div class="pr-seg" role="radiogroup" aria-label="Kind">
                                <label><input type="radio" name="type" value="earning" x-model="form.type"><span>Earning</span></label>
                                <label><input type="radio" name="type" value="deduction" x-model="form.type"><span>Deduction</span></label>
                            </div>
                        </div>
                        <label class="pr-field"><span>How it is worked out <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'calc' })" aria-label="Help: how amounts are worked out" title="Help: how amounts are worked out">i</button></span>
                            <select name="calculation" x-model="form.calculation" class="pr-select" required>
                                @foreach ($calcLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="pr-label" x-text="form.calculation === 'input' ? 'Added as an incentive or adjustment, one time or every month (Incentives & adjustments tab).' : 'Set once on the employee and paid every month; each employee can still choose another way.'"></small>
                        </label>
                        <label class="pr-field"><span x-text="['percent_of_basic', 'percent_of_component'].includes(form.calculation) ? 'Default percentage (%)' : 'Default amount (Rs)'"></span>
                            <input type="number" name="default_value" x-model="form.default_value" step="0.01" min="0" class="pr-input" inputmode="decimal"></label>
                        <label class="pr-field"><span>Ledger account (optional)</span>
                            <select name="account_id" x-model="form.account_id" class="pr-select">
                                <option value="" x-text="form.type === 'earning' ? 'Default: Salary 5281' : 'Default: Salary 5281 (reduces the salary cost)'"></option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_code }} · {{ $account->account_name }}</option>
                                @endforeach
                            </select></label>
                        <template x-if="form.id">
                            <label style="display:flex; align-items:center; gap:10px; font-size:14px">
                                <input type="checkbox" class="pr-check" name="is_active" value="1" x-model="form.is_active"> Active (offered for salaries and incentives)
                            </label>
                        </template>
                    </div>
                    <div class="pr-sheet-foot">
                        <button type="button" class="pr-btn" @click="open = false">Cancel</button>
                        <button type="submit" class="pr-btn pr-btn-primary" :disabled="busy || ! form.name.trim()">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
