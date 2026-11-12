# Goods Issue Line Removal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Take back a whole line, or part of one, from an issued goods issue that has no settlement, returning the stock to the same warehouse and batches and keeping every ledger in step.

**Architecture:** One service, `GoodsIssueLineRemovalService`, mirrors `DistributionService::postSupplementaryItems()` in reverse for one line and one quantity, reusing the shapes `GoodsIssueReversalService` already writes. Readers of a goods issue's batches switch from `ABS(quantity)` to net quantity per batch. The UI lives on the existing append-items page.

**Tech Stack:** Laravel 12, PHP 8.4, Pest (pgsql via phpunit.xml), Alpine.js, Blade, spatie/laravel-permission.

**Spec:** `docs/superpowers/specs/2026-11-12-goods-issue-line-removal-design.md`

## Global Constraints

- Every write happens in one `DB::transaction`, with the goods issue row read again with `lockForUpdate()`.
- All entries are dated the **issue date**; `removed_at` keeps the real time.
- Journal reference `GI-…-R{n}`, posted (`auto_post => true`), **Dr Stock in Hand (issue's account, else 1151) / Cr Van Stock (issue's account, else 1155)**, cost centre = salesman's.
- Batches come back **last-issued first** (reverse of the issue order), each at the unit cost it left at.
- Removing the issue's last remaining quantity is refused with: "This is all that is left on {GI}. Use Reverse & Re-issue to take back the whole issue."
- Page URL `/goods-issues/{goodsIssue}/items`, route name `goods-issues.items`, title "Add or remove items · {GI}"; the old GET `/goods-issues/{goodsIssue}/append-items` 301-redirects there (query string kept). Detail-page button text: "Add or remove items". The add POST keeps its route (`goods-issues.store-appended-items`).
- Permission name `goods-issue-remove-items`; seeded to roles `super-admin` and `admin`.
- Reason: required, 5–500 characters. Password checked with `Hash::check`, as `GoodsIssueController::reverse()` does.
- Never run `migrate:fresh`/`db:wipe` on the local MySQL; tests run on pgsql. Do not commit unless the user asks.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes and `bun run build` after view/CSS changes.

## Review Focus

1. **Same product on two lines** (main + supplementary) from the same batch: removing from one line must not touch the other line's movements, van batch or settlement figures. → test in Task 2.
2. **Quantity with decimals** (e.g. 2.5 of a 12.000 line) and a quantity equal to the standing amount within `StockValuationService::QTY_EPSILON`. → test in Task 2.
3. **Two people removing at once** from the same line: the second must see the reduced standing quantity (lock) and be refused if it would go below zero. → test in Task 2 (sequential calls on a stale model).
4. **Removal, then supplementary add, then removal**: `-S` and `-R` numbering stay independent and `ownsJournalReference()` accepts both. → test in Task 1.
5. **Draft settlement created after the page was opened**: the POST is refused server-side even though the page showed Remove. → test in Task 5.

---

### Task 1: Permission, audit table, model, journal reference

**Files:**
- Create: `database/migrations/2026_11_12_200000_add_goods_issue_item_removals.php` (via `php artisan make:migration`)
- Create: `app/Models/GoodsIssueItemRemoval.php` (via `php artisan make:model`), factory `database/factories/GoodsIssueItemRemovalFactory.php`
- Modify: `app/Models/GoodsIssue.php` (`ownsJournalReference()`, new `removals()` relation)
- Modify: `app/Models/GoodsIssueItem.php` (new `removals()` relation)
- Modify: `app/Services/GoodsIssueReversalService.php` (`postedJournalEntries()`, `copyToDraft()`)
- Test: `tests/Feature/GoodsIssueLineRemovalTest.php` (new), `tests/Feature/GoodsIssueReversalTest.php`

**Interfaces:**
- Produces: table `goods_issue_item_removals` (`id`, `goods_issue_id` FK, `goods_issue_item_id` FK, `quantity decimal(15,3)`, `cost_value decimal(15,2)`, `selling_value decimal(15,2)`, `reason text`, `journal_entry_id` nullable FK, `batches json`, `removed_by` FK users, `removed_at timestamp`, timestamps).
- Produces: `GoodsIssueItemRemoval` with casts `batches => array`, `removed_at => datetime`, relations `goodsIssue()`, `item()`, `journalEntry()`, `removedBy()`.
- Produces: `GoodsIssue::removals(): HasMany`, `GoodsIssueItem::removals(): HasMany`.
- Produces: `GoodsIssue::ownsJournalReference()` matches `^{issue_number}(-[SR]\d+)?$`.
- Migration also inserts permission `goods-issue-remove-items` for `super-admin`/`admin`, same shape as `2026_11_05_170337_add_sales_settlement_revert_permission.php`.

