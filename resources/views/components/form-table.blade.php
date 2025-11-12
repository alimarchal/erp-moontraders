@props([
'headers' => [],
'title' => null,
])

<style>
    .form-table-scroll::-webkit-scrollbar {
        height: 10px;
    }

    .form-table-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .form-table-scroll::-webkit-scrollbar-thumb {
        background: linear-gradient(to right, #10b981, #059669);
        border-radius: 10px;
        transition: background 0.3s ease;
    }

    .form-table-scroll::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(to right, #059669, #047857);
    }

    .form-table-scroll {
        scrollbar-width: thin;
        scrollbar-color: #10b981 #f1f5f9;
    }
</style>

<div>
    @if($title)
    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $title }}</h3>
    @endif

    <div class="form-table-scroll relative overflow-x-auto rounded-lg">
        <table class="min-w-full table-auto text-sm">
            <thead>
                <tr class="bg-green-800 text-white uppercase text-xs">
                    @foreach($headers as $header)
                    <th class="py-1 px-2 {{ $header['align'] ?? 'text-left' }}" @if(isset($header['width']))
                        style="min-width: {{ $header['width'] }};" @endif>
                        {!! $header['label'] !!}
                    </th>
                    @endforeach
                </tr>
            </thead>
            {{ $slot }}
        </table>
    </div>
</div>