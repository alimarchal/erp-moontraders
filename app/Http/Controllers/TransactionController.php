<?php

namespace App\Http\Controllers;

use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Create a general journal entry
     * POST /api/transactions/journal-entry
     */
    public function createJournalEntry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entry_date' => 'required|date',
            'reference' => 'nullable|string|max:50',
            'description' => 'required|string|max:500',
            'currency_id' => 'nullable|exists:currencies,id',
            'fx_rate' => 'nullable|numeric|min:0.000001',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
            'auto_post' => 'nullable|boolean',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.cost_center_id' => 'nullable|exists:cost_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->accountingService->createJournalEntry($request->all());

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Post a draft journal entry
     * POST /api/transactions/{id}/post
     */
    public function postJournalEntry($id)
    {
        $result = $this->accountingService->postJournalEntry($id);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Reverse a posted journal entry
     * POST /api/transactions/{id}/reverse
     */
    public function reverseJournalEntry(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->accountingService->reverseJournalEntry(
            $id,
            $request->input('description')
        );

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Record opening balance
     * POST /api/transactions/opening-balance
     */
    public function recordOpeningBalance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'entry_date' => 'nullable|date',
            'reference' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->accountingService->recordOpeningBalance(
            $request->input('amount'),
            $request->input('description', 'Opening balance - Owner capital'),
            [
                'entry_date' => $request->input('entry_date'),
                'reference' => $request->input('reference'),
                'auto_post' => $request->input('auto_post', true),
            ]
        );

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Record cash receipt (revenue received)
     * POST /api/transactions/cash-receipt
     */
    public function recordCashReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'revenue_account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'required|string|max:500',
            'reference' => 'nullable|string|max:50',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
            'auto_post' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->accountingService->recordCashReceipt(
            $request->input('amount'),
            $request->input('revenue_account_id'),
            $request->input('description'),
            [
                'reference' => $request->input('reference'),
                'cost_center_id' => $request->input('cost_center_id'),
                'auto_post' => $request->input('auto_post', true),
            ]
        );

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Record cash payment (expense paid)
     * POST /api/transactions/cash-payment
     */
    public function recordCashPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'expense_account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'required|string|max:500',
            'reference' => 'nullable|string|max:50',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
            'auto_post' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->accountingService->recordCashPayment(
            $request->input('amount'),
            $request->input('expense_account_id'),
            $request->input('description'),
            [
                'reference' => $request->input('reference'),
                'cost_center_id' => $request->input('cost_center_id'),
                'auto_post' => $request->input('auto_post', true),
            ]
        );

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Record credit sale (revenue on account)
     * POST /api/transactions/credit-sale
     */
    public function recordCreditSale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'revenue_account_id' => 'required|exists:chart_of_accounts,id',
            'customer_reference' => 'required|string|max:255',
            'description' => 'required|string|max:500',
            'reference' => 'nullable|string|max:50',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
            'auto_post' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->accountingService->recordCreditSale(
            $request->input('amount'),
            $request->input('revenue_account_id'),
            $request->input('customer_reference'),
            $request->input('description'),
            [
                'reference' => $request->input('reference'),
                'cost_center_id' => $request->input('cost_center_id'),
                'auto_post' => $request->input('auto_post', true),
            ]
        );

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Record payment received from customer
     * POST /api/transactions/payment-received
     */
    public function recordPaymentReceived(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'customer_reference' => 'required|string|max:255',
            'reference' => 'nullable|string|max:50',
            'invoice_ref' => 'nullable|string|max:50',
            'auto_post' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->accountingService->recordPaymentReceived(
            $request->input('amount'),
            $request->input('customer_reference'),
            [
                'reference' => $request->input('reference'),
                'invoice_ref' => $request->input('invoice_ref'),
                'auto_post' => $request->input('auto_post', true),
            ]
        );

        return response()->json($result, $result['success'] ? 201 : 400);
    }
}
