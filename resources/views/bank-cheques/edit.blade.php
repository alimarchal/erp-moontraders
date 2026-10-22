<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Edit Cheque" :crumbs="['Cheque register' => route('bank-cheques.index'), 'Edit' => null]">
            <a href="{{ route('bank-cheques.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16" style="display:flex;flex-direction:column;gap:20px;padding-top:16px">
        <x-status-message />
        <x-validation-errors />
        <form method="POST" action="{{ route('bank-cheques.update', $cheque) }}" class="bk-stack">
            @csrf
            @method('PUT')
            <section class="uf-card">
                <div class="uf-card-head"><h2 class="uf-card-title">Cheque</h2></div>
                <div class="uf-body">
                    @include('bank-cheques._form')
                </div>
            </section>
            <div class="bk-row" style="justify-content:space-between">
                <a href="{{ route('bank-cheques.index') }}" class="ak-btn ak-btn-ghost">Cancel</a>
                <button class="ak-btn ak-btn-primary">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
