<?php

use App\Models\ChartOfAccount;
use App\Models\GoodsIssue;
use App\Services\AccountingService;
use App\Services\DistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('creates a GL transfer for goods issue (1155 Dr / 1151 Cr)', function () {
    $user = \App\Models\User::factory()->create(['name' => 'Creator Bravo']);
    actingAs($user);

    // Ensure required base entities and COA accounts exist for the test environment
    $baseCurrency = \App\Models\Currency::factory()->base()->create();
    $assetType = \App\Models\AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);

    $stockInHand = ChartOfAccount::firstOrCreate(
        ['account_code' => '1151'],
        [
            'account_name' => 'Stock In Hand',
            'account_type_id' => $assetType->id,
            'currency_id' => $baseCurrency->id,
            'normal_balance' => 'debit',
            'is_group' => false,
            'is_active' => true,
        ]
    );

    $vanStock = ChartOfAccount::firstOrCreate(
        ['account_code' => '1155'],
        [
            'account_name' => 'Van Stock',
            'account_type_id' => $assetType->id,
            'currency_id' => $baseCurrency->id,
            'normal_balance' => 'debit',
            'is_group' => false,
            'is_active' => true,
        ]
    );

    $vehicle = \App\Models\Vehicle::factory()->create(['vehicle_number' => 'VH-9999']);
    $employee = \App\Models\Employee::factory()->create(['name' => 'Salesman Alpha']);

    // Minimal goods issue context with explicit relations/names
    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'draft',
        'stock_in_hand_account_id' => $stockInHand->id,
        'van_stock_account_id' => $vanStock->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'issued_by' => $user->id,
    ]);

    // Bind a fake accounting service to capture the payload
    $captured = [];
    $fakeAccounting = new class($captured) extends AccountingService
    {
        public array $captured;

        public function __construct(&$captured)
        {
            $this->captured = &$captured;
        }

        public function createJournalEntry(array $data): array
        {
            $this->captured = $data;

            // Return a fake JE object-like
            return ['success' => true, 'data' => (object) ['id' => 999]];
        }
    };

    App::instance(AccountingService::class, $fakeAccounting);

    $service = new DistributionService;

    // Call the new public method directly with a sample cost
    $je = $service->createGoodsIssueJournalEntry($goodsIssue, 123.45);

    expect($je)->not->toBeNull();
    expect($captured['reference'])->toBe($goodsIssue->issue_number);
    expect($captured['auto_post'])->toBeTrue();
    expect($captured['lines'])->toHaveCount(2);
    expect($captured['description'])->toContain('Salesman Alpha');
    expect($captured['description'])->toContain('Creator Bravo');
    expect($captured['lines'][0]['description'])->toContain('Salesman Alpha');
    expect($captured['lines'][1]['description'])->toContain('Creator Bravo');

    // Lines must balance and equal 123.45
    $debit = collect($captured['lines'])->sum('debit');
    $credit = collect($captured['lines'])->sum('credit');
    expect($debit)->toBe(123.45);
    expect($credit)->toBe(123.45);
});
