<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ChequeRegisterExport;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Services\ChequeClearingPostingService;
use App\Services\ChequeClearingService;
use App\Support\CashNotes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ChequeRegisterController extends Controller implements HasMiddleware
{
    /** Rows a register page can show. */
    public const PER_PAGE = [25, 50, 100, 250];

    /** The cheque statuses, in tab order. */
    public const STATUSES = ['pending', 'cleared', 'bounced', 'cancelled'];

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-cheque-register', only: ['index', 'history']),
            new Middleware('can:report-audit-cheque-register-manage', only: ['updateStatus', 'bulkClear']),
        ];
    }

    /**
     * The cheque register, built like Goods Issues. It opens empty: nothing loads until a company (or "all", for a
     * user with all-company access) is chosen and the filters are applied. The status tabs count every status under
     * the other filters. ?export=xlsx downloads the filtered list; ?format=pdf prints it on A4 landscape.
     */
    public function index(Request $request): View|Response
    {
        $canViewAllSuppliers = $this->canViewAllSuppliers();
        $userSupplierId = $this->getUserSupplierScope();
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => is_scalar($value) && trim((string) $value) !== '');
        $requestedSupplier = isset($filters['supplier_id']) ? (string) $filters['supplier_id'] : null;

        if ($requestedSupplier !== null && ! $canViewAllSuppliers && ($requestedSupplier === 'all' || (int) $requestedSupplier !== $userSupplierId)) {
            abort(403, 'You do not have permission to filter by this company.');
        }

        $supplierId = $canViewAllSuppliers
            ? ($requestedSupplier !== null && $requestedSupplier !== 'all' ? (int) $requestedSupplier : null)
            : $userSupplierId;
        $hasNoScope = ! $canViewAllSuppliers && ! $supplierId;
        $isApplied = $request->has('filter') && (! $canViewAllSuppliers || $requestedSupplier !== null);

        if (isset($filters['employee_id']) && ! $canViewAllSuppliers
            && ! Employee::query()->whereKey($filters['employee_id'])->where('supplier_id', $supplierId)->exists()) {
            abort(403, 'You do not have permission to filter by this salesman.');
        }

        $perPage = (string) $request->input('per_page', '50');
        $perPage = in_array($perPage, array_map('strval', self::PER_PAGE), true) ? $perPage : '50';
        $sort = (string) $request->input('sort', '-created_at');
        $sortColumns = ['cheque_date' => 'sales_settlement_cheques.cheque_date', 'created_at' => 'sales_settlement_cheques.created_at', 'amount' => 'sales_settlement_cheques.amount', 'cleared_date' => 'sales_settlement_cheques.cleared_date'];
        $sort = array_key_exists(ltrim($sort, '-'), $sortColumns) ? $sort : '-created_at';
        $sortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $status = in_array($filters['status'] ?? null, self::STATUSES, true) ? $filters['status'] : null;

        /** Cheques of the companies this user may see. */
        $scoped = fn (): Builder => SalesSettlementCheque::query()
            ->join('sales_settlements', 'sales_settlements.id', '=', 'sales_settlement_cheques.sales_settlement_id')
            ->when($supplierId, fn (Builder $query) => $query->where('sales_settlements.supplier_id', $supplierId))
            ->when($hasNoScope, fn (Builder $query) => $query->whereRaw('1 = 0'));

        $suppliers = Supplier::query()
            ->when(! $canViewAllSuppliers, fn ($query) => $query->whereKey($userSupplierId ?? 0))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name', 'short_name']);
        $employees = Employee::query()
            ->where('is_active', true)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($hasNoScope, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $customers = Customer::query()
            ->whereIn('id', $scoped()->whereNotNull('sales_settlement_cheques.customer_id')->distinct()->select('sales_settlement_cheques.customer_id'))
            ->orderBy('customer_name')
            ->get(['id', 'customer_name']);
        $bankNames = $scoped()->whereNotNull('sales_settlement_cheques.bank_name')->distinct()
            ->orderBy('sales_settlement_cheques.bank_name')->pluck('sales_settlement_cheques.bank_name');
        $depositBanks = BankAccount::where('is_active', true)->whereNotNull('chart_of_account_id')->orderBy('account_name')->get(['id', 'account_name', 'account_number']);

        $shared = [
            'suppliers' => $suppliers,
            'employees' => $employees,
            'customers' => $customers,
            'bankNames' => $bankNames,
            'depositBanks' => $depositBanks,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'selectedSupplier' => $requestedSupplier ?? ($canViewAllSuppliers ? null : (string) $userSupplierId),
            'canViewAllSuppliers' => $canViewAllSuppliers,
            'isApplied' => $isApplied,
        ];

        if (! $isApplied) {
            if ($request->filled('export') || $request->query('format') === 'pdf') {
                return redirect()->route('reports.cheque-register.index')->with('error', 'Choose a company and filters, then Apply.');
            }

            return view('reports.cheque-register.index', $shared + ['cheques' => null, 'stats' => null, 'totalAmount' => 0]);
        }

        $filtered = fn (): Builder => $this->applyFilters($scoped(), $filters);

        $list = $filtered()
            ->join('suppliers', 'suppliers.id', '=', 'sales_settlements.supplier_id')
            ->leftJoin('employees', 'employees.id', '=', 'sales_settlements.employee_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_settlement_cheques.customer_id')
            ->leftJoin('bank_accounts as deposit_banks', 'deposit_banks.id', '=', 'sales_settlement_cheques.cleared_bank_account_id')
            ->select([
                'sales_settlement_cheques.*',
                'sales_settlements.settlement_number',
                'sales_settlements.supplier_id',
                'suppliers.supplier_name',
                'suppliers.short_name as supplier_short_name',
                'employees.name as employee_name',
                'customers.customer_name',
                'customers.address as customer_address',
                'deposit_banks.account_name as deposit_bank_name',
            ])
            ->when($status, fn (Builder $query) => $query->where('sales_settlement_cheques.status', $status))
            ->orderBy($sortColumns[ltrim($sort, '-')], $sortDirection)
            ->orderBy('sales_settlement_cheques.id', $sortDirection);

        if ($request->input('export') === 'xlsx') {
            return Excel::download(new ChequeRegisterExport($list), 'cheque-register-'.now()->toDateString().'.xlsx');
        }

        if ($request->query('format') === 'pdf') {
            return Pdf::loadView('reports.cheque-register.pdf', [
                'cheques' => $list->get(),
                'companyName' => $supplierId ? $suppliers->firstWhere('id', $supplierId)?->supplier_name : 'All companies',
                'filters' => $filters,
            ])->setPaper('a4', 'landscape')->download('cheque-register-'.now()->toDateString().'.pdf');
        }

        $byStatus = $filtered()
            ->groupBy('sales_settlement_cheques.status')
            ->selectRaw('sales_settlement_cheques.status as status, COUNT(*) as cheques, COALESCE(SUM(sales_settlement_cheques.amount), 0) as amount')
            ->get()
            ->keyBy('status');
        $stats = ['all' => ['count' => (int) $byStatus->sum('cheques'), 'amount' => round((float) $byStatus->sum('amount'), 2)]];
        foreach (self::STATUSES as $each) {
            $stats[$each] = ['count' => (int) ($byStatus[$each]->cheques ?? 0), 'amount' => round((float) ($byStatus[$each]->amount ?? 0), 2)];
        }

        return view('reports.cheque-register.index', $shared + [
            'cheques' => $list->paginate((int) $perPage)->withQueryString(),
            'stats' => $stats,
            'totalAmount' => $stats[$status ?? 'all']['amount'],
        ]);
    }

    /**
     * Every register filter except the company (already scoped) and the status (the tabs).
     *
     * @param  Builder<SalesSettlementCheque>  $query
     * @param  array<string, string>  $filters
     * @return Builder<SalesSettlementCheque>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $column = fn (string $name) => 'sales_settlement_cheques.'.$name;
        $like = fn (string $value) => '%'.mb_strtolower(trim($value)).'%';
        $value = fn (string $key) => $filters[$key] ?? null;

        return $query
            ->when($value('employee_id'), fn (Builder $q, $id) => $q->where('sales_settlements.employee_id', $id))
            ->when($value('customer_id'), fn (Builder $q, $id) => $q->where($column('customer_id'), $id))
            ->when($value('cheque_date_from'), fn (Builder $q, $date) => $q->whereDate($column('cheque_date'), '>=', $date))
            ->when($value('cheque_date_to'), fn (Builder $q, $date) => $q->whereDate($column('cheque_date'), '<=', $date))
            ->when($value('entry_date_from'), fn (Builder $q, $date) => $q->whereDate($column('created_at'), '>=', $date))
            ->when($value('entry_date_to'), fn (Builder $q, $date) => $q->whereDate($column('created_at'), '<=', $date))
            ->when($value('cleared_date_from'), fn (Builder $q, $date) => $q->whereDate($column('cleared_date'), '>=', $date))
            ->when($value('cleared_date_to'), fn (Builder $q, $date) => $q->whereDate($column('cleared_date'), '<=', $date))
            ->when(in_array($value('cleared_via'), [ChequeClearingPostingService::VIA_CASH, ChequeClearingPostingService::VIA_BANK], true),
                fn (Builder $q) => $q->where($column('status'), 'cleared')->where($column('cleared_via'), $filters['cleared_via']))
            ->when($value('deposit_bank_id'), fn (Builder $q, $id) => $q->where($column('cleared_bank_account_id'), $id))
            ->when(is_numeric($value('amount_min')), fn (Builder $q) => $q->where($column('amount'), '>=', (float) $filters['amount_min']))
            ->when(is_numeric($value('amount_max')), fn (Builder $q) => $q->where($column('amount'), '<=', (float) $filters['amount_max']))
            ->when($value('bank_name'), fn (Builder $q, $bank) => $q->where($column('bank_name'), $bank))
            ->when($value('cheque_number'), fn (Builder $q, $number) => $q->whereRaw('LOWER(sales_settlement_cheques.cheque_number) LIKE ?', [$like($number)]))
            ->when($value('settlement_number'), fn (Builder $q, $number) => $q->whereRaw('LOWER(sales_settlements.settlement_number) LIKE ?', [$like($number)]));
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
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('is_active', true)],
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
            : DB::transaction(function () use ($cheque, $clearing, $validated, $user) {
                $bankCharge = isset($validated['bank_charge']) ? (float) $validated['bank_charge'] : null;

                // A bank charge on a bounce is credited to the deposit bank; the bounce dialog can name it.
                if ($validated['status'] === 'bounced' && $bankCharge > 0 && ! empty($validated['bank_account_id'])) {
                    $cheque->update(['bank_account_id' => (int) $validated['bank_account_id']]);
                }

                $clearing->changeStatus($cheque->fresh(), $validated['status'], $validated['status_date'] ?? null,
                    $bankCharge, $validated['notes'] ?? null, $user);
            }));

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

        return $user->hasAllSupplierAccess() || $user->can('report-audit-cheque-register-all-companies');
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
