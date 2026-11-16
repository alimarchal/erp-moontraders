<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cash Collection Distributor" :showSearch="true" :showRefresh="true" backRoute="reports.index">
            <x-slot name="afterFilter">
                {{-- Help sits next to Filters, like the goods issues list --}}
                <a href="{{ route('reports.cash-collection-distributor.help') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 no-print" aria-label="Help in Urdu" title="How this report works, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </a>
            </x-slot>
            <x-slot name="actions">
                @if ($hasQuery)
                    <a href="{{ route('reports.cash-collection-distributor.export.pdf', request()->query()) }}" aria-label="Download PDF" title="Download PDF (A4)"
                        class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 no-print">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    </a>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />

    <x-status-message />

    @push('header')
        @include('reports.cash-detail._sheet-styles')
        <style>
            /* On screen the sheet is read at arm's length, so it is set larger than on paper. */
            .ccd-screen { font-size: 14px; line-height: 1.4; color: #000; }
            .ccd-screen .p2-title { font-size: 26px; }
            .ccd-screen .p2-subtitle { font-size: 16px; }
            .ccd-screen .p2-no { font-size: 20px; }
            .ccd-screen table.p2-meta { font-size: 14px; margin: 8px 0 10px; }
            .ccd-screen table.p2-meta td { padding: 3px 8px 3px 0; }
            .ccd-screen table.p2-kpis td { padding: 6px 10px; }
            .ccd-screen table.p2-kpis small { font-size: 11px; min-height: 30px; }
            .ccd-screen table.p2-kpis b { font-size: 19px; }
            .ccd-screen .p2-gap { height: 14px; }
            .ccd-screen table.p2 { font-size: 14px; line-height: 1.35; }
            .ccd-screen table.p2 th, .ccd-screen table.p2 td { padding: 3px 8px; }
            .ccd-screen table.p2 th { font-size: 12px; }
            .ccd-screen table.p2 tr.cap th { font-size: 16px; padding: 4px 2px 3px; }
            .ccd-screen table.p2 tr.cap th small { font-size: 13px; }
            .ccd-screen table.p2 .calc { font-size: 11px; }
            .ccd-screen table.p2 td.c { white-space: normal; }
            .ccd-screen .p2-note { font-size: 13px; padding: 6px 10px; }
            /* The printed-by line and signature boxes belong to the print and PDF sheets only. */
            .ccd-screen .p2-foot, .ccd-screen .p2-sign { display: none; }
            @media (max-width: 767px) { .ccd-screen { font-size: 12px; } .ccd-screen table.p2 { font-size: 12px; } }
        </style>
        <style>
            .gl-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
            @media (min-width: 900px) { .gl-filter-row { grid-template-columns: 170px minmax(200px, 1.4fr) minmax(150px, 1fr) minmax(220px, 2fr) auto; } }
            .gl-filter-row .ak-field input, .gl-filter-row .ak-field select:not([multiple]) { height: 38px; box-sizing: border-box; }
            .gl-filter-row .ak-filter-buttons { display: flex; gap: 8px; padding-top: 0; }
            .ak-filters .select2-container { width: 100% !important; display: block; }
            .ak-filters .select2-container .select2-selection--single { height: 38px; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
            .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
            html.apl-on .rg-gi-filters { border: 0 !important; border-radius: 20px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08) !important; }
            html.apl-on .rg-gi-filters .ak-field label { font-size: 13px; font-weight: 500; color: #6e6e73; text-transform: none; letter-spacing: 0; }
            html.apl-on .rg-gi-filters .ak-btn { height: 38px; border-radius: 980px !important; text-transform: none; }
            html.apl-on .rg-gi-filters .ak-btn-primary { background: #0071e3 !important; border-color: #0071e3 !important; }
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 14px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;

                    @bottom-center {
                        content: "Page " counter(page) " of " counter(pages);
                    }
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    counter-reset: page 1;
                }

                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 10px !important;
                    box-shadow: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 11px !important;
                    width: 100% !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }

                .text-green-700,
                .text-blue-700,
                .text-orange-700,
                .text-purple-700 {
                    color: #000 !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 8px !important;
                }

                .print-info {
                    font-size: 9px !important;
                    margin-top: 5px !important;
                    margin-bottom: 10px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }

                .page-footer {
                    display: none;
                }
            }

            /* Select2 Styling to match Tailwind Inputs */
            .select2-container .select2-selection--multiple {
                min-height: 42px !important;
                border-color: #d1d5db !important;
                /* gray-300 */
                border-radius: 0.375rem !important;
                /* rounded-md */
                padding-top: 4px !important;
                padding-bottom: 4px !important;
            }

            .select2-container--default.select2-container--focus .select2-selection--multiple {
                border-color: #6366f1 !important;
                /* indigo-500 */
                box-shadow: 0 0 0 1px #6366f1 !important;
            }
        </style>
    @endpush

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <section id="ccd-filter-panel" class="ak-card rg-gi-filters" aria-label="Filters" x-data="{ open: {{ $hasQuery ? 'false' : 'true' }} }" @gl-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $hasQuery ? 'display:none' : '' }}">
            <form method="GET" action="{{ route('reports.cash-collection-distributor.index') }}" class="ak-filters" x-data="{ busy: false }" @submit="busy = true">
                <div class="gl-filter-row">
                    <div class="ak-field">
                        <label for="date">Date</label>
                        <input id="date" type="date" name="date" value="{{ $date }}" required>
                    </div>
                    <div class="ak-field">
                        <label for="supplier_id">Company</label>
                        <select id="supplier_id" name="supplier_id" required class="gl-select">
                            <option value="">Select company</option>
                            @if ($canChooseCompany)
                                <option value="all" @selected($allCompanies)>All Companies</option>
                            @endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected($supplierId == $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="designation">Designation</label>
                        <select id="designation" name="designation" class="gl-select">
                            <option value="">All Designations</option>
                            @foreach ($designations as $desig)
                                <option value="{{ $desig }}" @selected($designation == $desig)>{{ $desig }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="employee_ids">Salesmen</label>
                        <select id="employee_ids" name="employee_ids[]" multiple class="gl-select">
                            @foreach ($allEmployees as $employee)
                                <option value="{{ $employee->id }}" @selected(in_array($employee->id, $employeeIds))>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Loading…</span>
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </div>

    @unless($hasQuery)
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4 no-print">
            <div class="bg-white shadow-xl sm:rounded-lg p-10 text-center text-gray-600">
                <p class="text-lg font-semibold text-gray-800">No company selected</p>
                <p class="mt-1 text-sm">Choose a company{{ $canChooseCompany ? ' (or All Companies)' : '' }}, pick the date and click Apply to load the Cash Collection Distributor.</p>
            </div>
        </div>
    @else
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4">
            <div class="overflow-x-auto">
                <div class="ccd-screen">
                    @include('reports.cash-collection-distributor._sheet')
                </div>
            </div>
        </div>
    </div>

    @endunless

    @push('scripts')
        <script>
            /** Loads the printable sheet in a hidden frame, then opens the browser print dialog for it. */
            function ccdPrintSheet(button) {
                document.getElementById('ccd-print-frame')?.remove();
                const frame = document.createElement('iframe');
                frame.id = 'ccd-print-frame';
                frame.setAttribute('aria-hidden', 'true');
                frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
                button.disabled = true;
                frame.onload = () => {
                    button.disabled = false;
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                };
                frame.src = button.dataset.printUrl;
                document.body.appendChild(frame);
            }

            // The header Print button prints the A4 sheet too, not the screen page.
            document.addEventListener('DOMContentLoaded', () => {
                const printButton = document.querySelector('header button[aria-label="Print"], button[aria-label="Print"]');
                @if ($hasQuery)
                if (printButton) {
                    printButton.dataset.printUrl = @js(route('reports.cash-collection-distributor.index', request()->query() + ['layout' => 'print']));
                    printButton.removeAttribute('onclick');
                    printButton.onclick = () => ccdPrintSheet(printButton);
                }
                @else
                printButton?.remove();
                @endif
                document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('gl-toggle-filters')); });
            });

            // Ctrl+P / Cmd+P prints the same sheet as the Print button instead of the screen page.
            document.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 'p') {
                    const button = document.querySelector('[data-print-url]');
                    if (button && !button.disabled) {
                        event.preventDefault();
                        ccdPrintSheet(button);
                    }
                }
            });

            $(document).ready(function () {
                $('#supplier_id').select2({
                    width: '100%',
                    placeholder: 'Select company'
                });

                $('#designation').select2({
                    width: '100%',
                    placeholder: 'All Designations',
                    allowClear: true
                });

                $('#employee_ids').select2({
                    width: '100%',
                    placeholder: 'Select Salesmen',
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>
