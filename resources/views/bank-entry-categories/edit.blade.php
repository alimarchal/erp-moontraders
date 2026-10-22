<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Edit Entry Category" :crumbs="['Categories' => route('bank-entry-categories.index'), 'Edit' => null]">
            <a href="{{ route('bank-entry-categories.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page" style="max-width:48rem">
        <x-status-message />
        <x-validation-errors />
        <form method="POST" action="{{ route('bank-entry-categories.update', $category) }}" class="bk-stack">
            @csrf
            @method('PUT')
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Category</h2></div>
                <div class="uf-body">
                    @include('bank-entry-categories._form')
                </div>
            </section>
            <div class="bk-row" style="justify-content:space-between">
                <a href="{{ route('bank-entry-categories.index') }}" class="ak-btn ak-btn-ghost">Cancel</a>
                <button class="ak-btn ak-btn-primary">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
