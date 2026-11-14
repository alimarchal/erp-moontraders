{{--
    Payroll hub · Employee salaries tab. Filters, a tickable list of employees with their monthly set-up, a floating bulk
    bar, and the salary sheet: Earnings on the left, Deductions on the right, for one employee or many at once.
    The sheet opens from a row, from the bulk bar, from the header ("pr-salary" event) or from ?edit=<employee id>.
--}}
@php
    $hub = app(\App\Http\Controllers\PayrollHubController::class);
    $isPaged = $employees instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $list = collect($isPaged ? $employees->items() : $employees);
    $total = $isPaged ? $employees->total() : $list->count();
    $salaryFilter = $filters['salary'] ?? '';
    $supplierNames = $suppliers->pluck('supplier_name', 'id');
    $salaryUrl = fn (array $filter) => route('payroll.index', array_filter(['tab' => 'salaries', 'filter' => $filter ?: null, 'per_page' => request('per_page')]));
    $chipLabels = ['search' => 'Search', 'designation' => 'Designation', 'supplier_id' => 'Supplier', 'salary' => 'Salary'];
    $chipValue = fn (string $key, $value) => match ($key) {
        'supplier_id' => $supplierNames[$value] ?? $value,
        'salary' => $value === 'missing' ? 'No salary yet' : 'Salary set',
        default => $value,
    };
    $chips = \Illuminate\Support\Arr::only($filters, array_keys($chipLabels));
    $chips = \Illuminate\Support\Arr::except($chips, ['salary']);
    $money = fn ($value) => number_format((float) $value);

    $rows = $list->map(fn ($employee) => $hub->salaryRow($employee));
    $pageNet = $rows->sum('net');

    $categories = $components->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'calculation' => $c->calculation])->values();
    $sheetData = $list->mapWithKeys(function ($employee) use ($advances, $shorts) {
        $short = $shorts[$employee->id] ?? null;

        return [$employee->id => [
            'id' => $employee->id,
            'name' => $employee->name,
            'code' => $employee->employee_code,
            'designation' => $employee->designation,
            'salary' => $employee->salary ? [
                'salary_type' => $employee->salary->salary_type,
                'basic_salary' => (float) $employee->salary->basic_salary,
                'pay_method' => $employee->salary->pay_method,
                'notes' => $employee->salary->notes,
            ] : null,
            'lines' => $employee->salaryComponents
                ->filter(fn ($row) => $row->component && $row->component->is_active && $row->component->calculation !== \App\Models\SalaryComponent::CALC_INPUT)
                ->map(fn ($row) => [
                    'component_id' => $row->salary_component_id,
                    'calculation' => $row->calculation ?? $row->component->calculation,
                    'base_component_id' => $row->base_component_id,
                    'value' => (float) $row->value,
                ])->values(),
            'advances' => collect($advances[$employee->id] ?? [])->map(fn ($advance) => [
                'date' => $advance->advance_date?->format('d M Y'),
                'balance' => $advance->balance(),
                'due' => $advance->dueThisMonth(),
            ])->values(),
            'short' => ['total' => round((float) ($short->total ?? 0), 2), 'settlements' => (int) ($short->settlements ?? 0)],
        ]];
    });
    $pageIds = $list->pluck('id')->values();
@endphp

@php
    // After a failed save the window opens again with what was typed (the ids travel in _employee_ids).
    $oldSheet = session()->hasOldInput('_employee_ids') ? [
        'ids' => array_values(array_filter(array_map('intval', explode(',', (string) old('_employee_ids'))))),
        'form' => ['salary_type' => (string) old('salary_type', ''), 'pay_method' => (string) old('pay_method', ''), 'basic' => (string) old('basic_salary', ''), 'notes' => (string) old('notes', '')],
        'lines' => array_values((array) old('lines', [])),
        'replace' => (bool) old('replace_components'),
        'basics' => (array) old('basics', []),
    ] : null;
