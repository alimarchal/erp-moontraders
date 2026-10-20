<?php

use App\Models\BankCheque;
use App\Models\BankEntryCategory;
use App\Models\BankEntryRule;
use App\Models\BankPartyAlias;
use App\Models\BankStatementEntry;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->fixture = bankRegisterFixture();
    $this->admin = bankUser(array_merge(
        collect(['bank-entry-category', 'bank-party-alias', 'bank-entry-rule', 'bank-cheque'])->flatMap(fn ($g) => ["{$g}-list", "{$g}-create", "{$g}-edit", "{$g}-delete"])->all()
    ));
    $this->actingAs($this->admin);
});

test('category CRUD', function () {
    $this->get(route('bank-entry-categories.index'))->assertOk()->assertSee('Customer Receipt');
    $this->get(route('bank-entry-categories.create'))->assertOk();

    $this->post(route('bank-entry-categories.store'), ['name' => 'Rent', 'type' => 'payment', 'sort_order' => 5, 'is_active' => 1])->assertRedirect(route('bank-entry-categories.index'));
    $category = BankEntryCategory::where('name', 'Rent')->firstOrFail();
    expect($category->slug)->toBe('rent')->and($category->type)->toBe('payment');

    $this->get(route('bank-entry-categories.edit', $category))->assertOk()->assertSee('Rent');
    $this->put(route('bank-entry-categories.update', $category), ['name' => 'Office Rent', 'type' => 'payment', 'is_active' => 0])->assertRedirect();
    expect($category->fresh()->name)->toBe('Office Rent')->and($category->fresh()->is_active)->toBeFalse();

    $this->delete(route('bank-entry-categories.destroy', $category))->assertRedirect();
    expect(BankEntryCategory::where('name', 'Office Rent')->exists())->toBeFalse();
});

test('category names are unique and a category in use cannot be deleted', function () {
    $this->post(route('bank-entry-categories.store'), ['name' => 'Customer Receipt', 'type' => 'both'])->assertSessionHasErrors('name');

    BankStatementEntry::factory()->create(['bank_entry_category_id' => $this->fixture['category']->id]);
    $this->delete(route('bank-entry-categories.destroy', $this->fixture['category']))->assertSessionHas('error');
    expect($this->fixture['category']->fresh())->not->toBeNull();
});

test('alias CRUD normalises the name and keeps it unique', function () {
    $this->post(route('bank-party-aliases.store'), ['alias_name' => 'jameel  ahm', 'canonical_name' => 'Jameel Ahmed', 'company_id' => $this->fixture['company']->id])->assertRedirect();
    $alias = BankPartyAlias::firstOrFail();
    expect($alias->alias_name)->toBe('JAMEEL AHM')->and($alias->company_id)->toBe($this->fixture['company']->id);

    $this->post(route('bank-party-aliases.store'), ['alias_name' => 'Jameel Ahm', 'canonical_name' => 'x'])->assertSessionHasErrors('alias_name');

    $this->get(route('bank-party-aliases.index', ['filter' => ['alias_name' => 'jameel']]))->assertOk()->assertSee('JAMEEL AHM');
    $this->put(route('bank-party-aliases.update', $alias), ['alias_name' => 'JAMEEL AHM', 'canonical_name' => 'Jameel Ahmed Malik', 'is_active' => 1])->assertRedirect();
    expect($alias->fresh()->canonical_name)->toBe('Jameel Ahmed Malik');

    $this->delete(route('bank-party-aliases.destroy', $alias))->assertRedirect();
    expect(BankPartyAlias::count())->toBe(0);
});

test('rule CRUD validates the choices', function () {
    $payload = ['name' => 'Charges', 'match_field' => 'narration', 'match_type' => 'contains', 'match_value' => 'service charges', 'direction' => 'debit', 'bank_entry_category_id' => $this->fixture['category']->id, 'priority' => 10, 'is_active' => 1];

    $this->post(route('bank-entry-rules.store'), array_merge($payload, ['match_type' => 'regex']))->assertSessionHasErrors('match_type');
    $this->post(route('bank-entry-rules.store'), $payload)->assertRedirect(route('bank-entry-rules.index'));

    $rule = BankEntryRule::firstOrFail();
    expect($rule->created_by)->toBe($this->admin->id);

    $this->get(route('bank-entry-rules.index'))->assertOk()->assertSee('service charges');
    $this->get(route('bank-entry-rules.edit', $rule))->assertOk();
    $this->put(route('bank-entry-rules.update', $rule), array_merge($payload, ['priority' => 3]))->assertRedirect();
    expect($rule->fresh()->priority)->toBe(3);
    $this->delete(route('bank-entry-rules.destroy', $rule))->assertRedirect();
    expect(BankEntryRule::count())->toBe(0);
});

test('cheque CRUD keeps a cheque number unique per account and direction', function () {
    $payload = ['bank_account_id' => $this->fixture['bank']->id, 'cheque_no' => '00000264', 'direction' => 'issued', 'cheque_date' => '2025-03-01', 'amount' => 140000, 'status' => 'pending', 'payee_name' => 'Dalda Foods'];

    $this->post(route('bank-cheques.store'), $payload)->assertRedirect(route('bank-cheques.index'));
    $this->post(route('bank-cheques.store'), $payload)->assertSessionHasErrors('cheque_no');
    $this->post(route('bank-cheques.store'), array_merge($payload, ['direction' => 'received']))->assertSessionHasNoErrors();

    $cheque = BankCheque::where('direction', 'issued')->firstOrFail();
    $this->get(route('bank-cheques.index', ['filter' => ['status' => 'pending']]))->assertOk()->assertSee('00000264');
    $this->put(route('bank-cheques.update', $cheque), array_merge($payload, ['status' => 'bounced']))->assertRedirect();
    expect($cheque->fresh()->status)->toBe('bounced');

    $entry = BankStatementEntry::factory()->create();
    $cheque->update(['bank_statement_entry_id' => $entry->id]);
    $this->delete(route('bank-cheques.destroy', $cheque))->assertSessionHas('error');
});

test('masters respect the delete permission', function () {
    $this->actingAs(bankUser(['bank-entry-category-list', 'bank-entry-category-edit']));

    $this->delete(route('bank-entry-categories.destroy', $this->fixture['category']))->assertForbidden();
    $this->get(route('bank-entry-categories.create'))->assertForbidden();
});

test('the settings page lists the bank register for a permitted user', function () {
    Permission::findOrCreate('setting-view');

    $this->actingAs(bankUser(['setting-view', 'bank-entry-category-list', 'bank-statement-entry-list']))
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Bank Register')
        ->assertSee('Bank Entry Categories');
});
