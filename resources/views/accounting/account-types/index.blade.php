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
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
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
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
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
    <x-data-table :items="$accountTypes" :headers="[
            ['label' => 'ID', 'align' => 'text-center'],
            ['label' => 'Type Name', 'align' => 'text-left'],
            ['label' => 'Report Group', 'align' => 'text-left'],
            ['label' => 'Description', 'align' => 'text-left'],
            ['label' => 'Actions', 'align' => 'text-center print:hidden'],
        ]" emptyMessage="No account types found." :emptyRoute="route('account-types.create')"
        emptyLinkText="Add a new account type">

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
                {{ Str::limit($accountType->description, 200) ?? '-' }}
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <!-- View Button -->
                    <a href="{{ route('account-types.show', ['account_type' => $accountType->id]) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                        title="View">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </a>
                    <!-- Edit Button -->
                    <a href="{{ route('account-types.edit', ['account_type' => $accountType->id]) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                        title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </a>
                </div>
            </td>
        </tr>
        @endforeach
    </x-data-table>
</x-app-layout>