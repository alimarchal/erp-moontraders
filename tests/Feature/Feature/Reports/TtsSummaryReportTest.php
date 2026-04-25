<?php

use App\Models\ChartOfAccount;
use App\Models\SalesSettlement;
use App\Models\SchemeReceived;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-sales-tts-summary', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-sales-tts-summary');

    $this->supplier = Supplier::factory()->create([
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);

    $this->actingAs($this->user);
});

test('tts summary report index loads successfully', function () {
    $response = $this->get(route('reports.tts-summary.index'));

    $response->assertSuccessful();
    $response->assertViewHas('startDate');
    $response->assertViewHas('endDate');
    $response->assertViewHas('ttsReceived');
    $response->assertViewHas('promoReceived');
    $response->assertViewHas('totalBalance');
});

test('tts summary report calculates received and passed values for selected date', function () {
    $date = now()->toDateString();

    SchemeReceived::query()->create([
        'supplier_id' => $this->supplier->id,
        'category' => 'tts_received',
        'transaction_date' => $date,
        'amount' => 1000,
        'is_active' => true,
    ]);

    SchemeReceived::query()->create([
        'supplier_id' => $this->supplier->id,
        'category' => 'promo_received',
        'transaction_date' => $date,
        'amount' => 500,
        'is_active' => true,
    ]);

    $account5292 = ChartOfAccount::factory()->create([
        'account_code' => '5292',
        'account_name' => 'Scheme Discount Expense',
    ]);
    $account5288 = ChartOfAccount::factory()->create([
        'account_code' => '5288',
        'account_name' => 'Promo Passed',
    ]);
    $account5223 = ChartOfAccount::factory()->create([
        'account_code' => '5223',
        'account_name' => 'Percentage Expense',
    ]);

    $settlement = SalesSettlement::factory()->create([
        'supplier_id' => $this->supplier->id,
        'settlement_date' => $date,
        'status' => 'posted',
    ]);

    DB::table('sales_settlement_expenses')->insert([
        [
            'sales_settlement_id' => $settlement->id,
            'expense_date' => $date,
            'expense_account_id' => $account5292->id,
            'amount' => 300,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'sales_settlement_id' => $settlement->id,
            'expense_date' => $date,
            'expense_account_id' => $account5288->id,
            'amount' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'sales_settlement_id' => $settlement->id,
            'expense_date' => $date,
            'expense_account_id' => $account5223->id,
            'amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->get(route('reports.tts-summary.index', [
        'filter' => [
            'supplier_id' => $this->supplier->id,
            'start_date' => $date,
            'end_date' => $date,
        ],
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('ttsReceived', 1000.0);
    $response->assertViewHas('promoReceived', 500.0);
    $response->assertViewHas('totalReceived', 1500.0);
    $response->assertViewHas('ttsPassed', 300.0);
    $response->assertViewHas('promoPassed', 200.0);
    $response->assertViewHas('totalSchemedPassed', 500.0);
    $response->assertViewHas('percentagePassed', 100.0);
    $response->assertViewHas('totalBalance', 1000.0);
});
