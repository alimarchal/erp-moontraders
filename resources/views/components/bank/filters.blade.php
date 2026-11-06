@props(['action'])

<form method="GET" action="{{ $action }}" class="ak-card ak-filters bank-noprint" style="padding:16px">
    <div class="ak-filter-row" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); align-items:end">
        {{ $slot }}
        <div class="ak-filter-buttons">
            <a href="{{ $action }}" class="ak-btn ak-btn-ghost">Clear</a>
            <button type="submit" class="ak-btn ak-btn-primary">Apply</button>
        </div>
    </div>
</form>
