<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Suppliers are paid through the supplier Ledger Register (Online Amount), so the Supplier
     * Payments screen, its tables and its permissions are removed. A posted payment's journal
     * entry stays in the general ledger untouched.
     *
     * Every GRN now raises an editable draft invoice row in the Ledger Register, linked back
     * to it by goods_receipt_note_id.
     */
    public function up(): void
    {
        Schema::table('supplier_ledger_registers', function (Blueprint $table) {
            $table->foreignId('goods_receipt_note_id')->nullable()->after('journal_entry_id')
                ->constrained('goods_receipt_notes')->nullOnDelete();
        });

        Schema::dropIfExists('payment_grn_allocations');
        Schema::dropIfExists('supplier_payments');

        $permissionIds = DB::table('permissions')->where('name', 'like', 'supplier-payment-%')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('supplier_ledger_registers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('goods_receipt_note_id');
        });
    }
};
