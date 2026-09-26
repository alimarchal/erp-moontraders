<?php

use App\Enums\DocumentType;
use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\GoodsReceiptNote;
use App\Models\LedgerRegister;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DatabaseTriggerGuard;
use App\Services\InventoryService;
use App\Services\LedgerRegisterService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // The ledger register posts against cost center 1.
    if (! CostCenter::whereKey(1)->exists()) {
        DB::table('cost_centers')->insert(['id' => 1, 'code' => 'CC001', 'name' => 'Administration', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        // Sequences are not rolled back with the test transaction, so only ever move it forward.
        DB::statement("SELECT setval('cost_centers_id_seq', GREATEST((SELECT MAX(id) FROM cost_centers), (SELECT last_value FROM cost_centers_id_seq)))");
    }

    seedGrnPostingAccounts();

    $currencyId = DB::table('currencies')->where('is_base_currency', true)->value('id');
    $accounts = [
        '1112' => ['Pending Claims Debtors', 'Assets', 'debit'],
        '1171' => ['HBL Main Account', 'Assets', 'debit'],
        '4240' => ['ZA 0.5% Incentive Income', 'Income', 'credit'],
        '5210' => ['Administrative Expenses', 'Expenses', 'debit'],
        '5274' => ['Purchase Price Difference', 'Expenses', 'debit'],
    ];
    foreach ($accounts as $code => [$name, $typeName, $normalBalance]) {
        ChartOfAccount::firstOrCreate(['account_code' => $code], [
            'account_type_id' => AccountType::where('type_name', $typeName)->value('id')
                ?? AccountType::create(['type_name' => $typeName, 'report_group' => 'BalanceSheet', 'category' => 'Asset'])->id,
            'currency_id' => $currencyId,
            'account_name' => $name,
            'normal_balance' => $normalBalance,
            'is_active' => true,
        ]);
    }

    AccountingPeriod::create([
        'name' => now()->format('F Y'),
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    $this->supplier = Supplier::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->uom = Uom::factory()->create();
});

function glBalance(string $code): float
{
    return round((float) DB::table('journal_entry_details as line')
        ->join('journal_entries as je', 'je.id', '=', 'line.journal_entry_id')
        ->join('chart_of_accounts as account', 'account.id', '=', 'line.chart_of_account_id')
        ->where('je.status', 'posted')
        ->where('account.account_code', $code)
        ->selectRaw('COALESCE(SUM(line.debit - line.credit), 0) as balance')
        ->value('balance'), 2);
}

/** A GRN for 100 units at 100, owing the supplier 10,000. */
function postPurchaseGrn(object $test): GoodsReceiptNote
{
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $test->supplier->id,
        'warehouse_id' => $test->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now()->toDateString(),
        'supplier_invoice_number' => 'INV-'.fake()->unique()->numerify('#####'),
    ]);

    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $test->product->id,
        'stock_uom_id' => $test->uom->id,
        'purchase_uom_id' => $test->uom->id,
        'qty_in_purchase_uom' => 100,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 100,
        'extended_value' => 10000,
        'discount_value' => 0,
        'fmr_allowance' => 0,
        'sales_tax_value' => 0,
        'advance_income_tax' => 0,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 100,
        'total_cost' => 10000,
    ]);

    expect(app(InventoryService::class)->postGrnToInventory($grn->fresh())['success'])->toBeTrue();

    return $grn->fresh();
}

function postSupplierInvoice(object $test, float $amount, ?string $documentNumber = null): LedgerRegister
{
    $entry = LedgerRegister::factory()->create([
        'supplier_id' => $test->supplier->id,
        'transaction_date' => now()->toDateString(),
        'document_type' => DocumentType::Dr,
        'document_number' => $documentNumber ?? 'INV-'.fake()->unique()->numerify('#####'),
        'online_amount' => 0,
        'invoice_amount' => $amount,
        'expenses_amount' => 0,
        'za_point_five_percent_amount' => 0,
        'claim_adjust_amount' => 0,
    ]);

    expect(app(LedgerRegisterService::class)->postEntry($entry)['success'])->toBeTrue();

    return $entry->fresh();
}

/** Put an entry's line back on the account it used before Stock Received But Not Billed existed. */
function moveLineToLegacyAccount(int $journalEntryId, string $fromCode, string $toCode): void
{
    // Fire the deferred balance checks now; a table with pending trigger events cannot be altered.
    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    $restore = app(DatabaseTriggerGuard::class)->suspend('journal_entry_details', ['trg_block_posted_detail_updates']);

    try {
        DB::table('journal_entry_details')
            ->where('journal_entry_id', $journalEntryId)
            ->where('chart_of_account_id', ChartOfAccount::where('account_code', $fromCode)->value('id'))
            ->update(['chart_of_account_id' => ChartOfAccount::where('account_code', $toCode)->value('id')]);
    } finally {
        $restore();
    }
}

