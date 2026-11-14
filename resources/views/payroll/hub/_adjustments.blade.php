{{-- Incentives & adjustments tab: extra pay or cuts for chosen employees (commission, incentive, Eid bonus, fine), for one month
     only or every month until stopped. Payroll pulls each one into the month's payslip by itself, with its narration. --}}
@php
    $type = $filters['type'] ?? '';
    $status = $filters['status'] ?? '';
    $repeat = $filters['repeat'] ?? '';
    $base = array_merge(\Illuminate\Support\Arr::except($filters, ['type', 'status', 'repeat']), ['month' => $month]);
    $pillUrl = fn (array $extra) => route('payroll.index', ['tab' => 'adjustments', 'filter' => array_filter(array_merge($base, ['type' => $type ?: null, 'status' => $status ?: null, 'repeat' => $repeat ?: null], $extra))]);
    $monthLabel = \Carbon\Carbon::parse($month.'-01')->format('F Y');
    $earnTotal = (float) ($monthTotals['earning']->total ?? 0);
    $dedTotal = (float) ($monthTotals['deduction']->total ?? 0);
    $items = (int) ($monthTotals['earning']->items ?? 0) + (int) ($monthTotals['deduction']->items ?? 0);
    $chipLabels = ['search' => 'Employee', 'component_id' => 'Category'];
    $chips = \Illuminate\Support\Arr::only($filters, array_keys($chipLabels));
    $chipValue = fn (string $key, $value) => $key === 'component_id' ? ($oneOffComponents->firstWhere('id', (int) $value)?->name ?? $value) : $value;
    $openAdd = old('salary_component_id') !== null;
    $oldType = old('salary_component_id') ? ($oneOffComponents->firstWhere('id', (int) old('salary_component_id'))?->type ?? 'earning') : 'earning';
    $pick = $pickEmployees->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'code' => (string) $e->employee_code, 'designation' => (string) $e->designation])->values();
@endphp

