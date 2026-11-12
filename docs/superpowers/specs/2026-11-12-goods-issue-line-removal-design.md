# Goods Issue: remove a line, or part of it, before settlement

Date: 2026-11-12 · Status: draft for review

## Purpose

A salesman sometimes returns part of the load before the evening settlement (wrong product, too much loaded, a
product added by mistake). Today the only fix is Reverse & Re-issue, which cancels the whole issue and makes a
new draft. The owner wants to take back one line, or part of one line, from an issued goods issue, with the stock
going back to the **same warehouse and the same batches** it left from, and every ledger (stock, inventory
ledger, van stock, general ledger) staying in step, exactly as when the issue was posted, only in reverse.

## Decisions taken with the owner

| Question | Decision |
|---|---|
| What can be removed | Any line (original or supplementary), whole or part of its quantity |
| Who | New permission `goods-issue-remove-items` (seeded to super admin / admin roles by migration); every removal needs a reason and the user's password |
| Pages | Remove sits on the "+ Add more items" page (`/goods-issues/{id}/append-items`), next to adding: each line in "Items already on this issue" gets a Remove action. The detail page's "+ Add more items" button is renamed "Add or remove items" |
| Last quantity | Removing everything that is left on the issue is refused; the message points to Reverse & Re-issue (an issue with nothing on it would keep the vehicle lock and leave nothing to settle) |

## When a removal is allowed

Checked in the browser for the form and again on the server, inside the transaction, with the goods issue row
locked (`lockForUpdate`):

1. The issue is `issued` (not draft, not reversed).
2. No settlement exists for it, in any status (draft, verified, posted).
3. The quantity is greater than 0 and not more than what is still standing on that line (net of earlier removals).
4. After the removal, the issue still has some quantity on it (see "Last quantity").
5. The van still holds at least that quantity of the product (`van_stock_balances.quantity_on_hand`).
6. None of the issue's journal entries has already been reversed (same check as the reversal service).
7. The user has the permission and the password is right.

## What one removal writes (one database transaction)

The removal mirrors what posting the line wrote, for the removed quantity only. All dates are the **issue date**,
as supplementary posting and reversal already do, so the van stock of that day and the settlement agree; the real
time of the removal is stored on the audit row.

### Which batches come back

The line's standing movements are read per batch (net of earlier removals). The quantity is taken back from the
batch issued **last** first (the reverse of the oldest / soonest-expiring order used when issuing), so the stock
that must be sold first stays on the van. Each batch returns at the unit cost it left at.

### Tables

| Table | Entry |
|---|---|
| `stock_movements` | One row per batch: `movement_type = transfer`, same `reference_type/reference_id`, same `goods_issue_item_id`, same `stock_batch_id`, `warehouse_id`, `vehicle_id`, **positive** quantity, the batch's unit cost (the reversal service writes the same shape) |
| `current_stock_by_batch` | Quantity and value added back on the batch; status `active` (row created if it was deleted) |
| `stock_valuation_layers` | `StockValuationService::restoreBatch()` for each batch, as reversal does |
| `current_stock` | `syncCurrentStockFromValuationLayers()` for the product and warehouse |
| `inventory_ledger_entries` | Per batch, a pair of `adjustment` entries on the issue: warehouse **debit** (stock back in) and vehicle **credit** (stock off the van), both with `goods_issue_id`, product, batch, unit cost and a note "Removed from GI-… : reason" |
| `van_stock_balances` | Quantity reduced; `average_cost` recomputed as (old quantity × old average − removed cost) ÷ new quantity, so the van's value keeps matching 1155 |
| `van_stock_batches` | The line's row reduced by the quantity |
| `journal_entries` | New posted entry `GI-…-R{n}` dated the issue date: **Dr 1151 Stock in Hand / Cr 1155 Van Stock** for the removed cost, using the issue's own Stock in Hand and Van Stock accounts, cost centre of the salesman |
| `goods_issue_items` | `quantity_issued`, `total_value` reduced; a line taken to 0 stays (its movements keep their link) |
| `goods_issues` | `total_value` recomputed from the lines |
| `goods_issue_item_removals` (new) | `goods_issue_id`, `goods_issue_item_id`, `quantity`, `cost_value`, `selling_value`, `reason`, `journal_entry_id`, `batches` (json: batch id, code, quantity, unit cost), `removed_by`, `removed_at` |