@endphp
<div x-data="prSalaries({ employees: @js($sheetData), categories: @js($categories), pageIds: @js($pageIds), oldSheet: @js($oldSheet) })"
    x-init="init()"
    @pr-salary.window="openFor($event.detail && $event.detail.ids ? $event.detail.ids : [])"
    @keydown.escape.window="if (open) close()">

    {{-- Filters (hidden until the header Filters button) --}}
    <section id="pr-filter-panel" class="pr-card pr-filters no-print" x-data="{ show: false }" @pr-toggle-filters.window="show = ! show" x-show="show" x-cloak x-transition style="margin-top:14px">
        <form method="GET" action="{{ route('payroll.index') }}">
            <input type="hidden" name="tab" value="salaries">
            @if ($salaryFilter)<input type="hidden" name="filter[salary]" value="{{ $salaryFilter }}">@endif
            <div class="pr-filter-row">
                <label class="pr-field"><span>Search</span>
                    <input type="search" class="pr-input" name="filter[search]" value="{{ $filters['search'] ?? '' }}" placeholder="Name or employee code">
                </label>
                <label class="pr-field"><span>Designation</span>
                    <select class="pr-select" name="filter[designation]">
                        <option value="">All designations</option>
                        @foreach ($designations as $designation)
                            <option value="{{ $designation }}" @selected(($filters['designation'] ?? '') === $designation)>{{ $designation }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($suppliers->isNotEmpty())
                    <label class="pr-field"><span>Supplier</span>
                        <select class="pr-select" name="filter[supplier_id]">
                            <option value="">All suppliers</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="pr-field"><span>Rows</span>
                    <select class="pr-select" name="per_page">
                        @foreach (\App\Http\Controllers\PayrollHubController::PER_PAGE as $n)
                            <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                        @endforeach
                    </select>
                </label>
                <div style="display:flex; gap:8px">
                    <button type="submit" class="pr-btn pr-btn-primary">Apply</button>
                    <a href="{{ route('payroll.index', ['tab' => 'salaries']) }}" class="pr-btn">Clear</a>
                </div>
            </div>
        </form>
    </section>

    <section class="pr-card" style="margin-top:14px" aria-label="Employee salaries">
        <nav class="pr-subtabs" aria-label="Salary status">
            @foreach (['' => ['All', $salaryCounts['all']], 'set' => ['Salary set', $salaryCounts['set']], 'missing' => ['No salary yet', $salaryCounts['missing']]] as $key => [$label, $count])
                <a href="{{ $salaryUrl(array_filter(array_merge($filters, ['salary' => $key]))) }}" class="pr-subtab {{ $salaryFilter === $key ? 'is-on' : '' }}" @if ($salaryFilter === $key) aria-current="page" @endif>
                    {{ $label }} · {{ number_format($count) }}
                </a>
            @endforeach
        </nav>

        @if ($chips)
            <div class="pr-chips" aria-label="Active filters" style="border-bottom:0; padding-bottom:0">
                <span class="pr-label">Filtered by</span>
                @foreach ($chips as $key => $value)
                    <a href="{{ $salaryUrl(\Illuminate\Support\Arr::except($filters, $key)) }}" class="pr-chip" aria-label="Remove filter {{ $chipLabels[$key] }}">
                        <b>{{ $chipLabels[$key] }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                    </a>
                @endforeach
                <a href="{{ $salaryUrl(\Illuminate\Support\Arr::only($filters, ['salary'])) }}" class="pr-chip">Clear all</a>
            </div>
        @endif

        <div class="pr-toolbar" style="margin-top:10px">
            <p><b>{{ number_format($total) }}</b> {{ \Illuminate\Support\Str::plural('employee', $total) }}
                @if ($list->isNotEmpty()) · Net Rs <b>{{ $money($pageNet) }}</b> a month{{ $isPaged && $employees->hasPages() ? ' (this page)' : '' }}@endif
            </p>
            <span class="pr-spacer"></span>
            <p x-show="hint" x-cloak x-transition role="status" style="color:#8a4b00">Tick the employees first, or use “Set salary” on a row.</p>
        </div>

        @if ($list->isEmpty())
            <div class="pr-empty">
                <b>No employees here</b>
                {{ $chips || $salaryFilter ? 'Nothing matches these filters.' : 'Add active employees first; their salaries are set here.' }}
            </div>
        @else
            <div class="pr-scroll">
                <table class="pr-table pr-cards">
                    <caption class="sr-only">Employee salaries</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width:36px">
                                <input type="checkbox" class="pr-check" aria-label="Select every employee on this page"
                                    :checked="allTicked" x-effect="$el.indeterminate = ticked.length > 0 && ! allTicked" @change="toggleAll($event.target.checked)">
                            </th>
                            <th scope="col">Employee</th>
                            <th scope="col" class="pr-num">Basic<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'basic' })" aria-label="Help: basic salary" title="Help: basic salary">i</button></th>
                            <th scope="col" class="pr-num">Allowances<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'fixed-part' })" aria-label="Help: allowances" title="Help: allowances">i</button></th>
                            <th scope="col" class="pr-num">Deductions<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'deductions' })" aria-label="Help: deductions" title="Help: deductions">i</button></th>
                            <th scope="col" class="pr-num">Net</th>
                            <th scope="col">Type<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'salary-type' })" aria-label="Help: salary type" title="Help: salary type">i</button></th>
                            <th scope="col">Pay by<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'pay-method' })" aria-label="Help: pay method" title="Help: pay method">i</button></th>
                            <th scope="col" class="no-print"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $employee = $row['employee'];
                                $salary = $employee->salary;
                                $supplierShort = $employee->supplier ? ($employee->supplier->short_name ?: $employee->supplier->supplier_name) : null;
                            @endphp
                            <tr :style="ticked.includes({{ $employee->id }}) ? 'background:rgba(0,113,227,.05)' : ''">
                                <td data-label="">
                                    <label style="display:inline-flex; align-items:center; gap:8px">
                                        <input type="checkbox" class="pr-check" value="{{ $employee->id }}" x-model.number="ticked" aria-label="Select {{ $employee->name }}">
                                    </label>
                                </td>
                                <td data-label="Employee" style="text-align:left">
                                    <div>
                                        <div style="font-weight:600; color:#1d1d1f">{{ $employee->name }}</div>
                                        <div class="pr-label" style="font-size:12.5px">{{ collect([$employee->employee_code, $employee->designation, $supplierShort])->filter()->join(' · ') }}</div>
                                    </div>
                                </td>
                                <td data-label="Basic" class="pr-num">{{ $salary ? $money($row['basic']) : '—' }}</td>
                                <td data-label="Allowances" class="pr-num pr-earn">{{ $salary ? $money($row['earnings'] - $row['basic']) : '—' }}</td>
                                <td data-label="Deductions" class="pr-num pr-ded">{{ $salary ? $money($row['deductions']) : '—' }}</td>
                                <td data-label="Net" class="pr-num" style="font-weight:600">{{ $salary ? $money($row['net']) : '—' }}</td>
                                <td data-label="Type">
                                    @if (! $salary)
                                        <span class="pr-cap pr-cap-grey">No salary</span>
                                    @elseif ($salary->salary_type === 'fixed')
                                        <span class="pr-cap pr-cap-blue" title="Never cut for absent days">Fixed</span>
                                    @else
                                        <span class="pr-cap pr-cap-green" title="Absent days cut the pay">Basic</span>
                                    @endif
                                </td>
                                <td data-label="Pay by">{{ $salary ? ucfirst($salary->pay_method) : '—' }}</td>
                                <td data-label="" class="no-print" style="text-align:right">
                                    <button type="button" class="pr-btn pr-btn-sm" @click="openFor([{{ $employee->id }}])" aria-haspopup="dialog">
                                        {{ $salary ? 'Edit salary' : 'Set salary' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($isPaged && $employees->hasPages())
                <div style="padding:12px 16px">{{ $employees->links() }}</div>
            @endif
        @endif
    </section>

    {{-- Floating bulk bar --}}
    <div class="pr-bulkbar no-print" x-show="ticked.length > 0" x-cloak x-transition role="region" aria-label="Selected employees" style="margin-top:12px">
        <span><b x-text="ticked.length"></b> selected</span>
        <button type="button" class="pr-btn pr-btn-primary" @click="openFor(ticked)">Set salary for selected</button>
        @can('payroll-adjustment-manage')
            <a href="{{ route('payroll.index', ['tab' => 'adjustments']) }}" class="pr-btn">Add incentive / adjustment</a>
        @endcan
        <button type="button" class="pr-btn" @click="ticked = []">Clear</button>
    </div>

    {{-- The salary sheet --}}
    <template x-teleport="body">
        <div class="pr-sheet-wrap" x-show="open" x-cloak role="dialog" aria-modal="true" aria-labelledby="pr-salary-title">
            <div class="pr-scrim" @click="close()" x-show="open" x-transition.opacity></div>
            <form class="pr-sheet pr-sheet-lg" method="POST" :action="action" x-ref="form" @submit="submit($event)"
                x-show="open" x-transition:enter="pr-enter" x-transition:enter-start="pr-from" x-transition:enter-end="pr-to"
                x-transition:leave="pr-leave" x-transition:leave-start="pr-to" x-transition:leave-end="pr-from">
                @csrf
                <template x-if="single"><input type="hidden" name="_method" value="PUT"></template>
                <template x-if="! single">
                    <div><template x-for="id in ids" :key="id"><input type="hidden" name="employee_ids[]" :value="id"></template></div>
                </template>
                <input type="hidden" name="return_to" :value="returnTo">
                <input type="hidden" name="_employee_ids" :value="ids.join(',')">

                <header class="pr-sheet-head">
                    <div style="min-width:0">
                        <h3><span id="pr-salary-title" x-text="single ? emp.name : ids.length + ' employees'"></span> <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'salary-window' })" aria-label="Help: the salary window" title="Help: the salary window">i</button></h3>
                        <p x-text="single ? [emp.code, emp.designation].filter(Boolean).join(' · ') : namesLine"></p>
                    </div>
                    <button type="button" class="pr-x" @click="close()" aria-label="Close">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>

                <div class="pr-sheet-body">
                    {{-- Top: salary type, pay by, basic --}}
                    <div style="display:grid; gap:14px; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:16px">
                        <div class="pr-field">
                            <span>Salary type <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'salary-type' })" aria-label="Help: salary type" title="Help: salary type">i</button></span>
                            <div class="pr-seg" role="radiogroup" aria-label="Salary type">
                                <template x-if="! single"><label><input type="radio" name="salary_type" value="" x-model="form.salary_type"><span>Keep</span></label></template>
                                <label title="Absent days cut the pay"><input type="radio" name="salary_type" value="basic" x-model="form.salary_type"><span>Basic</span></label>
                                <label title="Never cut for absent days"><input type="radio" name="salary_type" value="fixed" x-model="form.salary_type"><span>Fixed</span></label>
                            </div>
                            <small class="pr-label" style="font-size:12px" x-text="form.salary_type === 'fixed' ? 'Never cut for absent days' : (form.salary_type === 'basic' ? 'Absent days cut the pay' : 'Each one keeps their own')"></small>
                        </div>
                        <div class="pr-field">
                            <span>Pay by <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'pay-method' })" aria-label="Help: pay method" title="Help: pay method">i</button></span>
                            <div class="pr-seg" role="radiogroup" aria-label="Pay by">
                                <template x-if="! single"><label><input type="radio" name="pay_method" value="" x-model="form.pay_method"><span>Keep</span></label></template>
                                <label><input type="radio" name="pay_method" value="cash" x-model="form.pay_method"><span>Cash</span></label>
                                <label><input type="radio" name="pay_method" value="bank" x-model="form.pay_method"><span>Bank</span></label>
                            </div>
                        </div>
                        <label class="pr-field">
                            <span><span x-text="single ? 'Basic salary (Rs a month)' : 'Same basic for everyone (optional)'">Basic salary (Rs a month)</span> <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'basic' })" aria-label="Help: basic salary" title="Help: basic salary">i</button></span>
                            <input type="number" class="pr-input" name="basic_salary" x-ref="first" min="0" step="0.01" inputmode="decimal"
                                x-model="form.basic" :required="single" :placeholder="single ? '0' : 'Keep each one\'s'">
                        </label>
                    </div>

                    {{-- Many employees: each one's own basic salary --}}
                    <template x-if="! single">
                        <section class="pr-col" style="margin-bottom:16px" aria-label="Basic salary of each employee">
                            <div class="pr-col-head">
                                <h4>Basic salary of each employee <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'basic' })" aria-label="Help: basic salary" title="Help: basic salary">i</button></h4>
                                <span style="display:flex; gap:6px">
                                    <button type="button" class="pr-btn pr-btn-sm" @click="ids.forEach((id) => basics[id] = form.basic)" :disabled="form.basic === ''" title="Copy the same basic into every row">Use same for all</button>
                                    <button type="button" class="pr-btn pr-btn-sm" @click="prefillBasics()" title="Put back each one's saved basic">Reset</button>
                                </span>
                            </div>
                            <p class="pr-label" style="font-size:12px; margin:0 0 6px">Type a different basic for anyone; a row left empty keeps what that employee has (or the same basic above).</p>
                            <div style="max-height:260px; overflow-y:auto; border-radius:12px; background:#fff; box-shadow: inset 0 0 0 1px var(--pr-sep)">
                                <template x-for="id in ids" :key="'b' + id">
                                    <label style="display:grid; grid-template-columns:1fr 150px; gap:10px; align-items:center; padding:6px 12px; border-bottom:1px solid rgba(60,60,67,.08)">
                                        <span style="min-width:0"><span style="font-weight:500" x-text="employees[id].name"></span>
                                            <span style="display:block; font-size:12px; color:var(--pr-muted)" x-text="[employees[id].code, employees[id].designation, employees[id].salary ? '' : 'No salary yet'].filter(Boolean).join(' · ')"></span></span>
                                        <input type="number" class="pr-input" :name="'basics[' + id + ']'" min="0" step="0.01" inputmode="decimal" x-model="basics[id]"
                                            :placeholder="form.basic !== '' ? form.basic : (employees[id].salary ? 'Keep' : 'Basic')" :aria-label="'Basic salary of ' + employees[id].name">
                                    </label>
                                </template>
                            </div>
                        </section>
                    </template>

                    <div class="pr-split">
                        <template x-for="side in ['earning', 'deduction']" :key="side">
                            <section :class="side === 'earning' ? 'pr-col pr-col-earn' : 'pr-col pr-col-ded'" :aria-label="side === 'earning' ? 'Earnings' : 'Deductions'">
                                <div class="pr-col-head">
                                    <h4><span x-text="side === 'earning' ? 'Earnings' : 'Deductions'"></span> <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: side === 'earning' ? 'fixed-part' : 'deductions' })" :aria-label="'Help: ' + (side === 'earning' ? 'earnings' : 'deductions')" title="Help">i</button> <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'calc' })" aria-label="Help: fixed, % of basic, % of another allowance" title="How amounts are worked out">%</button></h4>
                                    <span class="pr-label" style="font-size:12px" x-text="side === 'earning' ? 'Paid every month' : 'Cut every month'"></span>
                                </div>

                                <template x-if="side === 'earning'">
                                    <div style="display:flex; justify-content:space-between; gap:8px; padding:8px 2px; border-bottom:1px solid rgba(60,60,67,.08)">
                                        <span style="font-weight:500">Basic salary</span>
                                        <span class="pr-num" x-text="form.basic === '' && ! single ? 'Each one\'s own' : fmt(basic())"></span>
                                    </div>
                                </template>

                                <template x-for="row in rowsOf(side)" :key="row.key">
                                    <div style="display:grid; gap:8px; padding:10px 0; border-bottom:1px solid rgba(60,60,67,.08)">
                                        <div style="display:flex; gap:8px; align-items:center">
                                            <select class="pr-select" x-model.number="row.component_id" :aria-label="side === 'earning' ? 'Allowance' : 'Deduction'" style="flex:1">
                                                <option value="">Choose…</option>
                                                <template x-for="c in available(side, row)" :key="c.id">
                                                    <option :value="c.id" :selected="c.id == row.component_id" x-text="c.name"></option>
                                                </template>
                                            </select>
                                            <span class="pr-num" style="min-width:84px; font-weight:600" :class="side === 'earning' ? 'pr-earn' : 'pr-ded'" x-text="fmt(amount(row))"></span>
                                            <button type="button" class="pr-x" style="margin-left:0; width:26px; height:26px" @click="removeRow(row)" :aria-label="'Remove ' + catName(row.component_id)">
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                            </button>
                                        </div>
                                        <div style="display:flex; flex-wrap:wrap; gap:8px">
                                            <select class="pr-select" x-model="row.calculation" aria-label="How it is worked out" style="flex:1 1 150px">
                                                <option value="fixed">Fixed Rs</option>
                                                <option value="percent_of_basic">% of basic</option>
                                                <option value="percent_of_component">% of another allowance</option>
                                            </select>
                                            <input type="number" class="pr-input" x-model="row.value" min="0" step="0.01" inputmode="decimal" style="flex:1 1 90px"
                                                :placeholder="row.calculation === 'fixed' ? 'Rs' : '%'" :aria-label="row.calculation === 'fixed' ? 'Amount in Rs' : 'Percentage'">
                                            <template x-if="row.calculation === 'percent_of_component'">
                                                <select class="pr-select" x-model.number="row.base_component_id" aria-label="Percentage of which allowance" style="flex:1 1 150px">
                                                    <option value="">of…</option>
                                                    <template x-for="b in baseOptions(row)" :key="b.id">
                                                        <option :value="b.id" :selected="b.id == row.base_component_id" x-text="'of ' + b.name"></option>
                                                    </template>
                                                </select>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px">
                                    <button type="button" class="pr-btn pr-btn-sm" @click="addRow(side)" x-text="side === 'earning' ? '+ Add allowance' : '+ Add deduction'"></button>
                                    <button type="button" class="pr-btn pr-btn-sm" style="background:none; color:#0066cc" @click="newCat[side].show = ! newCat[side].show" :aria-expanded="newCat[side].show">+ New category</button>
                                    <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'categories' })" aria-label="Help: categories" title="Help: categories">i</button>
                                </div>
                                <div x-show="newCat[side].show" x-cloak style="display:grid; gap:8px; margin-top:10px; padding:12px; border-radius:12px; background:#fff; box-shadow:inset 0 0 0 1px rgba(60,60,67,.14)">
                                    <input type="text" class="pr-input" x-model="newCat[side].name" maxlength="120" :placeholder="side === 'earning' ? 'e.g. Travel Allowance' : 'e.g. Welfare Fund'" aria-label="New category name"
                                        @keydown.enter.prevent="createCategory(side)">
                                    <select class="pr-select" x-model="newCat[side].calculation" aria-label="Usually worked out as">
                                        <option value="fixed">Usually a fixed amount</option>
                                        <option value="percent_of_basic">Usually a % of basic</option>
                                        <option value="percent_of_component">Usually a % of another allowance</option>
                                    </select>
                                    <p x-show="newCat[side].error" x-text="newCat[side].error" role="alert" style="margin:0; font-size:13px; color:#d70015"></p>
                                    <div style="display:flex; gap:8px">
                                        <button type="button" class="pr-btn pr-btn-sm pr-btn-primary" @click="createCategory(side)" :disabled="newCat[side].busy || ! newCat[side].name.trim()"
                                            x-text="newCat[side].busy ? 'Adding…' : 'Add category'"></button>
                                        <button type="button" class="pr-btn pr-btn-sm" @click="newCat[side].show = false">Cancel</button>
                                    </div>
                                </div>

                                <template x-if="side === 'deduction'">
                                    <div style="margin-top:14px; padding-top:12px; border-top:1px dashed rgba(60,60,67,.2)">
                                        <div class="pr-label" style="margin-bottom:6px">Automatic every month <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'sources' })" aria-label="Help: where automatic amounts come from" title="Help: where automatic amounts come from">i</button></div>
                                        <template x-if="single">
                                            <div style="display:grid; gap:6px; font-size:13.5px">
                                                <template x-for="(a, i) in emp.advances" :key="i">
                                                    <div style="display:flex; justify-content:space-between; gap:8px">
                                                        <span><span x-text="'Advance of ' + a.date + ' · balance Rs ' + fmt(a.balance)"></span> <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'advance' })" aria-label="Help: advances" title="Help: advances">i</button></span>
                                                        <span class="pr-num pr-ded" x-text="fmt(a.due)"></span>
                                                    </div>
                                                </template>
                                                <div style="display:flex; justify-content:space-between; gap:8px">
                                                    <span>Settlement short <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'short' })" aria-label="Help: settlement short" title="Help: settlement short">i</button> · {{ $shortMonth->format('F Y') }} <span class="pr-label" style="font-size:12px" x-text="'(' + emp.short.settlements + ' settlements, booked to Short Amount)'"></span></span>
                                                    <span class="pr-num pr-ded" x-text="fmt(emp.short.total)"></span>
                                                </div>
                                                <p x-show="! emp.advances.length" class="pr-label" style="margin:0; font-size:12.5px">No open advance.</p>
                                            </div>
                                        </template>
                                        <template x-if="! single">
                                            <p class="pr-label" style="margin:0; font-size:13px">Advances and settlement shorts are taken automatically for each person when payroll is built.</p>
                                        </template>
                                    </div>
                                </template>

                                <div class="pr-col-total">
                                    <span x-text="side === 'earning' ? 'Total earnings' : 'Total deductions'"></span>
                                    <span :class="side === 'earning' ? 'pr-earn' : 'pr-ded'" x-text="fmt(total(side))"></span>
                                </div>
                            </section>
                        </template>
                    </div>

                    <div class="pr-net">
                        <small>Net salary (before advances &amp; shorts)</small>
                        <b x-text="'Rs ' + fmt(total('earning') - total('deduction'))"></b>
                    </div>

                    <template x-if="single">
                        <label class="pr-field" style="margin-top:14px">
                            <span>Notes</span>
                            <textarea class="pr-textarea" name="notes" maxlength="500" rows="2" x-model="form.notes" placeholder="Optional"></textarea>
                        </label>
                    </template>
                    <template x-if="! single">
                        <label style="display:flex; align-items:center; gap:8px; margin-top:14px; font-size:14px">
                            <input type="checkbox" class="pr-check" name="replace_components" value="1" :checked="oldSheet && oldSheet.replace">
                            Remove categories not listed here
                        </label>
                    </template>

                    <template x-for="(line, i) in validRows()" :key="line.key">
                        <div>
                            <input type="hidden" :name="'lines[' + i + '][component_id]'" :value="line.component_id">
                            <input type="hidden" :name="'lines[' + i + '][calculation]'" :value="line.calculation">
                            <input type="hidden" :name="'lines[' + i + '][value]'" :value="line.value === '' ? 0 : line.value">
                            <template x-if="line.calculation === 'percent_of_component'">
                                <input type="hidden" :name="'lines[' + i + '][base_component_id]'" :value="line.base_component_id">
                            </template>
                        </div>
                    </template>
                    <p x-show="error" x-text="error" role="alert" style="margin:12px 0 0; font-size:13.5px; color:#d70015"></p>
                </div>

                <footer class="pr-sheet-foot">
                    <span class="pr-foot-note">Saved set-ups are used by the next payroll run.</span>
                    <button type="button" class="pr-btn" @click="close()">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn-primary" :disabled="saving" x-text="saving ? 'Saving…' : 'Save'"></button>
                </footer>
            </form>
        </div>
    </template>
