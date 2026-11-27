{{-- Settings -> Customers -> View (/settings/customers/{customer}). Read only; changes happen on Edit.
     Credit used and available come from the customer's account balance (debit minus credit on every salesman
     account), not the stored credit_used column, which is not kept up to date. Each salesman account opens its statement. --}}
@php
    $limit = (float) $customer->credit_limit;
    $balance = (float) $accounts->sum(fn ($account) => (float) $account->debits - (float) $account->credits);
    $used = max(0, $balance);
    $percent = $limit > 0 ? min(100, (int) round($used / $limit * 100)) : 0;
    $available = max(0, $limit - $used);
    $lastEntry = $accounts->pluck('last_entry_date')->filter()->max();
    $canStatement = auth()->user()->can('report-audit-customer-account-statement');
    $canEdit = auth()->user()->can('customer-edit');
    $accountLabel = fn ($account) => $account ? $account->account_code.' — '.$account->account_name : 'Not linked';
    $phoneLink = $customer->phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $customer->phone) : null;
    $groups = [
        'Shop' => [
            'Customer code' => $customer->customer_code,
            'Business name' => $customer->business_name,
            'Channel' => $customer->channel_type,
            'Category' => 'Category '.$customer->customer_category,
            'Sales rep' => $customer->salesRep?->name ?? 'Unassigned',
            'Payment terms' => $customer->payment_terms !== null ? $customer->payment_terms.' days' : null,
        ],
        'Contact & location' => [
            'Phone' => $customer->phone,
            'Email' => $customer->email,
            'Address' => $customer->address,
            'Area' => $customer->sub_locality,
            'City' => $customer->city,
            'State / country' => collect([$customer->state, $customer->country])->filter()->implode(', '),
        ],
        'Tax & accounting' => [
            'NTN' => $customer->ntn,
            'Owner CNIC' => $customer->owner_cnic,
            'IT status (FBR)' => $customer->it_status ? 'Filer' : 'Non-filer',
            'Receivable account' => $accountLabel($customer->receivableAccount),
            'Payable account' => $accountLabel($customer->payableAccount),
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('customers.index') }}">Customers</a><span aria-hidden="true">›</span><span>{{ $customer->customer_code }}</span>
                </nav>
                <h1 class="ak-title">Customer</h1>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('customers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Customers</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @if ($canEdit)
                    <a href="{{ route('customers.edit', $customer) }}" class="ak-btn ak-btn-primary">Edit customer</a>
                @endif
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-customer-apple-style />

    <div class="ak-page">
        <x-status-message />

        <section class="ak-card cs-hero" aria-labelledby="cs-name">
            <div class="cs-hero-top">
                <span class="cs-cap {{ $customer->is_active ? 'cs-cap-green' : 'cs-cap-red' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span>
                <span class="cs-cap cs-cap-blue">{{ $customer->channel_type }}</span>
                <span class="cs-cap cs-cap-grey">Category {{ $customer->customer_category }}</span>
            </div>
            <div>
                <h2 id="cs-name">{{ $customer->customer_name }}</h2>
                <p>
                    <span class="cs-code">{{ $customer->customer_code }}</span>
                    @if ($customer->sub_locality || $customer->city) · {{ collect([$customer->sub_locality, $customer->city])->filter()->implode(', ') }} @endif
                    @if ($phoneLink) · <a href="{{ $phoneLink }}" class="cs-link">{{ $customer->phone }}</a> @endif
                </p>
            </div>
            <div class="cs-stats">
                <div class="cs-stat">
                    <span>Balance (Rs)</span>
                    <b class="{{ $balance > 0.005 ? 'cs-due' : ($balance < -0.005 ? 'cs-advance' : '') }}">{{ number_format($balance) }}</b>
                    <small>{{ $balance > 0.005 ? 'the customer owes' : ($balance < -0.005 ? 'paid in advance' : 'nothing due') }}</small>
                </div>
                <div class="cs-stat">
                    <span>Credit limit</span>
                    <b>{{ number_format($limit) }}</b>
                    <small>{{ $limit > 0 ? $percent.'% used' : 'no limit set' }}</small>
                </div>
                <div class="cs-stat">
                    <span>Credit available</span>
                    <b>{{ number_format($available) }}</b>
                    <small>can still be sold on credit</small>
                </div>
                <div class="cs-stat">
                    <span>Last entry</span>
                    <b>{{ $lastEntry ? \Carbon\Carbon::parse($lastEntry)->format('d M Y') : '—' }}</b>
                    <small>{{ $lastEntry ? \Carbon\Carbon::parse($lastEntry)->diffForHumans() : 'no sale or recovery yet' }}</small>
                </div>
            </div>
        </section>

        <section class="ak-card cs-section" aria-labelledby="cs-accounts-title">
            <h3 id="cs-accounts-title">
                <span>Accounts with salesmen <span class="ak-muted" style="font-weight:400">· {{ $accounts->count() }}</span></span>
                @if ($canStatement && $accounts->isNotEmpty())
                    <a href="{{ route('reports.customer-account-statement.index', ['filter' => ['customer_id' => $customer->id]]) }}" class="cs-link">All statements <span aria-hidden="true">→</span></a>
                @endif
            </h3>
            @if ($accounts->isEmpty())
                <p class="ak-muted" style="margin:0; font-size:14px">No salesman has sold to this customer on credit yet.</p>
            @else
                <div class="cs-accounts">
                    @foreach ($accounts as $account)
                        @php $accountBalance = (float) $account->debits - (float) $account->credits; @endphp
                        @if ($canStatement)
                            <a href="{{ route('reports.customer-account-statement.show', $account) }}" class="cs-account" aria-label="Statement of {{ $account->account_number }} with {{ $account->employee?->name }}">
                        @else
                            <div class="cs-account">
                        @endif
                            <span class="cs-account-top"><span class="cs-code">{{ $account->account_number }}</span><span>{{ $account->employee?->supplier?->short_name ?: $account->employee?->supplier?->supplier_name }}</span></span>
                            <b>{{ $account->employee?->name ?? 'Unknown salesman' }}</b>
                            <span class="cs-account-bal {{ $accountBalance > 0.005 ? 'cs-due' : ($accountBalance < -0.005 ? 'cs-advance' : '') }}">Rs {{ number_format($accountBalance) }}</span>
                            <span class="ak-muted" style="font-size:12.5px">{{ $account->last_entry_date ? 'last entry '.\Carbon\Carbon::parse($account->last_entry_date)->format('d M Y') : 'no entries' }} · {{ ucfirst($account->status) }}</span>
                            @if ($canStatement)<span class="cs-account-go">Open statement <span aria-hidden="true">→</span></span>@endif
                        @if ($canStatement)
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        <div class="cs-grid cs-grid-3">
            @foreach ($groups as $title => $rows)
                <section class="ak-card cs-section" aria-label="{{ $title }}">
                    <h3>{{ $title }}</h3>
                    <dl class="cs-facts">
                        @foreach ($rows as $label => $value)
                            <dt>{{ $label }}</dt>
                            <dd>
                                @if ($label === 'Phone' && $phoneLink)
                                    <a href="{{ $phoneLink }}" class="cs-link">{{ $value }}</a>
                                @elseif ($label === 'Email' && filled($value))
                                    <a href="mailto:{{ $value }}" class="cs-link">{{ $value }}</a>
                                @else
                                    {{ filled($value) ? $value : '—' }}
                                @endif
                            </dd>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>

        @if (filled($customer->notes))
            <section class="ak-card cs-section">
                <h3>Internal notes</h3>
                <p style="margin:0; font-size:14px; white-space:pre-line">{{ $customer->notes }}</p>
            </section>
        @endif
    </div>
</x-app-layout>
