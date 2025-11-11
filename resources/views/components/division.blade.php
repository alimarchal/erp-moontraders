<div>
    <!-- It is quality rather than quantity that matters. - Lucius Annaeus Seneca -->
     <x-label for="division_id" value="Division" />
    <select name="filter[division_id]" id="division_id"
            class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Division --</option>
        @foreach (App\Models\Division::get() as $division)
            <option value="{{ $division->id }}"
                {{ request('filter.division_id') == $division->id ? 'selected' : '' }}>
                {{ $division->name }}
            </option>
        @endforeach
    </select>
</div>