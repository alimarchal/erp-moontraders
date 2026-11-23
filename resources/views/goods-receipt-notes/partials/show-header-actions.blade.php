{{-- The action buttons of the redesigned GRN views (same permissions as the classic view). --}}
    @if (\App\Http\Controllers\GoodsReceiptNoteController::canViewHelp(auth()->user()))
        <a href="{{ route('goods-receipt-notes.help') }}" class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">How GRN works</a>
    @endif
    <button type="button" onclick="window.print()" class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Print</button>
    @if ($isDraft)
        @can('goods-receipt-note-edit')
            <a href="{{ route('goods-receipt-notes.edit', $grn->id) }}" class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50">Edit</a>
        @endcan
        @can('goods-receipt-note-post')
            <button type="button" onclick="window.showPasswordModal('postGrnPasswordModal')" class="inline-flex h-9 items-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700">Post to Inventory</button>
        @endcan
    @endif
    @if ($isPosted)
        @role('super-admin')
            <a href="{{ route('goods-receipt-notes.edit-special', $grn->id) }}" class="inline-flex h-9 items-center rounded-lg bg-amber-50 px-3 text-sm font-semibold text-amber-900 ring-1 ring-amber-300 hover:bg-amber-100">GRN Correction</a>
        @endrole
        @can('goods-receipt-note-reverse')
            <button type="button" onclick="confirmReverseGrn()" @if ($reversalRefusal) disabled title="{{ $reversalRefusal }}" @endif
                class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-red-700 ring-1 ring-red-300 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50">Reverse GRN</button>
        @endcan
    @endif
    <a href="{{ route('goods-receipt-notes.index') }}" aria-label="Back to goods receipt notes" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-950 text-white hover:bg-blue-900">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
    </a>
