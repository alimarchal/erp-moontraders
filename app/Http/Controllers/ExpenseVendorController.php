<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * The shop catalogue. A voucher maker can search and add a shop while raising a voucher (JSON endpoints);
 * the full list, edit and delete screens need the expense-vendor permissions.
 */
class ExpenseVendorController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:expense-vendor-list', only: ['index', 'show']),
            new Middleware('can:expense-vendor-create', only: ['create']),
            new Middleware('can:expense-vendor-edit', only: ['edit', 'update']),
            new Middleware('can:expense-vendor-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');

        $vendors = ExpenseVendor::query()
            ->addSelect(['outstanding' => ExpenseVoucher::query()
                ->selectRaw('COALESCE(SUM(amount - paid_amount), 0)')
                ->whereColumn('expense_vendor_id', 'expense_vendors.id')
                ->where('status', ExpenseVoucher::STATUS_APPROVED)
                ->where('payment_type', ExpenseVoucher::TYPE_CREDIT)])
            ->withCount('vouchers')
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q
                ->whereLike('name', "%{$term}%")->orWhereLike('phone', "%{$term}%")->orWhereLike('city', "%{$term}%")->orWhereLike('contact_person', "%{$term}%")))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['owes'] ?? null, fn ($query) => $query->whereRaw('(SELECT COALESCE(SUM(amount - paid_amount), 0) FROM expense_vouchers WHERE expense_vouchers.expense_vendor_id = expense_vendors.id AND status = ? AND payment_type = ?) > 0', [ExpenseVoucher::STATUS_APPROVED, ExpenseVoucher::TYPE_CREDIT]))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('expense-vendors.index', ['vendors' => $vendors, 'filters' => $filters, 'categories' => ExpenseVendor::CATEGORIES]);
    }

    public function create()
    {
        return view('expense-vendors.create', $this->formData() + ['vendor' => new ExpenseVendor(['category' => 'shop', 'is_active' => true])]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->canAny(['expense-vendor-create', 'expense-voucher-create']), 403);

        $data = $this->validated($request);

        if (($duplicate = $this->duplicateOf($data, null)) && ! $request->boolean('confirm_duplicate')) {
            $message = 'A shop called "'.$duplicate->name.'"'.($duplicate->city ? ' in '.$duplicate->city : '').' already exists.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'duplicate' => $this->option($duplicate)], 409)
                : back()->withInput()->withErrors(['name' => $message.' Tick "Save anyway" if this is a different shop.']);
        }

        $vendor = ExpenseVendor::create($data + ['is_active' => true]);

        if ($request->expectsJson()) {
            return response()->json($this->option($vendor), 201);
        }

        return redirect()->route('expense-vendors.show', $vendor)->with('success', 'Shop "'.$vendor->name.'" added.');
    }

    public function show(ExpenseVendor $expenseVendor)
    {
        $vouchers = $expenseVendor->vouchers()
            ->with(['supplier:id,supplier_name,short_name', 'company:id,company_name,abbr'])
            ->orderByDesc('voucher_date')->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('expense-vendors.show', [
            'vendor' => $expenseVendor->load('defaultExpenseAccount'),
            'vouchers' => $vouchers,
            'outstanding' => $expenseVendor->outstanding(),
            'spentTotal' => (float) $expenseVendor->vouchers()->where('status', ExpenseVoucher::STATUS_APPROVED)->sum('amount'),
            'payments' => $expenseVendor->payments()->where('status', 'approved')->latest('payment_date')->limit(20)->get(),
        ]);
    }

    public function edit(ExpenseVendor $expenseVendor)
    {
        return view('expense-vendors.edit', $this->formData() + ['vendor' => $expenseVendor]);
    }

    public function update(Request $request, ExpenseVendor $expenseVendor): RedirectResponse
    {
        $data = $this->validated($request) + ['is_active' => $request->boolean('is_active')];

        if (! $request->boolean('confirm_duplicate') && ($duplicate = $this->duplicateOf($data, $expenseVendor->id))) {
            return back()->withInput()->withErrors(['name' => 'Another shop called "'.$duplicate->name.'" exists in this city. Tick "Save anyway" if this is a different shop.']);
        }

        $expenseVendor->update($data);

        return redirect()->route('expense-vendors.show', $expenseVendor)->with('success', 'Shop updated.');
    }

    public function destroy(ExpenseVendor $expenseVendor): RedirectResponse
    {
        if ($expenseVendor->vouchers()->exists()) {
            return back()->with('error', 'This shop has vouchers, so it cannot be deleted. Make it inactive instead.');
        }

        $expenseVendor->delete();

        return redirect()->route('expense-vendors.index')->with('success', 'Shop deleted.');
    }

    /**
     * Shops as the maker types (select2). Only the first matches are ever sent, so the voucher form
     * stays light however large the catalogue grows.
     */
    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['expense-vendor-list', 'expense-voucher-create', 'expense-vendor-payment-create']), 403);

        $term = trim((string) $request->query('q', ''));

        $vendors = ExpenseVendor::query()
            ->where('is_active', true)
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('phone', "%{$term}%")->orWhereLike('city', "%{$term}%")))
            ->when($request->boolean('owing'), fn ($query) => $query->whereHas('vouchers', fn ($q) => $q->where('status', ExpenseVoucher::STATUS_APPROVED)->where('payment_type', ExpenseVoucher::TYPE_CREDIT)->whereColumn('paid_amount', '<', 'amount')))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json(['results' => $vendors->map(fn (ExpenseVendor $vendor) => $this->option($vendor))->values()]);
    }

    /**
     * @return array{id: int, uuid: string, text: string, account_id: int|null}
     */
    private function option(ExpenseVendor $vendor): array
    {
        return [
            'id' => $vendor->id,
            'uuid' => $vendor->uuid,
            'text' => $vendor->name.($vendor->city ? ' - '.$vendor->city : '').($vendor->phone ? ' ('.$vendor->phone.')' : ''),
            'account_id' => $vendor->default_expense_account_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(ExpenseVendor::CATEGORIES))],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'default_expense_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function duplicateOf(array $data, ?int $ignoreId): ?ExpenseVendor
    {
        return ExpenseVendor::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])
            ->when(filled($data['city'] ?? null), fn ($query) => $query->whereRaw('LOWER(city) = ?', [mb_strtolower(trim($data['city']))]))
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'categories' => ExpenseVendor::CATEGORIES,
            'accounts' => ChartOfAccount::query()->where('account_code', 'like', '52%')->where('account_code', '!=', '5200')->orderBy('account_name')->get(['id', 'account_code', 'account_name']),
        ];
    }
}
