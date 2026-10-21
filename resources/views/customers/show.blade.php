{{-- Settings -> Customers -> View (/settings/customers/{customer}). Read only; changes happen on Edit. --}}
@php
    $limit = (float) $customer->credit_limit;
    $used = (float) $customer->credit_used;
    $percent = $limit > 0 ? min(100, round($used / $limit * 100)) : 0;
    $accountLabel = fn ($account) => $account ? $account->account_code.' — '.$account->account_name : 'Not linked';
    $groups = [
        'Shop details' => [
            'Customer code' => $customer->customer_code,
            'Customer name' => $customer->customer_name,
            'Business name' => $customer->business_name,
            'Sales rep' => $customer->salesRep?->name ?? 'Unassigned',
            'Channel' => $customer->channel_type,
            'Category' => 'Category '.$customer->customer_category,
        ],
        'Contact & location' => [
            'Phone' => $customer->phone,
            'Email' => $customer->email,
            'Address' => $customer->address,
            'Area / sub locality' => $customer->sub_locality,
            'City' => $customer->city,
            'State / country' => collect([$customer->state, $customer->country])->filter()->implode(', '),
        ],
        'Tax & accounting' => [
            'NTN' => $customer->ntn,
            'Owner CNIC' => $customer->owner_cnic,
            'IT status (FBR)' => $customer->it_status ? 'Registered' : 'Not registered',
            'Payment terms' => $customer->payment_terms !== null ? $customer->payment_terms.' days' : null,
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
                <h1 class="ak-title">{{ $customer->customer_name }}</h1>
                <p class="ak-sub">
                    {{ $customer->customer_code }} · {{ $customer->channel_type }}
                    @if ($customer->city) · {{ $customer->sub_locality ? $customer->sub_locality.', ' : '' }}{{ $customer->city }} @endif
                    &middot;
                    @if ($customer->is_active)
                        <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                    @else
                        <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Inactive</span>
                    @endif
                </p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('customers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @can('customer-edit')
                    <a href="{{ route('customers.edit', $customer) }}" class="ak-btn ak-btn-primary">Edit customer</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />

        <section class="ak-kpis" aria-label="Credit summary" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
            <div class="ak-kpi"><span class="ak-kpi-body">
                <span class="ak-kpi-label">Credit limit</span>
                <span class="ak-kpi-value">{{ number_format($limit, 0) }}</span>
                <span class="ak-kpi-hint">{{ $percent }}% used</span>
            </span></div>
            <div class="ak-kpi"><span class="ak-kpi-body">
                <span class="ak-kpi-label">Credit used</span>
                <span class="ak-kpi-value">{{ number_format($used, 0) }}</span>
                <span class="ak-kpi-hint">of the limit</span>
            </span></div>
            <div class="ak-kpi"><span class="ak-kpi-body">
                <span class="ak-kpi-label">Credit available</span>
                <span class="ak-kpi-value">{{ number_format($customer->getAvailableCredit(), 0) }}</span>
                <span class="ak-kpi-hint">can still be sold on credit</span>
            </span></div>
            <div class="ak-kpi"><span class="ak-kpi-body">
                <span class="ak-kpi-label">Receivable balance</span>
                <span class="ak-kpi-value">{{ number_format((float) $customer->receivable_balance, 0) }}</span>
                <span class="ak-kpi-hint">last sale {{ $customer->last_sale_date?->format('d M Y') ?? '—' }}</span>
            </span></div>
        </section>

        <div class="uf-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr))">
            @foreach ($groups as $title => $rows)
                <section class="uf-card">
                    <div class="uf-card-head"><h2 class="uf-card-title">{{ $title }}</h2></div>
                    <dl style="margin:0; padding:8px 20px 16px">
                        @foreach ($rows as $label => $value)
                            <div style="display:flex; justify-content:space-between; gap:16px; padding:9px 0; border-bottom:1px solid var(--ak-line); font-size:14px">
                                <dt class="ak-muted" style="flex:none">{{ $label }}</dt>
                                <dd style="margin:0; text-align:right; word-break:break-word; color:var(--ak-text)">{{ filled($value) ? $value : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>

        @if (filled($customer->notes))
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Internal notes</h2></div>
                <p style="margin:0; padding:16px 20px; font-size:14px; white-space:pre-line">{{ $customer->notes }}</p>
            </section>
        @endif
    </div>
</x-app-layout>
