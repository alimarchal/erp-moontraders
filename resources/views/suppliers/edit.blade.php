<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Edit Supplier: {{ $supplier->supplier_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('suppliers.help') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 mr-2">How it works</a>
            <a href="{{ route('suppliers.index') }}" aria-label="Back" title="Back" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('suppliers.partials.form-styles')

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />
                    <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="ev-form">
                        @csrf
                        @method('PUT')
                        @include('suppliers.partials.form-fields', ['currencyOptions' => $currencyOptions, 'accountOptions' => $accountOptions])
                        <div class="ev-form-foot">
                            <a href="{{ route('suppliers.index') }}" class="ak-btn ak-btn-outline">Cancel <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:15px">منسوخ کریں</span></a>
                            <button type="submit" class="ak-btn ak-btn-primary">Save changes <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:15px">تبدیلیاں محفوظ کریں</span></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
