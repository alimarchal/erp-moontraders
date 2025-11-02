<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
                    Cost Center: {{ $costCenter->code }}
                </h2>
                <p class="text-sm text-gray-500">
                    {{ $costCenter->name }} · {{ $typeOptions[$costCenter->type] ?? ucfirst(str_replace('_', ' ', $costCenter->type)) }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('cost-centers.edit', $costCenter->id) }}"
                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Edit
                </a>
                <form method="POST" action="{{ route('cost-centers.destroy', $costCenter->id) }}"
                    onsubmit="return confirm('Delete this cost center?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Delete
                    </button>
                </form>
                <a href="{{ route('cost-centers.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-status-message class="shadow-md" />

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm text-gray-700 dark:text-gray-200">
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase">Code</span>
                        <span class="text-base font-semibold">{{ $costCenter->code }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase">Name</span>
                        <span class="text-base font-semibold">{{ $costCenter->name }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase">Type</span>
                        <span>{{ $typeOptions[$costCenter->type] ?? ucfirst(str_replace('_', ' ', $costCenter->type)) }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase">Parent</span>
                        <span>{{ $costCenter->parent ? $costCenter->parent->code . ' · ' . $costCenter->parent->name : '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase">Status</span>
                        <span
                            class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $costCenter->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-700' }}">
                            {{ $costCenter->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase">Children</span>
                        <span>{{ $costCenter->children->count() }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase">Start Date</span>
                        <span>{{ optional($costCenter->start_date)->format('d-m-Y') ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase">End Date</span>
                        <span>{{ optional($costCenter->end_date)->format('d-m-Y') ?? '—' }}</span>
                    </div>
                </div>

                @if ($costCenter->description)
                    <div class="px-6 pb-6">
                        <span class="block text-xs font-semibold text-gray-500 uppercase mb-1">Description</span>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            {{ $costCenter->description }}
                        </p>
                    </div>
                @endif
            </div>

            @if ($costCenter->children->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-3">
                            Child Cost Centers
                        </h3>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            @foreach ($costCenter->children as $child)
                                <li class="py-2 flex justify-between items-center">
                                    <div>
                                        <div class="font-semibold">{{ $child->code }} · {{ $child->name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $typeOptions[$child->type] ?? ucfirst(str_replace('_', ' ', $child->type)) }}
                                        </div>
                                    </div>
                                    <a href="{{ route('cost-centers.show', $child->id) }}"
                                        class="inline-flex items-center px-3 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800">
                                        View
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
