<div>
    <x-label for="harassment_confidential" value="Confidential" />
    <select name="filter[harassment_confidential]" id="harassment_confidential"
        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Option --</option>
        <option value="1" {{ request('filter.harassment_confidential')==='1' ? 'selected' : '' }}>Yes</option>
        <option value="0" {{ request('filter.harassment_confidential')==='0' ? 'selected' : '' }}>No</option>
    </select>
</div>