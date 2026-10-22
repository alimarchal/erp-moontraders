@props(['value', 'sign' => false])

@php($amount = (float) $value)
<span {{ $attributes->merge(['class' => 'tabular-nums whitespace-nowrap']) }}>{{ $sign && $amount > 0 ? '+' : '' }}{{ number_format($amount, 2) }}</span>
