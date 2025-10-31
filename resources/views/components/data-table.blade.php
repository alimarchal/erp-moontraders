@props([
'items' => [],
'headers' => [],
'emptyMessage' => 'No records found.',
'emptyRoute' => null,
'emptyLinkText' => 'Add a new record',
])

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 pb-16">
    <x-status-message />
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">

        @if ($items->count() > 0)
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
            </table>
        </div>
        <div class="px-2 py-2">
            {{ $items->links() }}
        </div>
        @else
        <p class="text-gray-700 dark:text-gray-300 text-center py-4">
            {{ $emptyMessage }}
            @if($emptyRoute)
            <a href="{{ $emptyRoute }}" class="text-blue-600 hover:underline">
                {{ $emptyLinkText }}
            </a>.
            @endif
        </p>
        @endif
    </div>
</div>