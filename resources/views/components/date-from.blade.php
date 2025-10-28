<div>
    <x-label for="date_from" value="{{ __('Date From') }}" />
    <x-input type="date" name="filter[date_from]" id="date_from" value="{{ request('filter.date_from') }}"
        class="block mt-1 w-full" />
</div>