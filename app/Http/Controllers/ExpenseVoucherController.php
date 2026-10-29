<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\ExpenseVoucher;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExpenseVoucherAttachmentService;
use App\Services\ExpenseVoucherService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExpenseVoucherController extends Controller implements HasMiddleware
{
    public function __construct(private ExpenseVoucherService $service, private ExpenseVoucherAttachmentService $attachments) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:expense-voucher-list', only: ['index', 'show', 'attachment']),
            new Middleware('can:expense-voucher-create', only: ['create', 'store', 'submit']),
            new Middleware('can:expense-voucher-edit', only: ['edit', 'update']),
            new Middleware('can:expense-voucher-delete', only: ['destroy']),
            new Middleware('can:expense-voucher-approve', only: ['approve', 'reject']),
            new Middleware('can:expense-voucher-print', only: ['print']),
        ];
    }

    public const PER_PAGE = ['15', '25', '50', '100', 'all'];

    public const SORTS = ['voucher_date' => 'voucher_date', 'voucher_number' => 'voucher_number', 'amount' => 'amount', 'created_at' => 'created_at'];

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');
        $today = now()->toDateString();
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? $today;

        $sort = (string) $request->input('sort', '-voucher_date');
        $sortField = self::SORTS[ltrim($sort, '-')] ?? 'voucher_date';
        $sortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $perPage = in_array((string) $request->input('per_page', '25'), self::PER_PAGE, true) ? (string) $request->input('per_page', '25') : '25';

        $scoped = ExpenseVoucher::query()
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->whereDate('voucher_date', '>=', $dateFrom)
            ->whereDate('voucher_date', '<=', $dateTo)
            ->when($filters['owner'] ?? null, function ($query, $owner) {
                [$type, $id] = array_pad(explode(':', $owner, 2), 2, null);

                return $type === 'company' ? $query->where('company_id', $id) : $query->where('supplier_id', $id);
            })
            ->when($filters['expense_account_id'] ?? null, fn ($query, $id) => $query->where('expense_account_id', $id))
            ->when($filters['party_name'] ?? null, fn ($query, $party) => $query->whereRaw('LOWER(party_name) LIKE ?', ['%'.mb_strtolower($party).'%']))
            ->when($filters['created_by'] ?? null, fn ($query, $id) => $query->where('created_by', $id))
            ->when($filters['checked_by'] ?? null, fn ($query, $id) => $query->where('checked_by', $id))
            ->when($filters['amount_min'] ?? null, fn ($query, $amount) => $query->where('amount', '>=', $amount))
            ->when($filters['amount_max'] ?? null, fn ($query, $amount) => $query->where('amount', '<=', $amount))
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q
                ->where('party_name', 'like', "%{$term}%")
                ->orWhere('voucher_number', 'like', "%{$term}%")
                ->orWhere('narration', 'like', "%{$term}%")));

        $statusCounts = (clone $scoped)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $query = (clone $scoped)
            ->with(['supplier:id,supplier_name,short_name', 'company:id,company_name,abbr', 'expenseAccount:id,account_code,account_name', 'creator:id,name', 'checker:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        $totalAmount = (float) (clone $query)->sum('amount');
        $vouchers = $query->orderBy($sortField, $sortDirection)->orderByDesc('id')
            ->paginate($perPage === 'all' ? max($query->count(), 1) : (int) $perPage)
            ->withQueryString();

        $visible = ExpenseVoucher::query()->when(! $user->hasAllSupplierAccess(), fn ($q) => $q->where('supplier_id', $user->supplier_id ?? 0));

        return view('expense-vouchers.index', [
            'vouchers' => $vouchers,
            'suppliers' => $this->suppliersFor($request),
            'companies' => $this->companiesFor($request),
            'accounts' => $this->expenseAccounts(),
            'parties' => (clone $visible)->distinct()->orderBy('party_name')->pluck('party_name'),
            'people' => User::query()->whereIn('id', (clone $visible)->select('created_by')->union((clone $visible)->select('checked_by')))->orderBy('name')->get(['id', 'name']),
            'statuses' => ExpenseVoucher::statusLabels(),
            'statusCounts' => $statusCounts,
            'filters' => $filters,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sort' => $sort,
            'perPage' => $perPage,
            'totalAmount' => $totalAmount,
        ]);
    }

    /**
     * The guide, English and Urdu (right to left) side by side.
     */
    public function attachment(Request $request, ExpenseVoucher $expenseVoucher)
    {
        $this->authorizeCompany($request, $expenseVoucher);

        abort_unless(ExpenseVoucherAttachmentService::enabled() && $this->attachments->exists($expenseVoucher->attachment), 404);

        return $this->attachments->response($expenseVoucher->attachment);
    }

    public function help(Request $request)
    {
        abort_unless($request->user()->canAny(['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-approve', 'expense-voucher-print']), 403);

        return view('expense-vouchers.help');
    }

    public function create(Request $request)
    {
        return view('expense-vouchers.create', $this->formData($request) + ['voucher' => new ExpenseVoucher(['voucher_date' => now()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['attachment'] = $request->file('attachment') ? $this->attachments->store($request->file('attachment'), Carbon::parse($data['voucher_date'])) : null;

        $voucher = $this->service->create($data);

        if ($request->input('action') === 'draft') {
            return redirect()->route('expense-vouchers.show', $voucher)->with('success', 'Voucher '.$voucher->voucher_number.' saved as a draft.');
        }

        return $this->attempt(fn () => $this->service->submit($voucher, $request->user()), $voucher, 'Voucher '.$voucher->voucher_number.' submitted for approval.');
    }

    public function show(Request $request, ExpenseVoucher $expenseVoucher)
    {
        $this->authorizeCompany($request, $expenseVoucher);

        return view('expense-vouchers.show', [
            'voucher' => $expenseVoucher->load(['supplier', 'company', 'expenseAccount', 'creator', 'submitter', 'checker', 'journalEntry.details.account']),
        ]);
    }

    public function edit(Request $request, ExpenseVoucher $expenseVoucher)
    {
        $this->authorizeCompany($request, $expenseVoucher);
        abort_unless($expenseVoucher->isEditable(), 403, 'Only a draft or rejected voucher can be changed.');

        return view('expense-vouchers.edit', $this->formData($request) + ['voucher' => $expenseVoucher]);
    }

    public function update(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);

        $data = $this->validated($request);
        $oldAttachment = $expenseVoucher->attachment;

        if ($request->file('attachment')) {
            $data['attachment'] = $this->attachments->store($request->file('attachment'), Carbon::parse($data['voucher_date']));
        } elseif ($request->boolean('remove_attachment')) {
            $data['attachment'] = null;
        }

        if (array_key_exists('attachment', $data) && $data['attachment'] !== $oldAttachment && $expenseVoucher->isEditable()) {
            $this->attachments->delete($oldAttachment);
        }

        if ($request->input('action') === 'draft') {
            return $this->attempt(fn () => $this->service->update($expenseVoucher, $data), $expenseVoucher, 'Voucher updated.');
        }

        return $this->attempt(function () use ($expenseVoucher, $data, $request) {
            $this->service->update($expenseVoucher, $data);
            $this->service->submit($expenseVoucher->fresh(), $request->user());
        }, $expenseVoucher, 'Voucher submitted for approval.');
    }

    public function destroy(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);
        abort_unless($expenseVoucher->isEditable(), 403, 'Only a draft or rejected voucher can be deleted.');

        $expenseVoucher->delete();

        return redirect()->route('expense-vouchers.index')->with('success', 'Voucher deleted.');
    }

    public function submit(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);

        return $this->attempt(fn () => $this->service->submit($expenseVoucher, $request->user()), $expenseVoucher, 'Voucher sent to the checker.');
    }

    public function approve(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);

        return $this->attempt(fn () => $this->service->approve($expenseVoucher, $request->user()), $expenseVoucher, 'Voucher approved and posted to the ledger.');
    }

    public function reject(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->reject($expenseVoucher, $request->user(), $validated['rejection_reason']), $expenseVoucher, 'Voucher rejected and returned to the maker.');
    }

    /**
     * 80mm thermal slip; only an approved voucher is printed.
     */
    public function print(Request $request, ExpenseVoucher $expenseVoucher)
    {
        $this->authorizeCompany($request, $expenseVoucher);
        abort_unless($expenseVoucher->isApproved(), 403, 'Only an approved voucher can be printed.');

        $expenseVoucher->load(['supplier', 'company', 'expenseAccount', 'creator', 'checker']);
        $expenseVoucher->forceFill(['printed_at' => $expenseVoucher->printed_at ?? now()])->saveQuietly();

        return view('expense-vouchers.print', ['voucher' => $expenseVoucher, 'autoprint' => $request->boolean('autoprint', true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $user = $request->user();

        $data = $request->validate([
            'voucher_date' => ['required', 'date'],
            'owner' => [$user->hasAllSupplierAccess() ? 'nullable' : 'required', 'string', 'max:30'],
            'party_name' => ['required', 'string', 'max:255'],
            'expense_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'narration' => ['nullable', 'string', 'max:1000'],
            'attachment' => ExpenseVoucherAttachmentService::rules(),
        ]);
        unset($data['attachment']);

        // "company:ID" is Moon Traders itself (companies table); "supplier:ID" is a distribution.
        // Left empty, an admin's voucher is Moon Traders' own.
        [$type, $id] = array_pad(explode(':', (string) ($data['owner'] ?? ''), 2), 2, null);
        unset($data['owner']);

        if ($type === 'supplier' && Supplier::whereKey($id)->exists()) {
            abort_if(! $user->hasAllSupplierAccess() && (int) $id !== (int) $user->supplier_id, 403, 'You can only raise vouchers for your own company.');

            return $data + ['supplier_id' => (int) $id, 'company_id' => null];
        }

        abort_unless($user->hasAllSupplierAccess(), 403, 'You can only raise vouchers for your own company.');

        $companyId = $type === 'company' && Company::whereKey($id)->exists() ? (int) $id : $this->moonTradersId();

        throw_if($companyId === null, ValidationException::withMessages(['owner' => 'No company is set up for Moon Traders.']));

        return $data + ['supplier_id' => null, 'company_id' => $companyId];
    }

    /**
     * Moon Traders itself: the first company of the companies table.
     */
    private function moonTradersId(): ?int
    {
        return Company::query()->orderBy('id')->value('id');
    }

    private function attempt(callable $action, ExpenseVoucher $voucher, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return redirect()->route('expense-vouchers.show', $voucher)->with('error', $e->getMessage());
        }

        return redirect()->route('expense-vouchers.show', $voucher)->with('success', $message);
    }

    private function authorizeCompany(Request $request, ExpenseVoucher $voucher): void
    {
        $user = $request->user();

        abort_unless($user->hasAllSupplierAccess() || (int) $user->supplier_id === (int) $voucher->supplier_id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        return [
            'suppliers' => $this->suppliersFor($request),
            'companies' => $this->companiesFor($request),
            'defaultOwner' => $this->moonTradersId() ? 'company:'.$this->moonTradersId() : '',
            'accounts' => $this->expenseAccounts(),
            'defaultAccountId' => ChartOfAccount::where('account_code', ExpenseVoucher::DEFAULT_ACCOUNT_CODE)->value('id'),
            'parties' => ExpenseVoucher::query()
                ->when(! $request->user()->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $request->user()->supplier_id ?? 0))
                ->distinct()->orderBy('party_name')->pluck('party_name'),
        ];
    }

    private function expenseAccounts()
    {
        return ChartOfAccount::query()
            ->where('account_code', 'like', '52%')
            ->where('account_code', '!=', '5200')
            ->orderBy('account_name')
            ->get(['id', 'account_code', 'account_name']);
    }

    /**
     * Moon Traders itself, offered to users who may book general expenses.
     */
    private function companiesFor(Request $request)
    {
        return $request->user()->hasAllSupplierAccess()
            ? Company::query()->orderBy('id')->limit(1)->get(['id', 'company_name', 'abbr'])
            : collect();
    }

    private function suppliersFor(Request $request)
    {
        $user = $request->user();

        return Supplier::query()
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('id', $user->supplier_id ?? 0))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name', 'short_name']);
    }
}
