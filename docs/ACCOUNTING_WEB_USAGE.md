# Using AccountingService in Web Controllers

## Overview
The `AccountingService` provides transactional accounting operations with automatic rollback. You can use it in any controller by injecting it through the constructor.

---

## Basic Usage Pattern

### 1. Inject Service in Controller

```php
<?php

namespace App\Http\Controllers;

use App\Services\AccountingService;
use Illuminate\Http\Request;

class YourController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function yourMethod(Request $request)
    {
        $result = $this->accountingService->createJournalEntry($request->all());

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
```

---

## Available Service Methods

### 1. Create General Journal Entry

```php
public function store(Request $request)
{
    $data = [
        'entry_date' => $request->entry_date,
        'reference' => $request->reference,
        'description' => $request->description,
        'currency_id' => $request->currency_id,
        'cost_center_id' => $request->cost_center_id,
        'auto_post' => true, // Auto-post after creation
        'lines' => [
            [
                'account_id' => $request->debit_account,
                'debit' => $request->amount,
                'credit' => 0,
                'description' => 'Debit side description',
            ],
            [
                'account_id' => $request->credit_account,
                'debit' => 0,
                'credit' => $request->amount,
                'description' => 'Credit side description',
            ],
        ],
    ];

    $result = $this->accountingService->createJournalEntry($data);

    if ($result['success']) {
        return redirect()
            ->route('journal-entries.show', $result['data']->id)
            ->with('success', 'Entry created successfully');
    }

    return redirect()->back()->with('error', $result['message']);
}
```

**Returns:**
```php
[
    'success' => true,
    'data' => JournalEntry object,
    'message' => 'Journal entry created successfully'
]
```

---

### 2. Post Draft Entry

```php
public function post($id)
{
    $result = $this->accountingService->postJournalEntry($id);

    if ($result['success']) {
        return redirect()
            ->back()
            ->with('success', 'Entry posted successfully');
    }

    return redirect()->back()->with('error', $result['message']);
}
```

---

### 3. Reverse Posted Entry

```php
public function reverse(Request $request, $id)
{
    $result = $this->accountingService->reverseJournalEntry(
        $id,
        $request->input('description', 'Reversing entry for correction')
    );

    if ($result['success']) {
        return redirect()
            ->route('journal-entries.show', $result['data']->id)
            ->with('success', 'Reversing entry created');
    }

    return redirect()->back()->with('error', $result['message']);
}
```

---

### 4. Record Opening Balance

```php
public function recordOpeningBalance(Request $request)
{
    $result = $this->accountingService->recordOpeningBalance(
        amount: $request->amount,
        description: 'Opening balance - Initial capital',
        options: [
            'entry_date' => $request->entry_date ?? now(),
            'reference' => 'OB-001',
            'auto_post' => true,
        ]
    );

    if ($result['success']) {
        return redirect()
            ->route('journal-entries.show', $result['data']->id)
            ->with('success', 'Opening balance recorded');
    }

    return redirect()->back()->with('error', $result['message']);
}
```

**What it does:**
```
Dr. Cash                    ₨500,000
    Cr. Owner Capital                   ₨500,000
```

---

### 5. Record Cash Receipt (Revenue)

```php
public function recordCashReceipt(Request $request)
{
    $result = $this->accountingService->recordCashReceipt(
        amount: $request->amount,
        revenueAccountId: $request->revenue_account_id,
        description: $request->description,
        options: [
            'reference' => $request->reference,
            'cost_center_id' => $request->cost_center_id,
            'auto_post' => true,
        ]
    );

    if ($result['success']) {
        return redirect()
            ->route('journal-entries.show', $result['data']->id)
            ->with('success', 'Cash receipt recorded');
    }

    return redirect()->back()->with('error', $result['message']);
}
```

**What it does:**
```
Dr. Cash                    ₨100,000
    Cr. Service Revenue                 ₨100,000
```

---

### 6. Record Cash Payment (Expense)

```php
public function recordCashPayment(Request $request)
{
    $result = $this->accountingService->recordCashPayment(
        amount: $request->amount,
        expenseAccountId: $request->expense_account_id,
        description: $request->description,
        options: [
            'reference' => $request->reference,
            'cost_center_id' => $request->cost_center_id,
            'auto_post' => true,
        ]
    );

    if ($result['success']) {
        return redirect()
            ->route('journal-entries.show', $result['data']->id)
            ->with('success', 'Cash payment recorded');
    }

    return redirect()->back()->with('error', $result['message']);
}
```

