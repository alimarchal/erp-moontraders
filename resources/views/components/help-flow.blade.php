{{-- One step-by-step flow of App\Support\HelpFlows, drawn as numbered steps joined by arrows. Lives inside a .bh (Urdu, RTL) block. --}}
@props(['flow'])

@php($definition = \App\Support\HelpFlows::get($flow))

@if ($definition)
    <div id="flow-{{ $flow }}" class="hf" {{ $attributes }}>
        <h3 class="hf-title"><span class="en">Flow</span> {{ $definition['title'] }}</h3>
        <ol class="hf-steps">
            @foreach ($definition['steps'] as $index => [$title, $note, $screen])
                <li class="hf-step">
                    <span class="hf-num" aria-hidden="true">{{ $index + 1 }}</span>
                    <span class="hf-body">
                        <b>{{ $title }}</b>
                        <small>{{ $note }}</small>
                        @if ($screen)<span class="en">{{ $screen }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ol>
    </div>
@endif
