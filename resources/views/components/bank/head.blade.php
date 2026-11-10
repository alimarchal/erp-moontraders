@props(['title', 'sub' => null, 'crumbs' => []])

<div class="ak-head">
    <div>
        <nav class="ak-crumbs" aria-label="Breadcrumb">
            <a href="{{ route('bank-statement-entries.index') }}">Bank Register</a>
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
        @if (request()->routeIs('*.index') && ! request()->routeIs('bank-statement-entries.index'))
            <a href="{{ route('bank-statement-entries.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Register</a>
        @endif
        @unless (request()->routeIs('bank-register.help'))
            <a href="{{ route('bank-register.help') }}" target="_blank" rel="noopener" class="ak-btn ak-btn-outline" title="How the bank register works, step by step, in Urdu">? Help <span dir="rtl" lang="ur">(اردو)</span></a>
        @endunless
        <x-bank.menu-button />
    </div>
</div>