**What it does:**
```
Dr. Rent Expense           ₨25,000
    Cr. Cash                           ₨25,000
```

---

### 7. Record Credit Sale

```php
public function recordCreditSale(Request $request)
{
    $result = $this->accountingService->recordCreditSale(
        amount: $request->amount,
        revenueAccountId: $request->revenue_account_id,
        customerReference: $request->customer_name,
        description: $request->description,
        options: [
            'reference' => $request->invoice_number,
            'cost_center_id' => $request->cost_center_id,
            'auto_post' => true,
        ]
    );

    if ($result['success']) {
        return redirect()
            ->route('journal-entries.show', $result['data']->id)
            ->with('success', 'Credit sale recorded');
    }

    return redirect()->back()->with('error', $result['message']);
}
```

**What it does:**
```
Dr. Accounts Receivable    ₨150,000
    Cr. Sales Revenue                  ₨150,000
```

---

### 8. Record Payment Received

```php
public function recordPaymentReceived(Request $request)
{
    $result = $this->accountingService->recordPaymentReceived(
        amount: $request->amount,
        customerReference: $request->customer_name,
        options: [
            'reference' => $request->receipt_number,
            'invoice_ref' => $request->invoice_number,
            'auto_post' => true,
        ]
    );

    if ($result['success']) {
        return redirect()
            ->route('journal-entries.show', $result['data']->id)
            ->with('success', 'Payment received recorded');
    }

    return redirect()->back()->with('error', $result['message']);
}
```

**What it does:**
```
Dr. Cash                    ₨150,000
    Cr. Accounts Receivable            ₨150,000
```

---

## Example: Sales Module Controller

```php
<?php

namespace App\Http\Controllers;

use App\Services\AccountingService;
use App\Models\Sale;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Complete a sale and record accounting entry
     */
    public function completeSale(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string',
            'invoice_number' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,credit',
            'revenue_account_id' => 'required|exists:chart_of_accounts,id',
        ]);

        // Save sale record in your sales table
        $sale = Sale::create([
            'customer_name' => $validated['customer_name'],
            'invoice_number' => $validated['invoice_number'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
        ]);

        // Record accounting entry based on payment method
        if ($validated['payment_method'] === 'cash') {
            // Cash sale
            $result = $this->accountingService->recordCashReceipt(
                amount: $validated['amount'],
                revenueAccountId: $validated['revenue_account_id'],
                description: "Cash sale - Invoice #{$validated['invoice_number']}",
                options: [
                    'reference' => $validated['invoice_number'],
                    'auto_post' => true,
                ]
            );
        } else {
            // Credit sale
            $result = $this->accountingService->recordCreditSale(
                amount: $validated['amount'],
                revenueAccountId: $validated['revenue_account_id'],
                customerReference: $validated['customer_name'],
                description: "Credit sale - Invoice #{$validated['invoice_number']}",
                options: [
                    'reference' => $validated['invoice_number'],
                    'auto_post' => true,
                ]
            );
        }

        if ($result['success']) {
            // Link journal entry to sale
            $sale->update(['journal_entry_id' => $result['data']->id]);

            return redirect()
                ->route('sales.show', $sale->id)
                ->with('success', 'Sale completed and recorded in accounts');
        }

        // If accounting fails, delete the sale (or handle differently)
        $sale->delete();

        return redirect()
            ->back()
            ->with('error', 'Failed to record accounting entry: ' . $result['message']);
    }
}
```

---

## Example: Expense Module Controller

```php
<?php

namespace App\Http\Controllers;

use App\Services\AccountingService;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Record an expense payment
     */
    public function recordExpense(Request $request)
    {
        $validated = $request->validate([
            'expense_type' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'expense_account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'required|string|max:500',
            'voucher_number' => 'nullable|string',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
        ]);

        // Save expense record
        $expense = Expense::create([
            'expense_type' => $validated['expense_type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'voucher_number' => $validated['voucher_number'],
        ]);

        // Record accounting entry
        $result = $this->accountingService->recordCashPayment(
            amount: $validated['amount'],
            expenseAccountId: $validated['expense_account_id'],
            description: $validated['description'],
            options: [
                'reference' => $validated['voucher_number'],
                'cost_center_id' => $validated['cost_center_id'],
                'auto_post' => true,
            ]
        );

        if ($result['success']) {
            $expense->update(['journal_entry_id' => $result['data']->id]);

            return redirect()
                ->route('expenses.index')
                ->with('success', 'Expense recorded successfully');
        }

        $expense->delete();

        return redirect()
            ->back()
            ->with('error', 'Failed to record expense: ' . $result['message']);
    }
}
```

