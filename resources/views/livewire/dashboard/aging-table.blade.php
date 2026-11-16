{{-- Late credit: aging creditors with filters. Shared by the Classic and Focus dashboards; expects the helpers both set up ($rs, $n, $k, $scope, $creditAging, $agingCustomers, $bucketNames, $agingSalesmen, $canCreditors, $customerStatement, $agingReportUrl, $creditUrl, $hasCredit). --}}
        @if ($hasCredit && $agingCustomers)
            @php
                $agingRows = collect($agingCustomers)->map(fn ($cust) => ['in' => $cust['in'], 'salesmen' => $cust['salesmen'], 'hay' => mb_strtolower($cust['name'].' '.$cust['code'].' '.$cust['city'])])->all();
            @endphp
            <section class="db-card db-aging" id="db-aging-list" aria-label="Late credit, aging creditors" style="--accent: var(--s6); padding: 0; overflow: hidden"
                x-data="{
                    aging: 'late', q: '', salesman: '', rows: {{ \Illuminate\Support\Js::from($agingRows) }},
                    inAging(bs) { return this.aging === 'all' ? true : (this.aging === 'late' ? bs.some(b => b >= 2) : bs.includes(this.aging)); },
                    partOf(parts) { return this.aging === 'all' ? parts.slice(1).reduce((a, b) => a + b, 0) : (this.aging === 'late' ? parts[2] + parts[3] : (parts[this.aging] ?? 0)); },
                    show(row) { return this.inAging(row.in) && (this.salesman === '' || row.salesmen === this.salesman) && (this.q === '' || row.hay.includes(this.q.toLowerCase())); },
                    get noMatch() { return ! this.rows.some(row => this.show(row)); },
                }"
                @aging-bucket.window="aging = $event.detail; $el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                <div class="db-card-head" style="padding: 14px 16px 6px; flex-wrap: wrap; gap: 10px">
                    <div>
                        <h3>Late credit: aging creditors</h3>
                        <p class="db-card-sub">Customers with unpaid credit older than 30 days, biggest first. Payments clear the oldest sales first; "Days" is the age of the oldest unpaid sale.</p>
                    </div>
                    <div class="db-filters" aria-label="Filter the list">
                        <div class="db-aging-chips">
                            <button type="button" @click="aging = 'late'" :class="aging === 'late' && 'is-on'">60+ days <b>{{ $n($k['creditOverdueAccounts'] ?? 0) }}</b></button>
                            @foreach ($bucketNames as $i => $label)
                                <button type="button" @click="aging = {{ $i }}" :class="aging === {{ $i }} && 'is-on'">{{ $label }} <b>{{ $n($creditAging['counts'][$i] ?? 0) }}</b></button>
                            @endforeach
                            <button type="button" @click="aging = 'all'" :class="aging === 'all' && 'is-on'">All late</button>
                        </div>
                        <select x-model="salesman" aria-label="Salesman">
                            <option value="">All salesmen</option>
                            @foreach ($agingSalesmen as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
                        </select>
                        <input type="search" x-model="q" placeholder="Search customer or city" aria-label="Search customer or city">
                    </div>
                </div>
                <div class="db-aging-scroll">
                    <table class="ak-dt db-aging-table">
                        <thead>
                            <tr>
                                <th scope="col">Customer</th>
                                <th scope="col">{{ $scope['supplier_id'] === null ? 'Company · salesman' : 'Salesman' }}</th>
                                <th scope="col" class="ak-num">Owes (Rs)</th>
                                <th scope="col" class="ak-num">In this range</th>
                                <th scope="col" class="ak-c">Oldest unpaid sale</th>
                                <th scope="col" class="ak-c">Last paid</th>
                                <th scope="col" class="ak-c">Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agingCustomers as $cust)
                                <tr x-show="show(rows[{{ $loop->index }}])">
                                    <td data-label="Customer">
                                        @if ($canCreditors)
                                            <a href="{{ $customerStatement($cust['id'], $cust['employee_id']) }}" class="ak-primary-link" title="Open {{ $cust['salesmen'] }}'s ledger for this customer">{{ $cust['name'] }}</a>
                                        @else
                                            <span class="ak-strong">{{ $cust['name'] }}</span>
                                        @endif
                                        <div class="ak-muted">{{ $cust['code'] }}{{ $cust['city'] ? ' · '.$cust['city'] : '' }}@if ($canCreditors) · <a href="{{ $customerStatement($cust['id']) }}" title="All salesmen for this customer">full statement</a>@endif</div>
                                    </td>
                                    <td data-label="{{ $scope['supplier_id'] === null ? 'Company · salesman' : 'Salesman' }}">
                                        @if ($scope['supplier_id'] === null)<div>{{ $cust['suppliers'] ?: '—' }}</div>@endif
                                        <div class="{{ $scope['supplier_id'] === null ? 'ak-muted' : '' }}">{{ $cust['salesmen'] ?: '—' }}</div>
                                    </td>
                                    <td class="ak-num ak-strong" data-label="Owes (Rs)">{{ number_format($cust['used']) }}</td>
                                    <td class="ak-num" data-label="In this range" x-text="Math.round(partOf({{ \Illuminate\Support\Js::from($cust['buckets']) }})).toLocaleString('en-PK')"></td>
                                    <td class="ak-c" data-label="Oldest unpaid sale">{{ $cust['oldest_unpaid'] ? \Carbon\Carbon::parse($cust['oldest_unpaid'])->format('d M Y') : '—' }}</td>
                                    <td class="ak-c" data-label="Last paid">{{ $cust['last_paid_days'] === null ? 'Never' : now()->subDays($cust['last_paid_days'])->format('d M Y') }}</td>
                                    <td class="ak-c" data-label="Days"><span class="ak-status {{ $cust['bucket'] >= 3 ? 'ak-status-red' : 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ $cust['days'] }}</span></td>
                                </tr>
                            @endforeach
                            <tr x-show="noMatch" x-cloak class="db-aging-none">
                                <td colspan="7" data-label="">No customers match these filters. Try another age range, salesman or search.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="ak-muted" style="padding: 8px 16px 10px; margin: 0; font-size: 12px">
                    Totals: 31-60 days Rs {{ $rs($creditAging['values'][1] ?? 0) }} &middot; 61-90 days Rs {{ $rs($creditAging['values'][2] ?? 0) }} &middot; over 90 days Rs {{ $rs($creditAging['values'][3] ?? 0) }}
                    @if (count($agingCustomers) >= 300) &middot; showing the 300 biggest @endif
                    @if ($agingReportUrl) &middot; <a href="{{ $agingReportUrl }}">Aging report →</a>@endif
                    @if ($creditUrl) &middot; <a href="{{ $creditUrl }}">Creditors ledger →</a>@endif
                </p>
            </section>
        @endif
