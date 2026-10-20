@extends('reports.pdf-layout')

@section('content')
    @php
        $landscape = $orientation === 'landscape';
        $money = fn ($value) => number_format((float) $value, 2);
    @endphp
    <table class="data" style="table-layout: fixed">
        <thead>
            <tr>
                <th class="center" style="width: 4%">#</th>
                <th style="width: {{ $landscape ? '30%' : '27%' }}; text-align: left">Customer</th>
                <th style="width: 13%">NTN</th>
                <th class="num">Sale</th>
                <th class="num">Advance Tax<br>(2.5%)</th>
                <th class="num">Benefits<br>(NTN customer)</th>
                <th class="num">Received from<br>customer</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                @php
                    $customer = $row['customer'];
                    $address = collect([$customer?->address, $customer?->sub_locality, $customer?->city])->filter()->implode(', ');
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td style="word-wrap: break-word">
                            <span class="bold">{{ $customer?->customer_code }}</span>@if ($customer?->owner_cnic) <span class="small muted">| CNIC {{ $customer->owner_cnic }}</span>@endif<br>
                            {{ $customer?->business_name ?: $customer?->customer_name }}@if ($address)<span class="small muted">, {{ $address }}</span>@endif
                    </td>
                    <td>{{ $customer?->ntn ?: '-' }}</td>
                    <td class="num">{{ $money($row['sale']) }}</td>
                    <td class="num">{{ $money($row['advance_tax']) }}</td>
                    <td class="num">{{ $money($row['benefits']) }}</td>
                    <td class="num bold">{{ $money($row['received']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center muted">No advance tax entries found for this company and period.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="3" class="num">Total ({{ $rows->count() }} customers)</td>
                    <td class="num">{{ $money($ntnSale) }}</td>
                    <td class="num">{{ $money($ntnAdvanceTax) }}</td>
                    <td class="num">{{ $money($totalBenefits) }}</td>
                    <td class="num">{{ $money($ntnReceived) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
    <p class="bold" style="margin-top: 14px">Non-NTN customers</p>
    <table class="data" style="table-layout: fixed">
        <thead>
            <tr>
                <th style="width: {{ $landscape ? '34%' : '31%' }}; text-align: left">Customer</th>
                <th style="width: 13%">NTN</th>
                <th class="num">Sale</th>
                <th class="num">Advance Tax<br>(2.5%)</th>
                <th class="num">Benefits<br>(NTN customer)</th>
                <th class="num">Received from<br>customer</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="word-wrap: break-word"><span class="bold">Sale to NON NTN customers</span><br><span class="small muted">Settlements' Total Sales less the NTN customers' sale above.</span></td>
                <td>-</td>
                <td class="num">{{ $money($nonNtnSale) }}</td>
                <td class="num">{{ $money($nonNtnAdvanceTax) }}</td>
                <td class="num">{{ $money(0) }}</td>
                <td class="num bold">{{ $money($nonNtnAdvanceTax) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="bold" style="margin-top: 14px">Calculation</p>
    <table class="data" style="table-layout: fixed">
        <tbody>
            <tr><td style="width: 75%">Sale to NTN customers (table 1)</td><td class="num">{{ $money($ntnSale) }}</td></tr>
            <tr><td>Benefits given at 2% (account 1161)</td><td class="num">{{ $money($totalBenefits) }}</td></tr>
            <tr><td>Advance Tax Received From Customer (Adv. Tax - Benefits, table 1)</td><td class="num">{{ $money($ntnReceived) }}</td></tr>
            <tr><td>Sale to NON NTN customers (table 2)</td><td class="num">{{ $money($nonNtnSale) }}</td></tr>
            <tr><td>Advance Tax on non-NTN sale (2.5%, table 2)</td><td class="num">{{ $money($nonNtnAdvanceTax) }}</td></tr>
            <tr><td class="bold">Tax to pay now (Received From Customer + 2.5% on non-NTN sale)</td><td class="num bold">{{ $money($totalReceived) }}</td></tr>
            <tr><td class="small muted">Total sale (NTN + non-NTN), equals the settlements' Total Sales</td><td class="num small">{{ $money($totalSale) }}</td></tr>
        </tbody>
    </table>
    <div class="note">A customer's sale is worked back from the benefit given (benefit / 2%). The advance tax is 2.5% of the sale; the benefit is part of it and the rest is received from the customer. Sale not made to NTN customers carries the full 2.5%.</div>
@endsection
