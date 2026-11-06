@php
    $isMaker = (int) $run->created_by === auth()->id();
    $isSubmitter = (int) $run->submitted_by === auth()->id();
    $tone = ['draft' => 'ak-status-slate', 'pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'paid' => 'ak-status-green', 'rejected' => 'ak-status-red'];
    $step = ['draft' => 1, 'rejected' => 1, 'pending' => 2, 'approved' => 3, 'paid' => 4][$run->status] ?? 1;
    $editable = $run->isEditable() && $isMaker;
    $slips = $run->slips->sortBy(fn ($slip) => $slip->employee?->name);
    $fmt = fn ($value) => number_format((float) $value, 2);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('payroll.index') }}">Payroll</a><span aria-hidden="true">›</span><span>{{ $run->run_number }}</span></nav>
                <h1 class="ak-title" style="margin:0">{{ $run->period_month->format('F Y') }} · {{ $run->ownerLabel() }}</h1>
                <p class="ak-sub">{{ $run->run_number }} · <span class="ak-status {{ $tone[$run->status] ?? '' }}"><i aria-hidden="true"></i>{{ $run->statusLabel() }}</span></p>
            </div>
            <div class="ak-head-actions no-print">
                <a href="{{ route('payroll.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('payroll.help') }}" class="ak-btn ak-btn-outline">How it works</a>
                <a href="{{ route('payroll.print', $run) }}" target="_blank" class="ak-btn ak-btn-outline">Print all slips</a>
            </div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')
    <style>
        .pr-steps { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:0; padding:0; list-style:none; } .pr-steps li { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--ak-muted); } .pr-steps li + li::before { content:''; width:28px; height:2px; background:var(--ak-border); }
        .pr-steps .dot { width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; border:2px solid var(--ak-border); background:#fff; font-size:12px; } .pr-steps li.is-done { color:#15803d; } .pr-steps li.is-done .dot { background:#16a34a; border-color:#16a34a; color:#fff; } .pr-steps li.is-now { color:var(--ak-navy); } .pr-steps li.is-now .dot { border-color:var(--ak-navy); color:var(--ak-navy); }
        .pr-kpis { display:grid; gap:14px; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); } .pr-kpi { padding:14px 18px; } .pr-kpi small { display:block; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--ak-muted); } .pr-kpi b { display:block; margin-top:3px; font-size:22px; font-variant-numeric:tabular-nums; }
        .pr-t { width:100%; border-collapse:collapse; font-size:13.5px; } .pr-t th { text-align:left; background:#f8fafc; padding:9px 12px; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--ak-muted); border-bottom:1px solid var(--ak-border); } .pr-t td { padding:9px 12px; border-bottom:1px solid var(--ak-line); vertical-align:top; } .pr-t .n { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .pr-t .earn { color:#166534; } .pr-t .ded { color:#b91c1c; } .pr-t tfoot td { font-weight:800; background:#f8fafc; border-top:2px solid var(--ak-border); } .pr-detail td { background:#f8fafc; padding:14px 18px; }
        .pr-cols { display:grid; gap:18px; grid-template-columns:1fr; } @media (min-width:900px){ .pr-cols { grid-template-columns:1fr 1fr; } }
        .pr-col h4 { margin:0 0 6px; font-size:12px; text-transform:uppercase; letter-spacing:.04em; } .pr-col ul { margin:0; padding:0; list-style:none; } .pr-col li { display:flex; justify-content:space-between; gap:10px; padding:5px 0; border-bottom:1px dashed var(--ak-border); font-size:13.5px; }
        .pr-mini { display:flex; flex-wrap:wrap; gap:8px; align-items:end; margin-top:12px; } .pr-mini input, .pr-mini select { height:36px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px; font-size:13.5px; } .pr-x { border:0; background:none; color:#b91c1c; cursor:pointer; font-size:16px; line-height:1; padding:0 4px; }
    </style>
    <div class="ak-page" x-data="{ dialog: null, openSlip: null }" @keydown.escape.window="dialog = null">
        <x-status-message />

        <section class="ak-card" style="padding:16px 20px">
            <ol class="pr-steps">
                <li class="{{ $step > 1 ? 'is-done' : 'is-now' }}"><span class="dot">{{ $step > 1 ? '✓' : '1' }}</span> Prepared by {{ $run->creator?->name }}</li>
                <li class="{{ $step > 2 ? 'is-done' : ($step === 2 ? 'is-now' : '') }}"><span class="dot">{{ $step > 2 ? '✓' : '2' }}</span> Checker approves</li>
                <li class="{{ $step > 3 ? 'is-done' : ($step === 3 ? 'is-now' : '') }}"><span class="dot">{{ $step > 3 ? '✓' : '3' }}</span> Posted to the ledger</li>
                <li class="{{ $step >= 4 ? 'is-done' : '' }}"><span class="dot">{{ $step >= 4 ? '✓' : '4' }}</span> Salaries paid{{ $run->paid_on ? ' on '.$run->paid_on->format('d M Y') : '' }}</li>
            </ol>
            @if($run->status === 'rejected' && $run->rejection_reason)<div class="ak-alert ak-alert-error" style="margin-top:12px"><b>Rejected by {{ $run->checker?->name }}:</b> {{ $run->rejection_reason }}</div>@endif
        </section>

        <div class="pr-kpis">
            <div class="ak-card pr-kpi"><small>Employees</small><b>{{ $slips->count() }}</b></div>
            <div class="ak-card pr-kpi"><small>Total earnings</small><b class="earn" style="color:#166534">{{ $fmt($run->total_earnings) }}</b></div>
            <div class="ak-card pr-kpi"><small>Total deductions</small><b style="color:#b91c1c">{{ $fmt($run->total_deductions) }}</b></div>
            <div class="ak-card pr-kpi"><small>Net to pay</small><b>{{ $fmt($run->total_net) }}</b></div>
        </div>

        @if($editable && $missing->isNotEmpty())
            <div style="background:#eef2ff; border:1px solid #c7d2fe; color:#312e81; padding:10px 14px; border-radius:10px; font-size:14px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between">
                <span><b>{{ $missing->count() }}</b> {{ \Illuminate\Support\Str::plural('employee', $missing->count()) }} can be paid but {{ $missing->count() === 1 ? 'is' : 'are' }} not in this payroll yet: {{ $missing->take(5)->pluck('name')->join(', ') }}{{ $missing->count() > 5 ? ' and '.($missing->count() - 5).' more' : '' }}.</span>
                <form method="POST" action="{{ route('payroll.add-employees', $run) }}">@csrf<button class="ak-btn ak-btn-primary" style="padding:6px 14px">Add them to this payroll</button></form>
            </div>
        @endif

        @if($editable && $withoutSalary->isNotEmpty())
            <div class="ak-alert ak-alert-warning" style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:10px 14px; border-radius:10px; font-size:14px">
                {{ $withoutSalary->count() }} active {{ \Illuminate\Support\Str::plural('employee', $withoutSalary->count()) }} of this company {{ $withoutSalary->count() === 1 ? 'has' : 'have' }} no salary set and {{ $withoutSalary->count() === 1 ? 'is' : 'are' }} not in this run:
                {{ $withoutSalary->take(6)->pluck('name')->join(', ') }}{{ $withoutSalary->count() > 6 ? ' and '.($withoutSalary->count() - 6).' more' : '' }}.
                @can('payroll-salary-manage')<a href="{{ route('payroll.salaries.index', ['filter' => ['salary' => 'missing']]) }}" style="text-decoration:underline; font-weight:600">Set their salaries</a>, then recalculate.@endcan
            </div>
        @endif

        <section class="ak-card" style="overflow-x:auto">
            <table class="pr-t" style="min-width:760px">
                <thead><tr><th>Employee</th><th class="n">Absent</th><th class="n">Earnings</th><th class="n">Deductions</th><th class="n">Net pay</th><th></th></tr></thead>
                <tbody>
                    @foreach($slips as $slip)
                        <tr>
                            <td><b>{{ $slip->employee?->name }}</b><div class="ak-muted">{{ $slip->employee?->employee_code }}{{ $slip->employee?->designation ? ' · '.$slip->employee->designation : '' }}</div></td>
                            <td class="n">{{ (float) $slip->absent_days > 0 ? rtrim(rtrim(number_format((float) $slip->absent_days, 2), '0'), '.') : '—' }}</td>
                            <td class="n earn">{{ $fmt($slip->total_earnings) }}</td>
                            <td class="n ded">{{ (float) $slip->total_deductions > 0 ? $fmt($slip->total_deductions) : '—' }}</td>
                            <td class="n"><b style="{{ (float) $slip->net_pay < 0 ? 'color:#b91c1c' : '' }}">{{ $fmt($slip->net_pay) }}</b></td>
                            <td class="n"><button type="button" class="ak-btn ak-btn-outline" style="padding:4px 12px" @click="openSlip = openSlip === {{ $slip->id }} ? null : {{ $slip->id }}"><span x-text="openSlip === {{ $slip->id }} ? 'Close' : '{{ $editable ? 'Open / adjust' : 'Open' }}'"></span></button></td>
                        </tr>
                        <tr class="pr-detail" x-show="openSlip === {{ $slip->id }}" x-cloak style="display:none">
                            <td colspan="6">
                                <div class="pr-cols">
                                    <div class="pr-col"><h4 style="color:#166534">Earnings</h4><ul>
                                        @foreach($slip->lines->where('kind', 'earning') as $line)
                                            <li><span>{{ $line->label }}@if($line->source === 'manual') <span class="ak-muted">(added)</span>@endif</span><span>{{ $fmt($line->amount) }}@if($editable && $line->source === 'manual')<form method="POST" action="{{ route('payroll.lines.destroy', [$run, $line]) }}" style="display:inline">@csrf @method('DELETE')<button class="pr-x" title="Remove" aria-label="Remove {{ $line->label }}">×</button></form>@endif</span></li>
                                        @endforeach
                                        <li style="font-weight:800; border-bottom:0"><span>Total earnings</span><span>{{ $fmt($slip->total_earnings) }}</span></li></ul></div>
                                    <div class="pr-col"><h4 style="color:#b91c1c">Deductions</h4><ul>
                                        @forelse($slip->lines->where('kind', 'deduction') as $line)
                                            <li><span>{{ $line->label }}@if($line->source === 'manual') <span class="ak-muted">(added)</span>@endif</span><span>{{ $fmt($line->amount) }}@if($editable && $line->source === 'manual')<form method="POST" action="{{ route('payroll.lines.destroy', [$run, $line]) }}" style="display:inline">@csrf @method('DELETE')<button class="pr-x" title="Remove" aria-label="Remove {{ $line->label }}">×</button></form>@endif</span></li>
                                        @empty
                                            <li><span class="ak-muted">Nothing deducted</span><span></span></li>
                                        @endforelse
                                        <li style="font-weight:800; border-bottom:0"><span>Total deductions</span><span>{{ $fmt($slip->total_deductions) }}</span></li></ul></div>
                                </div>
                                @if($editable)
                                    <div class="pr-mini">
                                        @if($slip->salary_type === 'basic')
                                            <form method="POST" action="{{ route('payroll.slips.update', [$run, $slip]) }}" class="pr-mini" style="margin:0">@csrf @method('PUT')
                                                <label style="font-size:12px">Absent days<br><input type="number" step="0.5" min="0" max="31" name="absent_days" value="{{ (float) $slip->absent_days }}" style="width:90px"></label>
                                                <button class="ak-btn ak-btn-outline" style="height:36px">Save</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('payroll.lines.store', [$run, $slip]) }}" class="pr-mini" style="margin:0">@csrf
                                            <label style="font-size:12px">Add this month<br><select name="salary_component_id" required><option value="">Choose…</option>@foreach($components as $component)<option value="{{ $component->id }}">{{ $component->type === 'earning' ? '＋' : '－' }} {{ $component->name }}</option>@endforeach</select></label>
                                            <label style="font-size:12px">Amount<br><input type="number" step="0.01" min="0.01" name="amount" required style="width:120px"></label>
                                            <label style="font-size:12px">Note (optional)<br><input type="text" name="label" maxlength="160" style="width:180px"></label>
                                            <button class="ak-btn ak-btn-primary" style="height:36px">Add</button>
                                        </form>
                                    </div>
                                @endif
                                <div style="margin-top:10px"><a href="{{ route('payroll.slips.show', [$run, $slip]) }}" target="_blank" class="ak-primary-link">Open printable slip →</a></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr><td colspan="2">Total</td><td class="n">{{ $fmt($run->total_earnings) }}</td><td class="n">{{ $fmt($run->total_deductions) }}</td><td class="n">{{ $fmt($run->total_net) }}</td><td></td></tr></tfoot>
            </table>
        </section>

        <section class="ak-card no-print" style="padding:16px 20px; display:flex; flex-wrap:wrap; gap:10px; align-items:center">
            @if($run->isEditable())
                @if($isMaker)
                    @can('payroll-create')
                        <form method="POST" action="{{ route('payroll.recalculate', $run) }}">@csrf<button class="ak-btn ak-btn-outline" title="Rebuild every slip from the latest salary set-up and advances. Lines you added by hand are kept.">Recalculate all</button></form>
                        <form method="POST" action="{{ route('payroll.submit', $run) }}">@csrf<button class="ak-btn ak-btn-primary">Send for approval</button></form>
                        <button type="button" class="ak-btn ak-btn-danger-outline" @click="dialog = 'delete'">Delete payroll</button>
                    @endcan
                @else
                    <p class="ak-muted" style="margin:0">Only {{ $run->creator?->name }}, who prepared this payroll, can change it.</p>
                @endif
            @endif
            @if($run->status === 'pending')
                @can('payroll-approve')
                    @if($isSubmitter)
                        <p class="ak-muted" style="margin:0">You sent this payroll for approval, so another user has to check it.</p>
                    @else
                        <button type="button" class="ak-btn ak-btn-success" @click="dialog = 'approve'">Approve and post</button>
                        <button type="button" class="ak-btn ak-btn-danger-outline" @click="dialog = 'reject'">Reject</button>
                    @endif
                @else
                    <p class="ak-muted" style="margin:0">Waiting for a checker.</p>
                @endcan
            @endif
            @if($run->status === 'approved')
                @can('payroll-pay')<button type="button" class="ak-btn ak-btn-primary" @click="dialog = 'pay'">Mark salaries as paid</button>@else<p class="ak-muted" style="margin:0">Approved. Waiting for the salaries to be paid.</p>@endcan
            @endif
            @if($run->status === 'paid')
                <p style="margin:0">Paid by {{ $run->paid_method === 'bank' ? 'bank ('.($run->paidBankAccount?->bank_name).' '.($run->paidBankAccount?->account_number).')' : 'cash' }} on {{ $run->paid_on?->format('d M Y') }}.</p>
            @endif
        </section>

        @php
            $dialogs = [
                'approve' => ['Approve and post this payroll?', 'Salary of '.$run->period_month->format('F Y').' for '.$slips->count().' employees is posted to the ledger (net Rs '.$fmt($run->total_net).') and every advance recovery is recorded. A posted payroll cannot be changed.', route('payroll.approve', $run), 'Approve and post', 'ak-btn-success'],
                'reject' => ['Reject this payroll?', 'It goes back to '.($run->creator?->name ?? 'the maker').' with your reason. Nothing is posted.', route('payroll.reject', $run), 'Reject payroll', 'ak-btn-danger-outline'],
                'delete' => ['Delete this payroll?', 'The slips are removed. Nothing has been posted, so you can build the month again.', route('payroll.destroy', $run), 'Delete payroll', 'ak-btn-danger-outline'],
            ];
        @endphp
        @foreach($dialogs as $key => [$title, $text, $url, $button, $class])
            <div class="uf-modal" x-show="dialog === '{{ $key }}'" x-cloak style="display:none" role="dialog" aria-modal="true">
                <div class="uf-modal-bg" x-show="dialog === '{{ $key }}'" x-transition.opacity @click="dialog = null"></div>
                <div class="uf-modal-box" x-show="dialog === '{{ $key }}'" x-transition>
                    <form method="POST" action="{{ $url }}">@csrf @if($key === 'delete') @method('DELETE') @endif
                        <div class="uf-modal-body" style="display:block"><h3>{{ $title }}</h3><p style="margin:8px 0 0; font-size:14px; color:#334155">{{ $text }}</p>
                            @if($key === 'reject')<label for="pr-reason" style="display:block; margin-top:14px; font-size:13px; font-weight:600">Reason</label><textarea id="pr-reason" name="rejection_reason" required maxlength="500" rows="3" style="margin-top:6px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:8px 12px"></textarea>@endif
                        </div>
                        <div class="uf-modal-foot"><button type="button" class="ak-btn ak-btn-outline" @click="dialog = null">Cancel</button><button class="ak-btn {{ $class }}">{{ $button }}</button></div>
                    </form>
                </div>
            </div>
        @endforeach
        <div class="uf-modal" x-show="dialog === 'pay'" x-cloak style="display:none" role="dialog" aria-modal="true" x-data="{ method: 'cash' }">
            <div class="uf-modal-bg" x-show="dialog === 'pay'" x-transition.opacity @click="dialog = null"></div>
            <div class="uf-modal-box" x-show="dialog === 'pay'" x-transition>
                <form method="POST" action="{{ route('payroll.pay', $run) }}">@csrf
                    <div class="uf-modal-body" style="display:block"><h3>Pay the salaries</h3><p style="margin:8px 0 12px; font-size:14px; color:#334155">Rs {{ $fmt($run->total_net) }} is paid out of {{ $slips->count() }} employees' payable salary.</p>
                        <div class="ev-seg"><label :class="{ 'is-on': method === 'cash' }"><input type="radio" name="method" value="cash" x-model="method"> Cash</label><label :class="{ 'is-on': method === 'bank' }"><input type="radio" name="method" value="bank" x-model="method"> Bank</label></div>
                        <div class="ak-field" style="margin-top:12px" x-show="method === 'bank'" x-cloak><label for="pay-bank" style="font-size:13px; font-weight:600">Bank account</label><select id="pay-bank" name="bank_account_id" :required="method === 'bank'" style="width:100%; height:40px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="">Select account</option>@foreach($bankAccounts as $bank)<option value="{{ $bank->id }}">{{ $bank->bank_name }} - {{ $bank->account_number }}</option>@endforeach</select></div>
                        <div class="ak-field" style="margin-top:12px"><label for="pay-date" style="font-size:13px; font-weight:600">Paid on</label><input id="pay-date" type="date" name="paid_on" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" style="width:100%; height:40px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
                    </div>
                    <div class="uf-modal-foot"><button type="button" class="ak-btn ak-btn-outline" @click="dialog = null">Cancel</button><button class="ak-btn ak-btn-primary">Mark as paid</button></div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
