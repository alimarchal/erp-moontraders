<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Company-wise advance tax, added up per customer.
 *
 * The Advance Tax Sales Register shows the same 1161 amounts date by date; this report
 * shows them customer by customer, with the customer details printed beside each total.
 */
class AdvanceTaxCustomerSummaryController extends Controller implements HasMiddleware
{
    /**
     * Account code of the Advance Tax line on a sales settlement.
     */
    private const ADVANCE_TAX_ACCOUNT_CODE = '1161';

    /**
     * Advance tax charged on sales, the same 2.5% the Advance Tax Sales Register uses.
     */
    private const ADVANCE_TAX_RATE = 0.025;

    /**
     * Per-customer advance tax entries live in one table for normal suppliers and in the
     * "income" table for suppliers flagged is_advance_tax_income. A supplier only ever
     * writes to one of them, so both are read and added together.
     *
     * @var array<int, string>
     */
    private const ENTRY_TABLES = ['sales_settlement_advance_taxes', 'sales_settlement_advance_tax_incomes'];

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-advance-tax-customer-summary'),
        ];
    }

    public function index(Request $request): View
    {
        $request->validate([
            'filter.supplier_id' => ['nullable', 'integer'],
            'filter.start_date' => ['nullable', 'date'],
            'filter.end_date' => ['nullable', 'date', 'after_or_equal:filter.start_date'],
        ]);

        $user = $request->user();
        $startDate = $request->input('filter.start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('filter.end_date', now()->endOfMonth()->format('Y-m-d'));
        $requestedSupplierId = $request->integer('filter.supplier_id') ?: null;

        if ($requestedSupplierId && ! $user->hasAllSupplierAccess() && $requestedSupplierId !== (int) $user->supplier_id) {
            abort(403, 'You do not have permission to view another company.');
        }

        $supplierId = $user->hasAllSupplierAccess()
            ? $requestedSupplierId
            : ($user->supplier_id ? (int) $user->supplier_id : null);

        $suppliers = Supplier::query()
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $user->supplier_id
                ? $query->whereKey($user->supplier_id)
                : $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);

        $selectedSupplier = $supplierId ? $suppliers->firstWhere('id', $supplierId) : null;
        $rows = collect();
        $benefitsPosted = 0.0;

        if ($selectedSupplier) {
            $rows = $this->customerRows($selectedSupplier->id, $startDate, $endDate);
            $benefitsPosted = $this->postedBenefits($selectedSupplier->id, $startDate, $endDate);
        }

        $totalBenefits = (float) $rows->sum('benefits');

        return view('reports.advance-tax-customer-summary.index', [
            'rows' => $rows,
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
            'selectedSupplierId' => $selectedSupplier?->id,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'hasFilter' => $selectedSupplier !== null,
            'totalEntries' => (int) $rows->sum('entries'),
            'totalSale' => (float) $rows->sum('sale'),
            'totalAdvanceTax' => (float) $rows->sum('advance_tax'),
            'totalBenefits' => $totalBenefits,
            'totalReceived' => (float) $rows->sum('received'),
            'benefitsPosted' => $benefitsPosted,
            'difference' => round($totalBenefits - $benefitsPosted, 2),
        ]);
    }

    /**
     * One row per customer, added up over the dates: entries, sale, advance tax on that sale (2.5%),
     * the advance tax benefit given (account 1161) and what was received from the customer.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function customerRows(int $supplierId, string $startDate, string $endDate): Collection
    {
        $totals = [];

        foreach (self::ENTRY_TABLES as $table) {
            $entries = DB::table("{$table} as entry")
                ->join('sales_settlements as settlement', 'settlement.id', '=', 'entry.sales_settlement_id')
                ->where('settlement.status', 'posted')
                ->whereNull('settlement.deleted_at')
                ->where('settlement.supplier_id', $supplierId)
                ->whereBetween('settlement.settlement_date', [$startDate, $endDate])
                ->groupBy('entry.customer_id')
                ->selectRaw('entry.customer_id, COUNT(*) as entries, SUM(entry.sale_amount) as sale_amount, SUM(entry.tax_amount) as tax_amount')
                ->get();

            foreach ($entries as $entry) {
                $totals[$entry->customer_id]['entries'] = ($totals[$entry->customer_id]['entries'] ?? 0) + (int) $entry->entries;
                $totals[$entry->customer_id]['sale_amount'] = ($totals[$entry->customer_id]['sale_amount'] ?? 0) + (float) $entry->sale_amount;
                $totals[$entry->customer_id]['tax_amount'] = ($totals[$entry->customer_id]['tax_amount'] ?? 0) + (float) $entry->tax_amount;
            }
        }

        $customers = Customer::query()->whereIn('id', array_keys($totals))->get()->keyBy('id');

        return collect($totals)
            ->map(fn (array $total, int $customerId) => [
                'customer_id' => $customerId,
                'customer' => $customers->get($customerId),
                'entries' => $total['entries'],
                'sale' => $total['sale_amount'],
                'advance_tax' => $total['sale_amount'] * self::ADVANCE_TAX_RATE,
                'benefits' => $total['tax_amount'],
                'received' => $total['sale_amount'] * self::ADVANCE_TAX_RATE - $total['tax_amount'],
            ])
            ->sortBy(fn (array $row) => mb_strtolower((string) $row['customer']?->customer_name))
            ->values();
    }

    /**
     * Advance Tax (1161) entered on the posted settlements of the company in the dates, the
     * same figure the Advance Tax Sales Register shows as "benefits".
     */
    private function postedBenefits(int $supplierId, string $startDate, string $endDate): float
    {
        return (float) DB::table('sales_settlement_expenses as expense')
            ->join('sales_settlements as settlement', 'settlement.id', '=', 'expense.sales_settlement_id')
            ->join('chart_of_accounts as account', 'account.id', '=', 'expense.expense_account_id')
            ->where('settlement.status', 'posted')
            ->whereNull('settlement.deleted_at')
            ->where('settlement.supplier_id', $supplierId)
            ->whereBetween('settlement.settlement_date', [$startDate, $endDate])
            ->where('account.account_code', self::ADVANCE_TAX_ACCOUNT_CODE)
            ->sum('expense.amount');
    }
}
