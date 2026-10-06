<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tickets" :createRoute="route('tickets.create')" createLabel="New Ticket"
            createPermission="ticket-create" :showSearch="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('tickets.index')">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <x-label for="search" value="Ticket # / Title" />
                <x-input id="search" type="text" name="search" class="block mt-1 w-full" value="{{ request('search') }}" />
            </div>
            <div>
                <x-label for="type" value="Type" />
                <select id="type" name="type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="status" value="Status" />
                <select id="status" name="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :headers="[
        ['label' => 'Ticket #', 'align' => 'text-left'],
        ['label' => 'Title', 'align' => 'text-left'],
        ['label' => 'Type', 'align' => 'text-left'],
        ['label' => 'Company', 'align' => 'text-left'],
        ['label' => 'Items', 'align' => 'text-center'],
        ['label' => 'Raised By', 'align' => 'text-left'],
        ['label' => 'Date', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" :items="$tickets" emptyMessage="No tickets found." :emptyRoute="route('tickets.create')" emptyLinkText="Raise a Ticket">
        @foreach ($tickets as $ticket)
            <tr class="border-b border-gray-200 text-sm hover:bg-gray-50 transition-colors duration-150">
                <td class="py-1 px-2 font-semibold">{{ $ticket->ticket_number }}</td>
                <td class="py-1 px-2">{{ $ticket->title }}</td>
                <td class="py-1 px-2">{{ $ticket->type->label() }}</td>
                <td class="py-1 px-2">{{ $ticket->supplier->supplier_name ?? '—' }}</td>
                <td class="py-1 px-2 text-center">{{ $ticket->items_count }}</td>
                <td class="py-1 px-2">{{ $ticket->creator->name ?? '—' }}</td>
                <td class="py-1 px-2">{{ $ticket->created_at->format('d-m-Y H:i') }}</td>
                <td class="py-1 px-2 text-center">
                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $ticket->status->badgeClasses() }}">{{ $ticket->status->label() }}</span>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('tickets.show', $ticket) }}" class="text-blue-700 hover:underline" title="View">View</a>
                        @can('ticket-edit')
                            @if ($ticket->isPending())
                                <a href="{{ route('tickets.edit', $ticket) }}" class="text-green-700 hover:underline" title="Edit">Edit</a>
                            @endif
                        @endcan
                        @can('ticket-delete')
                            @if ($ticket->isPending())
                                <form action="{{ route('tickets.destroy', $ticket) }}" method="POST" class="inline"
                                    onsubmit="return confirm('Delete this ticket?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-700 hover:underline">Delete</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
