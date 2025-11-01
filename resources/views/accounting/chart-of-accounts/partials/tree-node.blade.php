@php
$hasChildren = $node->childrenRecursive->isNotEmpty();
@endphp

<li x-data="{
        open: {{ $hasChildren ? 'true' : 'false' }},
        toggle() { this.open = !this.open },
        expand() { this.open = true },
        collapse() { this.open = false },
        init() {
            window.addEventListener('coa-expand-all', () => this.expand());
            window.addEventListener('coa-collapse-all', () => this.collapse());
        }
    }" class="relative">
    <div class="flex items-center gap-3 py-2 px-3 rounded-md transition hover:bg-gray-300 dark:hover:bg-gray-700/50">
        @if ($hasChildren)
        <button type="button" @click.stop="toggle"
            class="size-6 flex items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-200 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6" />
            </svg>
            <svg x-show="!open" x-cloak xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
            </svg>
        </button>
        @else
        <span class="size-6 flex items-center justify-center rounded-md bg-indigo-500/10 text-indigo-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 6v12m-3-2.818 3.879 2.879c1.171.87 3.07.87 4.242 0 1.172-.87 1.172-2.281 0-3.151-.553-.41-1.27-.82-2.121-.82-.792 0-1.468.292-2.121.702-.871.56-1.472.41-1.879.115-.407-.294-.847-.732-1.121-1.206-.55-.939-.553-2.152.121-3.121.69-.989 1.739-1.449 2.879-1.449.953 0 1.836.398 2.5 1" />
            </svg>
        </span>
        @endif

        <div class="flex flex-wrap items-center gap-2 text-sm cursor-pointer" @click="toggle">
            <span class="font-mono font-semibold text-gray-700 dark:text-gray-200">{{ $node->account_code }}</span>
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $node->account_name }}</span>

            @if ($node->accountType)
            <span
                class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-200">
                {{ $node->accountType->type_name }}
            </span>
            @endif

            <span
                class="text-xs px-2 py-0.5 rounded-full {{ $node->normal_balance === 'debit' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-200' }}">
                {{ ucfirst($node->normal_balance) }}
            </span>

            <span
                class="text-xs px-2 py-0.5 rounded-full {{ $node->is_group ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-200' }}">
                {{ $node->is_group ? 'Group' : 'Posting' }}
            </span>

            <span
                class="text-xs px-2 py-0.5 rounded-full {{ $node->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-200' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200' }}">
                {{ $node->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </div>

    @if ($hasChildren)
    <ul x-show="open" x-transition x-cloak
        class="space-y-1 ms-5 ps-5 border-s border-dashed border-gray-300 dark:border-gray-600">
        @foreach ($node->childrenRecursive as $child)
        @include('accounting.chart-of-accounts.partials.tree-node', ['node' => $child])
        @endforeach
    </ul>
    @endif
</li>