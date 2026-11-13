# Sales Settlement Overhaul: Design

Date: 2026-11-13

## Intent

Bring the Sales Settlement module up to the standard of the redesigned Goods Issue pages. That means the same Apple design, settlements that cannot be saved, posted or reverted twice, lighter photo uploads, help that is short and split by section, and a professional PDF. All of it must be proven in Chrome with a heavy, real goods issue settled through every field. Settlements carry money, stock and customer balances, so **no change may alter what gets saved or posted**.

## Goals

1. **Double-submit safety** on every action that writes data:
   - create, update, delete
   - post, revert
   - special edit (Apply Correction & Re-Post)
2. **Lighter attachments** on S3:
   - smaller images
   - WebP
   - thumbnails
   - signed links
   - staging cleanup
3. **Create and edit in the Goods Issue design**: the Apple skin, `x-page-header`, cards, the sticky action bar, phone layout, and Select2 styled like the claim-register filters.
4. **List and show polish**, both already Apple-styled: compare them to the Goods Issue list and show page and close the gaps. These include the `#` column, Quick look, the full date, and the compact create pill.
5. **Help in separate short modals**, one per section, clear and to the point (Urdu).
6. **A professional PDF**: rework the print2 sheet to the Goods Issue PDF standard.
7. **Verification**:
   - a heavy goods issue: many SKUs, several batches, a supplementary line and a removal
   - a settlement using every field
   - draft, edit, post, revert, re-post
   - every table checked
   - Chrome console, the network panel and phone sizes checked
   - any bug found is reported

## Non-goals

- No change to how totals are calculated, or to field names, request validation, or the posting and journal logic. The only exceptions are the locking fixes and the token check.
- No new storage provider. S3 stays the disk (`SETTLEMENT_ATTACHMENTS_DISK=s3`).
- No new settlement features.

## Anti-goals (a failure even if it looks good)

- Any settlement saving different numbers than before.
- A photo that no longer opens or prints.
- A page that scrolls sideways on a phone, or a console error.

## What exists today

- **`index` and `show`:** already use the `ak-*` Apple style (the same base as the Goods Issue list).
- **`create` (2,358 lines) and `edit` (2,825 lines):** old grey Tailwind. Data entry is a set of modal components:
  - credit sales, recoveries, cheques, bank transfers, bank slips
  - advance tax, percentage expense, AMR powder and liquid
  - the denomination grid and expense detail
- **PDF:** `print2.blade.php` through DomPDF, portrait and landscape, with attachments appended.
- **Help:** one large Urdu modal (`partials/help-modal`), opened by `ss-help`.
- **Uploads:**
  - in the browser, a shrink to 2000px at quality 82 (`x-attachment-compressor`)
  - on the server, `AttachmentImageOptimizer`
  - staging under `sales-settlement/_staging` before the transaction, then a move afterwards
  - files are streamed through PHP by the `attachment` action

## Findings that drive the safety work

| Action | Gap |
|---|---|
| Post | `DistributionService::postSalesSettlement` checks `status` on the route-bound model before any lock. Two requests can both post. |
| Revert | `SalesSettlementRevertService::revert` never locks the settlement row. Two requests can both revert. |
| Create | The server is safe (the goods issue lock plus the existing-draft check), but there is no button lock. A second click uploads photos again and leaves them in staging. |
| Update | No button lock; a second click saves the same data again and uploads photos again. |
| Special edit | Only a `confirm()` guard; the button stays clickable. |
| Delete (list) | No lock; the second click lands on a 404 page. |

## Approaches considered

**A. Restyle in place (chosen, my call).** Keep every input name, hidden field, Alpine component and JavaScript calculation. Change only markup classes and wrappers, add a shared settlement stylesheet (as `goods-issues/partials/form-style.blade.php` does), and restyle the modal components through CSS scoped to the settlement page.
- For: the saved data cannot change.
- Against: some old markup stays underneath.

**B. Rebuild create and edit as new Alpine components.**
- For: cleaner code.
- Against: it rewrites 5,000 lines of money logic and carries a high risk of changing what gets saved. Rejected.

**C. CSS skin only, with no markup changes.**
- For: fastest.
- Against: it cannot give the phone card layout, the `#` columns, or the sticky action bar. Rejected.

## Design

### 1. Safety

- **Post:**
  - Inside the transaction, lock the settlement row (`lockForUpdate`) and re-read `status`. If it is no longer `draft`, stop with "already posted".
- **Revert:**
  - Same pattern: lock the row and re-check `status === 'posted'` inside the transaction.
