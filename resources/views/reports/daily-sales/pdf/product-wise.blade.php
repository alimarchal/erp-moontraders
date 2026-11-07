<!doctype html>
<html><head><meta charset="utf-8"><title>SKU Wise Sales Report</title>@include('payroll.pdf._style')</head>
<body>
@php
    $fmt = fn ($v) => number_format((float) $v, 2);
    $show = fn (string $key) => in_array($key, $visibleColumns, true);
    $period = \Carbon\Carbon::parse($startDate)->format('d-M-Y').' to '.\Carbon\Carbon::parse($endDate)->format('d-M-Y');
    $np = fn ($profit, $sales) => $sales > 0 ? number_format($profit / $sales * 100, 2).'%' : '0.00%';
@endphp
<table class="head"><tr>
    <td><h1>{{ config('app.name') }}</h1><div class="sub">SKU Wise Sales Report &middot; {{ $period }}</div>@if($filterLines)<div class="sub">{{ implode('  |  ', $filterLines) }}</div>@endif</td>
    <td class="ref">{{ now()->format('d-M-Y H:i') }}<div class="sub">{{ $productSales->count() }} products &middot; {{ auth()->user()?->name }}</div></td>
</tr></table>
<table class="grid">
    <thead><tr>
        <th>Sr#</th><th>Product Name</th>@if($show('avg_price'))<th class="n">Avg Price</th>@endif<th class="n">Sold Qty</th>
        @if($show('returned'))<th class="n">Rtn Qty</th>@endif
        @if($show('shortage'))<th class="n">Short Qty</th>@endif
        @if($show('net_sales'))<th class="n">Net Sales</th>@endif
        @if($show('net_profit'))<th class="n">Net Profit</th>@endif
        @if($show('np_percent'))<th class="n">NP %</th>@endif
    </tr></thead>
    <tbody>
        @forelse($productSales as $row)
            <tr>
                <td>{{ $loop->iteration }}</td><td>{{ $row->product_name }}</td>@if($show('avg_price'))<td class="n">{{ $fmt($row->avg_selling_price) }}</td>@endif<td class="n">{{ number_format($row->total_sold, 0) }}</td>
                @if($show('returned'))<td class="n">{{ number_format($row->total_returned, 0) }}</td>@endif
                @if($show('shortage'))<td class="n">{{ number_format($row->total_shortage, 0) }}</td>@endif
                @if($show('net_sales'))<td class="n">{{ $fmt($row->total_sales_value) }}</td>@endif
                @if($show('net_profit'))<td class="n">{{ $fmt($row->net_profit) }}</td>@endif
                @if($show('np_percent'))<td class="n">{{ $np($row->net_profit, $row->total_sales_value) }}</td>@endif
            </tr>
        @empty
            <tr><td colspan="{{ 3 + count($visibleColumns) }}" style="text-align:center; padding:16px">No sales found for these filters.</td></tr>
        @endforelse
    </tbody>
    @if($productSales->isNotEmpty())
    <tfoot><tr>
        <td colspan="{{ $show('avg_price') ? 3 : 2 }}">Total ({{ $productSales->count() }} rows)</td><td class="n">{{ number_format($totals['total_sold'], 0) }}</td>
        @if($show('returned'))<td class="n">{{ number_format($totals['total_returned'], 0) }}</td>@endif
        @if($show('shortage'))<td class="n">{{ number_format($totals['total_shortage'], 0) }}</td>@endif
        @if($show('net_sales'))<td class="n">{{ $fmt($totals['total_sales_value']) }}</td>@endif
        @if($show('net_profit'))<td class="n">{{ $fmt($totals['net_profit']) }}</td>@endif
        @if($show('np_percent'))<td class="n">{{ $np($totals['net_profit'], $totals['total_sales_value']) }}</td>@endif
    </tr></tfoot>
    @endif
</table>
</body></html>