- [ ] **Step 1: Write the failing tests**

```php
it('treats -R journal references as the issue\'s own', function () {
    $issue = GoodsIssue::factory()->create(['issue_number' => 'GI-2026-0042']);
    expect($issue->ownsJournalReference('GI-2026-0042-R1'))->toBeTrue()
        ->and($issue->ownsJournalReference('GI-2026-0042-S2'))->toBeTrue()
        ->and($issue->ownsJournalReference('GI-2026-00421-R1'))->toBeFalse();
});

it('seeds the remove-items permission for admins', function () {
    expect(Permission::where('name', 'goods-issue-remove-items')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/GoodsIssueLineRemovalTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement** the migration, model, factory, relations and the regex change; in `GoodsIssueReversalService::postedJournalEntries()` widen the `like` to also take `{issue}-R%`; in `copyToDraft()` skip merged lines whose quantity is ≤ `QTY_EPSILON`.
- [ ] **Step 4: Run** the new file plus `tests/Feature/GoodsIssueReversalTest.php` — Expected: PASS.
- [ ] **Step 5: Pint.**

---

### Task 2: `GoodsIssueLineRemovalService` (the bookkeeping)

**Files:**
- Create: `app/Services/GoodsIssueLineRemovalService.php` (via `php artisan make:class`)
- Modify: `app/Services/InventoryLedgerService.php` (new `recordIssueRemoval()`)
- Modify: `app/Services/DistributionService.php` (extract the `-S{n}` journal builder so `-R{n}` reuses it, or add `createGoodsIssueRemovalJournalEntry()` beside it)
- Test: `tests/Feature/GoodsIssueLineRemovalTest.php`

**Interfaces:**
- Consumes: Task 1 model and reference rule.
- Produces:
  - `refusal(GoodsIssueItem $item, float $quantity): ?string` (every check of the spec's "When a removal is allowed" except permission/password)
  - `standingBatches(GoodsIssueItem $item): Collection` → rows `{stock_batch_id:int, batch_code:string, quantity:float, unit_cost:float}`, **last-issued first**, net of earlier removals
  - `preview(GoodsIssueItem $item, float $quantity): array{batches: list<array{stock_batch_id:int, batch_code:string, quantity:float, unit_cost:float}>, cost: float}`
  - `remove(GoodsIssueItem $item, float $quantity, string $reason): array{success: bool, message: string, removal: ?GoodsIssueItemRemoval}`
  - `InventoryLedgerService::recordIssueRemoval(int $productId, int $warehouseId, int $vehicleId, ?int $employeeId, float $quantity, float $unitCost, int $goodsIssueId, string $date, string $notes, int $batchId): void` → two `TYPE_ADJUSTMENT` entries: warehouse debit, vehicle credit.
- `remove()` writes exactly the table list in the spec ("Tables"), reusing `CurrentStockByBatch` update/create, `StockValuationService::restoreBatch($batchId, $warehouseId, $qty, $unitCost, $movementId)` and `InventoryService::syncCurrentStockFromValuationLayers()`; van balance `average_cost` becomes `(oldQty × oldAvg − cost) ÷ newQty` (0 when newQty is 0); `goods_issue_items.total_value` drops by `quantity × selling_price` of the returned batches; `goods_issues.total_value` = sum of lines.

Books helper (top of the test file): `expectBooks(array $fixture, array $expected): void` asserts, in one place, **every table the posting touches**: `stock_movements` (net per line and batch), `current_stock_by_batch`, `stock_valuation_layers.quantity_remaining`, `current_stock`, `inventory_ledger_entries` (warehouse and vehicle balance per batch), `van_stock_balances` (quantity and value), `van_stock_batches`, journal totals of 1151 and 1155 for the issue's references, `goods_issue_items` and `goods_issues.total_value`, and `goods_issue_item_removals`. Every test in Tasks 2–4 ends with `expectBooks()`.

Fixture (top of the test file): a helper `issueFromTwoBatches(): array` — two GRNs of the same product (batch A: 10 @ cost 10, sell 15; batch B: 10 @ cost 12, sell 18), a user with `goods-issue-list`, `goods-issue-edit`, `goods-issue-remove-items`, and a posted issue with one line of 16 (takes A 10 + B 6) plus a second line of another product (5 units) so "last quantity" is not hit. Build it like `postGoodsIssueToWrongVan()` in `tests/Feature/GoodsIssueReversalTest.php`.

- [ ] **Step 1: Write the failing tests** (names and assertions)

```php
it('removes part of a line from the batch issued last, at that batch\'s cost', function () {
    // remove 4 of 16 → batch B back 4 @ 12
    // asserts: CurrentStockByBatch B +4; valuation layer B quantity_remaining +4; current_stock +4
    // inventory_ledger_entries: warehouse debit 4 and vehicle credit 4 on batch B, goods_issue_id set
    // van_stock_balances qty 16+5−4 for the product line's product (16−4 = 12); van_stock_batches row 12
    // journal GI-…-R1 posted: Dr 1151 48.00 / Cr 1155 48.00, entry_date = issue_date
    // item quantity_issued 12, total_value 16 lines value − 4×18; removal row: quantity 4, cost_value 48, batches [B 4 @ 12]
});

