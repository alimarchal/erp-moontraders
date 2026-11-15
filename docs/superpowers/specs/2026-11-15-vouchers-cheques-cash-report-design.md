# Expense vouchers, cheque register and cash collection report: design

Date: 2026-11-15. Issues: #183–#190.

## Why
The daily cash sheet (/reports/cash-collection-distributor) has to balance for every company: the notes in the box must equal the cash in hand. Today it does not.
- Cash vouchers lower Cash in hand but not the note count.
- A bulk cheque clearing gives notes to the wrong company.

The owner also wants:
- expense vouchers to work like a standard payment voucher (bank transfer, reversal under a permission, dropdowns, a proper print);
- the cheque register to have the Goods Issues interface and more filters.

## Decisions made by the owner
1. **Bulk cash clearing must be one company.** A group that mixes companies is refused. The notes are counted once for the group.
2. **A bounced cheque posts Dr Debtors (the customer) / Cr 1122 Cheques in Hand.** A bank charge is optional.
3. **A cash voucher larger than the day's cash in hand is allowed,** but it shows a warning on approval and a red negative line on the report.
4. **Reversal works like goods-issue reverse:** a permission, a reason and the user's password.
5. Ship everything as one PR, with one full test run at the end.

## Approaches considered
- **A. Patch in place** (chosen): keep the existing tables, add columns and two small tables, and reuse the goods-issues Blade components. It is the smallest change and keeps the data that is already there.
- **B. One general "cash book" ledger** that every cash movement writes to, with the report reading only from it. This is cleaner in the long run, but it is a large migration of settlements, payroll and vendor payments. Too big for this PR.
- **C. Count the notes on the report itself**, as a daily closing count. This does not explain where the notes went, and the owner wants each voucher to carry its own notes.

## Data model
**`expense_vouchers`: new columns**
- `payment_method`: `cash`, `bank_transfer`, `cheque` or `credit`, indexed. Existing `cash` and `advance` rows become `cash`; `credit` rows become `credit`. `payment_type` stays as is, so advance and credit logic keep working.
- `bank_account_id`: nullable FK, required when the method is bank transfer or cheque. Only active bank accounts linked to the chart of accounts can be picked.
- `cheque_number`, `cheque_date` and `transaction_reference`.
- `paid_from_account_id`: FK to the chart of accounts, saved at approval.
- Reversal: `reversed_at`, `reversed_by`, `reversal_reason` and `reversal_journal_entry_id`.
- Status `reversed` is added to the status list.

**New `cash_payout_denominations` table**
- Polymorphic: `payable_type` and `payable_id`, unique together.
- `denom_5000` … `denom_10`, `denom_coins` and `total_amount`, plus user tracking.
- It is used for expense vouchers now. Vendor payments and payroll can use it later.

**New `cheque_clearings` table (one row per clearing, a batch)**
- `cleared_date`, `cleared_via`, `bank_account_id`, `supplier_id`, `employee_id`.
- `denom_5000` … `denom_10`, `denom_coins` and `total_amount`.
- `notes` and `created_by`.

**`sales_settlement_cheques`**
- Gets `cheque_clearing_id`.
- New clearings write their notes to the batch only.
- The old `cleared_denom_*` columns stay so old days still read correctly.

**Permissions**
- `expense-voucher-reverse`
- `report-audit-cheque-register-all-companies`

## Posting rules
| Event | Journal |
|---|---|
| Cash voucher | Dr expense / Cr 1121 |
| Bank transfer or cheque voucher | Dr expense / Cr the bank account's chart-of-accounts account |
| Credit voucher | Dr expense / Cr 2113 (no change) |
| Advance | Dr 1131 / Cr 1121, or the bank account if paid by bank |
| Voucher reversal | `reverseJournalEntry` dated the day of the reversal; the advance is cancelled |
| Cheque bounce (pending → bounced) | Dr the customer's debtors account / Cr 1122 |

**Expense account:** only active, non-group 52xx accounts are allowed.

**Reversal is refused when:**
- a credit voucher is already paid, in part or in full;
- an advance has already been recovered;
- the reverser is the maker.

**Approval and clearing:**
- Both lock the row inside the transaction.
- A cash voucher's notes must add up to its amount, the same check the cheque dialog does.

## Cash Collection Distributor
**Collection by distribution:** one merged table with these columns:
- Cash
- Cheque clearing
- Bank slips
- Online
- Cheques received
- Cleared into bank
- Total

**Totals:**
- Total = every collection column except cheque clearing and cleared into bank. Those two are cheques that were already counted on the day they were received.
- Cash received = cash + cheque clearing.

