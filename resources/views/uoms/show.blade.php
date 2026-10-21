{{-- Settings -> Units of measure -> View. Read only; changes happen on Edit. --}}
@php
    $rows = [
            'Name' => $uom->uom_name,
            'Symbol' => $uom->symbol,
            'Quantity' => $uom->must_be_whole_number ? 'Whole numbers only' : 'Any quantity',
            'Status' => $uom->enabled ? 'Enabled' : 'Disabled',
            'Used as base unit by' => number_format($baseCount).' products',
            'Used as sales unit by' => number_format($salesCount).' products',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('uoms.index') }}">Units of measure</a><span aria-hidden="true">›</span><span>{{ $uom->uom_name }}</span>
                </nav>
                <h1 class="ak-title">{{ $uom->uom_name }}</h1>
                <p class="ak-sub">
                    @if ($uom->enabled)
                        <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Enabled</span>
                    @else
                        <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Disabled</span>
                    @endif
                </p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('uoms.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                @can('uom-edit')
                    <a href="{{ route('uoms.edit', $uom) }}" class="ak-btn ak-btn-primary">Edit unit</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page">
        <x-status-message />

        <section class="uf-card" style="max-width:640px">
            <div class="uf-card-head"><h2 class="uf-card-title">Unit details</h2></div>
            <dl style="margin:0; padding:8px 20px 16px">
                @foreach ($rows as $label => $value)
                    <div style="display:flex; justify-content:space-between; gap:16px; padding:9px 0; border-bottom:1px solid var(--ak-line); font-size:14px">
                        <dt class="ak-muted" style="flex:none">{{ $label }}</dt>
                        <dd style="margin:0; text-align:right; word-break:break-word; color:var(--ak-text)">{{ filled($value) ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if (filled($uom->description ?? null))
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Description</h2></div>
                <p style="margin:0; padding:16px 20px; font-size:14px; white-space:pre-line">{{ $uom->description }}</p>
            </section>
        @endif
    </div>
</x-app-layout>
