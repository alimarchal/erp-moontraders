# Vouchers, Cheque Register and Cash Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the daily cash sheet balance for every company. Turn expense vouchers into standard payment vouchers (cash notes, bank transfer or cheque, reversal). Rebuild the cheque register on the Goods Issues design.

**Architecture:**
- Patch in place.
- Add columns to `expense_vouchers`.
- Add a polymorphic `cash_payout_denominations` table and a `cheque_clearings` batch table.
- Keep the old per-cheque `cleared_denom_*` columns so old days can still be read.
- One shared value object (`App\Support\CashNotes`) and one Blade component (`<x-cash-notes>`) handle every note count.
- The cash report reads notes in (settlements + clearing batches) and notes out (voucher payouts).

**Tech Stack:** Laravel 12, PHP 8.4, Pest on pgsql (`moontraders_test`), Alpine.js, Tailwind, Spatie Permission and QueryBuilder, Maatwebsite Excel, DomPDF.

**Spec:** `docs/superpowers/specs/2026-11-15-vouchers-cheques-cash-report-design.md` (issues #183–#190)

## Global Constraints
- **Never run** `migrate:fresh`, `migrate:refresh` or `db:wipe`. On the dev DB only `php artisan migrate` is allowed.
- Tests run on pgsql through phpunit.xml. Run the narrowest file while working. Run the whole affected set **once** at the end (Task 10).
- **Note values:** `SalesSettlementCheque::CLEARED_NOTES = [5000, 1000, 500, 100, 50, 20, 10]`, plus coins as an amount.
- **Accounts:**
  - 1121 Cash
  - 1122 Cheques in Hand
  - 1112 Debtors
  - 1131 Employee Advances
  - 2113 Expense Payables
  - expense accounts are active, non-group accounts whose code starts with `52`
  - a bank's account is `bank_accounts.chart_of_account_id`
- **Permissions:**
  - new: `expense-voucher-reverse`, `report-audit-cheque-register-all-companies`
  - grant both with `MigrationHelpers::grantPermissions([...])`
  - add both to `RoleAndPermissionSeeder` and `PermissionCatalog`
- **Reversal** needs a reason and the user's password (`Hash::check`), as `GoodsIssueController::reverse` does.
- **Copy for users:** plain English. Never show raw exception text. Log it, and flash "Posting failed. Please contact the admin." instead.
- Owner request (fonts and help): every page this plan touches (expense vouchers index, form and show; cheque register; cash collection distributor) uses the same look as /goods-issues:
  - Styles: `@include('settings.partials.ui-style')`, `<x-apple-skin />` and `<x-register-apple-style />`.
  - Help: an Urdu help popup through `<x-register-help page="<page>" />`, opened from a ? icon button in the header (copy the goods-issues `afterFilter` slot).
  - Fonts: the help loads the Google fonts Inter and Noto Nastaliq Urdu through `<x-bank.help-style />`. Any Urdu text on these pages (labels such as اخراجاتی واؤچر) uses `font-family: 'Noto Nastaliq Urdu', 'Noto Naskh Arabic', serif`.
  - Help topics: add a branch in `resources/views/components/register-help.blade.php` for each page. Put its topics in `resources/views/<area>/partials/help-topics.blade.php`, written in Urdu like the goods-issues topics, and covering the new flows (payment method, notes counted by the approver, reverse, cheque clearing, and the notes in/out/net check).
- Run `vendor/bin/pint --dirty --format agent` before every commit.
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus
1. **Two clicks on Approve, Clear or Reverse.** Expect exactly one journal. Tests are in Tasks 3, 4 and 6.
2. **A cash voucher reversed on a later day.** The original day's sheet must not change, and the reversal day shows cash coming back. Tests are in Tasks 4 and 8.
3. **Old cheques cleared before this change** (per-cheque notes, no batch). The report must still balance for those days. Test in Task 8.
4. **A company user with no all-companies permission.** They must never see or clear another company's cheques, including through the bulk ids. Test in Task 7.
5. **A cheque bounced after it was cleared.** It must first be un-cleared (same day only), so 1122 is not credited twice. Test in Task 6.

---

### Task 1: Schema, models and permissions

**Files:**
- Create: `database/migrations/2026_11_15_100000_add_payment_method_and_reversal_to_expense_vouchers.php`
- Create: `database/migrations/2026_11_15_100100_create_cash_payout_denominations_table.php`
- Create: `database/migrations/2026_11_15_100200_create_cheque_clearings_table.php`
- Create: `database/migrations/2026_11_15_100300_add_voucher_and_cheque_register_permissions.php`
- Create: `app/Models/CashPayoutDenomination.php`, `app/Models/ChequeClearing.php` (+ factories)
- Modify: `app/Models/ExpenseVoucher.php`, `app/Models/SalesSettlementCheque.php`, `database/seeders/RoleAndPermissionSeeder.php`, `app/Services/PermissionCatalog.php`
- Test: `tests/Feature/ExpenseVoucherSchemaTest.php`

**Interfaces (produces):**
- **`expense_vouchers` new columns:**
  - `payment_method` string(20), default `cash`, indexed
  - `bank_account_id` nullable FK
  - `cheque_number` string(30) nullable
  - `cheque_date` date nullable
  - `transaction_reference` string(100) nullable
  - `paid_from_account_id` nullable FK to `chart_of_accounts`
  - `reversed_at` timestamp nullable
  - `reversed_by` nullable FK to users
  - `reversal_reason` text nullable
  - `reversal_journal_entry_id` nullable FK
  - Back-fill: `payment_method = payment_type === 'credit' ? 'credit' : 'cash'`
- **`ExpenseVoucher`:**
  - constants `METHOD_CASH`, `METHOD_BANK_TRANSFER`, `METHOD_CHEQUE`, `METHOD_CREDIT`, `STATUS_REVERSED = 'reversed'`
  - `paymentMethodLabels(): array`
  - `isPaidByBank(): bool` (bank transfer or cheque)
  - `isReversed(): bool`
  - `cashPayout(): MorphOne<CashPayoutDenomination>` (name `payable`)
  - `bankAccount(): BelongsTo`, `reverser(): BelongsTo`
- **`cash_payout_denominations`:**
  - `payable_type`, `payable_id` (unique together)
  - `denom_5000`, `denom_1000`, `denom_500`, `denom_100`, `denom_50`, `denom_20`, `denom_10` (unsignedInteger, default 0)
  - `denom_coins` decimal(10,2), default 0
  - `total_amount` decimal(15,2)
  - user tracking and timestamps
  - The model computes `total_amount` on `saving`, like `SalesSettlementCashDenomination`.
- **`cheque_clearings`:**
  - `cleared_date` date
  - `cleared_via` string(10)
  - `bank_account_id` nullable
  - `supplier_id` nullable FK
  - `denom_5000`, `denom_1000`, `denom_500`, `denom_100`, `denom_50`, `denom_20`, `denom_10`, `denom_coins`, `total_amount` (as above)
  - `notes` string(500) nullable
  - `created_by`, timestamps
- **`sales_settlement_cheques`:** `cheque_clearing_id` nullable FK (nullOnDelete), and `bounce_journal_entry_id` nullable FK.
- **`SalesSettlementCheque`:** `clearing(): BelongsTo<ChequeClearing>`.
- **`ChequeClearing`:** `cheques(): HasMany`.

- [ ] **Step 1:** Write the test `it('backfills payment_method from payment_type')`. Insert a credit voucher and a cash voucher through the factory with `payment_method` unset; assert `credit` and `cash`. Also `it('computes the payout total on save')`: 2×5000 + 3×100 + coins 5.50 → `total_amount` 10305.50.
- [ ] **Step 2:** Run `php artisan test --compact tests/Feature/ExpenseVoucherSchemaTest.php` → FAIL.
- [ ] **Step 3:** Write the migrations, models, factories and seeder/catalog entries. Use `php artisan make:migration` and `make:model`.
- [ ] **Step 4:** Run the test → PASS. Then run `php artisan migrate` on the dev DB.
- [ ] **Step 5:** Pint, then commit "Vouchers and cheque clearings: schema, models, permissions".

### Task 2: CashNotes value object and the `<x-cash-notes>` component

**Files:**
- Create: `app/Support/CashNotes.php`, `resources/views/components/cash-notes.blade.php`
- Test: `tests/Unit/CashNotesTest.php`

**Interfaces (produces):**
- `final class CashNotes`
  - `public const NOTES = SalesSettlementCheque::CLEARED_NOTES;`
  - `static fromInput(array $entered): self`: keys are note values plus `'coins'`. Negative or non-numeric values count as 0.
  - `static fromColumns(object|array $row, string $prefix = 'denom_'): self`
  - `total(): float`
  - `count(int $note): int`
  - `coins(): float`
  - `columns(string $prefix = 'denom_'): array<string,int|float>`
  - `plus(self $other): self`, `minus(self $other): self` (counts may go negative)
  - `isEmpty(): bool`
- **Blade:** `<x-cash-notes name="denom" :required="$amountExpr" x-model-root="form.denom" />`
  - A compact table with one row per note: note | count input (`name="denom[5000]"`) | value. Then a Coins row.
  - Sticky footer: "Counted Rs X · Required Rs Y · Difference Rs Z" (green when 0, red otherwise) and a small "Clear" link.
  - Two columns at ≥640px, one on mobile.
  - Alpine only. No new JS file.

- [ ] **Step 1:** Write unit tests:
  - `fromInput(['5000'=>2,'100'=>'3','coins'=>'5.5','20'=>-4])->total()` is `10305.5`;
  - `columns('cleared_denom_')` has key `cleared_denom_5000 => 2`;
  - `minus` gives a negative count;
  - `fromColumns` round-trips.
- [ ] **Step 2:** Run `php artisan test --compact tests/Unit/CashNotesTest.php` → FAIL.
- [ ] **Step 3:** Implement `CashNotes` and the component.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit "CashNotes value object and compact cash-notes component".

### Task 3: Voucher posting by method, cash notes, locking, account rule

**Files:**
- Modify: `app/Services/ExpenseVoucherService.php` (approve, create, update)
- Modify: `app/Http/Controllers/ExpenseVoucherController.php` (`validated()`, approve)
- Modify: `app/Services/ExpenseVendorPaymentService.php` (lock)
- Test: `tests/Feature/ExpenseVoucherPaymentMethodTest.php`

**Interfaces:**
- Consumes `CashNotes`, the `ExpenseVoucher` constants and `cashPayout()`.
- Produces:
  - `ExpenseVoucherService::approve(ExpenseVoucher $voucher, User $checker): ExpenseVoucher`. Same signature. Inside the transaction it re-reads the voucher with `lockForUpdate()` and checks again.
  - `ExpenseVoucherService::cashInHandOn(string $date, ?int $supplierId): float`, for the warning.
  - A validation rule object `App\Rules\ExpenseAccount`: active, `is_group = 0`, code `LIKE '52%'`.

**Validation added to `validated()`:**
- `payment_method`: in cash, bank_transfer, cheque, credit; required.
- `bank_account_id`: required when bank_transfer or cheque; must exist in an active bank account with `chart_of_account_id` not null.
- `cheque_number` and `cheque_date`: required when cheque.
- `transaction_reference`: nullable, max 100.
- `denom` array: required when cash. `CashNotes::fromInput(denom)->total()` must equal `amount` (±0.009). Error: "The notes paid out add up to Rs X but the voucher is Rs Y."
- `amount`: `decimal:0,2`.
- `expense_account_id`: `new ExpenseAccount`.

**Posting:**
- **Credit side:**
  - cash → 1121
  - bank or cheque → the bank's `chart_of_account_id`
  - credit → 2113
- **Debit side:** 1131 for an advance, otherwise the expense account.
- Save `paid_from_account_id`. The cash payout notes are saved with the voucher on create and update, and deleted when the method is not cash.
- On approval, flash a warning (not an error) when `cashInHandOn(date, supplier) - amount < 0`: "Cash in hand for {date} goes negative (Rs X)."
- Use `cost_center_id` from the voucher's owner when a cost centre exists, otherwise 1. Keep 1 if there is no mapping; do not add one.
- Wrap posting errors: log them and flash "Posting failed. Please contact the admin."

- [ ] **Step 1:** Write the tests:
  - `it('posts a bank transfer voucher to the bank account and saves no notes')`: assert the credit line account is the bank's CoA id and `cashPayout` is null.
  - `it('posts a cash voucher to 1121 and stores the notes paid out')`: the notes are 1×1000 + 1×500 for an amount of 1500.
  - `it('refuses cash notes that do not add up')`: errors on `denom`.
  - `it('refuses a non-expense account')`: using 1121 → error on `expense_account_id`.
  - `it('posts one journal when approved twice')`: call `approve` twice; the second throws; `JournalEntry::count()` is 1.
  - `it('requires a bank account for a cheque voucher')`.
  - `it('warns when cash in hand goes negative')`: session has `warning`.
- [ ] **Step 2:** Run the file → FAIL.
- [ ] **Step 3:** Implement. Update the existing `ExpenseVoucherTest` payload to send `payment_method` and `denom` where it posts cash.
- [ ] **Step 4:** Run `tests/Feature/ExpenseVoucherPaymentMethodTest.php tests/Feature/ExpenseVoucherTest.php tests/Feature/ExpenseCreditAndAdvanceTest.php tests/Feature/ExpenseVendorTest.php` → PASS.
- [ ] **Step 5:** Commit "Expense vouchers: bank transfer and cheque, cash notes paid out, locked approval, expense-account rule".

### Task 4: Voucher reversal

**Files:**
- Create: `app/Services/ExpenseVoucherReversalService.php`
- Modify: `app/Http/Controllers/ExpenseVoucherController.php` (add `reverse`, plus middleware `permission:expense-voucher-reverse` for it), `routes/web.php` (`POST expense-vouchers/{expense_voucher}/reverse` named `expense-vouchers.reverse`)
- Test: `tests/Feature/ExpenseVoucherReversalTest.php`

**Interfaces (produces):** `ExpenseVoucherReversalService::reverse(ExpenseVoucher $voucher, User $by, string $reason): ExpenseVoucher`. It throws `RuntimeException` with user-facing text.

**Rules:**
- The voucher is locked and must be `approved` and not already reversed.
- `$by->id !== created_by`.
- A credit voucher needs `paid_amount == 0` and no allocations.
- An advance needs `recovered_amount == 0`; the advance status is then set to cancelled. If the model has no cancelled status, add `STATUS_CANCELLED = 'cancelled'`.
- Call `AccountingService::reverseJournalEntry(journal_entry_id, 'REVERSAL: expense voucher {no} - {reason}', today)`.
- Set the status to `reversed`, plus `reversed_at`, `reversed_by`, `reversal_reason` and `reversal_journal_entry_id`.
- The controller checks `Hash::check(password)` first; a wrong password returns an error.

- [ ] **Step 1:** Write the tests:
  - `it('reverses an approved cash voucher with reason and password')`: a REV journal exists, the status is `reversed`, and the 1121 net for the voucher is 0.
  - `it('refuses without the permission')`: 403.
  - `it('refuses a wrong password')`.
  - `it('refuses the maker')`.
  - `it('refuses a credit voucher that is partly paid')`.
  - `it('refuses an advance that was recovered')`.
  - `it('reverses only once when posted twice')`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement the service, route and controller action.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit "Expense vouchers: reverse with permission, reason and password".

### Task 5: Voucher screens (form, show, print, messages)

**Files:**
- Modify: `resources/views/expense-vouchers/_form.blade.php`, `_form-scripts.blade.php`, `show.blade.php`, `index.blade.php` (add the Reversed tab and count), `print.blade.php`
- Create: `resources/views/expense-vouchers/print-a4.blade.php`
- Modify: `app/Http/Controllers/ExpenseVoucherController.php` (form data: active linked bank accounts; employees with supplier short name; the print `?format=a4` defaulting to A4 and `?format=slip` for 80mm), `lang/en/validation.php` (or `attributes()` on the request) for friendly names: `attachments.*` → "attachment", `amount` max message "Amount is too large".
- Test: `tests/Feature/ExpenseVoucherScreensTest.php`

**What the screens show:**
- **Form:**
  - A "Payment method" select (Cash / Bank transfer / Cheque / Credit, pay later). It shows the bank account select, the cheque number and date, or the reference.
  - When cash is chosen, `<x-cash-notes>` is shown with the required amount bound to the amount field.
  - The vehicle field is cleared (`x-effect`) when hidden.
  - Employees are labelled "Name · Company".
- **Show page:**
  - The method and bank details.
  - The notes paid out.
  - A Reverse button (`@can('expense-voucher-reverse')`, approved and not reversed) that opens a modal asking for the reason and password.
  - Rejected vouchers show an Edit button and the last rejection reason (keep `rejection_reason` on resubmit; clear it only on approval).
  - A reversed banner.
- **A4 print:**
  - The company name from `Company::first()->company_name`.
  - The title "Cash Payment Voucher" or "Bank Payment Voucher".
  - Voucher no, date, payee, account code and name, method, and bank / cheque / reference.
  - The amount in figures and in words: use `NumberFormatter('en', NumberFormatter::SPELLOUT)` + " rupees only". If intl is missing, fall back to a small `App\Support\AmountInWords::rupees(float): string`.
  - The narration.
  - Signature lines: Prepared by, Checked by, Approved by, Received by.
  - "DUPLICATE" when `printed_at` was already set.

- [ ] **Step 1:** Write the tests:
  - `it('shows the reverse button only with the permission')`;
  - `it('prints an A4 voucher with the amount in words')`: 1500 shows "one thousand five hundred rupees only";
  - `it('keeps the rejection reason after resubmitting')`;
  - `it('shows a friendly message for a posting failure')`: mock `AccountingService` to return failure; the response does not contain "SQLSTATE".
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run → PASS. Then open `https://voucher-review.moontraders.test/expense-vouchers/create` in Chrome and check the form at 1440px and 375px.
- [ ] **Step 5:** Commit "Expense vouchers: payment method form, notes grid, reverse modal, A4 payment voucher print".

### Task 6: Cheque clearing batches, rules and bounce posting

**Files:**
- Create: `app/Services/ChequeClearingService.php` (moves `applyStatus` and the bulk logic out of the controller)
- Modify: `app/Http/Controllers/Reports/ChequeRegisterController.php` (`updateStatus`, `bulkClear` call the service; delete `shareNotes`), `app/Services/ChequeClearingPostingService.php` (add `postBounce`, `reverseBounce`)
- Test: `tests/Feature/Reports/ChequeClearingBatchTest.php`

**Interfaces (produces):**
- `ChequeClearingService::clear(Collection $cheques, string $clearedDate, string $via, ?int $bankAccountId, CashNotes $notes, ?string $remarks, User $by): ChequeClearing`. A single clearing is a collection of one.
- `ChequeClearingService::changeStatus(SalesSettlementCheque $cheque, string $status, ?string $date, ?float $bankCharge, ?string $remarks, User $by): void`, for pending, bounced and cancelled.
- `ChequeClearingPostingService::postBounce(SalesSettlementCheque $cheque, string $date, float $bankCharge = 0): JournalEntry`:
  - Dr 1112 / Cr 1122 for the amount.
  - If `bankCharge > 0`, also Dr 1112 / Cr the deposit bank account. Skip the charge if there is no deposit bank.
  - If the cheque has a customer and the settlement's employee has a `CustomerEmployeeAccount`, add a debit `CustomerEmployeeAccountTransaction` (`transaction_type 'cheque_bounce'`).
  - Stores `bounce_journal_entry_id`.
- `reverseBounce(SalesSettlementCheque $cheque): void`, used when a bounced cheque goes back to pending.

**Rules (inside one DB transaction, with the cheques locked by `lockForUpdate`):**
- Every cheque must be pending.
- A cash clearing with more than one distinct `salesSettlement->supplier_id` is refused: "Cash clearing must be one company at a time. Selected: A, B."
- `cleared_date` must be on or after the latest settlement date of the group and not later than today. Error: "Cleared date must be between {settlement date} and today."
- Cash: `$notes->total()` equals `sum(amount)` ±0.009. The notes are stored on the `ChequeClearing` row only; the cheques' `cleared_denom_*` stay null.
- Bank: no notes.
- Each cheque gets `cheque_clearing_id`, the status, the date and the via; `post()` is called per cheque, as today.
- **Un-clear** (cleared → pending, bounced or cancelled) only when `cleared_date` is today. Otherwise: "This cheque was cleared on {date}; it can only be changed on that day." Un-clearing one cheque of a batch reopens the whole batch: all its cheques go back to pending, their journals are reversed, and the batch is deleted.
- **Pending → bounced:** `postBounce`. A bounce from cleared must first un-clear on the same day, then bounce.
- History rows record the via, the bank and the batch id in the notes.

- [ ] **Step 1:** Write the tests:
  - `it('refuses a cash clearing that mixes companies')`;
  - `it('stores the counted notes once on the clearing batch')`: 2 cheques of the same company, 50,100 + 3,369, notes 10×5000 + 3×1000 + 4×100 + 1×50 + 1×10 + 9 coins. Assert `ChequeClearing::first()->total_amount == 53469` and both cheques point to it;
  - `it('refuses a cleared date before the settlement date')`;
  - `it('refuses a future cleared date')`;
  - `it('refuses un-clearing on a later day')`: use `travelTo`;
  - `it('reopens the whole batch when one cheque is un-cleared the same day')`;
  - `it('posts Dr Debtors Cr Cheques in Hand when a pending cheque bounces')`;
  - `it('clears only once when submitted twice')`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement. Update `tests/Feature/Reports/ChequeClearingHistoryTest.php` where it relied on `shareNotes`.
- [ ] **Step 4:** Run `tests/Feature/Reports/ChequeClearingBatchTest.php tests/Feature/Reports/ChequeClearingHistoryTest.php tests/Feature/ChequeRegisterReportScopeTest.php` → PASS.
- [ ] **Step 5:** Commit "Cheque clearing: one-company batches with notes stored once, date rules, same-day un-clear, bounce posting".

### Task 7: Cheque register interface (Goods Issues design)

**Files:**
- Modify: `app/Http/Controllers/Reports/ChequeRegisterController.php` (`index`)
- Modify: `resources/views/reports/cheque-register/index.blade.php` (rewrite)
- Create: `app/Exports/ChequeRegisterExport.php`, `resources/views/reports/cheque-register/pdf.blade.php`
- Modify: `resources/views/components/register-help.blade.php` (a `cheque-register` branch)
- Reference to copy: `resources/views/goods-issues/index.blade.php`, `GoodsIssueController@index` (stats query at about lines 162-174)
- Test: `tests/Feature/Reports/ChequeRegisterIndexTest.php`

**Controller:**
- `canViewAllSuppliers()` = `$user->hasAllSupplierAccess() || $user->can('report-audit-cheque-register-all-companies')`.
- **Opens empty:** when the request has no `filter` key at all, return the view with `cheques = null` and the empty state. A company user's own company is pre-selected but still needs Apply.
- `filter[supplier_id]` accepts an id or `all` (all only for all-access users).
- **New filters:**
  - `cleared_date_from` / `cleared_date_to`
  - `cleared_via` (cash or bank)
  - `deposit_bank_id` (`cleared_bank_account_id`)
  - `amount_min` / `amount_max`
  - `bank_name` (a select of distinct `bank_name` values within scope)
  - `settlement_number`
- **Stats:** the same query without the status filter, grouped by status, with COUNT and SUM, for the tabs All / Pending / Cleared / Bounced / Cancelled.
- **Per page:** `[25, 50, 100, 250]`; `all` is removed.
- **Exports:** `?export=xlsx` downloads `ChequeRegisterExport`. `?format=pdf` renders the PDF view in A4 landscape.
- **Columns:** a Company column (supplier short name). The status shows for every viewer; the Action column only with the manage permission.

**View:**
- Uses `x-page-header`, `x-register-header-actions page="cheque-register"`, `settings.partials.ui-style`, `x-apple-skin`, `x-register-apple-style`, `x-register-help`, `x-status-message`, and the `ak-tabs` / `ak-dt` / compact toggle / row lines / mobile cards / `ak-pager` / `ak-empty` from goods-issues.
- The filter drawer opens by default when nothing has been applied. "More filters" holds the advanced filters.
- Keeps the bulk bar and the status and history modals.
- The modal's notes block becomes `<x-cash-notes>`.
- The bulk modal lists the selected cheques (number · company · amount) and shows a red line when the companies are mixed and the method is cash.

- [ ] **Step 1:** Write the tests:
  - `it('loads no cheques until filters are applied')`: `assertViewHas('cheques', null)` and the text "Choose a company and filters, then Apply";
  - `it('shows every company to a user with the all-companies permission')`;
  - `it('refuses another company for a company user, including all')`: 403;
  - `it('filters by cleared date, cleared via, deposit bank and amount range')`;
  - `it('counts cheques per status in the tabs')`;
  - `it('exports the filtered cheques to Excel')`: `Excel::fake()` and `assertDownloaded`;
  - `it('shows the status column to viewers without the manage permission')`;
  - `it('shows an error flashed by a failed clearing')`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run `tests/Feature/Reports/ChequeRegisterIndexTest.php tests/Feature/ChequeRegisterReportScopeTest.php` → PASS. Then check in Chrome on the review site at 1440px and 375px against /goods-issues.
- [ ] **Step 5:** Commit "Cheque register: Goods Issues interface, opens empty, company and new filters, status tabs, Excel and PDF".

### Task 8: Cash Collection Distributor data

**Files:**
- Modify: `app/Http/Controllers/Reports/CashCollectionDistributorController.php`
- Test: `tests/Feature/Reports/CashCollectionDistributorReportTest.php` (extend it)

**Data rules:**
- Validate `date` as `date_format:Y-m-d`; a bad value goes to today with a flash message.
- Settlements: `where('status', 'posted')`.
- **Merged table `collection`** with columns Cash | Cheque clearing | Bank slips | Online | Cheques received | Cleared into bank | Total.
  - Total = cash + slips + online + cheques received.
  - "Cleared into bank" = cheques with `cleared_via = bank` on the date, per company.
  - Tables 1, 3 and 4 are removed.
- **Notes in:**
  - each settlement's denomination;
  - plus, for each `ChequeClearing` on the date whose `supplier_id` is in scope, its notes;
  - plus, for cleared cheques on the date with no `cheque_clearing_id`, their `cleared_denom_*` (old data);
  - plus a "Cash not counted" amount = cash received − counted notes in. This is signed; there is no `max(0)`.
- **Notes out:** the `cashPayout` notes of approved cash-method vouchers on the date, in scope. Vouchers with no payout notes (old data) go into a "Paid out, notes not counted" amount.
- **Reversed vouchers:**
  - Reversed on the same date as the voucher: left out completely.
  - Reversed on a later date: shown on the reversal date as a negative expense ("Returned: {voucher no}"), with its notes added back.
- **Denominations table:** rows per note with Notes in | Notes out | Net (count and value), then Coins, the "Cash not counted" row and the "Paid out, notes not counted" row. Net total = Cash in hand.
- **Expenses:**
  - only `payment_method = cash` (advances included) and cash vendor payments and salaries;
  - bank and cheque vouchers are left out;
  - general Moon Traders expenses (no `supplier_id`) are labelled "Moon Traders (general)" and appear only under All.

- [ ] **Step 1:** Write the tests:
  - `it('nets the notes paid out by cash vouchers so notes equal cash in hand')`;
  - `it('balances notes for one company after a batch clearing')`;
  - `it('still balances old per-cheque notes')`;
  - `it('shows cheques cleared into the bank')`;
  - `it('ignores draft settlements')`;
  - `it('excludes bank transfer vouchers from cash expenses')`;
  - `it('shows a voucher reversed on a later day as cash returned that day and leaves the original day unchanged')`;
  - `it('total collection equals cash plus slips plus online plus cheques received')`. Rewrite the old assertion at about line 67.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the file → PASS.
- [ ] **Step 5:** Commit "Cash Collection Distributor: notes in, out and net equal cash in hand; batch notes; bank-cleared cheques; posted settlements only".

### Task 9: Cash Collection Distributor layout

**Files:**
- Modify: `resources/views/reports/cash-collection-distributor.blade.php`, `resources/views/reports/cash-collection-distributor/_grid.blade.php`, `_sheet.blade.php`, `pdf.blade.php`, `help.blade.php`

**What changes:**
- No filler rows: remove the padding logic in `_grid`. Paired tables stack, or align to the top.
- A table whose rows are all zero renders one line instead, for example "No cheques received".
- Company rows where every amount is 0 are hidden.
- The explanation paragraph moves to `help.blade.php`.
- The screen header uses `x-page-header` and the goods-issues filter drawer. The A4 sheet styling stays.

- [ ] **Step 1:** Add the test `it('renders no empty filler rows')`: count `<tr` in the Expenses table for 1 expense = header + 1 + total.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run → PASS. Check in Chrome on `voucher-review` for 2026-11-14, All and one company, then the print and the PDF.
- [ ] **Step 5:** Commit "Cash Collection Distributor: compact layout without filler rows or all-zero tables".

### Task 10: Final check, review and PR
- [ ] Run `vendor/bin/pint --dirty --format agent`.
- [ ] Run once: `php artisan test --compact tests/Feature/ExpenseVoucher* tests/Feature/Expense* tests/Feature/ChequeRegisterReportScopeTest.php tests/Feature/Reports/ChequeClearing* tests/Feature/Reports/ChequeRegisterIndexTest.php tests/Feature/Reports/CashCollectionDistributorReportTest.php tests/Feature/SalesSettlementCashDenominationTest.php tests/Unit/CashNotesTest.php`. Expected: all pass.
- [ ] Run `php artisan migrate` on the review copy and repeat the 14-Nov scenarios in Chrome. Each company's sheet must show notes Net = Cash in hand.
- [ ] Run `/code-review` on the branch and fix the confirmed findings.
- [ ] Push the branch and open a PR that closes #183–#190.
