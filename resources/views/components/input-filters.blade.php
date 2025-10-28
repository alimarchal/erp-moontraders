@props(['name', 'label', 'type' => 'text'])

<div>
    <x-label for="{{ $name }}" value="{{ __($label) }}" />
    <x-input 
        type="{{ $type }}"
        name="filter[{{ $name }}]"
        id="{{ $name }}"
        value="{{ request('filter.' . $name) }}"
        placeholder="Search by {{ $label }}"
        {{ $attributes }}
        {{ $attributes->merge(['class' => 'block mt-1 w-full']) }}
    />
</div>