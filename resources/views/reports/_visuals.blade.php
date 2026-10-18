{{-- Coloured figure tiles and chart cards shown above a report table (screen and browser print). Needs $tiles and $charts. --}}
@if (! empty($tiles))
    <style>
        .rv-tiles { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 4px 0 14px; }
        @media (min-width: 900px) { .rv-tiles { grid-template-columns: repeat({{ count($tiles) }}, minmax(0, 1fr)); } }
        .rv-tile { border-radius: 10px; padding: 11px 14px; color: #fff; box-shadow: 0 6px 16px -8px rgba(15,23,42,.45); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .rv-tile b { display: block; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; opacity: .92; }
        .rv-tile strong { display: block; margin-top: 3px; font-size: 22px; line-height: 1.15; font-weight: 800; letter-spacing: -.01em; font-variant-numeric: tabular-nums; }
        .rv-tile span { display: block; margin-top: 2px; font-size: 11.5px; opacity: .92; }
        .rv-charts { display: grid; gap: 12px; grid-template-columns: minmax(0, 1fr); margin-bottom: 16px; }
        @media (min-width: 1000px) { .rv-charts { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .rv-card { border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px 14px 8px; background: #fff; box-shadow: 0 4px 14px -8px rgba(15,23,42,.3); break-inside: avoid; }
        .rv-card h4 { margin: 0; padding-left: 8px; border-left: 4px solid var(--accent, #2563eb); font-size: 13.5px; font-weight: 800; color: #0b2a6f; }
        .rv-card p { margin: 2px 0 6px 12px; font-size: 11.5px; color: #64748b; }
        @media print { .rv-tiles { grid-template-columns: repeat({{ count($tiles) }}, minmax(0, 1fr)); } .rv-charts { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    </style>
    <div class="rv-tiles no-print-break">
        @foreach ($tiles as $tile)
            <div class="rv-tile" style="background: {{ $tile['color'] }}">
                <b>{{ $tile['label'] }}</b>
                <strong>{{ $tile['value'] }}</strong>
                <span>{{ $tile['note'] ?? '' }}&nbsp;</span>
            </div>
        @endforeach
    </div>
    @if (! empty($charts))
        <div class="rv-charts">
            @foreach ($charts as $card)
                <div class="rv-card" style="--accent: {{ $card['accent'] ?? '#2563eb' }}">
                    <h4>{{ $card['title'] }}</h4>
                    <p>{{ $card['subtitle'] ?? '' }}</p>
                    {!! $card['svg'](420) !!}
                </div>
            @endforeach
        </div>
    @endif
@endif
