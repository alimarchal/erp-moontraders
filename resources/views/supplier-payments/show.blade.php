<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
                Payment: {{ $supplierPayment->payment_number }}
            </h2>

            <div class="flex justify-center items-center space-x-2 no-print">
                @if ($supplierPayment->status === 'draft')
                    @can('supplier-payment-edit')
                        <a href="{{ route('supplier-payments.edit', $supplierPayment->id) }}"
                            class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                            <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </a>
                    @endcan
                    @can('supplier-payment-post')
                        <form id="postPaymentForm" action="{{ route('supplier-payments.post', $supplierPayment->id) }}"
                            method="POST" onsubmit="return confirmPostPayment();" class="inline-block">
                            @csrf
                            <input type="hidden" id="post_password" name="password" value="">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                                <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Post Payment
                            </button>
                        </form>
                    @endcan
                    @can('supplier-payment-delete')
                        <form action="{{ route('supplier-payments.destroy', $supplierPayment->id) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to delete this payment?');" class="inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Delete
                            </button>
                        </form>
                    @endcan
                @elseif ($supplierPayment->status === 'posted')
                    @can('supplier-payment-reverse')
                        <form action="{{ route('supplier-payments.reverse', $supplierPayment->id) }}" method="POST"
                            onsubmit="return confirmReverse();" class="inline-block">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 transition">
                                <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                </svg>
                                Reverse Payment
                            </button>
                            <input type="hidden" name="password" id="reversePassword">
                        </form>
                    @endcan
                @endif

                <button onclick="window.print();"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                    </svg>
                </button>

                <a href="{{ route('supplier-payments.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
            </div>
        </div>
    </x-slot>

    @push('header')
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

                .text-emerald-600 {
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

                .header-table {
                    font-size: 11px !important;
                }
            }
        </style>
    @endpush

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md no-print" />

            <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
                <div class="overflow-x-auto">

                    <p class="text-center font-extrabold mb-2">
                        {{ config('app.name') }}<br>
                        Supplier Payment Voucher<br>
                        <span class="print-only print-info text-xs text-center">
                            Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                        </span>
                    </p>

                    <table class="header-table w-full mb-4" style="border-collapse: collapse; font-size: 13px;">
                        <tr>
                            <td class="py-1 px-2 font-semibold" style="width: 15%;">Payment #:</td>
                            <td class="py-1 px-2 font-bold" style="width: 35%;">{{ $supplierPayment->payment_number }}
                            </td>
                            <td class="py-1 px-2 font-semibold" style="width: 15%;">Payment Date:</td>
                            <td class="py-1 px-2" style="width: 35%;">
                                {{ \Carbon\Carbon::parse($supplierPayment->payment_date)->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 px-2 font-semibold">Supplier:</td>
                            <td class="py-1 px-2 font-bold">{{ $supplierPayment->supplier->supplier_name }}</td>
                            <td class="py-1 px-2 font-semibold">Status:</td>
                            <td class="py-1 px-2">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $supplierPayment->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                    {{ $supplierPayment->status === 'posted' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $supplierPayment->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $supplierPayment->status === 'bounced' ? 'bg-orange-100 text-orange-800' : '' }}
                                    {{ $supplierPayment->status === 'reversed' ? 'bg-purple-100 text-purple-800' : '' }}">
                                    {{ ucfirst($supplierPayment->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1 px-2 font-semibold">Payment Method:</td>
                            <td class="py-1 px-2">{{ ucfirst(str_replace('_', ' ', $supplierPayment->payment_method)) }}
                            </td>
                            <td class="py-1 px-2 font-semibold">Amount:</td>
                            <td class="py-1 px-2 font-bold text-emerald-600">₨
                                {{ number_format($supplierPayment->amount, 2) }}</td>
                        </tr>
                        @if ($supplierPayment->bankAccount)
                            <tr>
                                <td class="py-1 px-2 font-semibold">Bank Account:</td>
                                <td class="py-1 px-2">{{ $supplierPayment->bankAccount->account_name }}</td>
                                <td class="py-1 px-2 font-semibold">Account #:</td>
                                <td class="py-1 px-2">{{ $supplierPayment->bankAccount->account_number }}</td>
                            </tr>
                        @endif
                        @if ($supplierPayment->reference_number)
                            <tr>
                                <td class="py-1 px-2 font-semibold">Reference #:</td>
                                <td class="py-1 px-2">{{ $supplierPayment->reference_number }}</td>
                                <td class="py-1 px-2"></td>
                                <td class="py-1 px-2"></td>
                            </tr>
                        @endif
                        <tr>
                            <td class="py-1 px-2 font-semibold">Created By:</td>
                            <td class="py-1 px-2">{{ $supplierPayment->createdBy->name ?? 'N/A' }}
                                <span
                                    class="text-xs text-gray-500">{{ $supplierPayment->created_at->format('d M Y H:i') }}</span>
                            </td>
                            @if ($supplierPayment->posted_at)
                                <td class="py-1 px-2 font-semibold">Posted By:</td>
                                <td class="py-1 px-2">{{ $supplierPayment->postedBy->name ?? 'N/A' }}
                                    <span
                                        class="text-xs text-gray-500">{{ $supplierPayment->posted_at->format('d M Y H:i') }}</span>
                                </td>
                            @else
                                <td class="py-1 px-2"></td>
                                <td class="py-1 px-2"></td>
                            @endif
                        </tr>
                        @if ($supplierPayment->reversed_at)
                            <tr>
                                <td class="py-1 px-2 font-semibold">Reversed By:</td>
                                <td class="py-1 px-2">{{ $supplierPayment->reversedBy->name ?? 'N/A' }}
                                    <span
                                        class="text-xs text-gray-500">{{ $supplierPayment->reversed_at->format('d M Y H:i') }}</span>
                                </td>
                                <td class="py-1 px-2"></td>
                                <td class="py-1 px-2"></td>
                            </tr>
                        @endif
                    </table>

                    @if ($supplierPayment->description)
                        <div class="mb-4">
                            <span class="font-semibold text-gray-700">Description:</span>
                            <span class="text-sm text-gray-700">{{ $supplierPayment->description }}</span>
                        </div>
                    @endif

                    {{-- GRN Allocations --}}
                    @if ($supplierPayment->grnAllocations->count() > 0)
                        <p class="text-center font-extrabold mt-4 mb-2">GRN Allocations</p>

                        <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th>GRN Number</th>
                                    <th class="text-center">GRN Date</th>
                                    <th class="text-right">GRN Amount</th>
                                    <th class="text-right">Allocated Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($supplierPayment->grnAllocations as $allocation)
                                    <tr>
                                        <td style="vertical-align: middle;">
                                            <a href="{{ route('goods-receipt-notes.show', $allocation->grn_id) }}"
                                                class="text-blue-600 hover:text-blue-800 font-semibold">
                                                {{ $allocation->grn->grn_number }}
                                            </a>
                                        </td>
                                        <td class="text-center" style="vertical-align: middle;">
                                            {{ \Carbon\Carbon::parse($allocation->grn->receipt_date)->format('d M Y') }}
                                        </td>
                                        <td class="text-right tabular-nums" style="vertical-align: middle;">
                                            {{ number_format($allocation->grn->grand_total, 2) }}
                                        </td>
                                        <td class="text-right tabular-nums font-semibold" style="vertical-align: middle;">
                                            {{ number_format($allocation->allocated_amount, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td colspan="3" class="text-right px-2 py-1">Total Allocated</td>
                                    <td class="text-right tabular-nums px-2 py-1">
                                        ₨ {{ number_format($supplierPayment->grnAllocations->sum('allocated_amount'), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    @else
                        <p class="text-center font-extrabold mt-4 mb-2">GRN Allocations</p>
                        <p class="text-gray-500 text-center py-4">No GRN allocations found for this payment.</p>
                    @endif

                    {{-- Journal Entry --}}
                    @if ($supplierPayment->journalEntry)
                        <p class="text-center font-extrabold mt-6 mb-1">Journal Entry Details</p>
                        <p class="text-center text-sm text-gray-600 mb-2">
                            Entry:
                            <a href="{{ route('journal-entries.show', $supplierPayment->journalEntry->id) }}"
                                class="text-blue-600 hover:text-blue-800 font-semibold">
                                {{ $supplierPayment->journalEntry->entry_number }}
                            </a>
                            | Date: {{ \Carbon\Carbon::parse($supplierPayment->journalEntry->entry_date)->format('d M Y') }}
                        </p>

                        <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($supplierPayment->journalEntry->details->sortBy('line_no') as $detail)
                                    <tr>
                                        <td style="vertical-align: middle;">
                                            <span class="font-semibold">{{ $detail->account->account_code }}</span>
                                            <br>
                                            <span class="text-xs text-gray-500">{{ $detail->account->account_name }}</span>
                                        </td>
                                        <td style="vertical-align: middle;">{{ $detail->description }}</td>
                                        <td class="text-right tabular-nums font-semibold" style="vertical-align: middle;">
                                            @if ($detail->debit > 0)
                                                {{ number_format($detail->debit, 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right tabular-nums font-semibold" style="vertical-align: middle;">
                                            @if ($detail->credit > 0)
                                                {{ number_format($detail->credit, 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td colspan="2" class="text-right px-2 py-1">Totals</td>
                                    <td class="text-right tabular-nums px-2 py-1">
                                        ₨ {{ number_format($supplierPayment->journalEntry->details->sum('debit'), 2) }}
                                    </td>
                                    <td class="text-right tabular-nums px-2 py-1">
                                        ₨ {{ number_format($supplierPayment->journalEntry->details->sum('credit'), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif

                </div>
            </div>
        </div>
    </div>

    <!-- Password Modals -->
    <x-password-confirm-modal id="postPasswordModal" title="Confirm Payment Posting"
        message="This will create accounting journal entries and cannot be undone." confirmButtonText="Confirm Post"
        confirmButtonClass="bg-emerald-600 hover:bg-emerald-700" />

    <x-password-confirm-modal id="reversePasswordModal" title="Confirm Payment Reversal"
        message="WARNING: This will reverse the payment and create a reversing journal entry."
        warningClass="text-red-600" confirmButtonText="Confirm Reverse"
        confirmButtonClass="bg-orange-600 hover:bg-orange-700" />

    <script>
        function confirmPostPayment() {
            if (!confirm('Are you sure you want to POST this payment? This will create accounting journal entries and cannot be undone.')) {
                return false;
            }

            window.showPasswordModal('postPasswordModal');
            return false;
        }

        function confirmReverse() {
            window.showPasswordModal('reversePasswordModal');
            return false;
        }

        // Listen for password confirmation events
        document.addEventListener('passwordConfirmed', function (event) {
            const { modalId, password } = event.detail;

            if (modalId === 'postPasswordModal') {
                document.getElementById('post_password').value = password;
                document.getElementById('postPaymentForm').submit();
            } else if (modalId === 'reversePasswordModal') {
                document.getElementById('reversePassword').value = password;
                document.querySelector('form[action*="reverse"]').submit();
            }
        });
    </script>
</x-app-layout>