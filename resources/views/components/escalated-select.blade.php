<div>
    <x-label for="escalated" value="Escalated" />
    <select name="filter[escalated]" id="escalated"
        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Option --</option>
        <option value="1" {{ request('filter.escalated')==='1' ? 'selected' : '' }}>Yes</option>
        <option value="0" {{ request('filter.escalated')==='0' ? 'selected' : '' }}>No</option>
    </select>
</div>