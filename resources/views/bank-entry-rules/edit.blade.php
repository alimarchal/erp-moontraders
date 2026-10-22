<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Edit Auto-classify Rule" :crumbs="['Auto-classify rules' => route('bank-entry-rules.index'), 'Edit' => null]">
            <a href="{{ route('bank-entry-rules.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <x-status-message />
        <x-validation-errors />
        <form method="POST" action="{{ route('bank-entry-rules.update', $rule) }}" class="bk-stack">
            @csrf
            @method('PUT')
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Auto-classify rule</h2></div>
                <div class="uf-body">
                    @include('bank-entry-rules._form')
                </div>
            </section>
            <div class="bk-row" style="justify-content:space-between">
                <a href="{{ route('bank-entry-rules.index') }}" class="ak-btn ak-btn-ghost">Cancel</a>
                <button class="ak-btn ak-btn-primary">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
