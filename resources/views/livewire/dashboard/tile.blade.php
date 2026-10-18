@php
    /**
     * Headline figure tile.
     *
     * @var string $label  @var string $value  @var string $tone (hex)  @var string $icon
     * optional: $prefix, $hint, $badge, $badgeTone (up|down|flat), $href, $title, $spark (element id for a sparkline)
     */
    $hex = ltrim($tone ?? '#1d4ed8', '#');
    [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    $style = "--tone:#{$hex};--soft:rgba({$r},{$g},{$b},.12);--glow:rgba({$r},{$g},{$b},.38)";
    $icons = [
        'sales' => 'M2.25 18 9 11.25l4.3 4.3a11.95 11.95 0 0 1 5.8-5.8l2.65-1.2m0 0-5.94-2.28m5.94 2.28-2.28 5.94',
        'profit' => 'M12 6v12m-3-2.82.88.66c1.17.88 3.07.88 4.24 0 1.17-.88 1.17-2.3 0-3.18C13.54 12.22 12.77 12 12 12c-.72 0-1.45-.22-2-.66-1.1-.88-1.1-2.3 0-3.18s2.9-.88 4 0l.42.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'cash' => 'M2.25 18.75h19.5M3.75 6h16.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5V7.5A1.5 1.5 0 0 1 3.75 6ZM15 11.25a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        'card' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
        'box' => 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
        'store' => 'M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.35m-16.5 11.65V9.35m0 0a3 3 0 0 0 3.75-.62 3 3 0 0 0 4.5 0 3 3 0 0 0 4.5 0 3 3 0 0 0 3.75.62m-16.5 0a3 3 0 0 1-.62-4.72L4.2 3.44A1.5 1.5 0 0 1 5.26 3h13.48a1.5 1.5 0 0 1 1.06.44l1.19 1.19a3 3 0 0 1-.62 4.72',
        'invest' => 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6',
        'alert' => 'M12 9v3.75m-9.3 3.38c-.87 1.5.22 3.37 1.95 3.37h14.7c1.73 0 2.82-1.87 1.95-3.37L13.95 3.38c-.87-1.5-3.03-1.5-3.9 0L2.7 16.13ZM12 15.75h.01',
        'check' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    ];
    $tag = isset($href) ? 'a' : 'div';
@endphp
<{{ $tag }} @isset($href) href="{{ $href }}" @endisset class="db-kpi{{ isset($href) ? ' is-link' : '' }}" style="{{ $style }}" @isset($title) title="{{ $title }}" @endisset>
    <div class="db-kpi-top">
        <span class="db-kpi-label">{{ $label }}</span>
        <span class="db-kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$icon] ?? $icons['sales'] }}" /></svg></span>
    </div>
    <div class="db-kpi-value">@isset($prefix)<small>{{ $prefix }}</small>@endisset{{ $value }}</div>
    <div class="db-kpi-hint">@isset($badge)<span class="db-delta {{ $badgeTone ?? 'flat' }}">{{ $badge }}</span>@endisset @isset($hint)<span>{{ $hint }}</span>@endisset</div>
    @isset($spark)<div id="{{ $spark }}" class="db-spark" aria-hidden="true"></div>@endisset
</{{ $tag }}>
