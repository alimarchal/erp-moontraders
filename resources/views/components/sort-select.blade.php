<div>
    <x-label for="sort" value="Sort By" />
    @php($options = [
    '' => '-- Select Sort --',
    'id' => 'ID (Ascending)', '-id' => 'ID (Descending)',
    'complaint_number' => 'Complaint Number (A-Z)', '-complaint_number' => 'Complaint Number (Z-A)',
    'title' => 'Title (A-Z)', '-title' => 'Title (Z-A)',
    'status' => 'Status (A-Z)', '-status' => 'Status (Z-A)',
    'priority' => 'Priority (A-Z)', '-priority' => 'Priority (Z-A)',
    'created_at' => 'Created (Oldest)', '-created_at' => 'Created (Latest)',
    'updated_at' => 'Updated (Oldest)', '-updated_at' => 'Updated (Latest)',
    'assigned_at' => 'Assigned (Oldest)', '-assigned_at' => 'Assigned (Latest)',
    'resolved_at' => 'Resolved (Oldest)', '-resolved_at' => 'Resolved (Latest)',
    'expected_resolution_date' => 'Expected Resolution (Earliest)', '-expected_resolution_date' => 'Expected Resolution
    (Latest)'
    ])
    <select name="sort" id="sort"
        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @foreach($options as $val => $label)
        <option value="{{ $val }}" {{ request('sort')===$val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>