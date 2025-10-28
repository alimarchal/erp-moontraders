<div>
    <x-label for="source" value="Source" />
    <select name="filter[source]" id="source"
        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Source --</option>
        @foreach(['Email','Phone','Website','Walk-in','Social Media','Portal','Other'] as $src)
        <option value="{{ $src }}" {{ request('filter.source')===$src ? 'selected' : '' }}>{{ $src }}</option>
        @endforeach
    </select>
</div>