it('removes a whole line and leaves the books as if it had never been posted', function () {
    // remove all 16 → A 10 and B 6 back; 1151/1155 net for the issue equal only the other line's cost
});

it('works on what is left when the same line is removed twice', function () {
    // remove 4 then 8 → B 6 then (B 2 + A 6); journals -R1, -R2
});

it('takes a decimal quantity and the exact standing quantity', function () {
    // remove 2.5, then remove the remaining 13.5 in one go (no epsilon leftovers)
});

it('touches only its own line when the same product sits on two lines', function () {
    // second line same product from batch A; removing from line 1 leaves line 2's movements, van batch and value untouched
});

it('refuses', function (string $case, string $message) {
    // settlement exists (any status) → 'Settlement … has been made against it'
    // issue draft / cancelled → 'Only a posted (issued) goods issue'
    // more than standing → 'Only … is left on this line'
    // the issue's last quantity → the Global Constraints sentence
    // van holds less → 'The van holds …'
    // journal already reversed → 'already been reversed'
    // stale model: second remove on a model loaded before the first → refused, nothing written
})->with([...]);
```

- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/GoodsIssueLineRemovalTest.php` — Expected: FAIL (class missing).
- [ ] **Step 3: Implement** the service and `recordIssueRemoval()`; on any failure return `success=false` with the message and log `goods_issue`, `item`, `quantity`, `user`, `error`.
- [ ] **Step 4: Run** the file — Expected: PASS.
- [ ] **Step 5: Pint.**

---

### Task 3: Readers use net batches; settlement leaves out empty lines

**Files:**
- Modify: `app/Http/Controllers/GoodsIssueController.php:440-476` (show, posted branch)
- Modify: `app/Http/Controllers/SalesSettlementController.php:350-370` (settlement detail) and `:540-560` (`fetchGoodsIssueItems`)
- Test: `tests/Feature/GoodsIssueLineRemovalTest.php`

**Interfaces:**
- Consumes: Task 2 `remove()`.
- Produces: each reader selects `sb.id, sb.batch_code, SUM(-sm.quantity) as quantity, MAX(sm.unit_cost) as unit_cost, sb.selling_price, sb.is_promotional` grouped by batch, `HAVING SUM(-sm.quantity) > 0`; `fetchGoodsIssueItems` drops lines with `quantity_issued ≤ 0.0005` from `formattedItems`.

- [ ] **Step 1: Write the failing tests**

```php
it('shows the settlement form the reduced line and only the batches still on the van', function () {
    // remove 4 → GET api/sales-settlements/goods-issues/{id}/items: line quantity_issued 12, batches [A 10, B 2]
});

it('leaves a fully removed line out of the settlement form', function () {});

it('shows net batches on the issue page after a removal', function () {
    // GET goods-issues.show → assertSee('2 × 18.00'), assertDontSee('6 × 18.00')
});
```

- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement** the three queries and the filter.
- [ ] **Step 4: Run** the file plus `tests/Feature/GoodsIssueMultiLineSameProductTest.php` and the settlement tests that hit `fetchGoodsIssueItems` (`grep -rl "goods-issues/.*/items\|fetchGoodsIssueItems" tests`) — Expected: PASS.
- [ ] **Step 5: Pint.**

