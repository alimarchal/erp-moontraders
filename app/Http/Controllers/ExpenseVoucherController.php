<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Reports\CashCollectionDistributorController;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVoucher;
use App\Models\ExpenseVoucherAttachment;
use App\Models\ExpenseVoucherLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Rules\ExpenseAccount;
use App\Services\ExpenseVoucherAttachmentService;
use App\Services\ExpenseVoucherReversalService;
use App\Services\ExpenseVoucherService;
use App\Support\CashNotes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExpenseVoucherController extends Controller implements HasMiddleware
{
    public function __construct(private ExpenseVoucherService $service, private ExpenseVoucherAttachmentService $attachments) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:expense-voucher-list', only: ['index', 'show', 'attachment', 'summary']),
            new Middleware('can:expense-voucher-create', only: ['create', 'store', 'submit', 'employeeSearch']),
            new Middleware('can:expense-voucher-edit', only: ['edit', 'update']),
            new Middleware('can:expense-voucher-delete', only: ['destroy']),
            new Middleware('can:expense-voucher-approve', only: ['approve', 'reject']),
            new Middleware('can:expense-voucher-print', only: ['print']),
            new Middleware('can:expense-voucher-reverse', only: ['reverse']),
        ];
    }

    public const PER_PAGE = ['15', '25', '50', '100', 'all'];

    /** The first day the list looks back to when it shows every date (the "All" quick range). */
    public const EARLIEST_DATE = '2020-01-01';

    /** @var array<string, string> */
    public const VALIDATION_MESSAGES = [
        'amount.max' => 'Amount is too large.',
        'amount.gt' => 'Amount must be more than zero.',
        'amount.decimal' => 'Amount can have at most two decimals (paisa).',
        'voucher_date.before_or_equal' => 'The voucher date cannot be in the future.',
        'bank_account_id.exists' => 'Choose an active bank account that is linked to the chart of accounts.',
        'denom.required' => 'Enter the notes paid out.',
        'attachments.max' => 'You can attach at most :max files.',
        'vendor_bill_date.before_or_equal' => 'The shop bill date cannot be in the future.',
        'lines.required' => 'Add at least one bill line.',
        'lines.min' => 'Add at least one bill line.',
        'lines.max' => 'A voucher can hold at most :max bill lines. Make a second voucher for the rest.',
        'lines.*.amount.required' => 'Enter the amount.',
        'lines.*.amount.gt' => 'Amount must be more than zero.',
        'lines.*.amount.decimal' => 'At most two decimals (paisa).',
        'lines.*.amount.max' => 'Amount is too large.',
        'lines.*.expense_category_id.exists' => 'Choose an active category.',
    ];

    /** @var array<string, string> */
    public const VALIDATION_ATTRIBUTES = [
        'voucher_date' => 'voucher date',
        'owner' => 'company',
        'payee_type' => 'payee',
        'payment_method' => 'payment method',
        'bank_account_id' => 'bank account',
        'cheque_number' => 'cheque number',
        'cheque_date' => 'cheque date',
        'transaction_reference' => 'reference',
        'denom' => 'notes paid out',
        'denom.*' => 'note count',
        'denom.coins' => 'coins',
        'expense_vendor_id' => 'shop',
        'employee_id' => 'employee',
        'vehicle_id' => 'vehicle',
        'party_name' => 'paid to',
        'vendor_bill_no' => 'shop bill no.',
        'vendor_bill_date' => 'shop bill date',
        'lines' => 'bill lines',
        'lines.*.expense_category_id' => 'category',
        'lines.*.expense_account_id' => 'expense account',
        'lines.*.vehicle_id' => 'vehicle',
        'lines.*.description' => 'description',
        'lines.*.amount' => 'amount',
        'expense_category_id' => 'category',
        'expense_account_id' => 'expense account',
        'advance_installment' => 'monthly instalment',
        'attachments' => 'attachments',
        'attachments.*' => 'attachment',
    ];

    public const SORTS = ['voucher_date' => 'voucher_date', 'voucher_number' => 'voucher_number', 'amount' => 'amount', 'created_at' => 'created_at'];

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');
        $today = now()->toDateString();

        // A bare visit opens on the vouchers waiting for approval, whatever their date; "all" shows every status.
        $opensOnPending = $filters === [];
        if ($opensOnPending) {
            $filters = ['status' => ExpenseVoucher::STATUS_PENDING];
        }
        // A status tab clicked without dates keeps "all dates", so an old pending voucher does not vanish from its tab.
        $allDates = array_diff(array_keys($filters), ['status']) === [];
        $dateFrom = $filters['date_from'] ?? ($allDates ? self::EARLIEST_DATE : now()->startOfMonth()->toDateString());
        $dateTo = $filters['date_to'] ?? $today;

        $sort = (string) $request->input('sort', '-voucher_date');
        $sortField = self::SORTS[ltrim($sort, '-')] ?? 'voucher_date';
        $sortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $perPage = in_array((string) $request->input('per_page', '25'), self::PER_PAGE, true) ? (string) $request->input('per_page', '25') : '25';

        $scoped = ExpenseVoucher::query()
            ->visibleTo($user)
            ->whereDate('voucher_date', '>=', $dateFrom)
            ->whereDate('voucher_date', '<=', $dateTo)
            ->when($filters['owner'] ?? null, function ($query, $owner) {
                [$type, $id] = array_pad(explode(':', $owner, 2), 2, null);

                return $type === 'company' ? $query->where('company_id', $id) : $query->where('supplier_id', $id);
            })
            ->when($filters['expense_account_id'] ?? null, fn ($query, $id) => $query->where('expense_account_id', $id))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('expense_category_id', $id))
            ->when($filters['payment_type'] ?? null, fn ($query, $type) => $query->where('payment_type', $type))
            ->when($filters['payment_status'] ?? null, fn ($query, $status) => $query->where('payment_status', $status))
            ->when($filters['party_name'] ?? null, fn ($query, $party) => $query->whereRaw('LOWER(party_name) LIKE ?', ['%'.mb_strtolower($party).'%']))
            ->when($filters['created_by'] ?? null, fn ($query, $id) => $query->where('created_by', $id))
            ->when($filters['checked_by'] ?? null, fn ($query, $id) => $query->where('checked_by', $id))
            ->when($filters['amount_min'] ?? null, fn ($query, $amount) => $query->where('amount', '>=', $amount))
            ->when($filters['amount_max'] ?? null, fn ($query, $amount) => $query->where('amount', '<=', $amount))
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q
                ->whereLike('party_name', "%{$term}%")
                ->orWhereLike('voucher_number', "%{$term}%")
                ->orWhereLike('narration', "%{$term}%")));

        $statusCounts = (clone $scoped)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $query = (clone $scoped)
            ->with(['supplier:id,supplier_name,short_name', 'company:id,company_name,abbr', 'expenseAccount:id,account_code,account_name', 'creator:id,name', 'checker:id,name', 'attachments:id,expense_voucher_id', 'category:id,name'])
            ->withCount('lines')
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($q) => $q->where('status', $filters['status']));

        $totalAmount = (float) (clone $query)->sum('amount');
        $vouchers = $query->orderBy($sortField, $sortDirection)->orderByDesc('id')
            ->paginate($perPage === 'all' ? max($query->count(), 1) : (int) $perPage)
            ->withQueryString();

        $visible = ExpenseVoucher::query()->visibleTo($user);

        return view('expense-vouchers.index', [
            'vouchers' => $vouchers,
            'suppliers' => $this->suppliersFor($request),
            'companies' => $this->companiesFor($request),
            'accounts' => $this->expenseAccounts(),
            'parties' => (clone $visible)->distinct()->orderBy('party_name')->pluck('party_name'),
            'people' => User::query()->whereIn('id', (clone $visible)->select('created_by')->union((clone $visible)->select('checked_by')))->orderBy('name')->get(['id', 'name']),
            'statuses' => ExpenseVoucher::statusLabels(),
            'categories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name']),
            'statusCounts' => $statusCounts,
            'filters' => $filters,
            'opensOnPending' => $opensOnPending,
            'allDates' => $allDates,
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
    public function attachment(Request $request, ExpenseVoucher $expenseVoucher, ExpenseVoucherAttachment $attachment)
    {
        $this->authorizeCompany($request, $expenseVoucher);

        abort_unless($attachment->expense_voucher_id === $expenseVoucher->id && ExpenseVoucherAttachmentService::enabled() && $this->attachments->exists($attachment->file_path), 404);

        return $this->attachments->response($attachment->file_path);
    }

    /**
     * Employees for the advance-salary dropdown, loaded as the maker types: a company's maker only gets
     * their own company's staff.
     */
    public function employeeSearch(Request $request): JsonResponse
    {
        $user = $request->user();
        $term = trim((string) $request->query('q', ''));

        $employees = Employee::query()
            ->where('is_active', true)
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('employee_code', "%{$term}%")))
            ->with('supplier:id,supplier_name,short_name')
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'employee_code', 'supplier_id']);

        return response()->json(['results' => $employees->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'text' => self::employeeLabel($employee),
        ])->values()]);
    }

    /**
     * "Name · Company", so two employees of the same name in different companies are told apart.
     */
    public static function employeeLabel(Employee $employee): string
    {
        $company = $employee->supplier?->short_name ?: $employee->supplier?->supplier_name;

        return $employee->name.($company ? ' · '.$company : '');
    }

    /**
     * Approved expenses of a period by company, category and shop, so every cost is tracked back to
     * where it belongs. Credit shows what is still unpaid.
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $from = $request->date('date_from') ?? now()->startOfMonth();
        $to = $request->date('date_to') ?? now();
        $owner = (string) $request->input('owner', '');

        $base = ExpenseVoucher::query()
            ->visibleTo($user)
            ->where('status', ExpenseVoucher::STATUS_APPROVED)
            ->whereDate('voucher_date', '>=', $from->toDateString())
            ->whereDate('voucher_date', '<=', $to->toDateString())
            ->when($owner !== '', function ($query) use ($owner) {
                [$type, $id] = array_pad(explode(':', $owner, 2), 2, null);

                return $type === 'company' ? $query->where('company_id', $id) : $query->where('supplier_id', $id);
            });

        $totals = 'COUNT(*) as vouchers, SUM(amount) as total, SUM(CASE WHEN payment_type = \'credit\' THEN amount - paid_amount ELSE 0 END) as owed';

        return view('expense-vouchers.summary', [
            'from' => $from,
            'to' => $to,
            'owner' => $owner,
            'suppliers' => $this->suppliersFor($request),
            'companies' => $this->companiesFor($request),
            'byOwner' => (clone $base)->selectRaw("supplier_id, company_id, {$totals}")->groupBy('supplier_id', 'company_id')->orderByDesc('total')->get()
                ->each(fn ($row) => $row->label = $row->supplier_id ? Supplier::find($row->supplier_id)?->supplier_name : (Company::find($row->company_id)?->company_name ?? 'Moon Traders').' (general)'),
            'byCategory' => $this->totalsByLineCategory($base),
            'byVendor' => (clone $base)->whereNotNull('expense_vendor_id')->selectRaw("expense_vendor_id, {$totals}")->groupBy('expense_vendor_id')->orderByDesc('total')->limit(50)->get()
                ->each(fn ($row) => $row->label = ExpenseVendor::withTrashed()->find($row->expense_vendor_id)?->name),
            'grand' => (clone $base)->selectRaw($totals)->first(),
        ]);
    }

    /**
     * Totals by category from the bill lines, so a voucher of toner and a repair counts each under its own category.
     * Vouchers counts each voucher once per category. What is still owed on a credit voucher is shared over its
     * lines by amount, so the column adds up to the voucher's own balance. Advances (no category) get their own
     * row; a voucher saved without lines counts with its own category and amount.
     *
     * @param  Builder<ExpenseVoucher>  $vouchers
     * @return Collection<int, object>
     */
    private function totalsByLineCategory(Builder $vouchers): Collection
    {
        $amount = 'COALESCE(l.amount, v.amount)';
        $advanceKey = -1;

        return DB::table('expense_vouchers as v')
            ->leftJoin('expense_voucher_lines as l', 'l.expense_voucher_id', '=', 'v.id')
            ->whereIn('v.id', (clone $vouchers)->select('expense_vouchers.id'))
            ->selectRaw("CASE WHEN v.payment_type = 'advance' THEN {$advanceKey} ELSE COALESCE(l.expense_category_id, v.expense_category_id) END as category_key")
            ->selectRaw('COUNT(DISTINCT v.id) as vouchers')
            ->selectRaw("SUM({$amount}) as total")
            ->selectRaw("SUM(CASE WHEN v.payment_type = 'credit' AND v.amount > 0 THEN {$amount} * (v.amount - v.paid_amount) / v.amount ELSE 0 END) as owed")
            ->groupBy('category_key')
            ->orderByDesc('total')
            ->get()
            ->each(function ($row) use ($advanceKey) {
                $row->owed = round((float) $row->owed, 2);
                $row->label = match (true) {
                    (int) $row->category_key === $advanceKey => 'Employee advance',
                    $row->category_key === null => 'No category (older vouchers)',
                    default => ExpenseCategory::find($row->category_key)?->name,
                };
            });
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

        // The pictures are shrunk and stored first, outside the transaction; inside it only the rows are written. If the
        // save fails nothing is left behind, rows or files.
        $uploaded = $this->attachments->upload($request->file('attachments', []), Carbon::parse($data['voucher_date']));

        try {
            $voucher = DB::transaction(function () use ($data, $uploaded) {
                $voucher = $this->service->create($data);
                $this->attachments->record($voucher, $uploaded);

                return $voucher;
            });
        } catch (\Throwable $e) {
            $this->attachments->discard($uploaded);

            throw $e;
        }

        if ($request->input('action') === 'draft') {
            return redirect()->route('expense-vouchers.show', $voucher)->with('success', 'Voucher '.$voucher->voucher_number.' saved as a draft.');
        }

        return $this->attempt(fn () => $this->service->submit($voucher, $request->user()), $voucher, 'Voucher '.$voucher->voucher_number.' submitted for approval.');
    }

    public function show(Request $request, ExpenseVoucher $expenseVoucher)
    {
        $this->authorizeCompany($request, $expenseVoucher);

        $voucher = $expenseVoucher->load(['supplier', 'company', 'category', 'expenseVendor.defaultExpenseAccount', 'employee.supplier', 'employee.company', 'vehicle', 'attachments', 'expenseAccount', 'creator', 'submitter', 'checker', 'journalEntry.details.account', 'bankAccount.chartOfAccount', 'cashPayout', 'reverser']);

        return view('expense-vouchers.show', [
            'voucher' => $voucher,
            'companyName' => Company::query()->orderBy('id')->value('company_name') ?? config('app.name'),
            // Only the credit of vouchers this user may see, so a company user never sees another company's balance.
            'vendorOutstanding' => $voucher->expenseVendor
                ? round((float) ExpenseVoucher::query()->visibleTo($request->user())
                    ->where('expense_vendor_id', $voucher->expense_vendor_id)
                    ->where('status', ExpenseVoucher::STATUS_APPROVED)
                    ->where('payment_type', ExpenseVoucher::TYPE_CREDIT)
                    ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as due')
                    ->value('due'), 2)
                : null,
            'notesOnHand' => $this->notesOnHandFor($request, $voucher),
            'vendorHistory' => $voucher->expenseVendor
                ? ExpenseVoucher::query()->visibleTo($request->user())->where('expense_vendor_id', $voucher->expense_vendor_id)->whereKeyNot($voucher->id)
                    ->latest('voucher_date')->latest('id')->limit(5)->get(['id', 'uuid', 'voucher_number', 'voucher_date', 'amount', 'status'])
                : collect(),
            'advanceBalance' => $voucher->employee
                ? round((float) $voucher->employee->advances()->where('status', EmployeeAdvance::STATUS_OPEN)->selectRaw('COALESCE(SUM(amount - recovered_amount), 0) as due')->value('due'), 2)
                : null,
        ]);
    }

    public function edit(Request $request, ExpenseVoucher $expenseVoucher)
    {
        $this->authorizeCompany($request, $expenseVoucher);
        $this->authorizeMaker($request, $expenseVoucher);
        abort_unless($expenseVoucher->isEditable(), 403, 'Only a draft or rejected voucher can be changed.');

        return view('expense-vouchers.edit', $this->formData($request) + ['voucher' => $expenseVoucher]);
    }

    public function update(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);
        $this->authorizeMaker($request, $expenseVoucher);

        $data = $this->validated($request);
        $submit = $request->input('action') !== 'draft';
        $remove = array_map('intval', (array) $request->input('remove_attachments', []));
        $removedPaths = [];
        $uploaded = $this->attachments->upload($request->file('attachments', []), Carbon::parse($data['voucher_date']));

        try {
            $redirect = $this->attempt(function () use ($expenseVoucher, $data, $request, $submit, $remove, $uploaded, &$removedPaths) {
                DB::transaction(function () use ($expenseVoucher, $data, $request, $submit, $remove, $uploaded, &$removedPaths) {
                    $this->service->update($expenseVoucher, $data);

                    foreach ($expenseVoucher->attachments()->whereIn('id', $remove)->get() as $file) {
                        $removedPaths[] = $file->file_path;
                        $file->delete();
                    }

                    $this->attachments->record($expenseVoucher, $uploaded);

                    if ($submit) {
                        $this->service->submit($expenseVoucher->fresh(), $request->user());
                    }
                });
            }, $expenseVoucher, $submit ? 'Voucher submitted for approval.' : 'Voucher updated.');
        } catch (\Throwable $e) {
            $this->attachments->discard($uploaded);

            throw $e;
        }

        // Files go only after the database change is safely saved; files stored for a refused save are dropped.
        if (session()->has('error')) {
            $this->attachments->discard($uploaded);
        } else {
            foreach ($removedPaths as $path) {
                $this->attachments->delete($path);
            }
        }

        return $redirect;
    }

    public function destroy(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);
        $this->authorizeMaker($request, $expenseVoucher);
        abort_unless($expenseVoucher->isEditable(), 403, 'Only a draft or rejected voucher can be deleted.');

        $expenseVoucher->delete();

        return redirect()->route('expense-vouchers.index')->with('success', 'Voucher deleted.');
    }

    public function submit(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);
        $this->authorizeMaker($request, $expenseVoucher);

        return $this->attempt(fn () => $this->service->submit($expenseVoucher, $request->user()), $expenseVoucher, 'Voucher sent to the checker.');
    }

    public function approve(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);

        // The voucher's own state is checked before the notes, so the maker is told why they cannot approve.
        try {
            $this->service->ensurePendingFor($expenseVoucher, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->route('expense-vouchers.show', $expenseVoucher)->with('error', $e->getMessage());
        }

        $request->validate(
            ['approve_without_attachment' => [Rule::when($expenseVoucher->attachments()->doesntExist(), ['accepted'])]],
            ['approve_without_attachment.accepted' => 'No receipt or bill is attached. Tick the box to approve without one.'],
        );

        $notes = $this->approvalNotes($request, $expenseVoucher);

        $redirect = $this->attempt(fn () => $this->service->approve($expenseVoucher, $request->user(), $notes), $expenseVoucher, 'Voucher approved and posted to the ledger.');

        // Paying out more cash than the day brought in is allowed, but the checker is told. The voucher is
        // approved by now, so the day's figure already includes it.
        $voucher = $expenseVoucher->fresh();
        if (! session()->has('error') && $voucher->isApproved() && $voucher->payment_method === ExpenseVoucher::METHOD_CASH) {
            $date = $voucher->voucher_date->toDateString();
            $cashInHand = $this->service->cashInHandOn($date, $voucher->supplier_id);

            if ($cashInHand < 0) {
                $redirect->with('warning', 'Cash in hand for '.$date.' goes negative (Rs '.number_format($cashInHand, 2).').');
            }
        }

        return $redirect;
    }

    public function reject(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->reject($expenseVoucher, $request->user(), $validated['rejection_reason']), $expenseVoucher, 'Voucher rejected and returned to the maker.');
    }

    /**
     * Take back an approved voucher. Needs the permission, a reason and the user's own password.
     */
    public function reverse(Request $request, ExpenseVoucher $expenseVoucher): RedirectResponse
    {
        $this->authorizeCompany($request, $expenseVoucher);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            Log::warning("Failed expense voucher reversal attempt for {$expenseVoucher->voucher_number} - invalid password by user: ".$request->user()->name);

            return redirect()->route('expense-vouchers.show', $expenseVoucher)
                ->with('error', 'Invalid password. Reversing a voucher requires your password confirmation.');
        }

        return $this->attempt(
            fn () => app(ExpenseVoucherReversalService::class)->reverse($expenseVoucher, $request->user(), $validated['reason']),
            $expenseVoucher,
            'Voucher reversed.',
        );
    }

    /**
     * The A4 payment voucher by default; ?format=slip gives the 80mm thermal slip. Only an approved voucher
     * is printed. The first print is stamped, so every later print is marked as a duplicate.
     */
    public function print(Request $request, ExpenseVoucher $expenseVoucher)
    {
        $this->authorizeCompany($request, $expenseVoucher);
        abort_unless($expenseVoucher->isApproved() || $expenseVoucher->isReversed(), 403, 'Only an approved or reversed voucher can be printed.');

        $expenseVoucher->load(['supplier', 'company', 'category', 'expenseVendor', 'employee', 'expenseAccount', 'creator', 'submitter', 'checker', 'bankAccount', 'cashPayout', 'journalEntry.details.account', 'reverser']);
        $isDuplicate = $expenseVoucher->printed_at !== null;
        $expenseVoucher->forceFill(['printed_at' => $expenseVoucher->printed_at ?? now()])->saveQuietly();

        $view = $request->query('format') === 'slip' ? 'expense-vouchers.print' : 'expense-vouchers.print-a4';

        return view($view, [
            'voucher' => $expenseVoucher,
            'autoprint' => $request->boolean('autoprint', true),
            'isDuplicate' => $isDuplicate,
            'companyName' => Company::query()->orderBy('id')->value('company_name') ?? config('app.name'),
            'paidFromAccount' => $this->paidFromAccount($expenseVoucher),
        ]);
    }

    /**
     * For the checker of a pending cash voucher: the notes on hand for its company and day before this voucher, as the
     * cash collection report nets them, so the approve dialog can warn (never block) when a note runs short.
     *
     * @return array{counts: array<int, int>, company: string, date: string}|null
     */
    private function notesOnHandFor(Request $request, ExpenseVoucher $voucher): ?array
    {
        if ($voucher->status !== ExpenseVoucher::STATUS_PENDING || $voucher->payment_method !== ExpenseVoucher::METHOD_CASH
            || (int) $voucher->created_by === $request->user()->id || ! $request->user()->can('expense-voucher-approve')) {
            return null;
        }

        $notes = app(CashCollectionDistributorController::class)->notesOnHand($voucher->supplier_id, $voucher->voucher_date->toDateString());

        return [
            'counts' => collect(CashNotes::NOTES)->mapWithKeys(fn (int $note) => [$note => $notes->count($note)])->all(),
            'company' => $voucher->supplier ? ($voucher->supplier->short_name ?: $voucher->supplier->supplier_name) : 'all companies',
            'date' => $voucher->voucher_date->format('j M'),
        ];
    }

    /**
     * The account the money left from. Vouchers approved before it was stored fall back to the method's account.
     */
    private function paidFromAccount(ExpenseVoucher $voucher): ?ChartOfAccount
    {
        $accountId = $voucher->paid_from_account_id
            ?? ($voucher->isPaidByBank() ? $voucher->bankAccount?->chart_of_account_id : null);

        if ($accountId) {
            return ChartOfAccount::find($accountId, ['id', 'account_code', 'account_name']);
        }

        $code = $voucher->isCredit() ? ExpenseVoucherService::PAYABLES_ACCOUNT : ExpenseVoucherService::CASH_ACCOUNT;

        return ChartOfAccount::query()->where('account_code', $code)->first(['id', 'account_code', 'account_name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $user = $request->user();

        $method = $request->input('payment_method');
        $byBank = in_array($method, [ExpenseVoucher::METHOD_BANK_TRANSFER, ExpenseVoucher::METHOD_CHEQUE], true);
        // An advance is always one amount to Employee Advances; any bill lines sent with it are ignored.
        $isAdvance = $request->input('payee_type') === ExpenseVoucher::PAYEE_EMPLOYEE;
        $hasLines = ! $isAdvance && $request->has('lines');

        $data = $request->validate([
            'voucher_date' => ['required', 'date', 'before_or_equal:today'],
            'owner' => [$user->hasAllSupplierAccess() || $request->input('payee_type') === ExpenseVoucher::PAYEE_EMPLOYEE ? 'nullable' : 'required', 'string', 'max:30'],
            'payee_type' => ['required', Rule::in([ExpenseVoucher::PAYEE_VENDOR, ExpenseVoucher::PAYEE_EMPLOYEE, ExpenseVoucher::PAYEE_OTHER])],
            'payment_method' => ['required', Rule::in(array_keys(ExpenseVoucher::paymentMethodLabels()))],
            'bank_account_id' => [$byBank ? 'required' : 'nullable', 'integer', Rule::exists('bank_accounts', 'id')->where('is_active', true)->whereNotNull('chart_of_account_id')],
            'cheque_number' => [$method === ExpenseVoucher::METHOD_CHEQUE ? 'required' : 'nullable', 'string', 'max:50'],
            'cheque_date' => [$method === ExpenseVoucher::METHOD_CHEQUE ? 'required' : 'nullable', 'date'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'expense_vendor_id' => ['nullable', 'integer', 'exists:expense_vendors,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'vendor_bill_no' => ['nullable', 'string', 'max:80'],
            'vendor_bill_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'expense_account_id' => ['nullable', new ExpenseAccount],
            'amount' => [$hasLines ? 'nullable' : 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999'],
            'advance_installment' => ['nullable', 'numeric', 'gt:0', 'lte:amount'],
            'narration' => ['nullable', 'string', 'max:1000'],
        ] + ($hasLines ? $this->lineRules() : []) + ExpenseVoucherAttachmentService::manyRules(), self::VALIDATION_MESSAGES, self::VALIDATION_ATTRIBUTES);
        unset($data['attachments']);
        $lines = $hasLines ? array_values($data['lines']) : null;
        unset($data['lines']);

        $data = $this->withPaymentDetails($data);

        $payee = $data['payee_type'];
        $data['advance_installment'] = $payee === ExpenseVoucher::PAYEE_EMPLOYEE ? ($data['advance_installment'] ?? null) : null;
        $data['vendor_bill_no'] = $payee === ExpenseVoucher::PAYEE_VENDOR ? ($data['vendor_bill_no'] ?? null) : null;
        $data['vendor_bill_date'] = $payee === ExpenseVoucher::PAYEE_VENDOR ? ($data['vendor_bill_date'] ?? null) : null;

        // A shop is the only payee that can be paid later; an employee always takes an advance.
        throw_if($data['payment_method'] === ExpenseVoucher::METHOD_CREDIT && $payee !== ExpenseVoucher::PAYEE_VENDOR,
            ValidationException::withMessages(['payment_method' => 'Only a shop (vendor) can be paid on credit. Choose cash, bank transfer or cheque.']));

        if ($payee === ExpenseVoucher::PAYEE_EMPLOYEE) {
            $data = $this->forEmployee($data, $user);

            return $data + ['lines' => [['expense_account_id' => $data['expense_account_id'], 'amount' => $data['amount']]]];
        }

        $data['payment_type'] = $data['payment_method'] === ExpenseVoucher::METHOD_CREDIT ? ExpenseVoucher::TYPE_CREDIT : ExpenseVoucher::TYPE_CASH;

        if ($payee === ExpenseVoucher::PAYEE_VENDOR) {
            $vendor = ExpenseVendor::find($data['expense_vendor_id'] ?? null);
            throw_if(! $vendor, ValidationException::withMessages(['expense_vendor_id' => 'Choose the shop this expense was paid to.']));
            $data = ['party_name' => $vendor->name, 'employee_id' => null] + $data;
        } else {
            throw_if(blank($data['party_name'] ?? null), ValidationException::withMessages(['party_name' => 'Write who was paid.']));
            $data['expense_vendor_id'] = null;
            $data['employee_id'] = null;
        }

        if ($lines !== null) {
            return $this->withOwner($this->withLines($data, $lines), $user);
        }

        throw_if(blank($data['expense_category_id'] ?? null), ValidationException::withMessages(['expense_category_id' => 'Choose what this expense is for (category).']));

        // The category's usual account fills the account when none was chosen.
        $data['expense_account_id'] ??= ExpenseCategory::find($data['expense_category_id'])?->default_expense_account_id;

        throw_if(blank($data['expense_account_id'] ?? null), ValidationException::withMessages(['expense_account_id' => 'Choose the expense account.']));
        throw_if(! ExpenseAccount::allows($data['expense_account_id']), ValidationException::withMessages(['expense_account_id' => 'The category\'s usual account is not an active expense account (code 52...). Choose the expense account.']));

        // The single-line form (and older callers) becomes a bill of one line.
        $data['lines'] = [[
            'expense_category_id' => $data['expense_category_id'],
            'expense_account_id' => (int) $data['expense_account_id'],
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'amount' => $data['amount'],
        ]];

        return $this->withOwner($data, $user);
    }

    /**
     * Rules of the bill lines: 1 to 30 lines, each with an amount above zero in rupees and paisa, an active expense
     * account (filled from the category when left empty) and an optional active category and vehicle.
     *
     * @return array<string, array<int, mixed>>
     */
    private function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:'.ExpenseVoucherLine::MAX_LINES],
            'lines.*' => ['array'],
            'lines.*.expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'lines.*.expense_account_id' => ['nullable', new ExpenseAccount],
            'lines.*.vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999'],
        ];
    }

    /**
     * Turns the validated bill lines into what is saved: each line's account defaults to its category's usual
     * account, the voucher's vehicle fills lines without one, and the voucher amount is always the server's own sum
     * of the lines (a total sent by the browser is ignored). The voucher keeps the first line's account and
     * category for older code and the reports.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function withLines(array $data, array $lines): array
    {
        $categories = ExpenseCategory::query()->whereIn('id', array_filter(array_column($lines, 'expense_category_id')))->pluck('default_expense_account_id', 'id');
        $errors = [];

        foreach ($lines as $index => $line) {
            $categoryId = filled($line['expense_category_id'] ?? null) ? (int) $line['expense_category_id'] : null;
            $accountId = filled($line['expense_account_id'] ?? null) ? (int) $line['expense_account_id'] : ($categoryId ? $categories[$categoryId] ?? null : null);

            if (! $accountId) {
                $errors["lines.{$index}.expense_account_id"] = 'Choose the expense account.';
            } elseif (! ExpenseAccount::allows($accountId)) {
                $errors["lines.{$index}.expense_account_id"] = 'The category\'s usual account is not an active expense account (code 52...). Choose the expense account.';
            }

            $lines[$index] = [
                'expense_category_id' => $categoryId,
                'expense_account_id' => $accountId,
                'vehicle_id' => filled($line['vehicle_id'] ?? null) ? (int) $line['vehicle_id'] : ($data['vehicle_id'] ?? null),
                'description' => filled($line['description'] ?? null) ? trim((string) $line['description']) : null,
                'amount' => round((float) $line['amount'], 2),
            ];
        }

        throw_if($errors !== [], ValidationException::withMessages($errors));

        return array_merge($data, [
            'lines' => $lines,
            'amount' => ExpenseVoucherService::totalOf($lines),
            'expense_account_id' => $lines[0]['expense_account_id'],
            'expense_category_id' => $lines[0]['expense_category_id'],
            'vehicle_id' => collect($lines)->pluck('vehicle_id')->filter()->first(),
        ]);
    }

    /**
     * One whole-number count per note value; coins are an amount.
     *
     * @return array<string, array<int, string>>
     */
    private function noteRules(): array
    {
        $rules = [];
        foreach (CashNotes::NOTES as $note) {
            $rules['denom.'.$note] = ['nullable', 'integer', 'min:0', 'max:1000000'];
        }

        return $rules;
    }

    /**
     * Keeps only the details of the chosen payment method, so a voucher switched from cheque to cash
     * does not keep a stale bank or cheque. The notes of a cash voucher are counted by the approver.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withPaymentDetails(array $data): array
    {
        $method = $data['payment_method'];
        $byBank = in_array($method, [ExpenseVoucher::METHOD_BANK_TRANSFER, ExpenseVoucher::METHOD_CHEQUE], true);

        $data['bank_account_id'] = $byBank ? (int) $data['bank_account_id'] : null;
        $data['cheque_number'] = $method === ExpenseVoucher::METHOD_CHEQUE ? $data['cheque_number'] : null;
        $data['cheque_date'] = $method === ExpenseVoucher::METHOD_CHEQUE ? $data['cheque_date'] : null;
        $data['transaction_reference'] = $byBank ? ($data['transaction_reference'] ?? null) : null;

        return $data;
    }

    /**
     * The notes the approver hands out for a cash voucher; they must add up to the voucher amount.
     * Other payment methods need none.
     */
    private function approvalNotes(Request $request, ExpenseVoucher $voucher): ?CashNotes
    {
        if ($voucher->payment_method !== ExpenseVoucher::METHOD_CASH) {
            return null;
        }

        $data = $request->validate([
            'denom' => ['required', 'array'],
            'denom.coins' => ['nullable', 'numeric', 'min:0'],
        ] + $this->noteRules(), self::VALIDATION_MESSAGES, self::VALIDATION_ATTRIBUTES);

        $notes = CashNotes::fromInput($data['denom']);
        $amount = round((float) $voucher->amount, 2);

        throw_if(abs($notes->total() - $amount) > 0.009, ValidationException::withMessages([
            'denom' => 'The notes paid out add up to Rs '.number_format($notes->total(), 2).' but the voucher is Rs '.number_format($amount, 2).'.',
        ]));

        return $notes;
    }

    /**
     * An advance belongs to the employee's own company and is booked to Employee Advances (1131).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function forEmployee(array $data, User $user): array
    {
        $employee = Employee::find($data['employee_id'] ?? null);
        throw_if(! $employee, ValidationException::withMessages(['employee_id' => 'Choose the employee who takes the advance.']));
        abort_if(! $user->hasAllSupplierAccess() && (int) $employee->supplier_id !== (int) $user->supplier_id, 403, 'You can only give an advance to an employee of your own company.');

        $accountId = ChartOfAccount::where('account_code', ExpenseVoucherService::ADVANCES_ACCOUNT)->value('id');
        throw_if(! $accountId, ValidationException::withMessages(['employee_id' => 'The Employee Advances account (1131) is missing.']));

        unset($data['owner']);

        return $data + [
            'party_name' => $employee->name,
            'payment_type' => ExpenseVoucher::TYPE_ADVANCE,
            'expense_vendor_id' => null,
            'expense_category_id' => null,
            'expense_account_id' => $accountId,
            'supplier_id' => $employee->supplier_id,
            'company_id' => $employee->supplier_id ? null : ($employee->company_id ?? $this->moonTradersId()),
        ];
    }

    /**
     * "company:ID" is Moon Traders itself (companies table); "supplier:ID" is a distribution.
     * Left empty, an admin's voucher is Moon Traders' own.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withOwner(array $data, User $user): array
    {
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
        } catch (QueryException $e) {
            // A database error is a RuntimeException too, but its SQL must never reach the user.
            report($e);

            return redirect()->route('expense-vouchers.show', $voucher)->with('error', ExpenseVoucherService::POSTING_FAILED);
        } catch (RuntimeException $e) {
            return redirect()->route('expense-vouchers.show', $voucher)->with('error', $e->getMessage());
        }

        return redirect()->route('expense-vouchers.show', $voucher)->with('success', $message);
    }

    /**
     * Only the user who made a voucher may change it, delete it or send it to the checker, so the
     * maker and the checker are always two different people.
     */
    private function authorizeMaker(Request $request, ExpenseVoucher $voucher): void
    {
        abort_unless((int) $voucher->created_by === $request->user()->id, 403, 'Only the user who made this voucher can change it or send it to the checker.');
    }

    private function authorizeCompany(Request $request, ExpenseVoucher $voucher): void
    {
        abort_unless($voucher->canBeSeenBy($request->user()), 404);
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
            'vehicles' => Vehicle::query()->where('is_active', true)->orderBy('vehicle_number')->get(['id', 'vehicle_number']),
            'vendorCategories' => ExpenseVendor::CATEGORIES,
            'categories' => ExpenseCategory::active()->get(['id', 'name', 'default_expense_account_id']),
            'bankAccounts' => BankAccount::query()->active()->whereNotNull('chart_of_account_id')->orderBy('bank_name')->orderBy('account_name')->get(['id', 'account_name', 'account_number', 'bank_name']),
            'paymentMethods' => ExpenseVoucher::paymentMethodLabels(),
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
