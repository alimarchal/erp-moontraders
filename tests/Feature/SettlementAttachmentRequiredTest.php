<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCheque;
use App\Models\SalesSettlementCreditSale;
use App\Models\SalesSettlementRecovery;
use App\Models\User;
use App\Services\DistributionService;
use App\Services\SettlementAttachmentService;

beforeEach(function () {
    $this->settlement = SalesSettlement::factory()->create(['status' => 'draft']);
    $this->customer = Customer::factory()->create();
    $this->employee = Employee::factory()->create();

    $this->creditSale = fn (array $extra = []) => SalesSettlementCreditSale::create($extra + [
        'sales_settlement_id' => $this->settlement->id, 'customer_id' => $this->customer->id, 'employee_id' => $this->employee->id,
        'invoice_number' => 'INV-'.fake()->unique()->numerify('####'), 'sale_amount' => 1000, 'payment_received' => 0, 'previous_balance' => 0, 'new_balance' => 1000,
    ]);
    $this->recovery = fn (array $extra = []) => SalesSettlementRecovery::create($extra + [
        'sales_settlement_id' => $this->settlement->id, 'customer_id' => $this->customer->id, 'employee_id' => $this->employee->id,
        'recovery_number' => 'REC-'.fake()->unique()->numerify('####'), 'payment_method' => 'cash', 'amount' => 500, 'previous_balance' => 500, 'new_balance' => 0,
    ]);
});

it('keeps attachments optional until a section is switched on in .env', function () {
    ($this->creditSale)();
    SalesSettlementCheque::factory()->create(['status' => 'pending', 'sales_settlement_id' => $this->settlement->id, 'attachment' => null]);

    expect(SettlementAttachmentService::missingRequired($this->settlement))->toBe([])
        ->and(SettlementAttachmentService::isRequired('credit_sales'))->toBeFalse();
});

it('names the credit sales that still need a file when that section is compulsory', function () {
    config(['settlement_attachments.required.credit_sales' => true]);
    ($this->creditSale)(['invoice_number' => 'INV-1001']);
    ($this->creditSale)(['invoice_number' => 'INV-1002', 'attachment' => 'sales-settlement/x/credit-sales/a.pdf']);

    $missing = SettlementAttachmentService::missingRequired($this->settlement);

    expect($missing)->toHaveCount(1)->and($missing[0])->toContain('Credit Sales Detail')->toContain('INV-1001')->not->toContain('INV-1002');
});

it('covers cheques, bank transfers and bank slips with their own flags', function () {
    config(['settlement_attachments.required.cheques' => true]);
    SalesSettlementCheque::factory()->create(['status' => 'pending', 'sales_settlement_id' => $this->settlement->id, 'cheque_number' => 'CHQ-777', 'attachment' => null]);

    expect(SettlementAttachmentService::missingRequired($this->settlement)[0])->toContain('Cheque Payments')->toContain('CHQ-777');

    SalesSettlementCheque::query()->update(['attachment' => 'sales-settlement/x/cheques/a.png']);
    expect(SettlementAttachmentService::missingRequired($this->settlement))->toBe([]);
});

it('asks a file only from bank recoveries, never cash ones', function () {
    config(['settlement_attachments.required.recoveries' => true]);
    ($this->recovery)(['recovery_number' => 'REC-CASH', 'payment_method' => 'cash']);
    ($this->recovery)(['recovery_number' => 'REC-BANK', 'payment_method' => 'bank_transfer']);

    $missing = SettlementAttachmentService::missingRequired($this->settlement);

    expect($missing)->toHaveCount(1)->and($missing[0])->toContain('Recoveries Detail')->toContain('REC-BANK')->not->toContain('REC-CASH');
});

it('stops a settlement from being posted while a required attachment is missing', function () {
    config(['settlement_attachments.required.credit_sales' => true]);
    ($this->creditSale)(['invoice_number' => 'INV-5555']);

    $result = app(DistributionService::class)->postSalesSettlement($this->settlement);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('required attachments')->toContain('INV-5555')
        ->and($this->settlement->fresh()->status)->toBe('draft');
});

it('does not hold up posting for the attachment once every file is there', function () {
    config(['settlement_attachments.required.credit_sales' => true]);
    ($this->creditSale)(['attachment' => 'sales-settlement/x/credit-sales/a.pdf']);

    $result = app(DistributionService::class)->postSalesSettlement($this->settlement);

    expect($result['message'] ?? '')->not->toContain('required attachments');
});

it('ignores the compulsory flags while attachments are switched off', function () {
    config(['settlement_attachments.enabled' => false, 'settlement_attachments.required.credit_sales' => true]);
    ($this->creditSale)();

    expect(SettlementAttachmentService::isRequired('credit_sales'))->toBeFalse()
        ->and(SettlementAttachmentService::missingRequired($this->settlement))->toBe([]);
});

it('shows the missing attachments on the draft settlement page', function () {
    config(['settlement_attachments.required.credit_sales' => true]);
    ($this->creditSale)(['invoice_number' => 'INV-8888']);
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);

    $this->actingAs($admin)->get(route('sales-settlements.show', $this->settlement))->assertOk()
        ->assertSee('Required attachments are missing')->assertSee('INV-8888');
});