- **`FormOnce` token** (`_once`, already used by Goods Issue) on:
  - store, update, destroy, post, revert, updateSpecial
  - a resent form gets the "already sent" message and changes nothing
  - on create, a resent form is sent to the existing draft, as today
- **Button locks:**
  - a `submitting` flag plus `disabled` and a "Saving…" label on create, update and special edit, also covering the wait for photo shrinking
  - the list-page delete modal and the password modal (revert) get the same lock

### 2. Lighter uploads

- **Size and quality:** defaults change to 1600px at quality 75 (`attachment_images.max_px` / `quality`); `.env` can still override them.
- **WebP in the browser:**
  - the compressor outputs WebP when the browser supports it
  - otherwise JPEG
  - PDFs are untouched
- **Server side:**
  - accepts WebP, already allowed
  - DomPDF renders WebP (GD has WebP support here)
  - the print2 sheet is checked with a WebP photo
- **Thumbnails:**
  - on store, also write a 320px `thumbs/` copy next to the file
  - lists and preview modals use the thumbnail; opening the file shows the full image
  - missing thumbnails (old files) fall back to the full file
- **Signed links:** when the disk is S3, `attachment` redirects to a 10-minute `temporaryUrl` instead of streaming through PHP. The permission check stays first.
- **Staging cleanup:**
  - a scheduled command (daily) deletes `_staging/` files older than 24 hours
  - documented as an S3 lifecycle rule too

### 3. Create and edit

- `<x-apple-skin />`, `x-page-header` (back and help), `max-w-7xl`.
- Section cards in this order: Goods Issue, Items, Cash and Collections, Expenses, Summary, Notes.
- Items table:
  - a `#` column and SKU labels
  - phone card layout below 1024px
  - no sideways scroll
- Collection sections (credit, recoveries, cheques, transfers, slips) and the expense blocks:
  - shown as Apple cards, each with a count and total
  - the existing "Add" button opens the existing modal, restyled
- Sticky blurred bottom bar with the net cash and short/excess figures and the Save button.
- Select2 styled like the claim-register filters.

### 4. List and show

- Match the Goods Issue list:
  - a `#` column
  - Quick look on the settlement number
  - the full date
  - the compact create pill
  - checks at tablet and phone sizes
- Show page:
  - the same hero, stats and timeline pattern as the Goods Issue show page
  - the PDF dropdown in place of three separate buttons

### 5. Help (my call)

- A small **?** next to each section heading opens a short modal for that section only:
  - Items, Credit, Recoveries, Cheques, Bank, Cash, Expenses, Summary, Posting
  - 3 to 6 lines of Urdu each: what it is, what it does in the books, one common mistake
- The header Help button opens a short index that links to the same sections.
- Built on the existing `register-help` pattern.

### 6. PDF

Rework print2 to the Goods Issue PDF standard:
- a navy header table
- info boxes
- zebra rows and clear totals
- signature lines
- page numbers (already there)
- attachments using the full image

### 7. Verification

- Pest tests for:
  - the post and revert locks (a second call is refused)
  - `FormOnce` on each action
  - thumbnail and signed-link behaviour
  - unchanged totals for a full settlement
- Then in Chrome, on the local copy (moontraders.test):
  - Create a heavy goods issue: 8 or more SKUs across several batches, plus a supplementary line and a partial removal.
  - Settle it using every field:
    - sales, returns, shortages
    - credit sales with a photo
    - recoveries in cash and by bank, with a photo
    - cheques, bank transfers, bank slips
    - the denomination grid
    - expenses including advance tax, percentage expense and AMR powder and liquid
    - notes
  - Save as draft, edit, post, revert, then re-post.
  - Snapshot every table at each step:
    - stock by batch, valuation layers, current stock
    - the inventory ledger
    - van stock balances and batches
    - journals (1151, 1155, 4110, 5111, 1121, banks)
    - customer ledger, bank register
    - S3 objects
  - Try double clicks on every button.
  - Check the console, network and Laravel logs on every page, at 375, 768, 1024 and 1440 widths.
- Every bug found gets reported, and fixed if it is in scope.

## Constraints

- Local MySQL is a production copy: never wipe it; tests run on pgsql. Back up before any migration.
- Don't commit unless asked.
- Install the emilkowalski skills (`bunx skills@latest add emilkowalski/skills`) before the design work.
- Build with `bun run build`; run Pint on PHP changes.

## Left to the builder

- Exact CSS class names, and whether the modal restyle is CSS-only or needs small markup wrappers.
- The thumbnail naming scheme.
