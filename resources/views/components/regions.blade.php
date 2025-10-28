<div>
    {{-- It is quality rather than quantity that matters. - Lucius Seneca --}}
    <x-label for="region_id" value="Region" />
    <select name="filter[region_id]" id="region_id"
        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Region --</option>
        <?php foreach (App\Models\Region::get() as $region): ?>
        <option value="{{ $region->id }}" {{ request('filter.region_id')==$region->id ? 'selected' : '' }}>
            {{ $region->name }}
        </option>
        <?php endforeach; ?>
    </select>
</div>