</div>

<script>
    function prSalaries({ employees, categories, pageIds, oldSheet }) {
        return {
            employees, categories, pageIds, oldSheet,
            ticked: [], ids: [], rows: [], basics: {}, open: false, saving: false, hint: false, error: '', seq: 0,
            form: { salary_type: 'basic', pay_method: 'cash', basic: '', notes: '' },
            newCat: { earning: { show: false, name: '', calculation: 'fixed', error: '', busy: false }, deduction: { show: false, name: '', calculation: 'fixed', error: '', busy: false } },
            routes: { update: @js(route('payroll.salaries.update', ['employee' => '__ID__'])), bulk: @js(route('payroll.salaries.bulk')), store: @js(route('payroll.components.store')) },

            init() {
                if (this.oldSheet && this.oldSheet.ids.some((id) => this.employees[id])) {
                    this.$nextTick(() => {
                        this.openFor(this.oldSheet.ids);
                        this.form = { ...this.oldSheet.form };
                        this.rows = this.oldSheet.lines.filter((l) => this.cat(l.component_id)).map((l) => this.row(l));
                        this.error = @js($errors->first());
                    });
                    return;
                }
                const edit = new URLSearchParams(location.search).get('edit');
                if (edit && this.employees[edit]) { this.$nextTick(() => this.openFor([Number(edit)])); }
            },
            get allTicked() { return this.pageIds.length > 0 && this.pageIds.every((id) => this.ticked.includes(id)); },
            toggleAll(on) { this.ticked = on ? [...this.pageIds] : []; },
            get single() { return this.ids.length === 1; },
            get emp() { return this.single ? this.employees[this.ids[0]] : { name: '', code: '', advances: [], short: { total: 0, settlements: 0 } }; },
            get namesLine() {
                const names = this.ids.map((id) => this.employees[id]?.name).filter(Boolean);
                return names.slice(0, 4).join(', ') + (names.length > 4 ? ' and ' + (names.length - 4) + ' more' : '');
            },
            get action() { return this.single ? this.routes.update.replace('__ID__', this.ids[0]) : this.routes.bulk; },
            get returnTo() { const u = new URL(location.href); u.searchParams.delete('edit'); return u.toString(); },

            openFor(ids) {
                ids = (ids && ids.length ? ids : this.ticked).map(Number).filter((id) => this.employees[id]);
                if (! ids.length) { this.hint = true; setTimeout(() => this.hint = false, 3500); return; }
                this.ids = ids; this.error = ''; this.saving = false; this.seq = 0;
                this.newCat.earning.show = this.newCat.deduction.show = false;
                if (this.single) { this.prefillOne(this.employees[ids[0]]); } else { this.prefillMany(); }
                this.open = true;
                this.$nextTick(() => this.$refs.first && this.$refs.first.focus());
            },
            close() { if (! this.saving) { this.open = false; } },
            row(line) {
                return { key: ++this.seq, side: this.cat(line.component_id)?.type || line.side, component_id: line.component_id || '', calculation: line.calculation || 'fixed',
                    value: line.value === null || line.value === undefined ? '' : String(line.value), base_component_id: line.base_component_id || '' };
            },
            prefillOne(e) {
                this.form = { salary_type: e.salary?.salary_type || 'basic', pay_method: e.salary?.pay_method || 'cash', basic: e.salary ? String(e.salary.basic_salary) : '', notes: e.salary?.notes || '' };
                this.rows = e.lines.filter((l) => this.cat(l.component_id)).map((l) => this.row(l));
                if (! e.salary && ! this.rows.length) {
                    ['House Rent', 'Utility Allowance'].forEach((name) => {
                        const c = this.categories.find((x) => x.type === 'earning' && x.name.toLowerCase() === name.toLowerCase());
                        if (c) { this.rows.push(this.row({ component_id: c.id, calculation: c.calculation === 'input' ? 'fixed' : c.calculation, value: '' })); }
                    });
                }
            },
            prefillMany() {
                this.form = { salary_type: '', pay_method: '', basic: '', notes: '' };
                this.prefillBasics();
                const sig = (e) => JSON.stringify([...e.lines].sort((a, b) => a.component_id - b.component_id));
                const first = this.employees[this.ids[0]];
                const same = this.ids.every((id) => sig(this.employees[id]) === sig(first));
                this.rows = same ? first.lines.filter((l) => this.cat(l.component_id)).map((l) => this.row(l)) : [];
            },

            prefillBasics() {
                const old = this.oldSheet?.basics || {};
                this.basics = Object.fromEntries(this.ids.map((id) => [id, old[id] ?? (this.employees[id].salary ? String(this.employees[id].salary.basic_salary) : '')]));
            },
            cat(id) { return this.categories.find((c) => c.id == id); },
            catName(id) { return this.cat(id)?.name || 'row'; },
            rowsOf(side) { return this.rows.filter((r) => r.side === side); },
            available(side, row) {
                const used = this.rows.filter((r) => r !== row && r.component_id).map((r) => Number(r.component_id));
                return this.categories.filter((c) => c.type === side && (! used.includes(c.id) || c.id == row.component_id));
            },
            baseOptions(row) { return this.rows.filter((r) => r !== row && r.side === 'earning' && r.component_id).map((r) => this.cat(r.component_id)).filter(Boolean); },
            addRow(side, componentId = '', calculation = 'fixed') { this.rows.push({ key: ++this.seq, side, component_id: componentId, calculation, value: '', base_component_id: '' }); },
            removeRow(row) {
                this.rows = this.rows.filter((r) => r !== row);
                this.rows.forEach((r) => { if (r.base_component_id == row.component_id) { r.base_component_id = ''; } });
            },
            basic() { return parseFloat(this.form.basic) || 0; },
            amount(row, depth = 0) {
                const v = parseFloat(row.value) || 0;
                if (row.calculation === 'percent_of_basic') { return this.basic() * v / 100; }
                if (row.calculation === 'percent_of_component') {
                    const base = this.rows.find((r) => r !== row && r.component_id && r.component_id == row.base_component_id);
                    return base && depth < 4 ? this.amount(base, depth + 1) * v / 100 : 0;
                }
                return v;
            },
            total(side) { return (side === 'earning' ? this.basic() : 0) + this.rowsOf(side).reduce((sum, r) => sum + (r.component_id ? this.amount(r) : 0), 0); },
            validRows() { return this.rows.filter((r) => r.component_id); },
            fmt(n) { return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }); },

            async createCategory(side) {
                const box = this.newCat[side];
                if (! box.name.trim() || box.busy) { return; }
                box.busy = true; box.error = '';
                try {
                    const res = await fetch(this.routes.store, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ name: box.name.trim(), type: side, calculation: box.calculation, default_value: 0 }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.status === 201 && data.component) {
                        const c = { id: data.component.id, name: data.component.name, type: data.component.type, calculation: data.component.calculation };
                        this.categories.push(c);
                        this.addRow(side, c.id, c.calculation);
                        box.name = ''; box.show = false;
                    } else {
                        box.error = (data.errors && Object.values(data.errors)[0]?.[0]) || data.message || 'Could not add the category.';
                    }
                } catch (e) {
                    box.error = 'Could not reach the server. Try again.';
                } finally {
                    box.busy = false;
                }
            },

            submit(event) {
                this.error = '';
                if (this.rows.some((r) => r.calculation === 'percent_of_component' && r.component_id && ! r.base_component_id)) {
                    event.preventDefault(); this.error = 'Choose which allowance each percentage is of.'; return;
                }
                if (this.single && this.form.basic === '') { event.preventDefault(); this.error = 'Enter the basic salary.'; return; }
                if (this.saving) { event.preventDefault(); return; }
                this.saving = true;
            },
        };
    }
</script>
