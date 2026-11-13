# Sales Settlement Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make sales settlements safe against double submits, make attachments lighter, and bring every settlement page, the help and the PDF to the Goods Issue Apple standard. Saved data must not change.

**Architecture:**
- **Server safety:** a row lock with a status re-check inside Post and Revert, plus the existing `App\Support\FormOnce` token on every write.
- **Client safety:** an Alpine `submitting` lock on every write button.
- **Uploads:** the existing compressor and attachment service are extended (WebP, thumbnails, signed S3 links, staging prune).
- **Pages:** restyled in place, keeping every input name and JavaScript calculation.

**Tech Stack:** Laravel 12, PHP 8.4, Pest (pgsql), Alpine.js, Select2, Tailwind, Vite via `bun run build`, DomPDF, Flysystem S3.

**Spec:** `docs/superpowers/specs/2026-11-13-sales-settlement-overhaul-design.md`

## Global Constraints

- **Saved data must not change.**
  - No change to input names, request validation, total calculations, posting or journal logic.
  - The only exceptions are the locks and token checks named here.
- **Local MySQL is a production copy.**
  - Never migrate:fresh, refresh or wipe it.
  - Back up before any migration.
  - Tests run on pgsql.
- **No commits** unless the user asks. Each task ends with tests, Pint and a build, not a commit.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes. Run `bun run build` after view, CSS or JavaScript changes.
- **Design:**
  - Follow `.claude/skills/apple-design` and `emil-design-eng`.
  - Reuse `<x-apple-skin />`, `<x-register-apple-style />` and `x-page-header`.
  - Page width `max-w-7xl`.
  - No sideways page scroll at 375, 768, 1024 or 1440px.
- **Help text:** Urdu, 3–6 lines per section, clear and to the point.
- **Testing in Chrome:**
  - log in as FARRUKH (id 5); the login is in the scratchpad file `farrukh-test-login.txt`
  - check the super-admin-only views too, with the super admin login the user gave
  - never echo passwords

## Review Focus

1. **A photo still shrinking when Save is pressed.** The page waits, then sends exactly once, and the button stays locked meanwhile. (Task 2 test: the view carries the lock and the `pending` wait.)
2. **A WebP photo in the PDF.** It must render in print2. (Task 3 test: a WebP attachment produces a PDF without error.)
3. **Old attachments without a thumbnail.** The thumbnail request falls back to the full file. (Task 3 test.)
4. **Revert started from Special Edit**, where the outer lock is already held. The inner lock must not deadlock or refuse. (Task 1 test: special edit still passes.)
5. **A settlement whose goods issue line was fully removed** (a zero line). The create and edit pages skip it and saving works. (Covered by the existing `GoodsIssueLineRemovalTest`; rerun in Task 4.)

---

### Task 1: Server-side double-submit safety

**Files:**
- Modify: `app/Services/DistributionService.php` (`postSalesSettlement`, ~line 707)
- Modify: `app/Services/SalesSettlementRevertService.php` (`revert`, line 33)
- Modify: `app/Http/Controllers/SalesSettlementController.php` (`store`, `update`, `destroy`, `post`, `revert`, `updateSpecial`)
- Test: `tests/Feature/SalesSettlementDoubleSubmitTest.php`

**Interfaces:**
- Consumes: `FormOnce::FIELD`, `FormOnce::claim(?string): bool`, `FormOnce::alreadySentMessage(): string`
- Produces: every settlement write form must send `_once` (Task 2 adds it to the views)

- [ ] **Step 1: Write the failing tests** in `SalesSettlementDoubleSubmitTest.php`. Reuse the fixtures/helpers of `SalesSettlementRevertTest` and `SalesSettlementPostingValidationTest`.
  - `it('posts a settlement only once when the same model is posted twice')`
    - call `postSalesSettlement($settlement)` twice with the same stale model instance
    - second result `success === false`, message contains `already posted`
    - exactly 1 journal entry with the settlement's reference
  - `it('reverts only once when revert runs twice on a stale model')`
    - second call `success === false`
    - the reversal journal count is 1
  - `it('refuses a resent form token')` for each route: store, update, destroy, post, revert
    - the same `_once` value sent twice
    - the second response redirects with `FormOnce::alreadySentMessage()` in the session `error`
    - the row counts are unchanged
  - `it('treats a resent create as the existing draft')`
    - store twice with different tokens
    - one draft settlement exists

- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/SalesSettlementDoubleSubmitTest.php`. Expected: FAIL.

- [ ] **Step 3: Implement**
  - **In `postSalesSettlement`:**
    - right after `DB::beginTransaction()`, re-read the row with `SalesSettlement::whereKey($settlement->id)->lockForUpdate()->value('status')`
    - throw `Sales Settlement is already posted` unless it is `draft`
    - keep the later code using `$settlement`
  - **In `revert`:**
    - first thing inside the `DB::transaction` closure: `$locked = SalesSettlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail()`
    - if `$locked->status !== 'posted'`, throw `RuntimeException('Only posted settlements can be reverted.')`
    - the existing catch turns it into `success false`
  - **In the controller:**
    - call `FormOnce::claim($request->input(FormOnce::FIELD))` after authorization and status checks, before `prepareAttachmentUploads`
    - on false, redirect to the settlement's show page (index for destroy) `->with('error', FormOnce::alreadySentMessage())`
    - `post` and `destroy` need `Request $request` added

- [ ] **Step 4: Run** the new file plus `SalesSettlementRevertTest`, `SalesSettlementSpecialEditTest`, `SalesSettlementPostingValidationTest` and `SalesSettlementEditTest`. Expected: PASS.

### Task 2: Client-side button locks and tokens

**Files:**
- Modify:
  - `resources/views/sales-settlements/create.blade.php` (form at line 24, submit at 838)
  - `resources/views/sales-settlements/edit.blade.php` (form at line 216, submits at 1079–1084)
  - `resources/views/sales-settlements/index.blade.php` (delete modal form at line 567)
  - `resources/views/sales-settlements/show.blade.php` (post modal at ~1324, revert form at 1373)
  - `resources/views/components/password-confirm-modal.blade.php` (lock on confirm)
  - `resources/views/components/settlement-attachments.blade.php` (`bindForm` submit path)
- Test: extend `tests/Feature/SalesSettlementDoubleSubmitTest.php`

**Interfaces:**
- Consumes: the `_once` field from Task 1
- Produces: `window.SettlementAttachments.bindForm(form)` keeps the button locked across the photo wait and resubmit

- [ ] **Step 1: Write the failing test** `it('renders a once token and a locked submit on every settlement write form')`:
  - GET create, edit, show (draft and posted) and index
  - `assertSee('name="_once"', false)` on each write form
  - create and edit contain `x-bind:disabled="submitting"` and `Saving…`

- [ ] **Step 2: Run it.** Expected: FAIL.

- [ ] **Step 3: Implement**
  - **Forms:**
    - add `<input type="hidden" name="_once" value="{{ \App\Support\FormOnce::token() }}">` to each form
    - create and edit forms get `x-data="{ submitting:false }"` and `@submit="if (submitting) { $event.preventDefault(); return; } submitting = true"`
  - **Buttons:**
    - `x-bind:disabled="submitting"`
    - label swaps to `Saving…` / `Re-posting…`
    - the special-edit `confirm()` moves into the `@submit` handler, before the lock
  - **Photo wait:**
    - in `bindForm`, when photos are pending, the form re-submits after the wait
    - the re-submit must bypass the Alpine guard: set `form.dataset.resubmitting = '1'` and let the guard allow one pass when it is set
  - **Delete and revert modals:** `isSubmitting` the same way as `alpine-confirmation-modal`

- [ ] **Step 4: Run** the test file. `bun run build`. Expected: PASS.

### Task 3: Lighter attachments

**Files:**
- Modify:
  - `config/attachment_images.php`: defaults `max_px` 1600, `quality` 75; new `thumb_px` 320
  - `resources/views/components/attachment-compressor.blade.php`: `compress(file, { webp = false } = {})`
  - `resources/views/components/settlement-attachments.blade.php`: call `compress(file, { webp: true })`
  - `app/Services/SettlementAttachmentService.php`
  - `app/Http/Controllers/SalesSettlementController.php`: `attachment()`
  - `routes/console.php`
- Create: `app/Console/Commands/PruneSettlementAttachmentStaging.php` (`settlement-attachments:prune-staging {--hours=24}`)
- Test: `tests/Feature/SalesSettlementAttachmentsTest.php` (extend)

**Interfaces:**
- Produces:
  - `SettlementAttachmentService::thumbPath(string $path): string`: the same folder plus `/thumbs/` plus the basename with a `.jpg` extension
  - `storeUpload()` and `store()` also write the thumbnail for jpg/jpeg/png/webp (GD, `thumb_px`, quality 70)
  - `moveStagedUpload()` moves the thumbnail too
  - `delete()` deletes the thumbnail too
  - `GET sales-settlements/{s}/attachments/{section}/{id}?thumb=1` serves the thumbnail, or the full file when there is none
  - on an S3 disk it redirects (302) to `temporaryUrl($path, now()->addMinutes(10))`
  - authorization is unchanged and stays first

- [ ] **Step 1: Write failing tests**
  - `it('writes a thumbnail next to an uploaded photo')`: `Storage::fake('local')`, upload a 2000×1500 jpg, assert the `thumbPath` exists and its longest side is ≤ 320
  - `it('falls back to the full file when no thumbnail exists')`: GET `?thumb=1` → 200 with the original bytes
  - `it('redirects to a signed S3 link')`:
    - set config `settlement_attachments.disk=s3`, `Storage::fake('s3')`
    - `Storage::disk('s3')->buildTemporaryUrlsUsing(fn ($p) => 'https://signed.test/'.$p)`
    - assert a redirect to `https://signed.test/...`
  - `it('prunes staging files older than a day')`: two staged files, one touched 25 hours ago; run the command; only the new one remains
  - `it('renders a settlement PDF with a WebP attachment')`: GET `layout=print2&format=pdf` → 200, `application/pdf`

