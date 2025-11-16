<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Tax Rate #{{ $taxRate->id }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('tax-rates.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <x-status-message class="mb-4 mt-4" />
                <div class="p-6">
                    <form>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="tax_code" value="Tax Code" />
                                <x-input id="tax_code" type="text" name="tax_code"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxRate->taxCode ? $taxRate->taxCode->tax_code . ' - ' . $taxRate->taxCode->name : 'N/A'"
                                    disabled readonly />
                            </div>

                            <div>
                                <x-label for="rate" value="Tax Rate (%)" />
                                <x-input id="rate" type="text" name="rate"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="number_format($taxRate->rate, 2) . '%'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="effective_from" value="Effective From" />
                                <x-input id="effective_from" type="text" name="effective_from"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxRate->effective_from?->format('Y-m-d') ?? 'N/A'" disabled readonly />
                            </div>

                            <div>
                                <x-label for="effective_to" value="Effective To" />
                                <x-input id="effective_to" type="text" name="effective_to"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxRate->effective_to?->format('Y-m-d') ?? 'N/A'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <x-label for="region" value="Region" />
                                <x-input id="region" type="text" name="region"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$taxRate->region ?? 'All Regions'" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-6">
                            <div class="flex items-center">
                                <input id="is_active" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $taxRate->is_active ? 'checked' : '' }} disabled>
                                <label for="is_active" class="ml-2 text-sm text-gray-700">
                                    Active
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-6">
                            <div class="text-sm text-gray-500">
                                Created: {{ $taxRate->created_at?->format('Y-m-d H:i:s') }}<br>
                                Updated: {{ $taxRate->updated_at?->format('Y-m-d H:i:s') }}
                            </div>
                            <a href="{{ route('tax-rates.edit', $taxRate) }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Edit Tax Rate
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
