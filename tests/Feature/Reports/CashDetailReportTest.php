<?php

namespace Tests\Feature\Reports;

use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementBankSlip;
use App\Models\SalesSettlementCashDenomination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashDetailReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_detail_report_loads_successfully()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.cash-detail.index'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.cash-detail');
    }

    public function test_cash_detail_report_displays_data()
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['name' => 'John Doe']);

        $settlement = SalesSettlement::factory()->create([
            'employee_id' => $employee->id,
            'settlement_date' => now()->format('Y-m-d'),
            'cash_collected' => 5000
        ]);

        SalesSettlementCashDenomination::create([
            'sales_settlement_id' => $settlement->id,
            'denom_5000' => 1,
            'total_amount' => 5000
        ]);

        $response = $this->actingAs($user)->get(route('reports.cash-detail.index'));

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('5,000'); // Amount
        $response->assertSee('5000'); // Denomination Label
    }
}
