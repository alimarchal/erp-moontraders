<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('reports.index') }}">Reports</a><span aria-hidden="true">›</span>
                    <span>Cash Collection Distributor</span>
                </nav>
                <h1 class="ak-title" style="margin:6px 0 0">Cash Collection Distributor</h1>
            </div>
            <div class="ak-head-actions no-print">
                <button type="button" id="toggle" class="ak-btn ak-btn-outline" title="Show or hide the filters" aria-label="Filters">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                </button>
                @if($hasQuery)
                    <button type="button" class="ak-btn ak-btn-outline" data-print-url="{{ route('reports.cash-collection-distributor.index', request()->query() + ['layout' => 'print']) }}" onclick="ccdPrintSheet(this)" title="Print the report sheet (A4)" aria-label="Print">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.83c-.24.03-.48.06-.72.1m.72-.1a42.4 42.4 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.17c.24.03.48.06.72.1m-.72-.1L17.66 18m0 0 .23 2.5a1.13 1.13 0 0 1-1.12 1.24H7.23a1.13 1.13 0 0 1-1.12-1.24L6.34 18m11.32 0h1.09A2.25 2.25 0 0 0 21 15.75V9.46c0-1.08-.77-2.01-1.84-2.18a48 48 0 0 0-1.41-.2M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.46c0-1.08.77-2.01 1.84-2.18.47-.07.94-.14 1.41-.2m0 0a48.6 48.6 0 0 1 11.5 0m-11.5 0V3.38c0-.62.5-1.13 1.13-1.13h9.24c.63 0 1.13.5 1.13 1.13v3.7M18 10.5h.01" /></svg>
                    </button>
                    <a href="{{ route('reports.cash-collection-distributor.export.pdf', request()->query()) }}" class="ak-btn ak-btn-outline" title="Download PDF" aria-label="Download PDF">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    </a>
                @endif
                <a href="javascript:window.location.reload();" class="ak-btn ak-btn-outline" title="Refresh" aria-label="Refresh">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.02 9.35h4.99M2.99 19.64v-4.99m0 0h4.99m-4.99 0 3.18 3.18a8.25 8.25 0 0 0 13.8-3.7M4.03 9.87a8.25 8.25 0 0 1 13.8-3.7l3.18 3.18m0-4.99v4.99" /></svg>
                </a>
                <a href="{{ route('reports.cash-collection-distributor.help') }}" class="ak-btn ak-btn-outline" title="How this report works" aria-label="How this report works">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>
                </a>
                <a href="{{ route('reports.index') }}" class="ak-btn ak-btn-outline" title="Back to Reports" aria-label="Back to Reports">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                </a>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

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
            .ccd-screen .p2-note { font-size: 13px; padding: 6px 10px; }
            /* The printed-by line and signature boxes belong to the print and PDF sheets only. */
            .ccd-screen .p2-foot, .ccd-screen .p2-sign { display: none; }
            @media (max-width: 767px) { .ccd-screen { font-size: 12px; } .ccd-screen table.p2 { font-size: 12px; } }
        </style>
        <style>
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

    <x-filter-section :action="route('reports.cash-collection-distributor.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4">
            {{-- Date --}}
            <div>
                <x-label for="date" value="{{ __('Date') }}" />
                <x-input id="date" class="block mt-1 w-full" type="date" name="date" :value="$date" required />
            </div>
            
            {{-- Company Filter --}}
            <div>
                <x-label for="supplier_id" value="{{ __('Company') }}" />
                <select id="supplier_id" name="supplier_id" required class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Select company</option>
                    @if($canChooseCompany)
                        <option value="all" {{ $allCompanies ? 'selected' : '' }}>All Companies</option>
                    @endif
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            {{-- Designation Filter --}}
            <div>
                 <x-label for="designation" value="{{ __('Designation') }}" />
                 <select id="designation" name="designation" class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Designations</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig }}" {{ $designation == $desig ? 'selected' : '' }}>
                            {{ $desig }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            {{-- Multi-Select Salesman --}}
            <div>
                <x-label for="employee_ids" value="Salesman (Multi-Select)" class="pb-1" />
                <select id="employee_ids" name="employee_ids[]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($allEmployees as $employee)
                        <option value="{{ $employee->id }}" {{ in_array($employee->id, $employeeIds) ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

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
