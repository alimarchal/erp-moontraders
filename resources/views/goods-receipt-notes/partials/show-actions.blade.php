{{-- The password confirmations and hidden forms behind Post to Inventory and Reverse GRN, shared by every GRN view layout. --}}
<!-- Password Modal -->
<x-password-confirm-modal id="reverseGrnModal" title="Confirm GRN Reversal"
    message="WARNING: This will take the received stock out of the warehouse and post an offsetting journal entry. This action cannot be undone." errorKey="password"
    warningClass="text-red-600" confirmButtonText="Confirm Reverse"
    confirmButtonClass="bg-red-600 hover:bg-red-700" />

<x-password-confirm-modal id="postGrnPasswordModal" title="Confirm GRN Posting"
    message="Are you sure you want to post GRN {{ $grn->grn_number }} to inventory? This action cannot be undone."
    secondaryMessage="GRN نمبر: {{ $grn->grn_number }}
مصنوعات کی تفصیلات: {{ $grn->items->count() }} | کل رقم: Rs. {{ number_format($grn->items->sum('total_value_with_taxes') ?: $grn->grand_total, 2) }}"
    secondaryMessageDirection="rtl"
    errorKey="password"
    confirmButtonText="Post to Inventory" confirmButtonClass="bg-emerald-600 hover:bg-emerald-700" />

<form id="reverseGrnForm" action="{{ route('goods-receipt-notes.reverse', $grn->id) }}" method="POST" class="hidden">
    @csrf
    <input type="hidden" id="reverse_grn_password" name="password">
</form>

<form id="postGrnForm" action="{{ route('goods-receipt-notes.post', $grn->id) }}" method="POST" class="hidden">
    @csrf
    <input type="hidden" id="post_grn_password" name="password">
</form>

<script>
    function confirmReverseGrn() {
        window.showPasswordModal('reverseGrnModal');
    }

    // Listen for password confirmation event
    document.addEventListener('passwordConfirmed', function (event) {
        const { modalId, password } = event.detail;

        if (modalId === 'reverseGrnModal') {
            document.getElementById('reverse_grn_password').value = password;
            document.getElementById('reverseGrnForm').submit();
        } else if (modalId === 'postGrnPasswordModal') {
            document.getElementById('post_grn_password').value = password;
            document.getElementById('postGrnForm').submit();
        }
    });
</script>
