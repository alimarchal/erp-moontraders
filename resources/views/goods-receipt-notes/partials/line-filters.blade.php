{{-- The lines heading, error / warning filters and search, for the Option C and D GRN forms. --}}
<div class="flex flex-wrap items-center gap-2">
    <h3 class="mr-1 text-sm font-bold text-gray-900">{{ $heading ?? 'Lines' }} <span class="font-semibold text-gray-500" x-text="'· ' + items.length"></span></h3>
    <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter lines">
        <button type="button" @click="lineFilter = 'all'" :aria-pressed="lineFilter === 'all'"
            :class="lineFilter === 'all' ? 'bg-gray-900 text-white ring-gray-900' : 'bg-white text-gray-700 ring-gray-200'"
            class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1">All</button>
        <button type="button" x-show="issueCounts.error > 0" @click="lineFilter = 'error'" :aria-pressed="lineFilter === 'error'"
            :class="lineFilter === 'error' ? 'bg-red-700 text-white ring-red-700' : 'bg-red-50 text-red-800 ring-red-200'"
            class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"><span x-text="issueCounts.error"></span> errors</button>
        <button type="button" x-show="issueCounts.warning > 0" @click="lineFilter = 'warning'" :aria-pressed="lineFilter === 'warning'"
            :class="lineFilter === 'warning' ? 'bg-amber-600 text-white ring-amber-600' : 'bg-amber-50 text-amber-900 ring-amber-200'"
            class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"><span x-text="issueCounts.warning"></span> warnings</button>
    </div>
    <div x-show="items.length > 6" class="w-full sm:ml-auto sm:w-56">
        <label for="grn-line-search-{{ $searchId ?? 'list' }}" class="sr-only">Find a line</label>
        <input id="grn-line-search-{{ $searchId ?? 'list' }}" type="search" x-model="lineSearch" placeholder="Find product or line no."
            class="block h-8 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>
</div>
