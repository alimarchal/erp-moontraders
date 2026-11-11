@props(['title', 'sub' => null, 'crumbs' => []])

@php
    // Categories, party aliases and auto-classify rules are master data kept under Settings
    $inSettings = request()->routeIs(\App\Services\BankRegister\BankRegisterPermissions::SETUP_ROUTES);
@endphp


<div class="ak-head">
    <div>
        <nav class="ak-crumbs" aria-label="Breadcrumb">
            @if ($inSettings)
                <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>Bank Register setup</span>
            @else
                <a href="{{ route('bank-statement-entries.index') }}">Bank Register</a>
            @endif
            @foreach ($crumbs as $label => $url)
                <span aria-hidden="true">›</span>
                @if ($url)<a href="{{ $url }}">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
            @endforeach
        </nav>
        <h1 class="ak-title">{{ $title }}</h1>
        @if ($sub)<p class="ak-sub">{{ $sub }}</p>@endif
    </div>
    <div class="ak-head-actions">
        {{ $slot }}
        @if ($inSettings && request()->routeIs('*.index'))
            <a href="{{ route('settings.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Settings</a>
        @elseif (request()->routeIs('*.index') && ! request()->routeIs('bank-statement-entries.index'))
            <a href="{{ route('bank-statement-entries.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Register</a>
        @endif
        @unless (request()->routeIs('bank-register.help'))
            <x-bank.help-button :title="$title" />
        @endunless
        <x-bank.menu-button />
    </div>
</div>
