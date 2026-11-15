<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVoucher;
use App\Models\ExpenseVoucherAttachment;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Rules\ExpenseAccount;
use App\Services\ExpenseVoucherAttachmentService;
use App\Services\ExpenseVoucherReversalService;
use App\Services\ExpenseVoucherService;
use App\Support\CashNotes;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
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
        $dateFrom = $filters['date_from'] ?? ($opensOnPending ? self::EARLIEST_DATE : now()->startOfMonth()->toDateString());
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
            'byCategory' => (clone $base)->selectRaw("expense_category_id, {$totals}")->groupBy('expense_category_id')->orderByDesc('total')->get()
                ->each(fn ($row) => $row->label = $row->expense_category_id ? ExpenseCategory::find($row->expense_category_id)?->name : 'No category (advances, older vouchers)'),
            'byVendor' => (clone $base)->whereNotNull('expense_vendor_id')->selectRaw("expense_vendor_id, {$totals}")->groupBy('expense_vendor_id')->orderByDesc('total')->limit(50)->get()
                ->each(fn ($row) => $row->label = ExpenseVendor::withTrashed()->find($row->expense_vendor_id)?->name),
            'grand' => (clone $base)->selectRaw($totals)->first(),
        ]);
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
            'vendorOutstanding' => $voucher->expenseVendor?->outstanding(),
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
            'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'expense_account_id' => ['nullable', new ExpenseAccount],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999'],
            'advance_installment' => ['nullable', 'numeric', 'gt:0', 'lte:amount'],
            'narration' => ['nullable', 'string', 'max:1000'],
        ] + ExpenseVoucherAttachmentService::manyRules(), self::VALIDATION_MESSAGES, self::VALIDATION_ATTRIBUTES);
        unset($data['attachments']);

        $data = $this->withPaymentDetails($data);

        $payee = $data['payee_type'];
        $data['advance_installment'] = $payee === ExpenseVoucher::PAYEE_EMPLOYEE ? ($data['advance_installment'] ?? null) : null;
        $data['vendor_bill_no'] = $payee === ExpenseVoucher::PAYEE_VENDOR ? ($data['vendor_bill_no'] ?? null) : null;

        // A shop is the only payee that can be paid later; an employee always takes an advance.
        throw_if($data['payment_method'] === ExpenseVoucher::METHOD_CREDIT && $payee !== ExpenseVoucher::PAYEE_VENDOR,
            ValidationException::withMessages(['payment_method' => 'Only a shop (vendor) can be paid on credit. Choose cash, bank transfer or cheque.']));

        if ($payee === ExpenseVoucher::PAYEE_EMPLOYEE) {
            return $this->forEmployee($data, $user);
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

        throw_if(blank($data['expense_category_id'] ?? null), ValidationException::withMessages(['expense_category_id' => 'Choose what this expense is for (category).']));

        // The category's usual account fills the account when none was chosen.
        $data['expense_account_id'] ??= ExpenseCategory::find($data['expense_category_id'])?->default_expense_account_id;

        throw_if(blank($data['expense_account_id'] ?? null), ValidationException::withMessages(['expense_account_id' => 'Choose the expense account.']));
        throw_if(! ExpenseAccount::allows($data['expense_account_id']), ValidationException::withMessages(['expense_account_id' => 'The category\'s usual account is not an active expense account (code 52...). Choose the expense account.']));

        return $this->withOwner($data, $user);
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
