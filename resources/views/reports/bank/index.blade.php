<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bank Register Reports" :showSearch="false" :showRefresh="false" backRoute="reports.index" />
    </x-slot>
    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse ($reports as $slug => $report)
                    <a href="{{ route('reports.bank.show', $slug) }}" class="block bg-white rounded-lg shadow hover:shadow-lg hover:-translate-y-0.5 transition p-4">
                        <p class="font-semibold text-blue-950">{{ $report['title'] }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ $report['description'] }}</p>
                    </a>
                @empty
                    <p class="text-gray-600">You do not have access to any bank report.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
