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
                        @if ($row['unassigned'] ?? false)
                            <span class="bold">No customer entry</span><br>
                            <span class="small muted">Settlements with sales but no advance tax entry on any customer:</span>
                            @foreach ($row['settlements'] ?? [] as $settlement)
                                <br><span class="small"><b>{{ $settlement['number'] }}</b> | {{ \Illuminate\Support\Carbon::parse($settlement['date'])->format('d-M-Y') }} | {{ number_format($settlement['sale'], 2) }}</span>
                            @endforeach
                        @else
                            <span class="bold">{{ $customer?->customer_code }}</span>@if ($customer?->owner_cnic) <span class="small muted">| CNIC {{ $customer->owner_cnic }}</span>@endif<br>
                            {{ $customer?->business_name ?: $customer?->customer_name }}@if ($address)<span class="small muted">, {{ $address }}</span>@endif
                        @endif
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
                    <td colspan="3" class="num">Total ({{ $rows->reject(fn ($row) => $row['unassigned'] ?? false)->count() }} customers)</td>
                    <td class="num">{{ $money($totalSale) }}</td>
                    <td class="num">{{ $money($totalAdvanceTax) }}</td>
                    <td class="num">{{ $money($totalBenefits) }}</td>
                    <td class="num">{{ $money($totalReceived) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
    <div class="note">Sale is the Total Sales of the settlements the customer has an advance tax entry on. A settlement with several customers shows its sale against each of them, and counts once in the Total row.</div>
@endsection
