<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Services\ChequeClearingPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

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

    public function updateStatus(Request $request, SalesSettlementCheque $cheque, ChequeClearingPostingService $clearing)
    {
        $cheque->loadMissing('salesSettlement');
        $this->authorizeChequeAccess($cheque);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,cleared,bounced,cancelled'],
            'cleared_date' => ['required_if:status,cleared', 'nullable', 'date'],
            'cleared_via' => ['nullable', 'in:cash,bank'],
            'cleared_bank_account_id' => ['nullable', 'required_if:cleared_via,bank', Rule::exists('bank_accounts', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:500'],
            'denom' => ['nullable', 'array'],
            'denom.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $isCleared = $validated['status'] === 'cleared';
        $clearedVia = $isCleared ? ($validated['cleared_via'] ?? ChequeClearingPostingService::VIA_CASH) : null;
        $byCash = $clearedVia === ChequeClearingPostingService::VIA_CASH;

        // Notes are counted only when the salesman brings the cash back; a cheque the bank cleared brings no notes.
        $denominations = $this->clearedDenominations($isCleared && $byCash ? (array) ($validated['denom'] ?? []) : []);
        $amount = (float) $cheque->amount;

        if ($isCleared && $byCash && abs($denominations['total'] - $amount) > 0.009) {
            return back()->withInput()->with('error', $denominations['total'] > 0
                ? 'The notes you counted add up to Rs '.number_format($denominations['total'], 2).' but the cheque is Rs '.number_format($amount, 2).'. Make them equal.'
                : 'Count the cash notes brought back for this cleared cheque; they must add up to Rs '.number_format($amount, 2).'.');
        }

        // A cheque already cleared can be corrected only on the day it was cleared, because that day's cash report is already built from it.
        if ($isCleared && $cheque->status === 'cleared' && $cheque->cleared_date && ! $cheque->cleared_date->isToday()) {
            return back()->with('error', 'This cheque was cleared on '.$cheque->cleared_date->format('d-M-Y').'. Its cleared details can be changed only on that day.');
        }

        try {
            DB::transaction(fn () => $this->applyStatus($cheque, $clearing, $validated['status'], $validated['cleared_date'] ?? null, $clearedVia,
                isset($validated['cleared_bank_account_id']) ? (int) $validated['cleared_bank_account_id'] : null, $denominations['columns'], $validated['notes'] ?? null));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', match (true) {
            ! $isCleared => 'Cheque status updated successfully.',
            $byCash => 'Cheque cleared: the cash brought back is booked into Cash.',
            default => 'Cheque cleared by bank and booked into the bank account.',
        });
    }

    /**
     * Clear several pending cheques at once (month-end). The notes are counted once for all of them and shared out
     * over the cheques, so the day's cash report still adds up.
     */
    public function bulkClear(Request $request, ChequeClearingPostingService $clearing)
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

        $notPending = $cheques->reject(fn (SalesSettlementCheque $cheque) => $cheque->isPending());

        if ($notPending->isNotEmpty()) {
            return back()->with('error', 'Only pending cheques can be cleared together. Already '.$notPending->pluck('status')->unique()->implode(' / ').': '.$notPending->pluck('cheque_number')->implode(', ').'.');
        }

        $byCash = $validated['cleared_via'] === ChequeClearingPostingService::VIA_CASH;
        $total = round((float) $cheques->sum('amount'), 2);
        $counted = $this->clearedDenominations($byCash ? (array) ($validated['denom'] ?? []) : []);

        if ($byCash && abs($counted['total'] - $total) > 0.009) {
            return back()->with('error', 'The notes you counted add up to Rs '.number_format($counted['total'], 2).' but the '.$cheques->count().' cheques come to Rs '.number_format($total, 2).'. Make them equal.');
        }

        $shares = $byCash ? $this->shareNotes($counted['columns'], $cheques) : [];

        try {
            DB::transaction(function () use ($cheques, $clearing, $validated, $shares) {
                foreach ($cheques as $cheque) {
                    $this->applyStatus($cheque, $clearing, 'cleared', $validated['cleared_date'], $validated['cleared_via'],
                        isset($validated['cleared_bank_account_id']) ? (int) $validated['cleared_bank_account_id'] : null,
                        $shares[$cheque->id] ?? $this->clearedDenominations([])['columns'], $validated['notes'] ?? null);
                }
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', $cheques->count().' cheques cleared (Rs '.number_format($total, 2).') and booked into '.($byCash ? 'Cash' : 'the bank account').'.');
    }

    /**
     * @param  array<string, int|float|null>  $denominationColumns
     */
    private function applyStatus(SalesSettlementCheque $cheque, ChequeClearingPostingService $clearing, string $status, ?string $clearedDate, ?string $clearedVia, ?int $bankAccountId, array $denominationColumns, ?string $notes): void
    {
        $fromStatus = $cheque->status;
        $isCleared = $status === 'cleared';

        // Whatever was booked for the old clearing is taken back first; a corrected clearing is booked again below.
        $clearing->reverse($cheque, $status);

        $cheque->update($denominationColumns + [
            'status' => $status,
            'cleared_date' => $isCleared ? $clearedDate : null,
            'cleared_amount' => $isCleared ? $cheque->amount : null,
            'cleared_via' => $isCleared ? $clearedVia : null,
            'cleared_bank_account_id' => $isCleared && $clearedVia === ChequeClearingPostingService::VIA_BANK ? $bankAccountId : null,
            'status_updated_at' => now(),
        ]);

        if ($isCleared) {
            $clearing->post($cheque->fresh());
        }

        $tag = $isCleared ? ($clearedVia === ChequeClearingPostingService::VIA_BANK ? '[Cleared by bank] ' : '[Cash brought back] ') : '';

        $cheque->histories()->create([
            'from_status' => $fromStatus,
            'to_status' => $status,
            'cleared_date' => $isCleared ? $clearedDate : null,
            'cleared_amount' => $isCleared ? $cheque->amount : null,
            'notes' => trim($tag.($notes ?? '')) ?: null,
            'changed_by' => auth()->id(),
        ]);
    }

    /**
     * Share notes counted for several cheques out over them, biggest notes first, so each cheque carries notes close
     * to its own amount. Whatever cannot be matched exactly stays with the last cheque; the day's total is unchanged.
     *
     * @param  array<string, int|float|null>  $columns
     * @param  Collection<int, SalesSettlementCheque>  $cheques
     * @return array<int, array<string, int|float|null>>
     */
    private function shareNotes(array $columns, $cheques): array
    {
        $pool = [];

        foreach (SalesSettlementCheque::CLEARED_NOTES as $note) {
            $pool[$note] = (int) ($columns['cleared_denom_'.$note] ?? 0);
        }

        $coins = (float) ($columns['cleared_denom_coins'] ?? 0);
        $shares = [];
        $last = $cheques->last();

        foreach ($cheques as $cheque) {
            $remaining = (float) $cheque->amount;
            $share = [];

            foreach (SalesSettlementCheque::CLEARED_NOTES as $note) {
                $take = $cheque->is($last) ? $pool[$note] : min($pool[$note], (int) floor(($remaining + 0.001) / $note));
                $share['cleared_denom_'.$note] = $take > 0 ? $take : null;
                $pool[$note] -= $take;
                $remaining -= $take * $note;
            }

            $takeCoins = $cheque->is($last) ? $coins : min($coins, max(0, round($remaining, 2)));
            $share['cleared_denom_coins'] = $takeCoins > 0 ? $takeCoins : null;
            $coins -= $takeCoins;

            $shares[$cheque->id] = $share;
        }

        return $shares;
    }

    /**
     * @param  array<string, mixed>  $entered  note value => number of notes, plus "coins" as an amount
     * @return array{columns: array<string, int|float|null>, total: float}
     */
    private function clearedDenominations(array $entered): array
    {
        $columns = [];
        $total = 0.0;

        foreach (SalesSettlementCheque::CLEARED_NOTES as $note) {
            $qty = (int) ($entered[$note] ?? 0);
            $columns['cleared_denom_'.$note] = $qty > 0 ? $qty : null;
            $total += $qty * $note;
        }

        $coins = (float) ($entered['coins'] ?? 0);
        $columns['cleared_denom_coins'] = $coins > 0 ? $coins : null;

        return ['columns' => $columns, 'total' => $total + $coins];
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
            'entries' => $cheque->histories()->with('changedBy:id,name')->get()->map(fn ($entry) => [
                'when' => $entry->created_at?->format('d-M-Y h:i A'),
                'from' => $entry->from_status ? ucfirst($entry->from_status) : 'New',
                'to' => ucfirst($entry->to_status),
                'cleared_date' => $entry->cleared_date?->format('d-M-Y'),
                'cleared_amount' => $entry->cleared_amount !== null ? number_format((float) $entry->cleared_amount, 2) : null,
                'notes' => $entry->notes,
                'by' => $entry->changedBy?->name,
            ])->all(),
        ]);
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
