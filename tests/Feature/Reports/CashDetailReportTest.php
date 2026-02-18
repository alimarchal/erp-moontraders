<?php

namespace Tests\Feature\Reports;

use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementBankSlip;
use App\Models\SalesSettlementCashDenomination;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashDetailReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::create(['name' => 'report-audit-cash-detail']);
    }

    public function test_cash_detail_report_loads_successfully()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-audit-cash-detail');

        $response = $this->actingAs($user)->get(route('reports.cash-detail.index'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.cash-detail');
    }

    public function test_cash_detail_report_displays_data()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-audit-cash-detail');
        $employee = Employee::factory()->create(['name' => 'John Doe']);

        $settlement = SalesSettlement::factory()->create([
            'employee_id' => $employee->id,
            'settlement_date' => now()->format('Y-m-d'),
            'cash_collected' => 5000,
        ]);

        SalesSettlementCashDenomination::create([
            'sales_settlement_id' => $settlement->id,
            'denom_5000' => 1,
            'total_amount' => 5000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.cash-detail.index'));

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('5,000'); // Amount
        $response->assertSee('5000'); // Denomination Label
    }

    public function test_cash_detail_report_sums_multiple_settlements_per_employee()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-audit-cash-detail');

        $supplier = Supplier::factory()->create();
        $employee = Employee::factory()->create([
            'name' => 'Shahid Mughal',
            'supplier_id' => $supplier->id,
            'designation' => 'Deliveryman',
        ]);

        $today = now()->format('Y-m-d');

        // Settlement 1: cash denomination = 1,468
        $settlement1 = SalesSettlement::factory()->create([
            'employee_id' => $employee->id,
            'settlement_date' => $today,
            'cash_collected' => 1468,
        ]);

        SalesSettlementCashDenomination::create([
            'sales_settlement_id' => $settlement1->id,
            'denom_1000' => 1,
            'denom_100' => 4,
            'denom_50' => 1,
            'denom_10' => 0,
            'denom_coins' => 18,
            'total_amount' => 1468,
        ]);

        // Settlement 2: cash denomination = 2,900
        $settlement2 = SalesSettlement::factory()->create([
            'employee_id' => $employee->id,
            'settlement_date' => $today,
            'cash_collected' => 2900,
        ]);

        SalesSettlementCashDenomination::create([
            'sales_settlement_id' => $settlement2->id,
            'denom_1000' => 2,
            'denom_500' => 1,
            'denom_100' => 4,
            'total_amount' => 2900,
        ]);

        $response = $this->actingAs($user)->get(route('reports.cash-detail.index', [
            'date' => $today,
            'supplier_id' => $supplier->id,
            'designation' => 'Deliveryman',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Shahid Mughal');

        // Salesman Cash should show the SUM of both settlements: 1,468 + 2,900 = 4,368
        $response->assertViewHas('salesmanData', function ($salesmanData) {
            $shahid = $salesmanData->firstWhere('salesman_name', 'Shahid Mughal');

            return $shahid && (int) $shahid->amount === 4368;
        });

        // Cash denominations should aggregate across both settlements
        $response->assertViewHas('denominations', function ($denominations) {
            // 1000-note: 1 + 2 = 3 total
            return $denominations['1000'] === 3
                && $denominations['500'] === 1
                && $denominations['100'] === 8;
        });
    }

    public function test_cash_detail_report_sums_bank_slips_across_multiple_settlements()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-audit-cash-detail');

        $supplier = Supplier::factory()->create();
        $employee = Employee::factory()->create([
            'name' => 'Ali Khan',
            'supplier_id' => $supplier->id,
            'designation' => 'Deliveryman',
        ]);

        $today = now()->format('Y-m-d');

        $settlement1 = SalesSettlement::factory()->create([
            'employee_id' => $employee->id,
            'settlement_date' => $today,
            'cash_collected' => 500,
        ]);

        $bankAccount = BankAccount::factory()->create();

        SalesSettlementBankSlip::create([
            'sales_settlement_id' => $settlement1->id,
            'employee_id' => $employee->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 50000,
        ]);

        $settlement2 = SalesSettlement::factory()->create([
            'employee_id' => $employee->id,
            'settlement_date' => $today,
            'cash_collected' => 300,
        ]);

        SalesSettlementBankSlip::create([
            'sales_settlement_id' => $settlement2->id,
            'employee_id' => $employee->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 25000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.cash-detail.index', [
            'date' => $today,
            'supplier_id' => $supplier->id,
            'designation' => 'Deliveryman',
        ]));

        $response->assertStatus(200);

        // Bank Slips should sum across both settlements: 50,000 + 25,000 = 75,000
        $response->assertViewHas('bankSlipsData', function ($bankSlipsData) {
            $ali = $bankSlipsData->firstWhere('salesman_name', 'Ali Khan');

            return $ali && (int) $ali->amount === 75000;
        });

        // Salesman Cash should also sum: 500 + 300 = 800
        $response->assertViewHas('salesmanData', function ($salesmanData) {
            $ali = $salesmanData->firstWhere('salesman_name', 'Ali Khan');

            return $ali && (int) $ali->amount === 800;
        });
    }
}