---

## Response Format

All service methods return the same format:

```php
[
    'success' => true|false,
    'message' => 'Human-readable message',
    'data' => JournalEntry|null  // The created/updated entry, or null on failure
]
```

**Success Example:**
```php
[
    'success' => true,
    'message' => 'Journal entry created successfully',
    'data' => JournalEntry {
        id: 123,
        entry_date: '2025-01-15',
        status: 'posted',
        ...
    }
]
```

**Failure Example:**
```php
[
    'success' => false,
    'message' => 'Entry does not balance. Debits: 100000, Credits: 95000',
    'data' => null
]
```

---

## Transaction Safety

### Automatic Rollback
All service methods use `DB::transaction()`:
- Any exception triggers automatic rollback
- No partial data is saved
- Database constraints are validated
- Safe to use in nested transactions

### Example with External Operations

```php
public function processOrder(Request $request)
{
    DB::transaction(function () use ($request) {
        // 1. Create order
        $order = Order::create([...]);

        // 2. Update inventory
        Inventory::where('product_id', $order->product_id)
            ->decrement('quantity', $order->quantity);

        // 3. Record accounting entry (also wrapped in transaction internally)
        $result = $this->accountingService->recordCashReceipt(
            amount: $order->total,
            revenueAccountId: 10,
            description: "Sale - Order #{$order->id}",
            options: ['auto_post' => true]
        );

        if (!$result['success']) {
            throw new \Exception($result['message']);
        }

        $order->update(['journal_entry_id' => $result['data']->id]);
    });
}
```

---

## Error Handling

```php
public function yourMethod(Request $request)
{
    try {
        $result = $this->accountingService->createJournalEntry($data);

        if ($result['success']) {
            // Success - entry created
            Log::info('Journal entry created', ['entry_id' => $result['data']->id]);
            
            return redirect()
                ->route('journal-entries.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        // Business logic failure (e.g., entry doesn't balance)
        Log::warning('Failed to create journal entry', [
            'message' => $result['message'],
            'data' => $data,
        ]);

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $result['message']);

    } catch (\Exception $e) {
        // Unexpected error (shouldn't happen - service handles exceptions)
        Log::error('Unexpected error creating journal entry', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return redirect()
            ->back()
            ->with('error', 'An unexpected error occurred');
    }
}
```

---

## Flash Messages in Blade

Display success/error messages in your views:

```blade
@if (session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif
```

---

## Available Routes

After adding routes in `web.php`:

| Method | URI | Action | Name |
|--------|-----|--------|------|
| GET | /journal-entries | index | journal-entries.index |
| GET | /journal-entries/create | create | journal-entries.create |
| POST | /journal-entries | store | journal-entries.store |
| GET | /journal-entries/{id} | show | journal-entries.show |
| GET | /journal-entries/{id}/edit | edit | journal-entries.edit |
| PUT | /journal-entries/{id} | update | journal-entries.update |
| DELETE | /journal-entries/{id} | destroy | journal-entries.destroy |
| POST | /journal-entries/{id}/post | post | journal-entries.post |
| POST | /journal-entries/{id}/reverse | reverse | journal-entries.reverse |
| POST | /transactions/cash-receipt | recordCashReceipt | transactions.cash-receipt |
| POST | /transactions/cash-payment | recordCashPayment | transactions.cash-payment |
| POST | /transactions/opening-balance | recordOpeningBalance | transactions.opening-balance |

---

## Quick Start Checklist

1. ✅ AccountingService created at `app/Services/AccountingService.php`
2. ✅ JournalEntryController updated with service injection
3. ✅ Routes added to `routes/web.php`
4. ⏳ Create views for journal entries (index, create, edit, show)
5. ⏳ Add validation in Form Request classes
6. ⏳ Test all operations in browser

---

## Next Steps

1. **Create Blade Views** - Create views for listing, creating, editing journal entries
2. **Add Validation** - Update `StoreJournalEntryRequest` and `UpdateJournalEntryRequest`
3. **Test Operations** - Test all transaction types in your application
4. **Add Attachments** - Integrate file uploads for vouchers
5. **Reports** - Create controllers/views for trial balance, balance sheet, etc.

---

## Support

Check logs for detailed error information:
```bash
tail -f storage/logs/laravel.log
```

All service operations are logged with context for debugging.
