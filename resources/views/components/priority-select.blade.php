<div>
    <x-label for="priority" value="Priority" />
    <select name="filter[priority]" id="priority"
        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Priority --</option>
        @foreach(['Low','Medium','High','Critical'] as $p)
        <option value="{{ $p }}" {{ request('filter.priority')===$p ? 'selected' : '' }}>{{ $p }}</option>
        @endforeach
    </select>
</div>