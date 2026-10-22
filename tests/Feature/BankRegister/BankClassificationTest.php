<?php

use App\Models\BankCheque;
use App\Models\BankEntryCategory;
use App\Models\BankEntryRule;
use App\Models\BankPartyAlias;
use App\Models\BankStatementEntry;
use App\Services\BankRegister\BankEntryClassifier;

beforeEach(function () {
    $this->fixture = bankRegisterFixture();
});

function statementEntry(array $attributes = []): BankStatementEntry
{
    return BankStatementEntry::factory()->create($attributes);
}

test('an alias gives every spelling of a party the same company', function () {
    BankPartyAlias::create(['alias_name' => 'jameel  ahm', 'canonical_name' => 'Jameel Ahmed', 'company_id' => $this->fixture['company']->id]);

    $entry = statementEntry(['bank_account_id' => $this->fixture['bank']->id, 'party_name_in_statement' => 'JAMEEL AHM']);

    expect(app(BankEntryClassifier::class)->classify($entry))->toBeTrue()
        ->and($entry->fresh()->company_id)->toBe($this->fixture['company']->id)
        ->and($entry->fresh()->status)->toBe(BankStatementEntry::STATUS_UNCLASSIFIED);
});

test('alias names are stored without case or extra spaces', function () {
    $alias = BankPartyAlias::create(['alias_name' => '  Jameel   Ahm ', 'canonical_name' => 'Jameel Ahmed']);

    expect($alias->alias_name)->toBe('JAMEEL AHM');
});

test('a rule sets the category and the entry becomes classified', function () {
    $charges = BankEntryCategory::where('slug', 'bank-charges')->first();
    BankEntryRule::factory()->create([
        'match_field' => 'narration', 'match_type' => 'contains', 'match_value' => 'service charges',
        'direction' => 'debit', 'bank_entry_category_id' => $charges->id,
    ]);

    $entry = statementEntry(['bank_account_id' => $this->fixture['bank']->id, 'narration' => 'Monthly SERVICE CHARGES for June', 'credit' => 0, 'debit' => 500]);

    app(BankEntryClassifier::class)->classify($entry);

    expect($entry->fresh()->bank_entry_category_id)->toBe($charges->id)
        ->and($entry->fresh()->status)->toBe(BankStatementEntry::STATUS_CLASSIFIED);
});

test('a rule limited to debits ignores credits and the lowest priority number wins', function () {
    $charges = BankEntryCategory::where('slug', 'bank-charges')->first();
    $other = BankEntryCategory::where('slug', 'other')->first();
    BankEntryRule::factory()->create(['match_value' => 'fee', 'direction' => 'debit', 'bank_entry_category_id' => $charges->id, 'priority' => 10]);
    BankEntryRule::factory()->create(['match_value' => 'fee', 'direction' => 'any', 'bank_entry_category_id' => $other->id, 'priority' => 50]);

    $credit = statementEntry(['narration' => 'fee refund', 'credit' => 100, 'debit' => 0]);
    $debit = statementEntry(['narration' => 'fee', 'credit' => 0, 'debit' => 100]);

    $classifier = app(BankEntryClassifier::class);
    $classifier->classify($credit);
    $classifier->classify($debit);

    expect($credit->fresh()->bank_entry_category_id)->toBe($other->id)
        ->and($debit->fresh()->bank_entry_category_id)->toBe($charges->id);
});

test('an inactive rule is never applied', function () {
    BankEntryRule::factory()->create(['match_value' => 'zzz', 'is_active' => false]);
    $entry = statementEntry(['narration' => 'zzz']);

    expect(app(BankEntryClassifier::class)->classify($entry))->toBeFalse();
});

test('a pending issued cheque with the same number and amount is cleared by the matching statement line', function () {
    $cheque = BankCheque::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'cheque_no' => '00000264', 'amount' => 140000,
        'direction' => 'issued', 'company_id' => $this->fixture['company']->id,
    ]);
    $wrongAmount = BankCheque::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'cheque_no' => '00000999', 'amount' => 1]);

    $entry = statementEntry([
        'bank_account_id' => $this->fixture['bank']->id, 'reference' => 'CHQ Dr. Online 00000264',
        'debit' => 140000, 'credit' => 0,
    ]);
    $other = statementEntry(['bank_account_id' => $this->fixture['bank']->id, 'reference' => 'CHQ Dr. Online 00000999', 'debit' => 500, 'credit' => 0]);

    $classifier = app(BankEntryClassifier::class);
    $classifier->classify($entry);
    $classifier->classify($other);

    expect($cheque->fresh()->status)->toBe('cleared')
        ->and($cheque->fresh()->bank_statement_entry_id)->toBe($entry->id)
        ->and($entry->fresh()->company_id)->toBe($this->fixture['company']->id)
        ->and($wrongAmount->fresh()->status)->toBe('pending');
});

test('classifyPending only touches lines that are not yet classified or locked', function () {
    $charges = BankEntryCategory::where('slug', 'bank-charges')->first();
    BankEntryRule::factory()->create(['match_value' => 'charge', 'bank_entry_category_id' => $charges->id]);

    $open = statementEntry(['narration' => 'charge']);
    $posted = statementEntry(['narration' => 'charge', 'status' => BankStatementEntry::STATUS_POSTED]);

    expect(app(BankEntryClassifier::class)->classifyPending())->toBe(1)
        ->and($open->fresh()->bank_entry_category_id)->toBe($charges->id)
        ->and($posted->fresh()->bank_entry_category_id)->toBeNull();
});
