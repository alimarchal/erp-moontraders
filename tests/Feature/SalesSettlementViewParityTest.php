<?php

use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAdvanceTax;
use App\Models\SalesSettlementAmrPowder;
use App\Models\SalesSettlementBankSlip;
use App\Models\SalesSettlementPercentageExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->settlement = SalesSettlement::factory()->create(['status' => 'draft']);
});

it('shows every collection and expense table on the show page even when empty', function () {
    $this->actingAs($this->user)
        ->get(route('sales-settlements.show', $this->settlement))
        ->assertOk()
        ->assertSee('No credit sales recorded')
        ->assertSee('No recoveries recorded')
        ->assertSee('No cheques recorded')
        ->assertSee('No bank transfers recorded')
        ->assertSee('No AMR powder recorded')
        ->assertSee('No AMR liquid recorded')
        ->assertSee('No advance tax recorded')
        ->assertSee('No percentage expense recorded')
        ->assertSee('No bank slips recorded');
});

it('shows the same details on the show page and the printable sheet', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $currency = Currency::where('is_base_currency', true)->first() ?? Currency::factory()->base()->create();
    $type = AccountType::firstOrCreate(['type_name' => 'Asset'], ['report_group' => 'BalanceSheet']);
    $coa = ChartOfAccount::firstOrCreate(['account_code' => '1001'], ['account_name' => 'Bank', 'account_type_id' => $type->id, 'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_group' => false, 'is_active' => true]);
    $bank = BankAccount::create(['account_name' => 'Main Account', 'account_number' => '1', 'bank_name' => 'Bank', 'chart_of_account_id' => $coa->id, 'is_active' => true]);

    SalesSettlementAmrPowder::create(['sales_settlement_id' => $this->settlement->id, 'product_id' => $product->id, 'batch_code' => 'BATCH-XYZ', 'quantity' => 3, 'amount' => 90, 'notes' => 'damaged cartons']);
    SalesSettlementAdvanceTax::create(['sales_settlement_id' => $this->settlement->id, 'customer_id' => $customer->id, 'sale_amount' => 1000, 'tax_rate' => 2.5, 'tax_amount' => 25, 'invoice_number' => 'INV-77', 'notes' => 'ntn customer']);
    SalesSettlementPercentageExpense::create(['sales_settlement_id' => $this->settlement->id, 'customer_id' => $customer->id, 'invoice_number' => 'PCT-5', 'amount' => 40, 'notes' => 'pct note']);
    SalesSettlementBankSlip::create(['sales_settlement_id' => $this->settlement->id, 'employee_id' => $this->settlement->employee_id, 'bank_account_id' => $bank->id, 'deposit_date' => now(), 'reference_number' => 'SLIP-REF-9', 'amount' => 400, 'notes' => 'slip note']);

    $details = ['BATCH-XYZ', 'damaged cartons', 'INV-77', 'ntn customer', 'PCT-5', 'pct note', 'SLIP-REF-9', 'slip note'];

    $show = $this->actingAs($this->user)->get(route('sales-settlements.show', $this->settlement))->assertOk();
    $sheet = $this->actingAs($this->user)->get(route('sales-settlements.show', [$this->settlement, 'layout' => 'print2']))->assertOk();

    foreach ($details as $detail) {
        $show->assertSee($detail);
        $sheet->assertSee($detail);
    }
});

it('shows empty tables on the printable sheet and downloads it as a pdf', function () {
    $this->actingAs($this->user)
        ->get(route('sales-settlements.show', [$this->settlement, 'layout' => 'print2']))
        ->assertOk()
        ->assertSee('No credit sales recorded.')
        ->assertSee('No bank slips recorded.')
        ->assertSee('None recorded.');

    $this->actingAs($this->user)
        ->get(route('sales-settlements.show', [$this->settlement, 'layout' => 'print2', 'format' => 'pdf', 'orientation' => 'portrait']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
