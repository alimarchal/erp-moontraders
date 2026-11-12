<?php

namespace Database\Factories;

use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsIssueItemRemoval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsIssueItemRemoval>
 */
class GoodsIssueItemRemovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $item = GoodsIssueItem::factory();

        return [
            'goods_issue_id' => GoodsIssue::factory(),
            'goods_issue_item_id' => $item,
            'quantity' => 1,
            'cost_value' => 10,
            'selling_value' => 15,
            'reason' => 'Returned before settlement',
            'journal_entry_id' => null,
            'batches' => [],
            'removed_by' => User::factory(),
            'removed_at' => now(),
        ];
    }
}
