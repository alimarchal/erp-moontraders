<?php

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\ExpenseDetail;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-expense-detail', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'expense-detail-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'expense-detail-edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'expense-detail-delete', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'expense-detail-post', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'report-audit-expense-detail',
        'expense-detail-create',
        'expense-detail-edit',
        'expense-detail-delete',
        'expense-detail-post',
    ]);
    $this->actingAs($this->user);
});

test('expense detail report index loads successfully', function () {
    $response = $this->get(route('reports.expense-detail.index'));

    $response->assertSuccessful();
    $response->assertViewHas('expenses');
    $response->assertViewHas('categoryOptions');
});

test('expense detail report filters by category', function () {
    ExpenseDetail::factory()->category('fuel')->create([
        'transaction_date' => now()->toDateString(),
        'amount' => 500,
    ]);
    ExpenseDetail::factory()->category('tcs')->create([
        'transaction_date' => now()->toDateString(),
        'amount' => 300,
    ]);

    $response = $this->get(route('reports.expense-detail.index', [
        'category' => 'fuel',
        'date_from' => now()->startOfMonth()->toDateString(),
        'date_to' => now()->endOfMonth()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('totalAmount', 500.00);
});

test('expense detail report filters by posted status', function () {
    ExpenseDetail::factory()->create([
        'transaction_date' => now()->toDateString(),
        'amount' => 100,
        'posted_at' => null,
    ]);
    ExpenseDetail::factory()->posted()->create([
        'transaction_date' => now()->toDateString(),
        'amount' => 200,
    ]);

    $response = $this->get(route('reports.expense-detail.index', [
        'posted_status' => 'posted',
        'date_from' => now()->startOfMonth()->toDateString(),
        'date_to' => now()->endOfMonth()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('totalAmount', 200.00);
});

test('expense detail can be created from report', function () {
    $response = $this->post(route('reports.expense-detail.store'), [
        'category' => 'stationary',
        'transaction_date' => now()->toDateString(),
        'amount' => 1500.50,
        'description' => 'Office supplies',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('expense_details', [
        'category' => 'stationary',
        'amount' => 1500.50,
        'description' => 'Office supplies',
    ]);
});

test('expense detail can be updated from report', function () {
    $expense = ExpenseDetail::factory()->category('stationary')->create([
        'transaction_date' => now()->toDateString(),
        'amount' => 1000,
        'posted_at' => null,
    ]);

    $response = $this->put(route('reports.expense-detail.update', $expense), [
        'category' => 'stationary',
        'transaction_date' => now()->toDateString(),
        'amount' => 2000,
        'description' => 'Updated supplies',
    ]);

    $response->assertRedirect();
    $expense->refresh();
    expect((float) $expense->amount)->toBe(2000.00);
});

test('posted expense cannot be updated', function () {
    $expense = ExpenseDetail::factory()->posted()->create([
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->put(route('reports.expense-detail.update', $expense), [
        'category' => 'stationary',
        'transaction_date' => now()->toDateString(),
        'amount' => 999,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('posted expense cannot be deleted', function () {
    $expense = ExpenseDetail::factory()->posted()->create([
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->delete(route('reports.expense-detail.destroy', $expense));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('expense_details', ['id' => $expense->id]);
});

test('unposted expense can be deleted', function () {
    $expense = ExpenseDetail::factory()->create([
        'transaction_date' => now()->toDateString(),
        'posted_at' => null,
    ]);

    $response = $this->delete(route('reports.expense-detail.destroy', $expense));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertSoftDeleted('expense_details', ['id' => $expense->id]);
});

test('expense can be posted from report', function () {
    $debitAccount = ChartOfAccount::factory()->create([
        'account_code' => '5290',
        'is_group' => false,
        'is_active' => true,
    ]);

    $creditAccount = ChartOfAccount::factory()->create([
        'account_code' => '1121',
        'is_group' => false,
        'is_active' => true,
    ]);

    $expense = ExpenseDetail::factory()->create([
        'transaction_date' => now()->toDateString(),
        'amount' => 5000,
        'debit_account_id' => $debitAccount->id,
        'credit_account_id' => $creditAccount->id,
        'posted_at' => null,
    ]);

    $currency = Currency::first() ?? Currency::factory()->create();
    $accountingPeriod = AccountingPeriod::factory()->create([
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
    ]);

    $mockJournalEntry = JournalEntry::factory()->create([
        'currency_id' => $currency->id,
        'accounting_period_id' => $accountingPeriod->id,
        'entry_date' => now(),
        'status' => 'posted',
    ]);

    $this->mock(AccountingService::class, function ($mock) use ($mockJournalEntry) {
        $mock->shouldReceive('createJournalEntry')
            ->once()
            ->andReturn(['success' => true, 'data' => $mockJournalEntry]);
    });

    $this->post(route('reports.expense-detail.post', $expense))
        ->assertRedirect();

    $expense->refresh();

    expect($expense->posted_at)->not->toBeNull();
    expect($expense->posted_by)->toBe($this->user->id);
    expect($expense->journal_entry_id)->toBe($mockJournalEntry->id);
});

test('already posted expense cannot be posted again', function () {
    $expense = ExpenseDetail::factory()->posted()->create([
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->post(route('reports.expense-detail.post', $expense));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('opening balance is zero when viewing current calendar month', function () {
    $lastMonthDate = now()->subMonthNoOverflow()->startOfMonth()->addDays(10)->toDateString();
    $thisMonthDate = now()->startOfMonth()->addDays(5)->toDateString();
    $dateFrom = now()->startOfMonth()->toDateString();
    $dateTo = now()->endOfMonth()->toDateString();

    // Expense before current month — should NOT appear in opening balance
    ExpenseDetail::factory()->create([
        'transaction_date' => $lastMonthDate,
        'amount' => 3000,
        'category' => 'fuel',
    ]);

    // Expense in current month
    ExpenseDetail::factory()->create([
        'transaction_date' => $thisMonthDate,
        'amount' => 1000,
        'category' => 'fuel',
    ]);

    $response = $this->get(route('reports.expense-detail.index', [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('openingBalance', 0.0);
    $response->assertViewHas('totalAmount', 1000.0);
    $response->assertViewHas('closingBalance', 1000.0);
});

test('opening balance includes prior expenses when viewing a past month', function () {
    $twoMonthsAgoDate = now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(5)->toDateString();
    $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
    $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
    $lastMonthMid = now()->subMonthNoOverflow()->startOfMonth()->addDays(10)->toDateString();

    // Expense two months ago — should appear in opening balance for last-month view
    ExpenseDetail::factory()->create([
        'transaction_date' => $twoMonthsAgoDate,
        'amount' => 3000,
        'category' => 'fuel',
    ]);

    // Expense in last month — should appear in period total
    ExpenseDetail::factory()->create([
        'transaction_date' => $lastMonthMid,
        'amount' => 1000,
        'category' => 'fuel',
    ]);

    $response = $this->get(route('reports.expense-detail.index', [
        'date_from' => $lastMonthStart,
        'date_to' => $lastMonthEnd,
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('openingBalance', 3000.0);
    $response->assertViewHas('totalAmount', 1000.0);
    $response->assertViewHas('closingBalance', 4000.0);
});

test('unauthorized user cannot access expense detail report', function () {
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $response = $this->get(route('reports.expense-detail.index'));

    $response->assertForbidden();
});
