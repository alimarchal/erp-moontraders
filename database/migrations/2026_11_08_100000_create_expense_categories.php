<?php

use App\Support\MigrationHelpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = ['expense-category-manage'];

    /** @var array<int, array{string, string|null}> category name, usual expense account code */
    private const CATEGORIES = [
        ['Toner & IT', '5297'], ['Grocery / Tea & Refreshment', '5240'], ['Rent', '5241'], ['Utilities (electricity, gas, water)', '5232'],
        ['Fuel', '5298'], ['Vehicle maintenance & repair', '5299'], ['Van wash', '5289'], ['Stationery & printing', '5261'],
        ['Office maintenance', '5231'], ['Telephone & internet', '5212'], ['Travel & conveyance', '5222'], ['Food / loader charges', '5282'],
        ['Postal & courier', '5251'], ['Legal & professional', '5290'], ['Other', '5221'],
    ];

    /**
     * What an expense voucher is for (toner, grocery, rent, repair...). Each category can carry the usual
     * expense account, which fills the account of the voucher when the category is chosen.
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('default_expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('expense_vouchers', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->after('vehicle_id')->constrained('expense_categories')->nullOnDelete();
        });

        $accounts = DB::table('chart_of_accounts')->pluck('id', 'account_code');
        $now = now();

        foreach (self::CATEGORIES as $i => [$name, $code]) {
            DB::table('expense_categories')->insert([
                'name' => $name, 'default_expense_account_id' => $accounts[$code] ?? null, 'sort_order' => ($i + 1) * 10,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        MigrationHelpers::grantPermissions(self::PERMISSIONS);
    }

    public function down(): void
    {
        Schema::table('expense_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_category_id');
        });
        Schema::dropIfExists('expense_categories');
        MigrationHelpers::dropPermissions(self::PERMISSIONS);
    }
};
