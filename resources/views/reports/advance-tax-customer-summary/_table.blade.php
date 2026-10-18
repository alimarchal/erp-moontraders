{{-- Report table shared by the screen and the PDF. The header repeats on every PDF page. --}}
<table class="report-table">
    <thead>
        <tr class="bg-gray-50">
            <th class="text-center font-bold" style="width:4%">#</th>
            <th class="text-center font-bold px-2" style="width:34%">Customer</th>
            <th class="text-center font-bold px-2 whitespace-nowrap" style="width:11%">NTN</th>
            <th class="text-center font-bold px-2" style="width:12.75%">
                Sale
                <div class="text-xs font-normal text-gray-500">Total Sales</div>
            </th>
            <th class="text-center font-bold px-2" style="width:12.75%">
                Advance Tax (2.5%)
                <div class="text-xs font-normal text-gray-500">Sale × 2.5%</div>
            </th>
            <th class="text-center font-bold px-2" style="width:12.75%">
                Advance Tax Benefits<br>(NTN Customer)
                <div class="text-xs font-normal text-gray-500">Account 1161</div>
            </th>
            <th class="text-center font-bold px-2" style="width:12.75%">
                Advance Tax<br>Received From Customer
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
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="px-2">
                    <span class="font-semibold">{{ $customer?->customer_code }}</span>@if ($customer?->owner_cnic) <span class="text-xs">| CNIC {{ $customer->owner_cnic }}</span>@endif<br>
                    {{ $customer?->business_name ?: $customer?->customer_name }}@if ($address)<span class="text-xs">, {{ $address }}</span>@endif
                </td>
                <td class="px-2 whitespace-nowrap">{{ $customer?->ntn ?: '—' }}</td>
                <td class="text-right font-mono px-2">{{ number_format($row['sale'], 2) }}</td>
                <td class="text-right font-mono px-2">{{ number_format($row['advance_tax'], 2) }}</td>
                <td class="text-right font-mono px-2">{{ number_format($row['benefits'], 2) }}</td>
                <td class="text-right font-mono px-2">{{ number_format($row['received'], 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center italic text-gray-500 px-2 py-2">No advance tax entries found for this company and period.</td>
            </tr>
        @endforelse
    </tbody>
    @if ($rows->isNotEmpty())
        <tfoot class="bg-gray-100 font-extrabold">
            <tr>
                <td colspan="3" class="text-right px-2 py-1">Total ({{ $rows->count() }} customers)</td>
                <td class="text-right font-mono px-2 py-1">{{ number_format($totalSale, 2) }}</td>
                <td class="text-right font-mono px-2 py-1">{{ number_format($totalAdvanceTax, 2) }}</td>
                <td class="text-right font-mono px-2 py-1">{{ number_format($totalBenefits, 2) }}</td>
                <td class="text-right font-mono px-2 py-1">{{ number_format($totalReceived, 2) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