/** A GRN that reached stock without a journal entry, as 43 did on production. */
function dropJournalEntry(GoodsReceiptNote $grn): void
{
    $journalEntryId = $grn->journal_entry_id;
    DB::table('goods_receipt_notes')->where('id', $grn->id)->update(['journal_entry_id' => null]);

    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    $guard = app(DatabaseTriggerGuard::class);
    $restoreDetails = $guard->suspend('journal_entry_details', ['trg_block_posted_detail_deletes']);
    $restoreEntries = $guard->suspend('journal_entries', ['trg_block_posted_journal_deletes', 'trg_prevent_hard_delete']);

    try {
        DB::table('journal_entry_details')->where('journal_entry_id', $journalEntryId)->delete();
        DB::table('journal_entries')->where('id', $journalEntryId)->delete();
    } finally {
        $restoreDetails();
        $restoreEntries();
    }
}

it('puts a purchase into stock in hand once when both its GRN and its invoice are posted', function () {
    postPurchaseGrn($this);
    postSupplierInvoice($this, 10000);

    expect(glBalance('1151'))->toBe(10000.0)
        ->and(glBalance('2111'))->toBe(-10000.0)
        ->and(glBalance('2142'))->toBe(0.0);
});

it('holds a GRN on stock received but not billed until its invoice arrives', function () {
    postPurchaseGrn($this);

    expect(glBalance('1151'))->toBe(10000.0)
        ->and(glBalance('2142'))->toBe(-10000.0)
        ->and(glBalance('2111'))->toBe(0.0);
});

it('clears the invoice versus GRN difference into purchase price difference', function () {
    postPurchaseGrn($this);
    postSupplierInvoice($this, 10050);

    // Creditors follows the supplier's invoice; the extra 50 waits on 2142.
    expect(glBalance('2111'))->toBe(-10050.0)
        ->and(glBalance('2142'))->toBe(50.0);

    $this->artisan('accounting:clear-stock-received-not-billed', ['supplier' => $this->supplier->id, '--dry-run' => true])
        ->assertSuccessful();
    expect(glBalance('5274'))->toBe(0.0);

    $this->artisan('accounting:clear-stock-received-not-billed', ['supplier' => $this->supplier->id])
        ->assertSuccessful();

    expect(glBalance('2142'))->toBe(0.0)
        ->and(glBalance('5274'))->toBe(50.0)
        ->and(glBalance('1151'))->toBe(10000.0);

    // Nothing left, so a second run posts nothing.
    $entries = DB::table('journal_entries')->count();
    $this->artisan('accounting:clear-stock-received-not-billed', ['supplier' => $this->supplier->id])->assertSuccessful();
    expect(DB::table('journal_entries')->count())->toBe($entries);
});

