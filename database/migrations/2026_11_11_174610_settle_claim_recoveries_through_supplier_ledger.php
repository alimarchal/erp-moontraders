<?php

use App\Services\ClaimRegisterService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * A claim recovery is the claim adjusted in the supplier's ledger, and the Supplier Ledger Register's claim adjustment
 * books it (Dr Creditors / Cr Pending Claims). Claim register recoveries posted until now each booked the same money
 * again as received in HBL Main, so Pending Claims was taken off twice and the bank showed money that never came in.
 * Their journals are reversed on their own date. The claim register, the supplier ledger register and every
 * company's closing balance stay exactly as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $result = app(ClaimRegisterService::class)->settleRecoveriesThroughLedger();

        Log::info('Claim recoveries settled through the Supplier Ledger Register', $result);
    }

    public function down(): void
    {
        // The reversals are posted journals and stay.
    }
};
