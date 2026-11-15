<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Services\ChequeClearingPostingService;
use App\Services\ChequeClearingService;
use App\Support\CashNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ChequeRegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-cheque-register', only: ['index', 'history']),
            new Middleware('can:report-audit-cheque-register-manage', only: ['updateStatus', 'bulkClear']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 50;
        $canViewAllSuppliers = $this->canViewAllSuppliers();
        $userSupplierId = $this->getUserSupplierScope();

        $requestedSupplierId = $request->input('filter.supplier_id');
        if ($requestedSupplierId && ! $canViewAllSuppliers && (int) $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        $supplierId = $userSupplierId ?? $requestedSupplierId;
        $employeeId = $request->input('filter.employee_id');
        $customerId = $request->input('filter.customer_id');
        $statuses = array_filter((array) $request->input('filter.status', []));
        $chequeDateFrom = $request->input('filter.cheque_date_from');
        $chequeDateTo = $request->input('filter.cheque_date_to');
        $entryDateFrom = $request->input('filter.entry_date_from');
        $entryDateTo = $request->input('filter.entry_date_to');
        $bankName = $request->input('filter.bank_name');
        $chequeNumber = $request->input('filter.cheque_number');

        $allowedSorts = [
            'cheque_date' => 'sales_settlement_cheques.cheque_date',
            'created_at' => 'sales_settlement_cheques.created_at',
            'amount' => 'sales_settlement_cheques.amount',
        ];
        $sortBy = $request->input('sort', 'created_at');
        $sortBy = array_key_exists($sortBy, $allowedSorts) ? $sortBy : 'created_at';
        $sortDir = $request->input('direction', 'desc');
        $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'desc';

        if ($employeeId && ! $canViewAllSuppliers) {
            $isAllowedEmployee = Employee::query()
                ->where('id', $employeeId)
                ->where('supplier_id', $supplierId)
                ->exists();

            if (! $isAllowedEmployee) {
                abort(403, 'You do not have permission to filter by this salesman.');
            }
        }

        $suppliers = Supplier::query()
            ->when($supplierId, fn ($query) => $query->where('id', $supplierId))
            ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);
        $employees = Employee::query()
            ->where('is_active', true)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $customerIds = SalesSettlementCheque::query()
            ->join('sales_settlements', 'sales_settlements.id', '=', 'sales_settlement_cheques.sales_settlement_id')
            ->when($supplierId, fn ($query) => $query->where('sales_settlements.supplier_id', $supplierId))
            ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereNotNull('sales_settlement_cheques.customer_id')
            ->distinct()
            ->pluck('sales_settlement_cheques.customer_id');
        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name']);

        $query = SalesSettlementCheque::query()
            ->join('sales_settlements', 'sales_settlements.id', '=', 'sales_settlement_cheques.sales_settlement_id')
            ->leftJoin('employees', 'employees.id', '=', 'sales_settlements.employee_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_settlement_cheques.customer_id')
            ->select([
                'sales_settlement_cheques.*',
                'employees.name as employee_name',
                'customers.customer_name',
                'customers.address as customer_address',
            ])
            ->when($supplierId, fn ($q) => $q->where('sales_settlements.supplier_id', $supplierId))
            ->when(! $canViewAllSuppliers && ! $supplierId, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($employeeId, fn ($q) => $q->where('sales_settlements.employee_id', $employeeId))
            ->when($customerId, fn ($q) => $q->where('sales_settlement_cheques.customer_id', $customerId))
            ->when($statuses, fn ($q) => $q->whereIn('sales_settlement_cheques.status', $statuses))
            ->when($chequeDateFrom, fn ($q) => $q->where('sales_settlement_cheques.cheque_date', '>=', $chequeDateFrom))
            ->when($chequeDateTo, fn ($q) => $q->where('sales_settlement_cheques.cheque_date', '<=', $chequeDateTo))
            ->when($entryDateFrom, fn ($q) => $q->where('sales_settlement_cheques.created_at', '>=', $entryDateFrom))
            ->when($entryDateTo, fn ($q) => $q->where('sales_settlement_cheques.created_at', '<=', $entryDateTo.' 23:59:59'))
            ->when($bankName, fn ($q) => $q->where('sales_settlement_cheques.bank_name', 'like', '%'.$bankName.'%'))
            ->when($chequeNumber, fn ($q) => $q->where('sales_settlement_cheques.cheque_number', 'like', '%'.$chequeNumber.'%'))
            ->orderBy($allowedSorts[$sortBy], $sortDir)
            ->orderBy('sales_settlement_cheques.id', $sortDir);

        $totalAmount = (clone $query)->sum('sales_settlement_cheques.amount');

        if ($perPage === 'all') {
            $allItems = $query->get();
            $cheques = new LengthAwarePaginator(
                $allItems,
                $allItems->count(),
                $allItems->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $cheques = $query->paginate((int) $perPage)->withQueryString();
        }

        return view('reports.cheque-register.index', [
            'cheques' => $cheques,
            'suppliers' => $suppliers,
            'employees' => $employees,
            'customers' => $customers,
            'totalAmount' => $totalAmount,
            'perPage' => $perPage,
            'selectedSupplierId' => $supplierId,
            'selectedEmployeeId' => $employeeId,
            'selectedCustomerId' => $customerId,
            'selectedStatuses' => $statuses,
            'chequeDateFrom' => $chequeDateFrom,
            'chequeDateTo' => $chequeDateTo,
            'entryDateFrom' => $entryDateFrom,
            'entryDateTo' => $entryDateTo,
            'bankName' => $bankName,
            'chequeNumber' => $chequeNumber,
            'availableStatuses' => ['pending', 'cleared', 'bounced', 'cancelled'],
            'depositBanks' => BankAccount::where('is_active', true)->whereNotNull('chart_of_account_id')->orderBy('account_name')->get(['id', 'account_name', 'account_number']),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'canViewAllSuppliers' => $canViewAllSuppliers,
        ]);
    }

    public function updateStatus(Request $request, SalesSettlementCheque $cheque, ChequeClearingService $clearing)
    {
        $cheque->loadMissing('salesSettlement');
        $this->authorizeChequeAccess($cheque);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,cleared,bounced,cancelled'],
            'cleared_date' => ['required_if:status,cleared', 'nullable', 'date'],
            'cleared_via' => ['nullable', 'in:cash,bank'],
            'cleared_bank_account_id' => ['nullable', 'required_if:cleared_via,bank', Rule::exists('bank_accounts', 'id')->where('is_active', true)],
            'status_date' => ['nullable', 'date'],
            'bank_charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:500'],
            'denom' => ['nullable', 'array'],
            'denom.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        // Changing a cleared cheque reopens its whole batch, so the user must be allowed every cheque in it.
        if ($cheque->isCleared() && $cheque->cheque_clearing_id) {
            SalesSettlementCheque::with('salesSettlement')->where('cheque_clearing_id', $cheque->cheque_clearing_id)->get()
                ->each(fn (SalesSettlementCheque $member) => $this->authorizeChequeAccess($member));
        }

        $isCleared = $validated['status'] === 'cleared';
        $clearedVia = $isCleared ? ($validated['cleared_via'] ?? ChequeClearingPostingService::VIA_CASH) : null;
        $user = $request->user();

        $outcome = $this->attempt(fn () => $isCleared
            ? DB::transaction(function () use ($cheque, $clearing, $validated, $clearedVia, $user) {
                // A cheque already cleared is corrected by un-clearing it (same day only) and clearing it again.
                if ($cheque->isCleared()) {
                    $clearing->changeStatus($cheque, 'pending', null, null, 'Corrected: cleared again', $user);
                }

                $clearing->clear(collect([$cheque->fresh()]), $validated['cleared_date'], $clearedVia,
                    isset($validated['cleared_bank_account_id']) ? (int) $validated['cleared_bank_account_id'] : null,
                    CashNotes::fromInput((array) ($validated['denom'] ?? [])), $validated['notes'] ?? null, $user);
            })
            : $clearing->changeStatus($cheque, $validated['status'], $validated['status_date'] ?? null,
                isset($validated['bank_charge']) ? (float) $validated['bank_charge'] : null, $validated['notes'] ?? null, $user));

        if ($outcome) {
            return $outcome;
        }

        return redirect()->back()->with('success', match (true) {
            ! $isCleared => 'Cheque status updated successfully.',
            $clearedVia === ChequeClearingPostingService::VIA_CASH => 'Cheque cleared: the cash brought back is booked into Cash.',
            default => 'Cheque cleared by bank and booked into the bank account.',
        });
    }

    /**
     * Clear several pending cheques at once as one batch. A cash batch is one company, and the notes counted for it
     * are stored once on the batch.
     */
    public function bulkClear(Request $request, ChequeClearingService $clearing)
    {
        $validated = $request->validate([
            'cheque_ids' => ['required', 'array', 'min:1', 'max:500'],
            'cheque_ids.*' => ['integer', 'distinct'],
            'cleared_date' => ['required', 'date'],
            'cleared_via' => ['required', 'in:cash,bank'],
            'cleared_bank_account_id' => ['nullable', 'required_if:cleared_via,bank', Rule::exists('bank_accounts', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:500'],
            'denom' => ['nullable', 'array'],
            'denom.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $cheques = SalesSettlementCheque::with('salesSettlement')->whereIn('id', $validated['cheque_ids'])->orderBy('id')->get();

        if ($cheques->count() !== count($validated['cheque_ids'])) {
            return back()->with('error', 'Some of the selected cheques no longer exist. Reload the page and select again.');
        }

        $cheques->each(fn (SalesSettlementCheque $cheque) => $this->authorizeChequeAccess($cheque));

        $byCash = $validated['cleared_via'] === ChequeClearingPostingService::VIA_CASH;
        $batch = null;

        $outcome = $this->attempt(function () use (&$batch, $clearing, $cheques, $validated, $request) {
            $batch = $clearing->clear($cheques, $validated['cleared_date'], $validated['cleared_via'],
                isset($validated['cleared_bank_account_id']) ? (int) $validated['cleared_bank_account_id'] : null,
                CashNotes::fromInput((array) ($validated['denom'] ?? [])), $validated['notes'] ?? null, $request->user());
        });

        if ($outcome) {
            return $outcome;
        }

        return redirect()->back()->with('success', $cheques->count().' cheques cleared (Rs '.number_format((float) $batch->cheques()->sum('amount'), 2).') and booked into '.($byCash ? 'Cash' : 'the bank account').'.');
    }

    /**
     * Run a clearing step; a refused step comes back as an error flash, anything unexpected is logged.
     */
    private function attempt(callable $step): ?RedirectResponse
    {
        try {
            $step();
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Posting failed. Please contact the admin.');
        }

        return null;
    }

    /**
     * Every status change of a cheque, newest first, for the history popup.
     */
    public function history(SalesSettlementCheque $cheque): JsonResponse
    {
        $cheque->loadMissing('salesSettlement');
        $this->authorizeChequeAccess($cheque);

        return response()->json([
            'cheque_number' => $cheque->cheque_number,
            'amount' => number_format((float) $cheque->amount, 2),
            'entries' => $cheque->histories()->with(['changedBy:id,name', 'bankAccount:id,account_name', 'journalEntry:id,reference', 'reversedJournalEntry:id,reference'])->get()->map(fn ($entry) => [
                'when' => $entry->created_at?->format('d-M-Y h:i A'),
                'from' => $entry->from_status ? ucfirst($entry->from_status) : 'New',
                'to' => ucfirst($entry->to_status),
                'cleared_date' => $entry->cleared_date?->format('d-M-Y'),
                'cleared_amount' => $entry->cleared_amount !== null ? number_format((float) $entry->cleared_amount, 2) : null,
                'notes' => $entry->notes,
                'by' => $entry->changedBy?->name,
                'via' => $entry->cleared_via,
                'bank' => $entry->bankAccount?->account_name,
                'batch' => $entry->cheque_clearing_id,
                'journal_entry' => $entry->journalEntry ? ['id' => $entry->journalEntry->id, 'reference' => $entry->journalEntry->reference] : null,
                'reversed_journal_entry' => $entry->reversedJournalEntry ? ['id' => $entry->reversedJournalEntry->id, 'reference' => $entry->reversedJournalEntry->reference] : null,
                'bank_charge' => $entry->bank_charge !== null ? number_format((float) $entry->bank_charge, 2) : null,
                'batch_notes' => $entry->denominations ? $this->batchNotes(CashNotes::fromColumns($entry->denominations)) : null,
            ])->all(),
        ]);
    }

    /**
     * @return array{notes: array<int, int>, coins: float, total: string}
     */
    private function batchNotes(CashNotes $notes): array
    {
        return [
            'notes' => collect(CashNotes::NOTES)->mapWithKeys(fn (int $note) => [$note => $notes->count($note)])->filter()->all(),
            'coins' => $notes->coins(),
            'total' => number_format($notes->total(), 2),
        ];
    }

    private function getUserSupplierScope(): ?int
    {
        $user = auth()->user();

        if ($this->canViewAllSuppliers()) {
            return null;
        }

        return $user->supplier_id ? (int) $user->supplier_id : null;
    }

    private function canViewAllSuppliers(): bool
    {
        $user = auth()->user();

        return $user->is_super_admin === 'Yes'
            || $user->hasRole('super-admin')
            || $user->hasRole('admin');
    }

    private function authorizeChequeAccess(SalesSettlementCheque $cheque): void
    {
        if ($this->canViewAllSuppliers()) {
            return;
        }

        $userSupplierId = $this->getUserSupplierScope();

        if (! $userSupplierId || (int) $cheque->salesSettlement?->supplier_id !== $userSupplierId) {
            abort(403, 'You do not have permission to access this cheque.');
        }
    }
}