## Code that must change with it (otherwise the books drift)

1. **`GoodsIssue::ownsJournalReference()`** accepts `-R{n}` as well as `-S{n}`, so the detail page lists the entry
   and the reversal service finds it.
2. **`GoodsIssueReversalService`**: `postedJournalEntries()` includes `-R` entries, so a later full reversal offsets
   them too. Its movements, van and draft copy already work on net quantities, so they take only what is left;
   `copyToDraft()` skips lines at 0.
3. **Readers of a goods issue's batches** must read **net** quantity per batch (`SUM(quantity)` grouped by batch,
   keeping rows above 0) instead of `ABS(quantity)` of every row:
   - `SalesSettlementController::fetchGoodsIssueItems()` (the settlement form),
   - `SalesSettlementController` around line 360 (settlement detail),
   - `GoodsIssueController::show()` (issue detail and its PDF).
4. **Settlement form**: lines at quantity 0 are left out (`itemsInSettlementOrder()` filters them for the form).
   A line with part removed shows its reduced quantity; its brought-forward (BF) figure is unchanged because the
   removal's ledger entries come after the issue's transfer-in entry.
5. **Goods issue list and Quick look**: use the reduced line quantities and value (they read `goods_issue_items`
   and `goods_issues.total_value`, which are updated).

## Interface

- Entry point: the detail page's "+ Add more items" button becomes **"Add or remove items"** and opens
  `/goods-issues/{id}/append-items` as today. It is shown while the issue can take changes (issued, no settlement);
  the button stays for users with either `goods-issue-edit` (add) or `goods-issue-remove-items` (remove).
- On that page, the "Items already on this issue" table gets a last column with a **Remove** action on every line that
  still has quantity (a small "−" button, keyboard reachable, aria-label "Remove from GI-… : SKU"). Users without the
  permission see no Remove column.
- Remove opens an Apple sheet (same look as the page's other modals):
  - the SKU, what is standing on the line, a quantity field (defaults to all of it; never more),
  - a live preview of the batches that will come back (code, quantity, cost),
  - the journal it will post (Dr Stock in Hand / Cr Van Stock, Rs …),
  - a required reason, and the password.
- When a line cannot be removed, the button is disabled with the reason as its tooltip (last quantity on the issue,
  van holds less, journal already reversed). A draft settlement on the issue hides Remove for the whole page with a
  banner: "Settlement … exists; revert or delete it first".
- After a removal the page reloads with a success message; the line shows its reduced quantity and "removed 50 ·
  12 Nov by …". On the detail page the Timeline lists it and Accounting lists `GI-…-R1`.
- The Urdu help gets a "Remove" section.

## Errors

Any failed check returns to the page with the reason and nothing written (the transaction rolls back). The log gets
the issue, line, quantity, user and error.

## Tests (Pest, pgsql)

For each case the test asserts, after the action: batch stock, `current_stock`, valuation layers, inventory ledger
(warehouse and van) per batch, van balance and van batch, journal totals of 1151 and 1155, the line and issue totals,
and the audit row.

1. Remove a whole line: every table back as if the line had never been posted; the settlement form leaves it out.
2. Remove part of a line issued from two batches: the last-issued batch comes back first; the settlement form shows
   the reduced quantity and the net batches.
3. Remove twice from the same line: the second removal works on what is left; journal `-R1`, `-R2`.
4. Refused: settlement exists, issue reversed or draft, more than standing, removing the issue's last quantity,
   van holds less, missing permission, wrong password.
5. Remove part, then Reverse & Re-issue the whole issue: 1151 and 1155 net to zero for the issue, stock fully back,
   the draft copy carries only what was left.
6. Remove part, then post a settlement: the settlement consumes the reduced quantity and the van ends at zero.

## Out of scope

Removing after a settlement exists (the settlement must be reverted first); choosing batches by hand; removal from
the list page.
