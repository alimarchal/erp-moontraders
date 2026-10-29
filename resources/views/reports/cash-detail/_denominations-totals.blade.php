{{-- Cash denominations and grand totals side by side in one table, padded to the same height. --}}
@php
    $left = $denominationRows->map(fn ($row, $i) => [$i + 1, $row['label'], $row['qty'] > 0 ? $row['qty'] : '-', $cell($row['value'])])->values()->all();
    $left[] = [8, 'Coins', '-', $cell($coins)];
    $right = [
        ['Salesman cash', 'notes + coins', ''],
        ['Bank slips', 'deposited in bank', ''],
        ['Cash + bank slips', 'cash side total', 'b'],
        ['Bank transfers', 'online / IBFT', ''],
        ['Cheques', 'received from customers', ''],
        ['Bank transfers + cheques', 'non-cash side total', 'b'],
        ['Overall grand total', 'cash + slips + transfers + cheques', 'hl'],
    ];
    $rightAmounts = [$totals['totalCash'], $totals['totalBankSlips'], $totals['totalCash'] + $totals['totalBankSlips'], $totals['totalBankTransfers'], $totals['totalCheques'], $totals['totalBankTransfers'] + $totals['totalCheques'], $grandTotal];

    $count = max(count($left), count($right));
@endphp
<table class="p2 p2-twin">
    <colgroup>
        <col style="width: 4%"><col style="width: 18%"><col style="width: 14%"><col style="width: 13%"><col style="width: 2%"><col style="width: 4%"><col style="width: 32%"><col style="width: 13%">
    </colgroup>
    <thead>
        <tr class="cap">
            <th colspan="4">5 - Cash Denominations <small>count of notes handed in</small></th>
            <th class="sp"></th>
            <th colspan="3">6 - Grand Totals <small>all collections of the day</small></th>
        </tr>
        <tr>
            <th class="c">#</th>
            <th class="c">Denomination</th>
            <th class="c">Notes</th>
            <th class="n">Value (Rs.)</th>
            <th class="sp"></th>
            <th class="c">#</th>
            <th>Collection</th>
            <th class="n">Amount (Rs.)</th>
        </tr>
    </thead>
    <tbody>
        @for ($i = 0; $i < $count; $i++)
            <tr>
                @if (isset($left[$i]))
                    <td class="c">{{ $left[$i][0] }}</td>
                    <td class="c b">{{ $left[$i][1] }}</td>
                    <td class="c">{{ $left[$i][2] }}</td>
                    <td class="n">{{ $left[$i][3] }}</td>
                @else
                    <td class="blank">&nbsp;</td><td class="blank"></td><td class="blank"></td><td class="blank"></td>
                @endif
                <td class="sp"></td>
                @if (isset($right[$i]))
                    <td class="c {{ $right[$i][2] === 'hl' ? 'hlrow' : '' }}">{{ $i + 1 }}</td>
                    <td class="{{ $right[$i][2] === 'hl' ? 'hlrow' : '' }} {{ $right[$i][2] ? 'b' : '' }}">{{ $right[$i][0] }} <span class="calc">({{ $right[$i][1] }})</span></td>
                    <td class="n {{ $right[$i][2] === 'hl' ? 'hlrow' : '' }} {{ $right[$i][2] ? 'b' : '' }}">{{ $money($rightAmounts[$i]) }}</td>
                @else
                    <td class="blank">&nbsp;</td><td class="blank"></td><td class="blank"></td>
                @endif
            </tr>
        @endfor
        <tr class="tot">
            <td colspan="3" class="n">Total cash by denomination</td>
            <td class="n">{{ $money($cashByDenomination) }}</td>
            <td class="sp"></td>
            <td class="blank"></td><td class="blank"></td><td class="blank"></td>
        </tr>
    </tbody>
</table>
