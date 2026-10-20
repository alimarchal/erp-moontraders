{{-- Report table shared by the screen and the PDF. The header repeats on every PDF page. --}}
<table class="report-table">
    <thead>
        <tr>
            <th class="text-center font-bold" style="vertical-align:middle;width:4%">#</th>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:28%">Customer</th>
            <th class="text-center font-bold px-2 whitespace-nowrap" style="vertical-align:middle;width:10%">NTN</th>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:14.5%">
                Sale
                <div class="text-xs font-normal text-gray-500">Benefit ÷ 2%</div>
            </th>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:14.5%">
                Advance Tax (2.5%)
                <div class="text-xs font-normal text-gray-500">Sale × 2.5%</div>
            </th>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:14.5%">
                Advance Tax Benefits (2%)<br>(NTN Customer)
                <div class="text-xs font-normal text-gray-500">Account 1161</div>
            </th>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:14.5%">
                Advance Tax Received<br>From Customer
                <div class="text-xs font-normal text-gray-500">Adv. Tax − Benefits</div>
            </th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $index => $row)
            @php
                $customer = $row['customer'];
                $address = collect([$customer?->address, $customer?->sub_locality, $customer?->city])->filter()->implode(', ');
            @endphp
            <tr>
                <td class="text-center" style="vertical-align:middle">{{ $index + 1 }}</td>
                <td class="px-2" style="vertical-align:middle">
                    <div>
                        <span class="font-semibold">{{ $customer?->customer_code }}</span>
                        @if ($customer?->owner_cnic)<span class="text-xs"> | CNIC {{ $customer->owner_cnic }}</span>@endif
                    </div>
                    <div class="font-medium">{{ $customer?->business_name ?: $customer?->customer_name }}</div>
                    @if ($address)<div class="text-xs text-gray-500">{{ $address }}</div>@endif
                </td>
                <td class="px-2 whitespace-nowrap text-center" style="vertical-align:middle">{{ $customer?->ntn ?: '—' }}</td>
                <td class="text-right font-mono px-2" style="vertical-align:middle">{{ number_format($row['sale'], 2) }}</td>
                <td class="text-right font-mono px-2" style="vertical-align:middle">{{ number_format($row['advance_tax'], 2) }}</td>
                <td class="text-right font-mono px-2" style="vertical-align:middle">{{ number_format($row['benefits'], 2) }}</td>
                <td class="text-right font-mono px-2" style="vertical-align:middle">{{ number_format($row['received'], 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center italic text-gray-500 px-2 py-2">No advance tax entries found for this company and period.</td>
            </tr>
        @endforelse
    </tbody>
    @if ($rows->isNotEmpty())
        <tfoot class="font-extrabold">
            <tr>
                <td colspan="3" class="text-right px-2 py-1">Total ({{ $rows->count() }} customers)</td>
                <td class="text-right font-mono px-2 py-1">{{ number_format($ntnSale, 2) }}</td>
                <td class="text-right font-mono px-2 py-1">{{ number_format($ntnAdvanceTax, 2) }}</td>
                <td class="text-right font-mono px-2 py-1">{{ number_format($totalBenefits, 2) }}</td>
                <td class="text-right font-mono px-2 py-1">{{ number_format($ntnReceived, 2) }}</td>
            </tr>
        </tfoot>
    @endif
</table>

<h3 class="font-bold mt-4 mb-1">Non-NTN customers</h3>
<table class="report-table">
    <thead>
        <tr>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:32%">Customer</th>
            <th class="text-center font-bold px-2 whitespace-nowrap" style="vertical-align:middle;width:10%">NTN</th>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:14.5%">
                Sale
                <div class="text-xs font-normal text-gray-500">Total Sales − NTN sale</div>
            </th>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:14.5%">
                Advance Tax (2.5%)
                <div class="text-xs font-normal text-gray-500">Sale × 2.5%</div>
            </th>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:14.5%">
                Advance Tax Benefits (2%)<br>(NTN Customer)
                <div class="text-xs font-normal text-gray-500">Account 1161</div>
            </th>
            <th class="text-center font-bold px-2" style="vertical-align:middle;width:14.5%">
                Advance Tax Received<br>From Customer
                <div class="text-xs font-normal text-gray-500">Adv. Tax − Benefits</div>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="px-2">
                <span class="font-semibold">Sale to NON NTN customers</span><br>
                <span class="text-xs">Settlements' Total Sales less the NTN customers' sale above.</span>
            </td>
            <td class="px-2">—</td>
            <td class="text-right font-mono px-2">{{ number_format($nonNtnSale, 2) }}</td>
            <td class="text-right font-mono px-2">{{ number_format($nonNtnAdvanceTax, 2) }}</td>
            <td class="text-right font-mono px-2">{{ number_format(0, 2) }}</td>
            <td class="text-right font-mono px-2">{{ number_format($nonNtnAdvanceTax, 2) }}</td>
        </tr>
    </tbody>
</table>

<h3 class="font-bold mt-4 mb-1">Calculation</h3>
<table class="report-table" style="width:60%">
    <tbody>
        <tr><td class="px-2">Sale to NTN customers (table 1)</td><td class="text-right font-mono px-2">{{ number_format($ntnSale, 2) }}</td></tr>
        <tr><td class="px-2">Benefits given at 2% (account 1161)</td><td class="text-right font-mono px-2">{{ number_format($totalBenefits, 2) }}</td></tr>
        <tr><td class="px-2">Advance Tax Received From Customer (Adv. Tax − Benefits, table 1)</td><td class="text-right font-mono px-2">{{ number_format($ntnReceived, 2) }}</td></tr>
        <tr><td class="px-2">Sale to NON NTN customers (table 2)</td><td class="text-right font-mono px-2">{{ number_format($nonNtnSale, 2) }}</td></tr>
        <tr><td class="px-2">Advance Tax on non-NTN sale (2.5%, table 2)</td><td class="text-right font-mono px-2">{{ number_format($nonNtnAdvanceTax, 2) }}</td></tr>
        <tr class="font-extrabold"><td class="px-2">Tax to pay now (Received From Customer + 2.5% on non-NTN sale)</td><td class="text-right font-mono px-2">{{ number_format($totalReceived, 2) }}</td></tr>
        <tr><td class="px-2 text-xs">Total sale (NTN + non-NTN), equals the settlements' Total Sales</td><td class="text-right font-mono px-2 text-xs">{{ number_format($totalSale, 2) }}</td></tr>
    </tbody>
</table>
