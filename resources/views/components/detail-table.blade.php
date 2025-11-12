@props([
'headers' => [],
'title' => null,
'footerSlot' => null,
])

<div>
    @if($title)
    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $title }}</h3>
    @endif

    <div class="relative overflow-x-auto rounded-lg">
        <table class="min-w-max w-full table-auto text-sm">
            <thead>
                <tr class="bg-green-800 text-white uppercase text-sm">
                    @foreach($headers as $header)
                    <th class="py-2 px-2 {{ $header['align'] ?? 'text-left' }}">
                        {{ $header['label'] }}
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="text-black text-md leading-normal font-extrabold">
                {{ $slot }}
            </tbody>
            @if(isset($footer))
            <tfoot class="bg-gray-50">
                {{ $footer }}
            </tfoot>
            @endif
        </table>
    </div>
</div>