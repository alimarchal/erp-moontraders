{{--
    Quick look at one shop, loaded into the Shop Directory pop-up: status, credit, contact and tax details, and each
    salesman account with its balance. Links open the customer, its statements and the edit form.
--}}
@php
    $balance = (float) $accounts->sum(fn ($account) => (float) $account->debits - (float) $account->credits);
    $limit = (float) $customer->credit_limit;
    $lastSale = $accounts->pluck('last_sale')->filter()->max();
    $lastRecovery = $accounts->pluck('last_recovery')->filter()->max();
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d M Y') : '—';
    $ago = fn ($value) => $value ? \Carbon\Carbon::parse($value)->diffForHumans() : 'never';
    $phone = $customer->phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $customer->phone) : null;
    $tone = fn (float $amount) => $amount > 0.004 ? 'cs-due' : ($amount < -0.004 ? 'cs-advance' : '');
    $facts = [
        'Code' => $customer->customer_code,
        'Business name' => $customer->business_name,
        'Channel' => $customer->channel_type.' · Category '.$customer->customer_category,
        'Sales rep' => $customer->salesRep?->name,
        'Address' => $customer->address,
        'Area' => collect([$customer->sub_locality, $customer->city])->filter()->implode(', '),
        'Owner CNIC' => $customer->owner_cnic,
        'NTN' => $customer->ntn,
        'Filer (FBR)' => $customer->it_status ? 'Filer' : 'Non-filer',
        'Payment terms' => $customer->payment_terms !== null ? $customer->payment_terms.' days' : null,
        'Email' => $customer->email,
    ];
@endphp
<div class="sl-peek" data-sl-title="{{ $customer->customer_name }}">
    <div class="cs-hero-top">
        <span class="cs-cap {{ $customer->is_active ? 'cs-cap-green' : 'cs-cap-red' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span>
        <span class="cs-cap {{ $customer->it_status ? 'cs-cap-blue' : 'cs-cap-grey' }}">{{ $customer->it_status ? 'Filer' : 'Non-filer' }}</span>
        @if ($limit > 0 && $balance > $limit)<span class="cs-cap cs-cap-red">Over credit limit</span>@endif
    </div>
    <p class="sl-peek-sub"><span class="cs-code">{{ $customer->customer_code }}</span>@if ($phone) · <a href="{{ $phone }}" class="cs-link">{{ $customer->phone }}</a>@endif</p>

    <div class="cs-stats">
        <div class="cs-stat"><span>Balance (Rs)</span><b class="{{ $tone($balance) }}">{{ number_format($balance) }}</b><small>{{ $balance > 0.004 ? 'the shop owes' : ($balance < -0.004 ? 'paid in advance' : 'nothing due') }}</small></div>
        <div class="cs-stat"><span>Credit limit</span><b>{{ $limit > 0 ? number_format($limit) : '—' }}</b><small>{{ $limit > 0 ? number_format(max(0, $limit - max(0, $balance))).' still available' : 'no limit set' }}</small></div>
        <div class="cs-stat"><span>Last sale</span><b>{{ $date($lastSale) }}</b><small>{{ $ago($lastSale) }}</small></div>
        <div class="cs-stat"><span>Last recovery</span><b>{{ $date($lastRecovery) }}</b><small>{{ $ago($lastRecovery) }}</small></div>
    </div>

    <div class="sl-peek-links">
        @can('customer-list')<a href="{{ route('customers.show', $customer) }}" class="ak-btn ak-btn-outline ak-btn-sm">Open customer</a>@endcan
        @can('report-audit-customer-account-statement')@if ($accounts->isNotEmpty())<a href="{{ route('reports.customer-account-statement.index', ['filter' => ['customer_id' => $customer->id]]) }}" class="ak-btn ak-btn-outline ak-btn-sm">Account statements</a>@endif @endcan
        @can('customer-edit')<a href="{{ route('customers.edit', $customer) }}" class="ak-btn ak-btn-outline ak-btn-sm">Edit</a>@endcan
    </div>

    <section>
        <h4 class="sl-peek-h">Salesman accounts <span class="ak-muted">· {{ $accounts->count() }}</span></h4>
        @forelse ($accounts as $account)
            @php $accountBalance = (float) $account->debits - (float) $account->credits; @endphp
            <div class="sl-peek-acc">
                <div>
                    <b>{{ $account->employee?->name ?? 'Unknown salesman' }}</b>
                    <span class="ak-muted">{{ $account->employee?->supplier?->short_name ?: $account->employee?->supplier?->supplier_name }} · <span class="cs-code">{{ $account->account_number }}</span> · last sale {{ $date($account->last_sale) }}</span>
                </div>
                @can('report-audit-customer-account-statement')
                    <a href="{{ route('reports.customer-account-statement.show', $account) }}" class="cs-amount-link {{ $tone($accountBalance) }}" title="Open the statement">{{ number_format($accountBalance) }}</a>
                @else
                    <b class="{{ $tone($accountBalance) }}">{{ number_format($accountBalance) }}</b>
                @endcan
            </div>
        @empty
            <p class="ak-muted" style="margin:0; font-size:14px">No salesman has sold to this shop on credit yet.</p>
        @endforelse
    </section>

    <section>
        <h4 class="sl-peek-h">Details</h4>
        <dl class="cs-facts">
            @foreach ($facts as $label => $value)
                <dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd>
            @endforeach
        </dl>
    </section>
</div>
