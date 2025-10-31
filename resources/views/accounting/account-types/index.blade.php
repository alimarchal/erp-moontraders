<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Account Types" :createRoute="route('account-types.create')" createLabel="Add Account Type"
            :showSearch="true" :showRefresh="true" backRoute="dashboard" />
    </x-slot>

    <!-- FILTER SECTION -->
    <x-filter-section :action="route('account-types.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Filter by Type Name (Dropdown) -->
            <div>
                <x-label for="filter_type_name" value="Type Name" />
                <select name="filter[type_name]" id="filter_type_name"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Type Names</option>
                    @foreach($typeNames as $typeName)
                    <option value="{{ $typeName }}" {{ request('filter.type_name')==$typeName ? 'selected' : '' }}>
                        {{ $typeName }}
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter by Report Group (Dropdown) -->
            <div>
                <x-label for="filter_report_group" value="Report Group" />
                <select name="filter[report_group]" id="filter_report_group"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Report Groups</option>
                    @foreach($reportGroups as $group)
                    <option value="{{ $group }}" {{ request('filter.report_group')==$group ? 'selected' : '' }}>
                        {{ $group }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <!-- TABLE SECTION -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 pb-16">
        <x-status-message />
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">

            @if ($accountTypes->count() > 0)
            <div class="relative overflow-x-auto rounded-lg">
                <table class="min-w-max w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-green-800 text-white uppercase text-sm">
                            <th class="py-2 px-2 text-center">ID</th>
                            <th class="py-2 px-2 text-left">Type Name</th>
                            <th class="py-2 px-2 text-left">Report Group</th>
                            <th class="py-2 px-2 text-left">Description</th>
                            <th class="py-2 px-2 text-center print:hidden">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-black text-md leading-normal font-extrabold">
                        @foreach ($accountTypes as $index => $accountType)
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="py-1 px-2 text-center">
                                {{ $accountTypes->firstItem() + $index }}
                            </td>
                            <td class="py-1 px-2 text-left">
                                {{ $accountType->type_name }}
                            </td>
                            <td class="py-1 px-2 text-left">
                                {{ $accountType->report_group ?? '-' }}
                            </td>
                            <td class="py-1 px-2 text-left">
                                <div class="w-96 break-words leading-relaxed">
                                    {{ Str::limit($accountType->description, 50) ?? '-' }}
                                </div>
                            </td>
                            <td class="py-1 px-2 text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="{{ route('account-types.show', ['account_type' => $accountType->id]) }}"
                                        class="inline-flex items-center px-3 py-1 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                        View
                                    </a>
                                    <a href="{{ route('account-types.edit', ['account_type' => $accountType->id]) }}"
                                        class="inline-flex items-center px-3 py-1 bg-blue-800 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-2 py-2">
                {{ $accountTypes->links() }}
            </div>
            @else
            <p class="text-gray-700 dark:text-gray-300 text-center py-4">
                No account types found.
                <a href="{{ route('account-types.create') }}" class="text-blue-600 hover:underline">
                    Add a new account type
                </a>.
            </p>
            @endif
        </div>
    </div>
</x-app-layout>