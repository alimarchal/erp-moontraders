@props(['name','label','includeUnassigned'=>false])
<div>
    <x-label :for="$name" :value="$label" />
    <select name="filter[{{ $name }}]" id="{{ $name }}"
        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">-- Select User --</option>
        @if($includeUnassigned)
        <option value="unassigned" {{ request('filter.'.$name)==='unassigned' ? 'selected' : '' }}>Unassigned</option>
        @endif
        @foreach(App\Models\User::orderBy('name')->get() as $u)
        <option value="{{ $u->id }}" {{ request('filter.'.$name)==$u->id ? 'selected' : '' }}>{{ $u->name }}</option>
        @endforeach
    </select>
</div>