{{-- View / Edit / Delete for one GRN in the redesigned lists (same permissions as the classic list). --}}
<div class="flex items-center justify-end gap-1">
    <a href="{{ route('goods-receipt-notes.show', $grn->id) }}" class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 hover:bg-blue-50" aria-label="View {{ $grn->grn_number }}">View</a>
    @if ($grn->status === 'draft')
        @can('goods-receipt-note-edit')
            <a href="{{ route('goods-receipt-notes.edit', $grn->id) }}" class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100" aria-label="Edit {{ $grn->grn_number }}">Edit</a>
        @endcan
        @can('goods-receipt-note-delete')
            <button type="button" x-data aria-label="Delete {{ $grn->grn_number }}"
                x-on:click.stop.prevent="$dispatch('open-delete-modal', { url: '{{ route('goods-receipt-notes.destroy', $grn->id) }}', number: '{{ $grn->grn_number }}' })"
                class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50">Delete</button>
        @endcan
    @endif
</div>