**Denominations:** the table has Notes in, Notes out and Net columns.
- Notes in come from settlements and cheque-clearing batches.
- Notes out come from cash vouchers, using `cash_payout_denominations`.
- Rows for "cash not counted" and "paid out with no count" keep the totals honest.
- The `max(0)` clamp is removed, and any mismatch shows as a "Difference" row.
- The Net total must equal Cash in hand.

**A batch's notes:** counted when its company is in the filter (a batch is always one company now). For old cheques without a batch, the notes come from the per-cheque columns, as today.

**What is counted:**
- Only posted settlements.
- Cash vouchers reversed later show on the reversal day as cash returned.
- Bank and cheque vouchers are left out of the cash expenses.

**Layout:**
- No filler rows.
- Tables that are all zero, and company rows that are all zero, are hidden.
- The explanation paragraph moves to the Help page.
- The date is validated.

**Expenses:** general Moon Traders expenses show under All Companies only, as today, with a clear "Moon Traders (general)" label.

## Compact cash-notes entry (owner request)
One shared Blade component, `<x-cash-notes>`, used everywhere notes are counted: the single cheque clearing, the bulk clearing and the cash expense voucher.

- **Layout:** a compact table with one row per note value: note | count input | value. It fits in two columns on a laptop and one on a phone.
- **Inputs:** small, right-aligned and numeric.
- **Coins:** the last row.
- **Sticky footer:** Counted Rs …, Required Rs …, and a Difference shown in green or red.
- **Clear button:** one small link.

It replaces today's 4-column card grid, which has a label, an input and a "= value" line under every note.

## Cheque register
**Opens empty (owner request):** no cheques load by default. The page shows the filter panel open, with **Company** first. "All companies" is offered only to users with all-company access. Apply loads the list. The empty state reads "Choose a company and filters, then Apply", like the cash report.

**Rebuilt with the goods-issues building blocks:**
- page header and header actions (Excel and PDF)
- status tabs with counts and totals
- count · total line, Comfortable/Compact switch, row lines, mobile cards
- filter drawer with "More filters"

The bulk-clear bar and the status and history dialogs stay.

**Filters:**
- Company (was "Supplier"), salesman, customer and status
- cheque date and entry date ranges
- **cleared date range, cleared via, deposit bank and amount min/max**
- cheque bank and cheque number

The table gains a Company column.

**Messages:** error and validation messages are shown.

**Bulk dialog:**
- It lists the selected cheques.
- Cash clearing is refused when the group mixes companies.

**Status changes:**
- A cleared date must be on or after the settlement date and not later than today.
- Un-clearing is allowed only on the cleared day.
- Bounce asks for a date and an optional bank charge.

**Access:**
- The all-companies view uses the new permission, as well as the admin roles.
- Viewers without the manage permission still see the status.

## Expense voucher screens
**Form:**
- A payment method dropdown that shows the bank account, cheque number and date, or reference.
- For cash, a notes grid with a live difference, the same as the cheque dialog.
- Employees are listed with their company.

**Voucher page:** a Reverse button, shown only with the permission, that opens a modal asking for the reason and password. Rejected vouchers show an Edit button and keep the rejection reason.

**Print:** an A4 Cash/Bank Payment Voucher with:
- the company name from settings
- the amount in words
- the account code
- the method and bank details
- Prepared / Checked / Approved / Received signatures

The 80mm slip stays as an option.

**Errors:** friendly messages instead of field keys and raw SQL. Posting errors are logged; the user sees "Posting failed, contact admin".

## Not in this PR
- Back-filling `cleared_date` on the 265 old cleared cheques.
- Splitting one voucher into several expense lines.
- Withholding tax.
- Denominations for vendor payments and payroll. The table supports them; the screens come later.

## Tests
These are written as feature tests (Pest, pgsql), run once at the end:
- A bulk clear with mixed companies is refused.
- A batch's notes equal its cash for one company and for All.
- Cash voucher notes reduce the net notes, and Net equals Cash in hand.
- A bank transfer voucher credits the bank's account and leaves cash alone.
- Reversal: needs the permission and the password; is refused for the maker, a paid credit voucher or a recovered advance; posts the opposite journal; shows on the reversal day.
- Approving twice posts one journal.
- A non-expense account is refused.
- A bounce posts Dr debtors / Cr 1122.
- Un-clearing on a later day is refused, and a future cleared date is refused.
- The cheque register filters and tab counts work, and the export works.
- The report's Collection total matches the summary, draft settlements are left out, and a cheque cleared by bank shows.
