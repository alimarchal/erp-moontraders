<div>
    <x-label for="status" value="Status" />
    <select name="filter[status]" id="status"
        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Status --</option>
        @php($list = App\Models\ComplaintStatusType::active()->orderBy('name')->pluck('name')->toArray())
        @foreach($list as $s)
        <option value="{{ $s }}" {{ request('filter.status')===$s ? 'selected' : '' }}>{{ $s }}</option>
        @endforeach
    </select>
</div>