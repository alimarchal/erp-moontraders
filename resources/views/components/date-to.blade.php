<div>
    <x-label for="date_to" value="{{ __('Date To') }}" />
    <x-input type="date" name="filter[date_to]" id="date_to" value="{{ request('filter.date_to') }}"
        class="block mt-1 w-full" />
</div>