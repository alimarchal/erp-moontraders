@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div class="ak-head-text">
                <h1 class="ak-title">Dashboard</h1>
                <p class="ak-sub">{{ $greeting }}, {{ auth()->user()->name }} &middot; {{ now()->format('l, d F Y') }}</p>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    @livewire('dashboard')
</x-app-layout>
