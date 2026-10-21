@props(['title', 'subtitle' => null])

{{-- Shown on paper only (the page header is hidden when printing). --}}
<div class="bank-printonly bank-keep mb-3 border-b-2 border-gray-800 pb-2">
    <div class="flex items-end justify-between gap-4">
        <div>
            <p class="text-lg font-bold text-gray-900">{{ \App\Models\Company::query()->value('company_name') ?? config('app.name') }} — {{ $title }}</p>
            @if ($subtitle)
                <p class="text-xs text-gray-600" dir="auto">{{ $subtitle }}</p>
            @endif
        </div>
        <p class="text-xs text-gray-600 text-right">Printed {{ now()->format('d-m-Y H:i') }}<br>by {{ auth()->user()?->name }}</p>
    </div>
</div>
