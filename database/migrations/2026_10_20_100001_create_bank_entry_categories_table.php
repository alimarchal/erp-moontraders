<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_entry_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('type', 20)->default('both')->comment('receipt, payment or both');
            $table->foreignId('default_chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()
                ->comment('Ledger account used on the other side of the bank when this category is posted.');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $defaults = [
            ['Customer Receipt', 'receipt'],
            ['Principal Payment', 'payment'],
            ['Supplier Payment', 'payment'],
            ['Salary / Staff', 'payment'],
            ['Cash Deposit', 'receipt'],
            ['Bank Charges', 'payment'],
            ['Tax / Government', 'payment'],
            ['Inter-bank Transfer', 'both'],
            ['Own / Self Transfer', 'both'],
            ['Owner / Partner', 'both'],
            ['Other', 'both'],
        ];

        DB::table('bank_entry_categories')->insertOrIgnore(array_map(
            fn (array $row, int $index) => [
                'name' => $row[0],
                'slug' => str($row[0])->slug()->value(),
                'type' => $row[1],
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $defaults,
            array_keys($defaults),
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_entry_categories');
    }
};
