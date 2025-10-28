<div>
    <x-label for="harassment_only" value="Harassment" />
    <select name="filter[harassment_only]" id="harassment_only"
        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Option --</option>
        <option value="1" {{ request('filter.harassment_only')==='1' ? 'selected' : '' }}>Only Harassment</option>
    </select>
</div>