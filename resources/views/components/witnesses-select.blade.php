<div>
    <x-label for="has_witnesses" value="Witnesses" />
    <select name="filter[has_witnesses]" id="has_witnesses"
        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Option --</option>
        <option value="1" {{ request('filter.has_witnesses')==='1' ? 'selected' : '' }}>Has</option>
        <option value="0" {{ request('filter.has_witnesses')==='0' ? 'selected' : '' }}>None</option>
    </select>
</div>