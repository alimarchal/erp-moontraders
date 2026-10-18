@extends('reports.pdf-layout')

@section('content')
    @php($money = fn ($value) => number_format((float) $value, 2))
    <table class="data">
        <thead>
            <tr>
                <th class="center" style="width: 6%">Sr#</th>
                <th style="text-align: left">Supplier</th>
                <th class="num">Stock in quantity</th>
                <th class="num">Stock amount (Rs.)</th>
                <th class="num">Credit balance salesman</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($stockData as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="bold">{{ $row->supplier_name }}</td>
                    <td class="num">{{ $money($row->total_quantity) }}</td>
                    <td class="num">{{ $money($row->total_amount) }}</td>
                    <td class="num">{{ $money($row->credit_balance_salesman) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="num">Grand total</td>
                <td class="num">{{ $money($grandTotalQuantity) }}</td>
                <td class="num">{{ $money($grandTotalAmount) }}</td>
                <td class="num">{{ $money($grandTotalSalesmanCreditBalance) }}</td>
            </tr>
        </tfoot>
    </table>
    <div class="note">Stock is the quantity and value on hand in warehouses and vans (or the snapshot of the chosen date). Credit balance salesman is what customers owe on the salesmen's accounts up to that date.</div>
@endsection
