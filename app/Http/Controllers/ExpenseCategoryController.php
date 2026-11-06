<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The list of expense categories offered on a voucher.
 */
class ExpenseCategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:expense-category-manage')];
    }

    public function index()
    {
        return view('expense-categories.index', [
            'categories' => ExpenseCategory::query()->withCount('vouchers')->with('defaultExpenseAccount:id,account_code,account_name')->orderBy('sort_order')->orderBy('name')->get(),
            'accounts' => ChartOfAccount::query()->where('account_code', 'like', '52%')->where('account_code', '!=', '5200')->orderBy('account_name')->get(['id', 'account_code', 'account_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name'],
            'default_expense_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
        ]);

        ExpenseCategory::create($data + ['sort_order' => 200, 'is_active' => true]);

        return back()->with('success', '"'.$data['name'].'" added.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name,'.$expenseCategory->id],
            'default_expense_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
        ]) + ['is_active' => $request->boolean('is_active')];

        $expenseCategory->update($data);

        return back()->with('success', '"'.$expenseCategory->name.'" updated.');
    }
}
