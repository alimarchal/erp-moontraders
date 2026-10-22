@props(['items' => [], 'headers' => [], 'emptyMessage' => 'No records found.', 'emptyRoute' => null, 'emptyLinkText' => 'Add a new record'])

<section class="ak-card" aria-label="List">
    @if ($items->count() > 0)
        <div class="ak-dt-scroll" tabindex="0">
            <table class="ak-dt">
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            @php($align = $header['align'] ?? 'text-left')
                            <th scope="col" class="{{ $align === 'text-center' ? 'ak-c' : ($align === 'text-right' ? 'ak-num' : '') }}">{!! $header['label'] !!}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>{{ $slot }}</tbody>
                @if (isset($footer))<tfoot>{{ $footer }}</tfoot>@endif
            </table>
        </div>
        @if (method_exists($items, 'hasPages') && $items->hasPages())
            <div class="ak-pager">
                <p>Showing <b>{{ number_format($items->firstItem()) }}–{{ number_format($items->lastItem()) }}</b> of <b>{{ number_format($items->total()) }}</b></p>
                <div class="ak-pager-right"><div>{{ $items->onEachSide(1)->links() }}</div></div>
            </div>
        @endif
    @else
        <div class="ak-empty">
            <h2>{{ $emptyMessage }}</h2>
            @if ($emptyRoute)<a href="{{ $emptyRoute }}" class="ak-btn ak-btn-primary">{{ $emptyLinkText }}</a>@endif
        </div>
    @endif
</section>
