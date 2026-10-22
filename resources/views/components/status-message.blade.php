@if (session('success'))
@php
    // One look for every page: delete / deactivate = red, edit / update = orange, everything else = green.
    $statusTone = match (true) {
        (bool) preg_match('/delet|deactivat|inactiv|remov|cancel/i', (string) session('success')) => ['bg-red-100 border-red-500 text-red-700', 'Done'],
        (bool) preg_match('/updat|edit|chang|saved/i', (string) session('success')) => ['bg-orange-100 border-orange-500 text-orange-700', 'Updated'],
        default => ['bg-green-100 border-green-500 text-green-700', 'Success'],
    };
@endphp
<div {{ $attributes->merge(['class' => $statusTone[0].' border-l-4 p-4 w-full max-w-7xl mx-auto mb-4 shadow-md']) }}
    data-status-sound="success"
    role="alert">
    <p class="font-bold">{{ $statusTone[1] }}</p>
    <p>{{ session('success') }}</p>
</div>
@endif

@if (session('error'))
<div {{ $attributes->merge(['class' => 'max-w-7xl mx-auto sm:px-6 lg:px-8 bg-red-100 border-l-4 border-red-500
    text-red-700 py-4 mx-6']) }}
    data-status-sound="error"
    role="alert">
    <p class="font-bold">Error</p>
    @if(is_array(session('error')))
    <p>{{ session('error.message') }}</p>
    <p class="text-xs text-gray-500 mt-2">{{ session('error.db') }}</p>
    @else
    {{-- Escaped first, so a message listing several lines keeps them apart. --}}
    <p>{!! nl2br(e(session('error'))) !!}</p>
    @endif
</div>
@endif

@if (session('warning'))
<div {{ $attributes->merge(['class' => 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mx-6']) }}
    data-status-sound="warning"
    role="alert">
    <p class="font-bold">Warning</p>
    <p>{{ session('warning') }}</p>
</div>
@endif

@if (session('info'))
<div {{ $attributes->merge(['class' => 'bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mx-6']) }}
    data-status-sound="info"
    role="alert">
    <p class="font-bold">Information</p>
    <p>{{ session('info') }}</p>
</div>
@endif

<x-status-sound-script />
