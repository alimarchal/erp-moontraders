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
        <th>Sr#</th><th>Product Name</th>@if($show('category'))<th>Category</th>@endif@if($show('brand'))<th>Brand</th>@endif@if($show('branch'))<th>Branch</th>@endif@if($show('avg_price'))<th class="n">Avg Price</th>@endif<th class="n">Sold Qty</th>
        @if($show('returned'))<th class="n">Rtn Qty</th>@endif
        @if($show('shortage'))<th class="n">Short Qty</th>@endif
        @if($show('net_sales'))<th class="n">Net Sales</th>@endif
        @if($show('net_profit'))<th class="n">Net Profit</th>@endif
        @if($show('np_percent'))<th class="n">NP %</th>@endif
        @if($show('prev_sales'))<th class="n">Prev Month Sales</th>@endif
        @if($show('change'))<th class="n">Change vs Prev</th>@endif
        @if($show('change_pct'))<th class="n">Change %</th>@endif
    </tr></thead>
    <tbody>
        @forelse($productSales as $row)
            <tr>
                <td>{{ $loop->iteration }}</td><td>{{ $row->product_name }}</td>@if($show('category'))<td>{{ $row->category_name ?? '-' }}</td>@endif@if($show('brand'))<td>{{ $row->brand ?: '-' }}</td>@endif@if($show('branch'))<td>{{ $row->branch_names ?: '-' }}</td>@endif@if($show('avg_price'))<td class="n">{{ $fmt($row->avg_selling_price) }}</td>@endif<td class="n">{{ number_format($row->total_sold, 0) }}</td>
                @if($show('returned'))<td class="n">{{ number_format($row->total_returned, 0) }}</td>@endif
                @if($show('shortage'))<td class="n">{{ number_format($row->total_shortage, 0) }}</td>@endif
                @if($show('net_sales'))<td class="n">{{ $fmt($row->total_sales_value) }}</td>@endif
                @if($show('net_profit'))<td class="n">{{ $fmt($row->net_profit) }}</td>@endif
                @if($show('np_percent'))<td class="n">{{ $np($row->net_profit, $row->total_sales_value) }}</td>@endif
                @if($show('prev_sales'))<td class="n">{{ $fmt($row->previous_sales) }}</td>@endif
                @if($show('change'))<td class="n" style="color:{{ $row->sales_change < 0 ? '#b91c1c' : '#15803d' }}">{{ ($row->sales_change > 0 ? '+' : '').$fmt($row->sales_change) }}</td>@endif
                @if($show('change_pct'))<td class="n" style="color:{{ $row->sales_change < 0 ? '#b91c1c' : '#15803d' }}">{{ $row->sales_change_pct === null ? 'New' : ($row->sales_change_pct > 0 ? '+' : '').number_format($row->sales_change_pct, 2).'%' }}</td>@endif
            </tr>
        @empty
            <tr><td colspan="{{ 3 + count($visibleColumns) }}" style="text-align:center; padding:16px">No sales found for these filters.</td></tr>
        @endforelse
    </tbody>
    @if($productSales->isNotEmpty())
    <tfoot><tr>
        <td colspan="{{ 2 + ($show('category') ? 1 : 0) + ($show('brand') ? 1 : 0) + ($show('branch') ? 1 : 0) + ($show('avg_price') ? 1 : 0) }}">Total ({{ $productSales->count() }} rows)</td><td class="n">{{ number_format($totals['total_sold'], 0) }}</td>
        @if($show('returned'))<td class="n">{{ number_format($totals['total_returned'], 0) }}</td>@endif
        @if($show('shortage'))<td class="n">{{ number_format($totals['total_shortage'], 0) }}</td>@endif
        @if($show('net_sales'))<td class="n">{{ $fmt($totals['total_sales_value']) }}</td>@endif
        @if($show('net_profit'))<td class="n">{{ $fmt($totals['net_profit']) }}</td>@endif
        @if($show('np_percent'))<td class="n">{{ $np($totals['net_profit'], $totals['total_sales_value']) }}</td>@endif
        @if($show('prev_sales'))<td class="n">{{ $fmt($totals['previous_sales']) }}</td>@endif
        @if($show('change'))<td class="n">{{ ($totals['sales_change'] > 0 ? '+' : '').$fmt($totals['sales_change']) }}</td>@endif
        @if($show('change_pct'))<td class="n">{{ $totals['previous_sales'] > 0 ? ($totals['sales_change'] > 0 ? '+' : '').number_format($totals['sales_change'] / $totals['previous_sales'] * 100, 2).'%' : '-' }}</td>@endif
    </tr></tfoot>
    @endif
</table>
</body></html>