describe('reposting the double-counted history', function () {
    beforeEach(function () {
        // What production holds: the GRN credited Creditors and the invoice debited Stock In Hand.
        $this->grn = postPurchaseGrn($this);
        moveLineToLegacyAccount($this->grn->journal_entry_id, '2142', '2111');

        $this->invoice = postSupplierInvoice($this, 10000, $this->grn->supplier_invoice_number);
        moveLineToLegacyAccount($this->invoice->journal_entry_id, '2142', '1151');

        // A GRN posted to stock without any journal entry.
        $this->orphan = postPurchaseGrn($this);
        dropJournalEntry($this->orphan);

        DB::table('supplier_payments')->insert([
            'payment_number' => 'PAY-2026-000001',
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'amount' => 10000,
            'status' => 'draft',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    it('starts out counting the purchase twice', function () {
        expect(glBalance('1151'))->toBe(20000.0)
            ->and(glBalance('2111'))->toBe(-20000.0);
    });

    it('changes nothing on a dry run', function () {
        $entries = DB::table('journal_entries')->count();

        $this->artisan('accounting:repost-supplier-invoices', ['--dry-run' => true])->assertSuccessful();

        expect(DB::table('journal_entries')->count())->toBe($entries)
            ->and(DB::table('supplier_payments')->value('status'))->toBe('draft');
    });

    it('leaves stock in hand equal to the stock and creditors equal to the invoices', function () {
        $this->artisan('accounting:repost-supplier-invoices')->assertSuccessful();

        // Two GRNs of 10,000 in stock; one invoiced, one still awaiting its invoice.
        expect(glBalance('1151'))->toBe(20000.0)
            ->and(glBalance('2111'))->toBe(-10000.0)
            ->and(glBalance('2142'))->toBe(-10000.0)
            ->and($this->orphan->fresh()->journal_entry_id)->not->toBeNull()
            ->and(DB::table('supplier_payments')->value('status'))->toBe('cancelled');

        $books = DB::table('journal_entry_details')->selectRaw('SUM(debit) as debits, SUM(credit) as credits')->first();
        expect((float) $books->debits)->toBe((float) $books->credits);
    });

    it('leaves a GRN whose line total disagrees with its unit cost for correction', function () {
        DB::table('goods_receipt_note_items')->where('grn_id', $this->orphan->id)->update(['total_cost' => 7500]);

        $this->artisan('accounting:repost-supplier-invoices')
            ->expectsOutputToContain($this->orphan->grn_number)
            ->assertSuccessful();

        expect($this->orphan->fresh()->journal_entry_id)->toBeNull()
            ->and(glBalance('5271'))->toBe(0.0);
    });

    it('posts a GRN at the value its stock movement received when the line total disagrees with its unit cost', function () {
        // GRN-2026-0033: the batch was received, issued and sold at the line total, not at quantity × unit cost.
        DB::table('goods_receipt_note_items')->where('grn_id', $this->orphan->id)->update(['total_cost' => 7500]);
        DB::table('stock_movements')->where('reference_id', $this->orphan->id)->where('movement_type', 'grn')->update(['total_value' => 7500]);
        $stockBefore = glBalance('1151');

        $this->artisan('accounting:repost-supplier-invoices')
            ->expectsOutputToContain('Round Off')
            ->assertSuccessful();

        expect($this->orphan->fresh()->journal_entry_id)->not->toBeNull()
            ->and(round(glBalance('1151') - $stockBefore, 2))->toBe(7500.0 - 10000.0)
            ->and(glBalance('5271'))->toBe(2500.0);
    });

    it('posts nothing the second time it runs', function () {
        $this->artisan('accounting:repost-supplier-invoices')->assertSuccessful();
        $entries = DB::table('journal_entries')->count();

        $this->artisan('accounting:repost-supplier-invoices')->assertSuccessful();

        expect(DB::table('journal_entries')->count())->toBe($entries);
    });

    it('takes the reclassification back out when the GRN is later reversed', function () {
        $this->artisan('accounting:repost-supplier-invoices')->assertSuccessful();

        $result = app(InventoryService::class)->reverseGrnInventory($this->grn->fresh());

        // Only the orphan GRN's stock is left. The invoice still stands as owed to the
        // supplier, and on 2142 it now offsets the orphan GRN until a credit note arrives.
        expect($result['success'])->toBeTrue()
            ->and(glBalance('1151'))->toBe(10000.0)
            ->and(glBalance('2111'))->toBe(-10000.0)
            ->and(glBalance('2142'))->toBe(0.0);
    });
});

it('no longer raises a draft supplier payment when a GRN is posted', function () {
    Permission::firstOrCreate(['name' => 'goods-receipt-note-post', 'guard_name' => 'web']);
    $this->user->givePermissionTo('goods-receipt-note-post');

    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now()->toDateString(),
    ]);
    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 10,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 10,
        'extended_value' => 1000,
        'quantity_received' => 10,
        'quantity_accepted' => 10,
        'unit_cost' => 100,
        'total_cost' => 1000,
    ]);

    $this->post(route('goods-receipt-notes.post', $grn), ['password' => 'password'])->assertRedirect();

    expect($grn->fresh()->status)->toBe('posted')
        ->and(DB::table('supplier_payments')->count())->toBe(0);
});

it('refuses to edit or delete a ledger register entry that is already posted', function () {
    foreach (['report-audit-ledger-register-edit', 'report-audit-ledger-register-delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo('report-audit-ledger-register-edit', 'report-audit-ledger-register-delete');

    $entry = postSupplierInvoice($this, 10000);

    $this->put(route('reports.ledger-register.update', $entry), [
        'supplier_id' => $this->supplier->id,
        'transaction_date' => now()->toDateString(),
        'document_type' => DocumentType::Dr->value,
        'document_number' => $entry->document_number,
        'invoice_amount' => 99999,
        'online_amount' => 0,
        'expenses_amount' => 0,
        'za_point_five_percent_amount' => 0,
        'claim_adjust_amount' => 0,
    ])->assertSessionHas('error');

    $this->delete(route('reports.ledger-register.destroy', $entry))->assertSessionHas('error');

    expect((float) $entry->fresh()->invoice_amount)->toBe(10000.0)
        ->and(LedgerRegister::whereKey($entry->id)->exists())->toBeTrue();
});

it('refuses to post a GRN whose line total disagrees with its unit cost', function () {
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now()->toDateString(),
    ]);
    // 2,592 × 396.05 is 1,026,559.82; a line total of 769,919.87 was saved against it on GRN-2026-0033.
    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 2592,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 2592,
        'extended_value' => 1026559.82,
        'quantity_received' => 2592,
        'quantity_accepted' => 2592,
        'unit_cost' => 396.049315,
        'total_cost' => 769919.8651,
    ]);

    $result = app(InventoryService::class)->postGrnToInventory($grn->fresh());

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('does not equal quantity × unit cost')
        ->and($grn->fresh()->status)->toBe('draft')
        ->and(DB::table('stock_movements')->count())->toBe(0);
});
