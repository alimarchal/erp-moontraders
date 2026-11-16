<?php

use App\Support\MigrationHelpers;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = ['expense-voucher-reverse', 'report-audit-cheque-register-all-companies'];

    /**
     * Created here so production only needs `php artisan migrate`; super-admin and admin receive them.
     */
    public function up(): void
    {
        MigrationHelpers::grantPermissions(self::PERMISSIONS);
    }

    public function down(): void
    {
        MigrationHelpers::dropPermissions(self::PERMISSIONS);
    }
};