<div x-data="{ confirm: { open: false, url: '', label: '' }, stop: { open: false, url: '', label: '', from: '' } }" @keydown.escape.window="confirm.open = false; stop.open = false">

    <section id="pr-filter-panel" class="pr-card pr-filters no-print" x-data="{ open: {{ count($chips) ? 'true' : 'false' }} }" @pr-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition aria-label="Filters">
        <form method="GET" action="{{ route('payroll.index') }}">
            <input type="hidden" name="tab" value="adjustments">
            @if ($type)<input type="hidden" name="filter[type]" value="{{ $type }}">@endif
            @if ($status)<input type="hidden" name="filter[status]" value="{{ $status }}">@endif
            @if ($repeat)<input type="hidden" name="filter[repeat]" value="{{ $repeat }}">@endif
            <div class="pr-filter-row">
                <label class="pr-field"><span>Month</span><input type="month" name="filter[month]" value="{{ $month }}" class="pr-input"></label>
                <label class="pr-field"><span>Employee</span><input type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" class="pr-input" placeholder="Name or code" autocomplete="off"></label>
                <label class="pr-field"><span>Category</span>
                    <select name="filter[component_id]" class="pr-select">
                        <option value="">Any</option>
                        @foreach ($oneOffComponents as $component)
                            <option value="{{ $component->id }}" @selected((string) ($filters['component_id'] ?? '') === (string) $component->id)>{{ $component->name }}</option>
                        @endforeach
                    </select></label>
                <div style="display:flex; gap:8px">
                    <button type="submit" class="pr-btn pr-btn-primary">Apply</button>
                    <a href="{{ route('payroll.index', ['tab' => 'adjustments']) }}" class="pr-btn">Clear</a>
                </div>
            </div>
        </form>
    </section>

    <div class="pr-stats">
        <div class="pr-card pr-stat"><small>Earnings · {{ $monthLabel }}</small><b class="is-earn">{{ number_format($earnTotal) }}</b></div>
        <div class="pr-card pr-stat"><small>Deductions · {{ $monthLabel }}</small><b class="is-ded">{{ number_format($dedTotal) }}</b></div>
        <div class="pr-card pr-stat"><small>Items <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'one-off' })" aria-label="Help: incentives and adjustments" title="Help: incentives and adjustments">i</button></small><b>{{ number_format($items) }}</b></div>
    </div>

    <section class="pr-card" aria-label="Incentives and adjustments" style="margin-top:14px">
        <nav class="pr-subtabs" aria-label="Kind and status">
            <a href="{{ $pillUrl(['type' => null]) }}" class="pr-subtab {{ $type === '' ? 'is-on' : '' }}" @if ($type === '') aria-current="true" @endif>All</a>
            <a href="{{ $pillUrl(['type' => 'earning']) }}" class="pr-subtab {{ $type === 'earning' ? 'is-on' : '' }}" @if ($type === 'earning') aria-current="true" @endif>Earnings</a>
            <a href="{{ $pillUrl(['type' => 'deduction']) }}" class="pr-subtab {{ $type === 'deduction' ? 'is-on' : '' }}" @if ($type === 'deduction') aria-current="true" @endif>Deductions</a>
            <span aria-hidden="true" style="width:1px; background:var(--pr-sep); margin:0 4px"></span>
            <a href="{{ $pillUrl(['status' => $status === 'pending' ? null : 'pending']) }}" class="pr-subtab {{ $status === 'pending' ? 'is-on' : '' }}" @if ($status === 'pending') aria-current="true" @endif>Waiting for payroll</a>
            <a href="{{ $pillUrl(['status' => $status === 'applied' ? null : 'applied']) }}" class="pr-subtab {{ $status === 'applied' ? 'is-on' : '' }}" @if ($status === 'applied') aria-current="true" @endif>Applied</a>
            <span aria-hidden="true" style="width:1px; background:var(--pr-sep); margin:0 4px"></span>
            <a href="{{ $pillUrl(['repeat' => $repeat === 'once' ? null : 'once']) }}" class="pr-subtab {{ $repeat === 'once' ? 'is-on' : '' }}" @if ($repeat === 'once') aria-current="true" @endif>One time</a>
            <a href="{{ $pillUrl(['repeat' => $repeat === 'monthly' ? null : 'monthly']) }}" class="pr-subtab {{ $repeat === 'monthly' ? 'is-on' : '' }}" @if ($repeat === 'monthly') aria-current="true" @endif>Every month</a>
        </nav>

        @if ($chips)
            <div class="pr-chips" aria-label="Active filters" style="margin-top:10px">
                <span class="pr-label">Filtered by</span>
                @foreach ($chips as $key => $value)
                    <a href="{{ route('payroll.index', ['tab' => 'adjustments', 'filter' => array_filter(array_merge(\Illuminate\Support\Arr::except($filters, $key), ['month' => $month]))]) }}" class="pr-chip"><b>{{ $chipLabels[$key] }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span></a>
                @endforeach
            </div>
        @endif

        <div class="pr-toolbar" style="margin-top:10px">
            <p><b>{{ $monthLabel }}</b> · {{ number_format($adjustments->total()) }} {{ \Illuminate\Support\Str::plural('item', $adjustments->total()) }}</p>
            <span class="pr-spacer"></span>
            @can('payroll-import-excel')
                <button type="button" class="pr-btn pr-btn-sm no-print" @click="$dispatch('pr-import', 'adjustments')">Import Excel</button>
            @endcan
            <button type="button" class="pr-btn pr-btn-sm pr-btn-primary no-print" @click="$dispatch('pr-adjust')">+ Add</button>
        </div>

        @if ($adjustments->isEmpty())
            <div class="pr-empty">
                <b>Nothing for {{ $monthLabel }}</b>
                Add a commission, incentive, Eid bonus or fine for chosen employees, for this month only or every month; it goes on their payslip by itself.
            </div>
        @else
            <div class="pr-scroll" tabindex="0" aria-label="Incentives and adjustments table">
                <table class="pr-table pr-cards">
                    <caption class="sr-only">Incentives and adjustments for {{ $monthLabel }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col">Category<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'categories' })" aria-label="Help: categories" title="Help: categories">i</button></th>
                            <th scope="col" class="pr-num">Amount</th>
                            <th scope="col">Narration</th>
                            <th scope="col">Repeats<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'recurring' })" aria-label="Help: one time or every month" title="Help: one time or every month">i</button></th>
                            <th scope="col">Status<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'one-off' })" aria-label="Help: when it reaches the payslip" title="Help: when it reaches the payslip">i</button></th>
                            <th scope="col">Added by</th>
                            <th scope="col" class="no-print"><span class="sr-only">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($adjustments as $adjustment)
                            @php
                                $isEarning = $adjustment->component?->type === 'earning';
                                $paidRun = $adjustment->is_recurring ? $adjustment->slipLines->first()?->slip?->run : $adjustment->appliedRun;
                                $isApplied = $adjustment->is_recurring ? $paidRun !== null : ! $adjustment->isPending();
                            @endphp
                            <tr>
                                <td data-label="Employee">
                                    <span style="font-weight:600">{{ $adjustment->employee?->name ?? '—' }}</span>
                                    <div style="font-size:12.5px; color:var(--pr-muted)">{{ $adjustment->employee?->employee_code }}{{ $adjustment->employee?->designation ? ' · '.$adjustment->employee->designation : '' }}</div>
                                </td>
                                <td data-label="Category">
                                    {{ $adjustment->component?->name ?? '—' }}
                                    <span class="pr-cap {{ $isEarning ? 'pr-cap-green' : 'pr-cap-red' }}" style="margin-left:4px">{{ $isEarning ? 'Earning' : 'Deduction' }}</span>
                                </td>
                                <td data-label="Amount" class="pr-num {{ $isEarning ? 'pr-earn' : 'pr-ded' }}" style="font-weight:600">{{ $isEarning ? '' : '−' }}{{ number_format((float) $adjustment->amount, 2) }}</td>
                                <td data-label="Narration" style="max-width:260px">{{ $adjustment->note ?: '—' }}</td>
                                <td data-label="Repeats">
                                    <span class="pr-cap {{ $adjustment->is_recurring ? 'pr-cap-blue' : '' }}">{{ $adjustment->is_recurring ? 'Every month' : 'One time' }}</span>
                                    <span style="display:block; font-size:12px; margin-top:3px; color:var(--pr-muted)">{{ $adjustment->scheduleLabel() }}</span>
                                </td>
                                <td data-label="Status">
                                    @if (! $isApplied)
                                        <span class="pr-cap pr-cap-amber">Waiting for payroll</span>
                                    @else
                                        <span class="pr-cap pr-cap-green">Applied</span>
                                        @if ($paidRun)
                                            @can('payroll-list')
                                                <a href="{{ route('payroll.show', $paidRun->uuid) }}" style="display:block; font-size:12px; margin-top:3px; color:var(--pr-blue)">{{ $paidRun->run_number }}</a>
                                            @else
                                                <span style="display:block; font-size:12px; margin-top:3px; color:var(--pr-muted)">{{ $paidRun->run_number }}</span>
                                            @endcan
                                        @endif
                                    @endif
                                </td>
                                <td data-label="Added by" style="color:var(--pr-muted)">{{ $adjustment->creator?->name ?? '—' }}</td>
                                <td data-label="" class="no-print" style="text-align:right">
                                    @if ($adjustment->is_recurring)
                                        <button type="button" class="pr-btn pr-btn-sm" aria-label="Stop {{ $adjustment->component?->name }} of {{ $adjustment->employee?->name }}"
                                            @click="stop = { open: true, url: @js(route('payroll.adjustments.stop', $adjustment)), label: @js(($adjustment->component?->name ?? '').' of '.($adjustment->employee?->name ?? '')), from: @js($isApplied ? $nextMonth : $month) }">Stop</button>
                                    @endif
                                    @if ($adjustment->is_recurring ? ! $isApplied && $adjustment->period_month->format('Y-m') === $month : $adjustment->isPending())
                                        <button type="button" class="pr-btn pr-btn-sm pr-btn-danger" aria-label="Remove {{ $adjustment->component?->name }} of {{ $adjustment->employee?->name }}"
                                            @click="confirm = { open: true, url: @js(route('payroll.adjustments.destroy', $adjustment)), label: @js(($adjustment->component?->name ?? '').' of '.($adjustment->employee?->name ?? '')) }">Remove</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($adjustments->hasPages())
                <div style="padding:12px 16px">{{ $adjustments->links() }}</div>
            @endif
        @endif
    </section>

    {{-- Remove confirmation --}}
    <div class="pr-sheet-wrap" x-show="confirm.open" x-cloak role="dialog" aria-modal="true" aria-labelledby="pr-adj-del-title">
        <div class="pr-scrim" @click="confirm.open = false" x-show="confirm.open" x-transition.opacity></div>
        <div class="pr-sheet" style="max-width:420px" x-show="confirm.open"
            x-transition:enter="pr-enter" x-transition:enter-start="pr-from" x-transition:enter-end="pr-to" x-transition:leave="pr-leave" x-transition:leave-start="pr-to" x-transition:leave-end="pr-from">
            <div class="pr-sheet-head"><div><h3 id="pr-adj-del-title">Remove <span x-text="confirm.label"></span>?</h3><p>It comes off the payslip too while that payroll is still a draft.</p></div></div>
            <form method="POST" :action="confirm.url" class="pr-sheet-foot" x-data="{ busy: false }" @submit="busy = true">
                @csrf @method('DELETE')
                <button type="button" class="pr-btn" @click="confirm.open = false">Cancel</button>
                <button type="submit" class="pr-btn pr-btn-danger" :disabled="busy">Remove</button>
            </form>
        </div>
    </div>

    {{-- Stop a recurring item --}}
    <div class="pr-sheet-wrap" x-show="stop.open" x-cloak role="dialog" aria-modal="true" aria-labelledby="pr-adj-stop-title">
        <div class="pr-scrim" @click="stop.open = false" x-show="stop.open" x-transition.opacity></div>
        <div class="pr-sheet" style="max-width:440px" x-show="stop.open"
            x-transition:enter="pr-enter" x-transition:enter-start="pr-from" x-transition:enter-end="pr-to" x-transition:leave="pr-leave" x-transition:leave-start="pr-to" x-transition:leave-end="pr-from">
            <div class="pr-sheet-head"><div><h3 id="pr-adj-stop-title">Stop <span x-text="stop.label"></span>?</h3><p>Months already paid keep it. From the month you choose it is no longer added, and draft payslips drop it at once.</p></div></div>
            <form method="POST" :action="stop.url" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <div class="pr-sheet-body"><label class="pr-field"><span>Stop from (first month without it)</span><input type="month" name="from_month" required class="pr-input" x-model="stop.from"></label></div>
                <div class="pr-sheet-foot">
                    <button type="button" class="pr-btn" @click="stop.open = false">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn-danger" :disabled="busy">Stop</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Add incentive / adjustment --}}
    <div x-data="{
            open: @js($openAdd), busy: false, kind: @js($oldType), term: '', repeat: @js((string) old('repeat', 'once')),
            components: @js($oneOffComponents->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type])->values()),
            people: @js($pick),
            picked: @js(array_map('intval', (array) old('employee_ids', []))),
            componentId: @js((string) old('salary_component_id', '')),
            get options() { return this.components.filter(c => c.type === this.kind); },
            get shown() { const t = this.term.trim().toLowerCase(); return t === '' ? this.people : this.people.filter(p => (p.name + ' ' + p.code + ' ' + p.designation).toLowerCase().includes(t)); },
            selectShown() { const ids = new Set(this.picked); this.shown.forEach(p => ids.add(p.id)); this.picked = [...ids]; },
            setKind(k) { this.kind = k; if (! this.options.some(c => String(c.id) === this.componentId)) { this.componentId = this.options[0] ? String(this.options[0].id) : ''; } },
            show() { this.open = true; this.setKind(this.kind); this.$nextTick(() => this.$refs.month.focus()); },
        }"
        x-init="if (open) show()" @pr-adjust.window="show()" @keydown.escape.window="open = false">
        <div class="pr-sheet-wrap" x-show="open" x-cloak role="dialog" aria-modal="true" aria-labelledby="pr-adj-title">
            <div class="pr-scrim" @click="open = false" x-show="open" x-transition.opacity></div>
            <form method="POST" action="{{ route('payroll.adjustments.store') }}" class="pr-sheet pr-sheet-lg" x-show="open" @submit="busy = true"
                x-transition:enter="pr-enter" x-transition:enter-start="pr-from" x-transition:enter-end="pr-to" x-transition:leave="pr-leave" x-transition:leave-start="pr-to" x-transition:leave-end="pr-from">
                @csrf
                <div class="pr-sheet-head">
                    <div><h3 id="pr-adj-title">Add incentive or adjustment <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'one-off' })" aria-label="Help: incentives and adjustments" title="Help: incentives and adjustments">i</button></h3><p>It goes on the payslip by itself with its narration; while the payroll is still a draft the slip updates at once.</p></div>
                    <button type="button" class="pr-x" @click="open = false" aria-label="Close">✕</button>
                </div>
                <div class="pr-sheet-body">
                    <div class="pr-split">
                        <div style="display:grid; gap:14px; align-content:start">
                            <div class="pr-field"><span>Kind</span>
                                <div class="pr-seg" role="radiogroup" aria-label="Kind">
                                    <label><input type="radio" value="earning" :checked="kind === 'earning'" @change="setKind('earning')"><span>Earning · incentive, commission</span></label>
                                    <label><input type="radio" value="deduction" :checked="kind === 'deduction'" @change="setKind('deduction')"><span>Deduction · fine, recovery</span></label>
                                </div>
                            </div>
                            <div class="pr-field"><span>Repeats <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'recurring' })" aria-label="Help: one time or every month" title="Help: one time or every month">i</button></span>
                                <div class="pr-seg" role="radiogroup" aria-label="Repeats">
                                    <label><input type="radio" name="repeat" value="once" x-model="repeat"><span>One time</span></label>
                                    <label><input type="radio" name="repeat" value="monthly" x-model="repeat"><span>Every month</span></label>
                                </div>
                                <small class="pr-label" style="font-size:12px" x-text="repeat === 'monthly' ? 'Paid each month from the first month until the last month, or until you stop it.' : 'Paid in this month only; next month it is not added.'"></small>
                            </div>
                            <div style="display:grid; gap:10px; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr))">
                                <label class="pr-field"><span x-text="repeat === 'monthly' ? 'First month' : 'Month'">Month</span><input type="month" name="month" x-ref="month" required class="pr-input" value="{{ old('month', $month) }}"></label>
                                <label class="pr-field" x-show="repeat === 'monthly'" x-cloak><span>Last month (optional)</span><input type="month" name="end_month" class="pr-input" value="{{ old('end_month') }}" :disabled="repeat !== 'monthly'"></label>
                            </div>
                            <label class="pr-field"><span>Category</span>
                                <select name="salary_component_id" class="pr-select" required x-model="componentId">
                                    <template x-for="c in options" :key="c.id"><option :value="String(c.id)" x-text="c.name"></option></template>
                                </select>
                                <template x-if="options.length === 0"><small style="color:var(--pr-red)">No category of this kind yet.</small></template>
                            </label>
                            @can('payroll-salary-manage')
                                <a href="{{ route('payroll.index', ['tab' => 'categories', 'new' => 1]) }}" style="font-size:13px; color:var(--pr-blue); text-decoration:none">+ New category</a>
                            @endcan
                            <label class="pr-field"><span>Amount (Rs) for each employee</span><input type="number" name="amount" step="0.01" min="0.01" required class="pr-input" value="{{ old('amount') }}" inputmode="decimal"></label>
                            <label class="pr-field"><span>Narration (shown on the payslip)</span><input type="text" name="note" maxlength="300" class="pr-input" value="{{ old('note') }}" placeholder="e.g. October sales target achieved"></label>
                        </div>
                        <div class="pr-col" style="display:flex; flex-direction:column; min-height:320px">
                            <template x-for="id in picked" :key="'h' + id"><input type="hidden" name="employee_ids[]" :value="id"></template>
                            <div class="pr-col-head"><h4>Employees</h4><span class="pr-label"><b x-text="picked.length"></b> selected</span></div>
                            <input type="search" x-model="term" class="pr-input" placeholder="Search name, code or designation" aria-label="Search employees">
                            <div style="display:flex; gap:6px; margin:8px 0">
                                <button type="button" class="pr-btn pr-btn-sm" @click="selectShown()">Select all shown</button>
                                <button type="button" class="pr-btn pr-btn-sm" @click="picked = []">Clear</button>
                            </div>
                            <div style="flex:1; overflow-y:auto; max-height:300px; border-radius:12px; background:#fff; box-shadow: inset 0 0 0 1px var(--pr-sep)">
                                <template x-for="p in shown" :key="p.id">
                                    <label style="display:flex; align-items:center; gap:10px; padding:8px 12px; border-bottom:1px solid rgba(60,60,67,.08); cursor:pointer">
                                        <input type="checkbox" class="pr-check" :value="p.id" x-model.number="picked">
                                        <span style="min-width:0"><span x-text="p.name" style="font-weight:500"></span>
                                            <span style="display:block; font-size:12px; color:var(--pr-muted)" x-text="[p.code, p.designation].filter(Boolean).join(' · ')"></span></span>
                                    </label>
                                </template>
                                <p x-show="shown.length === 0" class="pr-empty" style="padding:24px">No employee matches.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pr-sheet-foot">
                    <span class="pr-foot-note" x-text="picked.length ? picked.length + ' employee' + (picked.length === 1 ? '' : 's') + ' will get it' : 'Tick at least one employee'"></span>
                    <button type="button" class="pr-btn" @click="open = false">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn-primary" :disabled="busy || picked.length === 0 || ! componentId">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
