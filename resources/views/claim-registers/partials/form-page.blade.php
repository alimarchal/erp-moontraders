{{-- The add and edit pages of a claim: one sheet-like card with the fields, a quiet footer with Cancel and Save.
     Expects $title, $subtitle, $action, $method, $submitLabel, $suppliers and, when editing, $claimRegister. --}}
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$title" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="claim-registers.index">
            <x-slot name="actions">
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: 'entry' })" aria-haspopup="dialog" title="How claims are entered, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                    <span class="sr-only">Help</span>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="claims" :has-supplier="true" />

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 pb-16">
        @if (session('error'))
            <div class="rg-flash rg-flash-err mb-4" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="rg-flash rg-flash-err mb-4" role="alert">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="rg-card rg-form bg-white sm:rounded-lg">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="rg-form-head">
                <p>{{ $subtitle }}</p>
            </div>

            <div class="rg-form-body">
                @include('claim-registers.partials.form-fields', ['claimRegister' => $claimRegister ?? null, 'suppliers' => $suppliers])
            </div>

            <div class="rg-form-foot">
                <a href="{{ route('claim-registers.index') }}" class="rg-pill rg-pill-grey inline-flex items-center">Cancel</a>
                <button type="submit" class="rg-pill bg-indigo-600 text-white">{{ $submitLabel }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
