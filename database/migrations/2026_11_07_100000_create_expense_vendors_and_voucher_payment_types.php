<?php

use App\Support\MigrationHelpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'expense-vendor-list', 'expense-vendor-create', 'expense-vendor-edit', 'expense-vendor-delete',
        'expense-voucher-view-all', 'expense-voucher-view-company',
    ];

    /**
     * The shop catalogue, many attachments per voucher, and the cash / credit / advance payment types.
     */
    public function up(): void
    {
        Schema::create('expense_vendors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->index();
            $table->string('category', 40)->default('shop');
            $table->string('phone', 40)->nullable();
            $table->string('city', 80)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->foreignId('default_expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_voucher_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_voucher_id')->constrained('expense_vouchers')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('expense_vouchers', function (Blueprint $table) {
            $table->foreignId('expense_vendor_id')->nullable()->after('company_id')->constrained('expense_vendors')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->after('expense_vendor_id')->constrained('employees')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->after('employee_id')->constrained('vehicles')->nullOnDelete();
            $table->string('payee_type', 20)->default('other')->after('vehicle_id');
            $table->string('payment_type', 20)->default('cash')->after('payee_type')->index();
            $table->string('payment_status', 20)->default('paid')->after('payment_type')->index();
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
            $table->decimal('advance_installment', 15, 2)->nullable()->after('paid_amount');
            $table->string('vendor_bill_no', 80)->nullable()->after('party_name');
        });

        // Vouchers raised before this change were all paid in cash and have at most one file.
        DB::table('expense_vouchers')->update(['paid_amount' => DB::raw('amount')]);

        DB::table('expense_vouchers')->whereNotNull('attachment')->orderBy('id')->each(function ($voucher) {
            DB::table('expense_voucher_attachments')->insert([
                'expense_voucher_id' => $voucher->id,
                'file_name' => basename($voucher->attachment),
                'file_path' => $voucher->attachment,
                'file_type' => null,
                'file_size' => null,
                'created_by' => $voucher->created_by,
                'created_at' => $voucher->created_at,
                'updated_at' => now(),
            ]);
        });

        MigrationHelpers::addAccount('2110', '2113', 'Expense Payables', 'credit', 'Unpaid shop and vendor expenses (credit vouchers), settled by a vendor payment');
        MigrationHelpers::grantPermissions(self::PERMISSIONS);

        // Checkers keep the company-wide view they had before vouchers became "own only".
        $viewCompany = DB::table('permissions')->where('name', 'expense-voucher-view-company')->where('guard_name', 'web')->value('id');
        $approve = DB::table('permissions')->where('name', 'expense-voucher-approve')->where('guard_name', 'web')->value('id');

        if ($viewCompany && $approve) {
            $roleIds = DB::table('role_has_permissions')->where('permission_id', $approve)->pluck('role_id');
            DB::table('role_has_permissions')->insertOrIgnore($roleIds->map(fn ($roleId) => ['permission_id' => $viewCompany, 'role_id' => $roleId])->all());
        }
    }

    public function down(): void
    {
        Schema::table('expense_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_vendor_id');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['payee_type', 'payment_type', 'payment_status', 'paid_amount', 'advance_installment', 'vendor_bill_no']);
        });
        Schema::dropIfExists('expense_voucher_attachments');
        Schema::dropIfExists('expense_vendors');
        MigrationHelpers::dropAccount('2113');
        MigrationHelpers::dropPermissions(self::PERMISSIONS);
    }
};
