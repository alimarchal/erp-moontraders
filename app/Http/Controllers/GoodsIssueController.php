<?php

namespace App\Http\Controllers;

use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Warehouse;
use App\Models\Vehicle;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Uom;
use App\Http\Requests\StoreGoodsIssueRequest;
use App\Http\Requests\UpdateGoodsIssueRequest;
use App\Services\DistributionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class GoodsIssueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $goodsIssues = QueryBuilder::for(
            GoodsIssue::query()->with(['warehouse', 'vehicle', 'employee', 'issuedBy'])
        )
            ->allowedFilters([
                AllowedFilter::partial('issue_number'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('issue_date_from'),
                AllowedFilter::scope('issue_date_to'),
            ])
            ->defaultSort('-issue_date')
            ->paginate(20)
            ->withQueryString();

        return view('goods-issues.index', [
            'goodsIssues' => $goodsIssues,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('vehicle_number')->get(['id', 'vehicle_number', 'vehicle_type']),
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('goods-issues.create', [
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('vehicle_number')->get(['id', 'vehicle_number', 'vehicle_type']),
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_code']),
            'products' => Product::where('is_active', true)->orderBy('product_name')->get(['id', 'product_code', 'product_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGoodsIssueRequest $request)
    {
        DB::beginTransaction();

        try {
            // Generate issue number
            $issueNumber = $this->generateIssueNumber();

            // Calculate total value
            $totalValue = 0;
            foreach ($request->items as $item) {
                $totalValue += $item['quantity_issued'] * $item['unit_cost'];
            }

            // Create goods issue
            $goodsIssue = GoodsIssue::create([
                'issue_number' => $issueNumber,
                'issue_date' => $request->issue_date,
                'warehouse_id' => $request->warehouse_id,
                'vehicle_id' => $request->vehicle_id,
                'employee_id' => $request->employee_id,
                'issued_by' => auth()->id(),
                'status' => 'draft',
                'total_value' => $totalValue,
                'notes' => $request->notes,
            ]);

            // Create line items
            foreach ($request->items as $index => $item) {
                GoodsIssueItem::create([
                    'goods_issue_id' => $goodsIssue->id,
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'quantity_issued' => $item['quantity_issued'],
                    'unit_cost' => $item['unit_cost'],
                    'uom_id' => $item['uom_id'],
                    'total_value' => $item['quantity_issued'] * $item['unit_cost'],
                ]);
            }

            DB::commit();

            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('success', "Goods Issue '{$goodsIssue->issue_number}' created successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating Goods Issue', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to create Goods Issue: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(GoodsIssue $goodsIssue)
    {
        $goodsIssue->load([
            'warehouse',
            'vehicle',
            'employee',
            'issuedBy',
            'items.product',
            'items.uom'
        ]);

        return view('goods-issues.show', [
            'goodsIssue' => $goodsIssue,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GoodsIssue $goodsIssue)
    {
        if ($goodsIssue->status !== 'draft') {
            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('error', 'Only draft Goods Issues can be edited.');
        }

        $goodsIssue->load('items');

        return view('goods-issues.edit', [
            'goodsIssue' => $goodsIssue,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('vehicle_number')->get(['id', 'vehicle_number', 'vehicle_type']),
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_code']),
            'products' => Product::where('is_active', true)->orderBy('product_name')->get(['id', 'product_code', 'product_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGoodsIssueRequest $request, GoodsIssue $goodsIssue)
    {
        if ($goodsIssue->status !== 'draft') {
            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('error', 'Only draft Goods Issues can be updated.');
        }

        DB::beginTransaction();

        try {
            // Calculate total value
            $totalValue = 0;
            foreach ($request->items as $item) {
                $totalValue += $item['quantity_issued'] * $item['unit_cost'];
            }

            // Update goods issue
            $goodsIssue->update([
                'issue_date' => $request->issue_date,
                'warehouse_id' => $request->warehouse_id,
                'vehicle_id' => $request->vehicle_id,
                'employee_id' => $request->employee_id,
                'total_value' => $totalValue,
                'notes' => $request->notes,
            ]);

            // Delete old items and create new ones
            $goodsIssue->items()->delete();

            foreach ($request->items as $index => $item) {
                GoodsIssueItem::create([
                    'goods_issue_id' => $goodsIssue->id,
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'quantity_issued' => $item['quantity_issued'],
                    'unit_cost' => $item['unit_cost'],
                    'uom_id' => $item['uom_id'],
                    'total_value' => $item['quantity_issued'] * $item['unit_cost'],
                ]);
            }

            DB::commit();

            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('success', "Goods Issue '{$goodsIssue->issue_number}' updated successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating Goods Issue', [
                'goods_issue_id' => $goodsIssue->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update Goods Issue. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GoodsIssue $goodsIssue)
    {
        if ($goodsIssue->status !== 'draft') {
            return back()->with('error', 'Only draft Goods Issues can be deleted.');
        }

        DB::beginTransaction();

        try {
            $issueNumber = $goodsIssue->issue_number;
            $goodsIssue->items()->delete();
            $goodsIssue->delete();

            DB::commit();

            return redirect()
                ->route('goods-issues.index')
                ->with('success', "Goods Issue '{$issueNumber}' deleted successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Unable to delete Goods Issue.');
        }
    }

    /**
     * Post goods issue to transfer inventory from warehouse to vehicle
     */
    public function post(GoodsIssue $goodsIssue)
    {
        if ($goodsIssue->status !== 'draft') {
            return back()->with('error', 'Only draft Goods Issues can be posted.');
        }

        $distributionService = app(DistributionService::class);
        $result = $distributionService->postGoodsIssue($goodsIssue);

        if ($result['success']) {
            return redirect()
                ->route('goods-issues.show', $goodsIssue->id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->with('error', $result['message']);
    }

    /**
     * Generate unique goods issue number
     */
    private function generateIssueNumber(): string
    {
        $year = now()->year;
        $lastIssue = GoodsIssue::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastIssue ? ((int) substr($lastIssue->issue_number, -4)) + 1 : 1;

        return sprintf('GI-%d-%04d', $year, $sequence);
    }
}
