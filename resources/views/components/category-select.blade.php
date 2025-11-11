<div>
    <x-label for="category" value="Category" />
    <select name="filter[category]" id="category"
        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Category --</option>
        @foreach(App\Models\ComplaintCategory::orderBy('category_name')->get() as $cat)
        <option value="{{ $cat->category_name }}" {{ request('filter.category')===$cat->category_name ? 'selected' : ''
            }}>{{ $cat->category_name }}</option>
        @endforeach
    </select>
</div>