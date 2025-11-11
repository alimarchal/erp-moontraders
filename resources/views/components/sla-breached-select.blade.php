<div>
    <x-label for="sla_breached" value="SLA Status" />
    <select name="filter[sla_breached]" id="sla_breached"
        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select SLA Status --</option>
        <option value="1" {{ request('filter.sla_breached')==='1' ? 'selected' : '' }}>SLA Breached</option>
        <option value="0" {{ request('filter.sla_breached')==='0' ? 'selected' : '' }}>Within SLA</option>
    </select>
</div>