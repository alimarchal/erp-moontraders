<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><span>Shops</span></nav>
                <h1 class="ak-title">Shops <span class="ev-ur" lang="ur" dir="rtl">دکانیں / پارٹیاں</span></h1>
                <p class="ak-sub">Everyone Moon Traders pays for daily expenses, with what is still owed to each.</p>
            </div>
            <div class="ak-head-actions">
                @can('expense-vendor-payment-list')<a href="{{ route('expense-vendor-payments.index') }}" class="ak-btn ak-btn-outline">Vendor payments</a>@endcan
                @can('expense-vendor-create')<a href="{{ route('expense-vendors.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> New shop</a>@endcan
            </div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')
    <div class="ak-page">
        <x-status-message />
        <form method="GET" class="ak-card ak-filters" style="padding:14px 16px; display:flex; flex-wrap:wrap; gap:12px; align-items:end">
            <div class="ak-field" style="flex:2; min-width:220px"><label for="f_search">Search</label><input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" placeholder="Name, phone, city or contact person" style="height:38px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:0 12px"></div>
            <div class="ak-field" style="min-width:160px"><label for="f_category">Category</label>
                <select id="f_category" name="filter[category]" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="">All</option>@foreach($categories as $key => $label)<option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="ak-field" style="min-width:140px"><label for="f_status">Status</label>
                <select id="f_status" name="filter[status]" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="">Any</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select></div>
            <label class="ev-remove" style="height:38px"><input type="checkbox" name="filter[owes]" value="1" @checked(! empty($filters['owes']))> We owe money</label>
            <button class="ak-btn ak-btn-primary" style="height:38px">Apply</button>
            @if($filters)<a href="{{ route('expense-vendors.index') }}" class="ak-btn ak-btn-outline" style="height:38px">Clear</a>@endif
        </form>

        <section class="ak-card" style="overflow-x:auto">
            <table class="ak-dt" style="min-width:820px">
                <thead><tr><th>Shop</th><th>Category</th><th>Phone · city</th><th class="ak-num">Vouchers</th><th class="ak-num">We owe (Rs)</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($vendors as $vendor)
                        <tr>
                            <td><a class="ak-primary-link" href="{{ route('expense-vendors.show', $vendor) }}">{{ $vendor->name }}</a>@if($vendor->contact_person)<div class="ak-muted">{{ $vendor->contact_person }}</div>@endif</td>
                            <td>{{ $vendor->categoryLabel() }}</td>
                            <td>{{ $vendor->phone ?: '—' }}<div class="ak-muted">{{ $vendor->city }}</div></td>
                            <td class="ak-num">{{ number_format($vendor->vouchers_count) }}</td>
                            <td class="ak-num ak-strong" style="{{ (float) $vendor->outstanding > 0 ? 'color:#b91c1c' : '' }}">{{ (float) $vendor->outstanding > 0 ? number_format((float) $vendor->outstanding, 2) : '—' }}</td>
                            <td><span class="ak-status {{ $vendor->is_active ? 'ak-status-green' : 'ak-status-slate' }}"><i aria-hidden="true"></i>{{ $vendor->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td style="white-space:nowrap">
                                @if((float) $vendor->outstanding > 0)@can('expense-vendor-payment-create')<a href="{{ route('expense-vendor-payments.create', ['vendor' => $vendor->id]) }}" class="ak-btn ak-btn-outline" style="padding:4px 10px">Pay</a>@endcan @endif
                                @can('expense-vendor-edit')<a href="{{ route('expense-vendors.edit', $vendor) }}" class="ak-btn ak-btn-outline" style="padding:4px 10px">Edit</a>@endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="ak-c ak-muted" style="padding:32px">No shop yet. Add one here, or while making an expense voucher.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="padding:12px 16px">{{ $vendors->links() }}</div>
        </section>
    </div>
</x-app-layout>
