<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\ExpenseVoucher;
use App\Models\Supplier;
use App\Services\ExpenseVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use RuntimeException;

class ExpenseVoucherController extends Controller implements HasMiddleware
{
    public function __construct(private ExpenseVoucherService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:expense-voucher-list', only: ['index', 'show']),
            new Middleware('can:expense-voucher-create', only: ['create', 'store', 'submit']),
            new Middleware('can:expense-voucher-edit', only: ['edit', 'update']),
            new Middleware('can:expense-voucher-delete', only: ['destroy']),
            new Middleware('can:expense-voucher-approve', only: ['approve', 'reject']),
            new Middleware('can:expense-voucher-print', only: ['print']),
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->input('filter', []);

        $vouchers = ExpenseVoucher::query()
            ->with(['supplier:id,supplier_name,short_name', 'expenseAccount:id,account_name', 'creator:id,name'])
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('voucher_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('voucher_date', '<=', $date))
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q->where('party_name', 'like', "%{$term}%")->orWhere('voucher_number', 'like', "%{$term}%")))
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('expense-vouchers.index', [
            'vouchers' => $vouchers,
            'suppliers' => $this->suppliersFor($request),
            'statuses' => ExpenseVoucher::statusLabels(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        return view('expense-vouchers.create', $this->formData($request) + ['voucher' => new ExpenseVoucher(['voucher_date' => now()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $voucher = $this->service->create($this->validated($request));

        return redirect()->route('expense-vouchers.show', $voucher)->with('success', 'Voucher '.$voucher->voucher_number.' saved as a draft.');
    }

    public function show(Request $request, ExpenseVoucher $expenseVoucher)
    {
        $this->authorizeCompany($request, $expenseVoucher);

        return view('expense-vouchers.show', [
            'voucher' => $expenseVoucher->load(['supplier', 'expenseAccount', 'creator', 'submitter', 'checker', 'journalEntry']),
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

        return $this->attempt(fn () => $this->service->update($expenseVoucher, $this->validated($request)), $expenseVoucher, 'Voucher updated.');
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

        $expenseVoucher->load(['supplier', 'expenseAccount', 'creator', 'checker']);
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
            'supplier_id' => [$user->hasAllSupplierAccess() ? 'nullable' : 'required', 'exists:suppliers,id'],
            'party_name' => ['required', 'string', 'max:255'],
            'expense_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'narration' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if(! $user->hasAllSupplierAccess() && (int) $data['supplier_id'] !== (int) $user->supplier_id, 403, 'You can only raise vouchers for your own company.');

        $data['supplier_id'] = $data['supplier_id'] ?? null;

        return $data;
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
            'accounts' => ChartOfAccount::query()
                ->where('account_code', 'like', '52%')
                ->where('account_code', '!=', '5200')
                ->orderBy('account_name')
                ->get(['id', 'account_code', 'account_name']),
        ];
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
