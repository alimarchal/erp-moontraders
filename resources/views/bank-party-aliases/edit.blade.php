<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Edit Party Alias" :crumbs="['Party aliases' => route('bank-party-aliases.index'), 'Edit' => null]">
            <a href="{{ route('bank-party-aliases.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <x-status-message />
        <x-validation-errors />
        <form method="POST" action="{{ route('bank-party-aliases.update', $alias) }}" class="bk-stack">
            @csrf
            @method('PUT')
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
