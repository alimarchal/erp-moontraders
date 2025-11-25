<?php

use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Config::set('database.default', 'sqlite');
    Config::set('database.connections.sqlite.database', ':memory:');

    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
    DB::reconnect();

    Schema::dropAllViews();
    Schema::dropAllTables();

    Schema::create('account_types', function (Blueprint $table): void {
        $table->id();
        $table->string('type_name');
        $table->string('report_group');
        $table->string('description')->nullable();
        $table->timestamps();
    });

    Schema::create('currencies', function (Blueprint $table): void {
        $table->id();
        $table->string('currency_code', 3);
        $table->string('currency_name');
        $table->string('currency_symbol', 10)->nullable();
        $table->decimal('exchange_rate', 15, 6)->default(1.000000);
        $table->boolean('is_base_currency')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('chart_of_accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('parent_id')->nullable();
        $table->foreignId('account_type_id');
        $table->foreignId('currency_id');
        $table->string('account_code', 20)->unique();
        $table->string('account_name');
        $table->enum('normal_balance', ['debit', 'credit']);
        $table->text('description')->nullable();
        $table->boolean('is_group')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
});

it('seeds updated indirect expense accounts', function () {
    $this->seed(AccountTypeSeeder::class);
    $this->seed(CurrencySeeder::class);
    $this->seed(ChartOfAccountSeeder::class);

    $indirectExpensesId = DB::table('chart_of_accounts')
        ->where('account_name', 'Indirect Expenses')
        ->value('id');

    expect($indirectExpensesId)->not->toBeNull();

    $expectedAccounts = [
        'AMR Powder',
        'AMR Liquid',
        'Toll Tax / Labor',
        'Food/Salesman/Loader Charges',
        'Scheme Discount Expense',
    ];

    $foundAccounts = DB::table('chart_of_accounts')
        ->whereIn('account_name', $expectedAccounts)
        ->get(['account_name', 'parent_id']);

    expect($foundAccounts)->toHaveCount(count($expectedAccounts));

    $foundAccounts->each(function (object $account) use ($indirectExpensesId): void {
        expect($account->parent_id)->toBe($indirectExpensesId);
    });

    expect(DB::table('chart_of_accounts')->where('account_name', 'AMR Expense')->exists())->toBeFalse();
});
