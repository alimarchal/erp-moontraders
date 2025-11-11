<div>
    <x-label for="branch_id" value="Branch" />
    <select name="filter[branch_id]" id="branch_id"
        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select Branch --</option>
        @foreach(App\Models\Branch::orderBy('name')->get() as $branch)
        <option value="{{ $branch->id }}" {{ request('filter.branch_id')==$branch->id ? 'selected' : '' }}>{{
            $branch->name }}</option>
        @endforeach
    </select>
</div>