# Payroll hub — design (approved 2026-11-13, layout A)

## Why
Payroll today is split over separate pages (Salaries, Bonus & Commission, Components, Payroll) with its own flat look.
The owner wants one Payroll screen in the Goods Issue (Apple) style where salaries, bonuses/deductions, categories and
monthly runs are all reachable without leaving, with pop-ups for every edit.

## Decisions (owner)
- Layout A: `/payroll` with tabs **Payroll runs · Employee salaries · Bonus & deductions · Categories**; a run opens on
  its own page under the same tab bar; every edit is a pop-up (sheet). Old URLs redirect to the right tab.
- Salary pop-up: **Earnings left** (Basic first, then allowances), **Deductions right** (recurring deductions, plus
  read-only open advances and this month's settlement shortages).
- Categories are open-ended: new earning/deduction categories can be added from the pop-up's dropdown or the
  Categories tab. Default categories: House Rent, Utility Allowance, Medical Allowance; deduction Death Insurance.
  **EOBI is switched off** (migration).
- Each allowance per employee: **Fixed Rs**, **% of basic**, or **% of another allowance**.
- One employee or many: tick all / some, set in one pop-up; change any one later.
- **Settlement shortages are deducted automatically** (posted settlements of the month), credited back to 5213
  (where the shortage was written off). Per-slip switch to waive.
- **Settlement excess is the employee's earning** (4250 → paid with salary), by settlement date of the month.
  (Corrected by the owner on 2026-11-13; an earlier note read it as company income.)
- Advances come from expense vouchers (EmployeeAdvance) and are recovered by instalment.
- Payslip: earnings left, deductions right, net pay, status. PDF and Excel.
- Every schema change ships as a migration with a working `down()`; production needs only `php artisan migrate`.
- Access stays on the existing permissions (payroll-list / -create / -approve / -pay / -salary-manage /
  -adjustment-manage); each tab shows only to those allowed.

## Schema
- `sales_settlement_short_amounts` (settlement id, amount, description) — written on posting, deleted on revert,
  backfilled from existing "Cash Shortage (Expected…" journal lines.
- `employee_salary_components`: `calculation` (nullable override), `base_component_id` (for % of another allowance).
- `payroll_slips.recover_settlement_short` boolean default true.
- `salary_components`: new rows above; EOBI deactivated and unassigned.

## Approaches considered
- A (chosen): tabbed hub + run page in same shell — matches Goods Issue list→detail, linkable, light on phones.
- B: everything incl. the run inside a full-screen window — no page change, but heavy on phones and not linkable.
