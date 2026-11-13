{{-- Payroll runs tab: one row per month's payroll, with filters, stage pills, totals and the New payroll sheet. --}}
@php
    $authUser = auth()->user();
    $isPaginator = $runs instanceof \Illuminate\Contracts\Pagination\Paginator;
    $rows = $isPaginator ? collect($runs->items()) : $runs;
    $status = $filters['status'] ?? '';
    $withFilters = fn (array $f) => route('payroll.index', array_filter(['tab' => 'runs', 'filter' => $f ?: null, 'per_page' => request('per_page')]));
    $statusUrl = fn (string $s) => $withFilters(array_filter(array_merge(\Illuminate\Support\Arr::except($filters, ['status']), ['status' => $s ?: null])));
    $statusPills = ['' => 'All', 'draft' => 'Draft', 'pending' => 'Pending approval', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected'];
    $allCount = (int) collect($statusCounts)->sum();
    $chipLabels = ['search' => 'Payroll no.', 'month_from' => 'From', 'month_to' => 'To', 'owner' => 'Company'];
    $chipValue = function (string $key, $value) use ($suppliers) {
        if (in_array($key, ['month_from', 'month_to'], true)) {
            return rescue(fn () => \Carbon\Carbon::parse($value.'-01')->format('M Y'), $value, false);
        }
        if ($key === 'owner') {
            return $value === 'company' ? 'All employees' : ($suppliers->firstWhere('id', (int) $value)?->supplier_name ?? $value);
        }

        return $value;
    };
    $chips = \Illuminate\Support\Arr::only($filters, array_keys($chipLabels));
    $stage = function (\App\Models\PayrollRun $run): array {
        return match ($run->status) {
            'draft' => ['Draft', 'grey', 'Made by '.($run->creator?->name ?? '—')],
            'pending' => ['Pending approval', 'amber', 'Sent by '.($run->creator?->name ?? '—')],
            'approved' => ['Approved · posted', 'blue', 'Checked by '.($run->checker?->name ?? '—')],
            'paid' => ['Paid '.($run->paid_on?->format('d M') ?? ''), 'green', 'Checked by '.($run->checker?->name ?? '—')],
            'rejected' => ['Rejected', 'red', 'By '.($run->checker?->name ?? '—')],
            default => [ucfirst($run->status), 'grey', ''],
        };
    };
    $money = fn ($v) => number_format((float) $v);
    $canCreate = $authUser->can('payroll-create');
    $openNew = request()->boolean('new') || old('month') !== null;
@endphp

<div x-data="{ confirm: { open: false, url: '', number: '' } }" @keydown.escape.window="confirm.open = false">

    {{-- Filters (hidden until the header Filters button) --}}
    <section id="pr-filter-panel" class="pr-card pr-filters no-print" x-data="{ open: {{ count($chips) ? 'true' : 'false' }} }" @pr-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition aria-label="Filters">
        <form method="GET" action="{{ route('payroll.index') }}">
            <input type="hidden" name="tab" value="runs">
            @if ($status)<input type="hidden" name="filter[status]" value="{{ $status }}">@endif
            <div class="pr-filter-row">
                <label class="pr-field"><span>Payroll no.</span>
                    <input type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" class="pr-input" placeholder="PR-…" autocomplete="off"></label>
                <label class="pr-field"><span>From month</span>
                    <input type="month" name="filter[month_from]" value="{{ $filters['month_from'] ?? '' }}" class="pr-input"></label>
                <label class="pr-field"><span>To month</span>
                    <input type="month" name="filter[month_to]" value="{{ $filters['month_to'] ?? '' }}" class="pr-input"></label>
                @if ($suppliers->isNotEmpty())
                    <label class="pr-field"><span>Company</span>
                        <select name="filter[owner]" class="pr-select">
                            <option value="">Any</option>
                            <option value="company" @selected(($filters['owner'] ?? '') === 'company')>All employees</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['owner'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select></label>
                @endif
                <label class="pr-field"><span>Rows</span>
                    <select name="per_page" class="pr-select">
                        @foreach (\App\Http\Controllers\PayrollHubController::PER_PAGE as $n)
                            <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                        @endforeach
                    </select></label>
                <div style="display:flex; gap:8px">
                    <button type="submit" class="pr-btn pr-btn-primary">Apply</button>
                    <a href="{{ route('payroll.index', ['tab' => 'runs']) }}" class="pr-btn">Clear</a>
                </div>
            </div>
        </form>
    </section>

    {{-- Totals of what is listed --}}
    <div class="pr-stats">
        <div class="pr-card pr-stat"><small>Payrolls shown</small><b>{{ number_format($isPaginator ? $runs->total() : $rows->count()) }}</b></div>
        <div class="pr-card pr-stat"><small>Total earnings</small><b class="is-earn">{{ $money($rows->sum('total_earnings')) }}</b></div>
        <div class="pr-card pr-stat"><small>Total deductions</small><b class="is-ded">{{ $money($rows->sum('total_deductions')) }}</b></div>
        <div class="pr-card pr-stat"><small>Net pay</small><b>{{ $money($rows->sum('total_net')) }}</b></div>
    </div>

    <section class="pr-card" aria-label="Payroll runs" style="margin-top:14px">
        <nav class="pr-subtabs" aria-label="Payroll stage">
            @foreach ($statusPills as $key => $label)
                <a href="{{ $statusUrl($key) }}" class="pr-subtab {{ $status === $key ? 'is-on' : '' }}" @if ($status === $key) aria-current="page" @endif>
                    {{ $label }} {{ number_format($key === '' ? $allCount : (int) ($statusCounts[$key] ?? 0)) }}
                </a>
            @endforeach
            <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'status' })" aria-label="Help: what each stage means" title="Help: what each stage means">i</button>
        </nav>

        @if ($chips)
            <div class="pr-chips" aria-label="Active filters" style="margin-top:10px">
                <span class="pr-label">Filtered by</span>
                @foreach ($chips as $key => $value)
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="pr-chip" aria-label="Remove filter {{ $chipLabels[$key] }}"><b>{{ $chipLabels[$key] }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span></a>
                @endforeach
                <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['status'])) }}" class="pr-chip">Clear all</a>
            </div>
        @endif

        @if ($rows->isEmpty())
            <div class="pr-empty">
                <b>{{ count($filters) ? 'No payroll matches these filters' : 'No payroll yet' }}</b>
                {{ count($filters) ? 'Change or clear the filters to see more.' : 'Start a month and every employee with a salary gets a payslip.' }}
                @if ($canCreate && ! count($filters))
                    <div style="margin-top:14px"><button type="button" class="pr-btn pr-btn-primary" @click="$dispatch('pr-new-run')">Start the first payroll</button></div>
                @endif
            </div>
        @else
            <div class="pr-toolbar" style="margin-top:10px">
                <p><b>{{ number_format($isPaginator ? $runs->total() : $rows->count()) }}</b> {{ \Illuminate\Support\Str::plural('payroll', $isPaginator ? $runs->total() : $rows->count()) }} · net Rs <b>{{ $money($rows->sum('total_net')) }}</b></p>
            </div>
            <div class="pr-scroll" tabindex="0" aria-label="Payroll runs table">
                <table class="pr-table pr-cards">
                    <caption class="sr-only">Payroll runs</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width:40px">#</th>
                            <th scope="col">Payroll</th>
                            <th scope="col">Company</th>
                            <th scope="col" class="pr-num">Staff</th>
                            <th scope="col" class="pr-num">Earnings<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'payslip' })" aria-label="Help: what earnings are" title="Help: what earnings are">i</button></th>
                            <th scope="col" class="pr-num">Deductions<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'deductions' })" aria-label="Help: what deductions are" title="Help: what deductions are">i</button></th>
                            <th scope="col" class="pr-num">Net pay<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'payslip' })" aria-label="Help: what net pay is" title="Help: what net pay is">i</button></th>
                            <th scope="col">Stage<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'status' })" aria-label="Help: what each stage means" title="Help: what each stage means">i</button></th>
                            <th scope="col" class="no-print"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $run)
                            @php
                                [$stageText, $stageTone, $stageSub] = $stage($run);
                                $canDelete = $canCreate && $run->isEditable() && (int) $run->created_by === $authUser->id;
                            @endphp
                            <tr>
                                <td data-label="#" style="color:#8e8e93; font-size:12.5px">{{ ($isPaginator ? $runs->firstItem() : 1) + $loop->index }}</td>
                                <td data-label="Payroll">
                                    <a href="{{ route('payroll.show', $run) }}" style="font-weight:600; color:var(--pr-text); text-decoration:none">{{ $run->run_number }}</a>
                                    <div style="font-size:12.5px; color:var(--pr-muted)">{{ $run->period_month->format('F Y') }}</div>
                                </td>
                                <td data-label="Company">{{ $run->ownerLabel() }}</td>
                                <td data-label="Staff" class="pr-num">{{ number_format($run->slips_count) }}</td>
                                <td data-label="Earnings" class="pr-num pr-earn">{{ $money($run->total_earnings) }}</td>
                                <td data-label="Deductions" class="pr-num pr-ded">{{ $money($run->total_deductions) }}</td>
                                <td data-label="Net pay" class="pr-num" style="font-weight:600">{{ $money($run->total_net) }}</td>
                                <td data-label="Stage">
                                    <span class="pr-cap pr-cap-{{ $stageTone }}">{{ $stageText }}</span>
                                    @if ($stageSub)<div style="font-size:11.5px; color:var(--pr-muted); margin-top:3px">{{ $stageSub }}</div>@endif
                                </td>
                                <td data-label="" class="no-print">
                                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:4px" x-data="{ open: false, pos: '',
                                        toggle(e) {
                                            const r = e.currentTarget.getBoundingClientRect();
                                            const flip = r.bottom + 220 > window.innerHeight;
                                            this.pos = 'right:' + Math.max(8, window.innerWidth - r.right) + 'px;' + (flip ? 'bottom:' + (window.innerHeight - r.top + 4) + 'px' : 'top:' + (r.bottom + 4) + 'px');
                                            this.open = ! this.open;
                                        } }" @keydown.escape.window="open = false" @scroll.window="open = false" @resize.window="open = false">
                                        <a href="{{ route('payroll.show', $run) }}" class="pr-btn pr-btn-sm" aria-label="Open {{ $run->run_number }}">Open</a>
                                        <button type="button" class="pr-btn pr-btn-sm" style="width:32px; padding:0" @click="toggle($event)" :aria-expanded="open" aria-haspopup="menu" aria-label="More actions for {{ $run->run_number }}">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="12" cy="19" r="1.7" /></svg>
                                        </button>
                                        <div class="pr-row-menu" role="menu" x-show="open" x-cloak :style="pos" @click.outside="open = false">
                                            <a role="menuitem" href="{{ route('payroll.slips.pdf', $run) }}">Payslips PDF</a>
                                            <a role="menuitem" href="{{ route('payroll.export.pdf', $run) }}">Register PDF</a>
                                            <a role="menuitem" href="{{ route('payroll.export.excel', $run) }}">Excel</a>
                                            <a role="menuitem" href="{{ route('payroll.print', $run) }}" target="_blank" rel="noopener">Print slips</a>
                                            @if ($canDelete)
                                                <button type="button" role="menuitem" class="is-danger" @click="open = false; confirm = { open: true, url: @js(route('payroll.destroy', $run)), number: @js($run->run_number) }">Delete draft</button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" data-label="">Total</td>
                            <td class="pr-num pr-earn" data-label="Earnings">{{ $money($rows->sum('total_earnings')) }}</td>
                            <td class="pr-num pr-ded" data-label="Deductions">{{ $money($rows->sum('total_deductions')) }}</td>
                            <td class="pr-num" data-label="Net pay">{{ $money($rows->sum('total_net')) }}</td>
                            <td colspan="2" data-label=""></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($isPaginator && $runs->hasPages())
                <div style="padding:12px 16px">{{ $runs->links() }}</div>
            @endif
        @endif
    </section>

    {{-- Delete draft confirmation --}}
    <div class="pr-sheet-wrap" x-show="confirm.open" x-cloak role="dialog" aria-modal="true" aria-labelledby="pr-run-del-title">
        <div class="pr-scrim" @click="confirm.open = false" x-show="confirm.open" x-transition.opacity></div>
        <div class="pr-sheet" style="max-width:420px" x-show="confirm.open"
            x-transition:enter="pr-enter" x-transition:enter-start="pr-from" x-transition:enter-end="pr-to" x-transition:leave="pr-leave" x-transition:leave-start="pr-to" x-transition:leave-end="pr-from">
            <div class="pr-sheet-head">
                <div><h3 id="pr-run-del-title">Delete <span x-text="confirm.number"></span>?</h3><p>The draft and its payslips are removed. Bonuses and deductions stay waiting for the next payroll.</p></div>
            </div>
            <form method="POST" :action="confirm.url" class="pr-sheet-foot" x-data="{ busy: false }" @submit="busy = true">
                @csrf @method('DELETE')
                <button type="button" class="pr-btn" @click="confirm.open = false">Cancel</button>
                <button type="submit" class="pr-btn pr-btn-danger" :disabled="busy">Delete draft</button>
            </form>
        </div>
    </div>

    {{-- New payroll --}}
    @if ($canCreate)
        <div x-data="{ open: @js($openNew), busy: false }" @pr-new-run.window="open = true; $nextTick(() => $refs.month.focus())" @keydown.escape.window="open = false"
            x-init="if (open) $nextTick(() => $refs.month.focus())">
            <div class="pr-sheet-wrap" x-show="open" x-cloak role="dialog" aria-modal="true" aria-labelledby="pr-new-title">
                <div class="pr-scrim" @click="open = false" x-show="open" x-transition.opacity></div>
                <form method="POST" action="{{ route('payroll.store') }}" class="pr-sheet" x-show="open" @submit="busy = true"
                    x-transition:enter="pr-enter" x-transition:enter-start="pr-from" x-transition:enter-end="pr-to" x-transition:leave="pr-leave" x-transition:leave-start="pr-to" x-transition:leave-end="pr-from">
                    @csrf
                    <div class="pr-sheet-head">
                        <div><h3 id="pr-new-title">New payroll <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'lifecycle' })" aria-label="Help: how a monthly payroll goes" title="Help: how a monthly payroll goes">i</button></h3><p>Every employee with a salary gets a payslip: basic, allowances, absent days, bonuses, advances and settlement shorts are filled in by themselves.</p></div>
                        <button type="button" class="pr-x" @click="open = false" aria-label="Close">✕</button>
                    </div>
                    <div class="pr-sheet-body" style="display:grid; gap:14px">
                        <label class="pr-field"><span>Month</span>
                            <input type="month" name="month" x-ref="month" required class="pr-input" value="{{ old('month', now()->subMonthNoOverflow()->format('Y-m')) }}" max="{{ now()->format('Y-m') }}"></label>
                        <label class="pr-field"><span>Pay whom</span>
                            <select name="owner" class="pr-select" required>
                                @foreach ($owners as $owner)
                                    <option value="{{ $owner['value'] }}" @selected(old('owner', $owners->first()['value'] ?? null) === $owner['value'])>{{ $owner['label'] }}</option>
                                @endforeach
                            </select></label>
                        @if ($owners->isEmpty())
                            <p class="pr-label" style="color:var(--pr-red)">No company is linked to your account, so a payroll cannot be started.</p>
                        @endif
                    </div>
                    <div class="pr-sheet-foot">
                        <button type="button" class="pr-btn" @click="open = false">Cancel</button>
                        <button type="submit" class="pr-btn pr-btn-primary" :disabled="busy || {{ $owners->isEmpty() ? 'true' : 'false' }}"><span x-show="!busy">Build payslips</span><span x-show="busy" x-cloak>Building…</span></button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