- [ ] **Step 2: Run them.** Expected: FAIL.

- [ ] **Step 3: Implement** to the interfaces above.
  - The compressor outputs `image/webp` when `webp` is true and `canvas.toBlob` returns a WebP blob; the file name gets a `.webp` extension. Otherwise it keeps the current behaviour.
  - Schedule the prune: `Schedule::command('settlement-attachments:prune-staging')->dailyAt('03:10')`.
  - The show page and attachment previews use `?thumb=1` for images in lists; the full link opens the file.

- [ ] **Step 4: Run** `SalesSettlementAttachmentsTest`, `SettlementAttachmentRequiredTest` and the new tests. Then Pint and `bun run build`. Expected: PASS.

### Task 4: Create and edit Apple redesign with section help

**Files:**
- Create:
  - `resources/views/sales-settlements/partials/form-style.blade.php`: `ssf-*` classes, scoped under `.ssf-page`, modelled on `goods-issues/partials/form-style.blade.php`; it includes the restyle of the settlement modal components
  - `resources/views/components/settlement-section-help.blade.php`: props `section`, `title`; renders a `?` button dispatching `ss-help-section` with `{section}`
  - `resources/views/sales-settlements/partials/section-help-modal.blade.php`: one modal listening for `ss-help-section`, holding Urdu content keyed by `items`, `credit`, `recoveries`, `cheques`, `bank`, `cash`, `expenses`, `summary`, `posting`; the content is taken and trimmed from `partials/help-modal.blade.php`
- Modify:
  - `create.blade.php`, `edit.blade.php`: header to `x-page-header`, `<x-apple-skin />`, section cards, `#` column, SKU labels, phone cards under 1024px, a sticky action bar with the net figures; Select2 classes like `/reports/claim-register`
  - `partials/help-modal.blade.php`: becomes a short index linking to the section modals
- Test:
  - `tests/Feature/SalesSettlementViewParityTest.php` and `SalesSettlementEditTest.php` must still pass unchanged
  - add `it('shows a help button on every settlement section')` to `SalesSettlementEditTest.php`, asserting all 9 `ss-help-section` keys on create and edit

- [ ] **Step 1: Snapshot the input names before changing anything.**
  - Run: `grep -o 'name="[^"]*"' resources/views/sales-settlements/create.blade.php | sort -u > <scratchpad>/ss-create-names-before.txt`
  - Do the same for edit and for every component the page includes (`credit-sales-modal`, `recoveries-modal`, `cheque-payment-modal`, `bank-transfer-modal`, `bank-slips-modal`, `advance-tax-modal`, `percentage-expense-modal`, `amr-expense-modal`, `detail-table`).

- [ ] **Step 2: Write the help test.** Run it. Expected: FAIL.

