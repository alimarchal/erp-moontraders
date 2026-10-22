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
    <div class="ak-head-actions">{{ $slot }}</div>
</div>
