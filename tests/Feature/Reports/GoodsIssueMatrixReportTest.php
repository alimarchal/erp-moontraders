<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsIssueMatrixReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_goods_issue_report_displays_matrix_data()
    {
        // Setup
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::factory()->create(['name' => 'Dairy']);
        $product = Product::factory()->create([
            'product_name' => 'Milk',
            'category_id' => $category->id,
            'product_code' => 'P001'
        ]);

        $employee = Employee::factory()->create(['name' => 'John Doe']);

        $startDate = Carbon::create(2023, 10, 1);
        $endDate = Carbon::create(2023, 10, 31);

        // Create Goods Issue on day 1
        $gi1 = GoodsIssue::factory()->create([
            'issue_date' => $startDate->copy()->addDays(0),
            'employee_id' => $employee->id,
            'status' => 'issued',
        ]);
        GoodsIssueItem::factory()->create([
            'goods_issue_id' => $gi1->id,
            'product_id' => $product->id,
            'quantity_issued' => 10,
        ]);

        // Create Goods Issue on day 5
        $gi2 = GoodsIssue::factory()->create([
            'issue_date' => $startDate->copy()->addDays(4),
            'employee_id' => $employee->id,
            'status' => 'issued',
        ]);
        GoodsIssueItem::factory()->create([
            'goods_issue_id' => $gi2->id,
            'product_id' => $product->id,
            'quantity_issued' => 5,
        ]);

        // Create Sales Settlement linked to GI1 (simplified)
        // Note: In real app, settlement might be linked directly or logically. 
        // Based on plan, we fetch settlements by employee/date match or relation.
        // Assuming we rely on Employee+Date filter for now or direct link if available.
        // The plan says "Fetch SalesSettlementItems... filtered by Date and Employee".

        $settlement = SalesSettlement::factory()->create([
            'employee_id' => $employee->id,
            'settlement_date' => $startDate->copy()->addDays(1), // Settlement usually happens next day
            'status' => 'posted',
        ]);

        SalesSettlementItem::factory()->create([
            'sales_settlement_id' => $settlement->id,
            'product_id' => $product->id,
            'quantity_sold' => 8,
            'total_sales_value' => 800,
            'total_cogs' => 600, // Profit = 200
        ]);

        // Action
        $response = $this->get(route('reports.goods-issue.index', [
            'filter' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'employee_id' => [$employee->id],
            ]
        ]));

        // Assert
        $response->assertOk();
        $response->assertViewIs('reports.goods-issue.index');

        // Check view data structure
        $matrixData = $response->viewData('matrixData');

        $this->assertCount(1, $matrixData['products']); // Only 1 product
        $productRow = $matrixData['products']->first();

        $this->assertEquals('Milk', $productRow['product_name']);

        // Check daily data
        // Keys in daily_data should be date strings 'Y-m-d'
        $this->assertEquals(10, $productRow['daily_data'][$startDate->format('Y-m-d')]['qty']);
        $this->assertEquals(5, $productRow['daily_data'][$startDate->copy()->addDays(4)->format('Y-m-d')]['qty']);

        // Check totals
        // Issued 10 + 5 = 15
        $this->assertEquals(15, $productRow['totals']['total_issued_qty']);

        // Settlement data (8 sold, 800 sales, 200 profit)
        $this->assertEquals(8, $productRow['totals']['total_sold_qty']);
        $this->assertEquals(800, $productRow['totals']['total_sale']);
        $this->assertEquals(200, $productRow['totals']['total_profit']);
    }
}