---

### Task 4: End-to-end books after removal (reverse and settle)

**Files:**
- Test: `tests/Feature/GoodsIssueLineRemovalTest.php`
- Modify only if a test fails: the code from Tasks 1–3.

**Interfaces:**
- Consumes: `GoodsIssueReversalService::reverse()`, the settlement posting path used in existing settlement tests.

- [ ] **Step 1: Write the tests**

```php
it('balances the books when a partly removed issue is then reversed', function () {
    // remove 4, reverse → for the issue's references (main, -R1, REV-…): sum Dr−Cr on 1151 = 0 and on 1155 = 0
    // warehouse stock back to the GRN totals per batch; van balance 0; replacement draft line quantity 12 (not 16)
});

it('settles the reduced quantity and empties the van', function () {
    // remove 4, post a settlement selling all 12 + 5 → van_stock_balances 0, no negative batches
});
```

- [ ] **Step 2: Run** — fix in Tasks 1–3 code until PASS.
- [ ] **Step 3: Pint.**

---

### Task 5: HTTP action, page, sheet, help

**Files:**
- Modify: `routes/web.php`: `GET goods-issues/{goodsIssue}/items` → `appendItemsForm`, name `goods-issues.items` (middleware as the old append-items GET); old `GET goods-issues/{goodsIssue}/append-items` becomes a closure 301 to `goods-issues.items` keeping the query string; update every `route('goods-issues.append-items', …)` call (views, JS, controller redirects); `POST goods-issues/{goodsIssue}/items/{item}/remove` → `GoodsIssueController@removeItem`, name `goods-issues.remove-item`, middleware `can:goods-issue-remove-items`
- Create: `app/Http/Requests/RemoveGoodsIssueItemRequest.php` (`quantity` required numeric > 0, `reason` required string 5–500, `password` required string; `authorize()` = `can('goods-issue-remove-items')`)
- Modify: `app/Http/Controllers/GoodsIssueController.php` (`removeItem()`; `appendItemsForm()` passes `removableLines` = per line `{id, line_no, sku, standing, batches (standingBatches), refusal}` and `canRemove`)
- Modify: `resources/views/goods-issues/append-items.blade.php` (Remove column + Apple sheet with quantity, live batch preview computed in Alpine from `batches` last-first, journal line, reason, password; settlement banner hides Remove)
- Modify: `resources/views/goods-issues/show.blade.php` (button text "Add or remove items"; shown for `goods-issue-edit` or `goods-issue-remove-items`; Timeline events from `removals`; line note "removed {qty} · {d M} by {name}")
- Modify: `resources/views/components/register-help.blade.php` (goods-issues page: `gd-remove` section, Urdu)
- Test: `tests/Feature/GoodsIssueLineRemovalTest.php`

**Interfaces:**
- Consumes: Task 2 `refusal()`, `standingBatches()`, `remove()`.
- Produces: success redirect to `goods-issues.items` with `success` = "Removed {qty} of {SKU} from {GI}. Stock is back in {warehouse}; journal {ref} posted."; errors redirect back with `error` and `_remove_item` old input so the sheet reopens.

- [ ] **Step 1: Write the failing tests**

```php
it('removes through the page with reason and password', function () {});        // asserts redirect + success text + DB effects (light)
it('rejects a wrong password and writes nothing', function () {});              // session error 'Invalid password…'
it('forbids users without the permission', function () {});                     // 403, and no Remove column on the page
it('refuses when a draft settlement appeared after the page was opened', function () {});
it('redirects the old append-items address to the items page', function () {});    // 301, Location ends with /items, query kept
it('shows Remove on each line of the add-or-remove page and the renamed button', function () {
    // assertSee('Add or remove items'), assertSee('aria-label="Remove from', false)
});
```

- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement** route, request, controller, views, help.
- [ ] **Step 4: Run** the file plus all `tests/Feature/GoodsIssue*Test.php` and `tests/Feature/Permissions/GoodsIssuePermissionTest.php` — Expected: PASS.
- [ ] **Step 5: Pint and `bun run build`.**
- [ ] **Step 6: Browser check** (Chrome): on an issued issue without settlement, open Add or remove items at 1440 and 390 widths, open the sheet, check the preview, cancel; console free of app errors. Do not submit a removal on the local production copy unless the user asks.
