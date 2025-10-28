<div>
    <x-label for="harassment_sub_category" value="Harassment Sub Category" />
    @php($subs = ['Verbal','Physical','Sexual','Discriminatory','Bullying','Cyber','Retaliation','Other'])
    <select name="filter[harassment_sub_category]" id="harassment_sub_category"
        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Sub Category --</option>
        @foreach($subs as $sub)
        <option value="{{ $sub }}" {{ request('filter.harassment_sub_category')===$sub ? 'selected' : '' }}>{{ $sub }}
        </option>
        @endforeach
    </select>
</div>