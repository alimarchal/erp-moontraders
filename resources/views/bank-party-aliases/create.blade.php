<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="New Party Alias" :crumbs="['Party aliases' => route('bank-party-aliases.index'), 'Add' => null]">
            <a href="{{ route('bank-party-aliases.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page" style="max-width:48rem">
        <x-status-message />
        <x-validation-errors />
        <form method="POST" action="{{ route('bank-party-aliases.store') }}" class="bk-stack">
            @csrf
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Party alias</h2></div>
                <div class="uf-body">
                    @include('bank-party-aliases._form')
                </div>
            </section>
            <div class="bk-row" style="justify-content:space-between">
                <a href="{{ route('bank-party-aliases.index') }}" class="ak-btn ak-btn-ghost">Cancel</a>
                <button class="ak-btn ak-btn-primary">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
