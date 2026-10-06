@php
    $tabClass = fn (bool $active) => $active
        ? 'border-indigo-600 text-indigo-700'
        : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300';
    $current = request('status');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tickets" :createRoute="route('tickets.create')" createLabel="New Ticket"
            createPermission="ticket-create" :showSearch="false" backRoute="settings.index" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-status-message />

            <div class="bg-white shadow-xl sm:rounded-xl overflow-hidden">
                <div class="px-4 pt-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200">
                    <nav class="flex gap-6 -mb-px" aria-label="Status">
                        <a href="{{ route('tickets.index', request()->except('status', 'page')) }}"
                            class="pb-3 border-b-2 text-sm font-semibold {{ $tabClass($current === null || $current === '') }}">
                            All <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs">{{ $statusCounts->sum() }}</span>
                        </a>
                        @foreach ($statuses as $status)
                            <a href="{{ route('tickets.index', array_merge(request()->except('page'), ['status' => $status->value])) }}"
                                class="pb-3 border-b-2 text-sm font-semibold {{ $tabClass($current === $status->value) }}">
                                {{ $status->label() }}
                                <span class="ml-1 rounded-full px-2 py-0.5 text-xs {{ $status->badgeClasses() }}">{{ $statusCounts[$status->value] ?? 0 }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <form method="GET" action="{{ route('tickets.index') }}" class="pb-3 flex flex-wrap gap-2">
                        @if ($current)
                            <input type="hidden" name="status" value="{{ $current }}">
                        @endif
                        <select name="type" onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 text-sm py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All types</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search ticket # or title…"
                            class="rounded-lg border-gray-300 text-sm py-1.5 w-64 focus:border-indigo-500 focus:ring-indigo-500">
                    </form>
                </div>

                @forelse ($tickets as $ticket)
                    <a href="{{ route('tickets.show', $ticket) }}"
                        class="flex items-start gap-4 px-5 py-4 border-b border-gray-100 last:border-0 hover:bg-indigo-50/40 transition">
                        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ticket->type->iconPath() }}" /></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900 truncate">{{ $ticket->title }}</span>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ticket->status->badgeClasses() }}">{{ $ticket->status->label() }}</span>
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600">{{ $ticket->type->label() }}</span>
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">
                                {{ $ticket->ticket_number }} · raised by {{ $ticket->creator->name ?? '—' }}
                                {{ $ticket->created_at->diffForHumans() }}
                                @if ($ticket->supplier) · {{ $ticket->supplier->supplier_name }} @endif
                                · {{ $ticket->items_count }} {{ \Illuminate\Support\Str::plural('item', $ticket->items_count) }}
                            </span>
                        </span>
                    </a>
                @empty
                    <div class="py-16 text-center">
                        <p class="text-gray-700 font-medium">No tickets found</p>
                        <p class="text-sm text-gray-500 mt-1">Raise a ticket to request a price change, a new SKU or a re-activation.</p>
                        @can('ticket-create')
                            <a href="{{ route('tickets.create') }}" class="mt-4 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New Ticket</a>
                        @endcan
                    </div>
                @endforelse

                @if ($tickets->hasPages())
                    <div class="px-4 py-3">{{ $tickets->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