- [ ] **Step 3: Implement the redesign.**
  - Change classes, wrappers and headings only.
  - Keep every `id`, `name`, `x-data`, `@click` and every element the scripts query (`getElementById` / `querySelector` targets).

- [ ] **Step 4: Re-run the snapshot.** `diff` the before and after name lists. Expected: identical.

- [ ] **Step 5: Run tests.** Run all `tests/Feature/SalesSettlement*` plus `GoodsIssueLineRemovalTest`, then `bun run build`. Expected: PASS.

- [ ] **Step 6: Chrome check** of create and edit:
  - widths 375, 768, 1024 and 1440 with no sideways scroll (`document.documentElement.scrollWidth <= innerWidth`)
  - the console is clean
  - every modal opens, adds a row and closes
  - the totals match the old page for the same entries

### Task 5: List, show and PDF polish

**Files:**
- Modify:
  - `resources/views/sales-settlements/index.blade.php`: `#` column; Quick look modal on the settlement number (number, date, goods issue, salesman, vehicle, status, net sale, cash, credit, short/excess); full date `d M Y`; compact create pill (`data-create`)
  - `resources/views/sales-settlements/show.blade.php`: replace the three Print/PDF buttons with one dropdown (Print, PDF portrait, PDF landscape); hero, stats and timeline pattern as on `goods-issues/show.blade.php`; section `?` help buttons
  - `resources/views/sales-settlements/print2.blade.php`: navy header table, info boxes, zebra rows, totals box, signature lines, in the style of `goods-issues/show-pdf.blade.php`; tables only (DomPDF)
- Test:
  - `tests/Feature/SalesSettlementListTest.php`: `it('opens a quick look from the settlement number')`, asserting the `ss-peek` dispatch and the `#` header
  - `tests/Feature/SalesSettlementShowTotalsTest.php`: the PDF portrait and landscape still return 200

- [ ] **Step 1:** Write the list test. Run it. Expected: FAIL.

- [ ] **Step 2:** Implement the list, show and print2 changes.

- [ ] **Step 3:** Run `SalesSettlementListTest`, `SalesSettlementShowTotalsTest` and `SalesSettlementViewParityTest`, then `bun run build`. Expected: PASS.

- [ ] **Step 4: Chrome check.**
  - the list and show pages at four widths
  - the Quick look opens and closes with Escape
  - download both PDFs and read them

### Task 6: Heavy end-to-end run in Chrome

**Files:**
- Reuse the scratchpad snapshot tools (`gi_snapshot.php`).
- Add `ss_snapshot.php` (scratchpad, read-only). It records, per settlement:
  - settlement lines and batches
  - stock by batch, valuation layers, current stock
  - the inventory ledger
  - van balances and batches
  - journals for 1151, 1155, 4110, 5111, 1121 and the bank accounts
  - the customer ledger
  - bank register lines
  - S3 object keys under the settlement folder

- [ ] **Step 1: Create the goods issue in Chrome.**
  - log in as FARRUKH
  - 8 or more SKUs across at least 2 batches
  - post it
  - add a supplementary line
  - remove part of one line
  - snapshot

- [ ] **Step 2: Create the settlement, filling every field:**
  - sales, returns, shortages
  - credit sales (2 customers, one with a photo)
  - recoveries (cash, and bank with a photo)
  - cheque, bank transfer, bank slip (with a photo)
  - the denomination grid
  - expenses: advance tax, percentage expense, AMR powder and liquid
  - notes
  - double-click Save
  - expect exactly 1 draft
  - snapshot

- [ ] **Step 3: Edit the draft.** Change one credit sale and one expense, double-click Update, snapshot. The totals must equal the hand-computed figures.

- [ ] **Step 4: Post.** Double-click Post; expect 1 journal. Snapshot. Check:
  - cost of goods sold equals net 1155 for the sold quantity
  - returns went back to the same batches
  - every ledger table balances

- [ ] **Step 5: Revert, then post again.**
  - revert with a double-click on confirm; expect 1 reversal
  - post again
  - snapshot and diff against Step 4: same figures, reversal journals net to zero

- [ ] **Step 6: Check every page.**
  - console, network panel and `laravel-boost browser-logs` / `last-error`
  - all at 375, 768, 1024 and 1440px
  - list every bug found to the user

- [ ] **Step 7: Run the whole suite:** `php artisan test --compact` for tests related to settlements and goods issues, then the full suite once. Report the pass and fail counts.
