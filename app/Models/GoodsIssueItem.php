<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GoodsIssueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_issue_id',
        'line_no',
        'product_id',
        'quantity_issued',
        'unit_cost',
        'selling_price',
        'total_value',
        'uom_id',
        'exclude_promotional',
        'is_supplementary',
        'supplementary_posted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_issued' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'total_value' => 'decimal:2',
            'exclude_promotional' => 'boolean',
            'is_supplementary' => 'boolean',
            'supplementary_posted_at' => 'datetime',
        ];
    }

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function getTotalValue(): float
    {
        return $this->quantity_issued * $this->unit_cost;
    }

    /**
     * Quantities taken back from this line before settlement.
     */
    public function removals(): HasMany
    {
        return $this->hasMany(GoodsIssueItemRemoval::class, 'goods_issue_item_id')->orderBy('removed_at');
    }

    /**
     * The batches this line still holds on the van: its transfer movements netted per batch (an issue takes
     * stock out, a removal brings some back), keeping batches above zero, oldest priority first.
     *
     * @return Collection<int, object{stock_batch_id: int, batch_code: ?string, quantity: float, unit_cost: float, selling_price: float, is_promotional: bool}>
     */
    public function netIssuedBatches(): Collection
    {
        return DB::table('stock_movements as sm')
            ->join('stock_batches as sb', 'sm.stock_batch_id', '=', 'sb.id')
            ->where('sm.reference_type', GoodsIssue::class)
            ->where('sm.reference_id', $this->goods_issue_id)
            ->where('sm.goods_issue_item_id', $this->id)
            ->where('sm.movement_type', 'transfer')
            ->groupBy('sb.id', 'sb.batch_code', 'sb.selling_price', 'sb.is_promotional', 'sb.priority_order')
            ->selectRaw('sb.id as stock_batch_id, sb.batch_code, SUM(-sm.quantity) as quantity, MAX(sm.unit_cost) as unit_cost, sb.selling_price, sb.is_promotional')
            ->havingRaw('SUM(-sm.quantity) > 0.0005')
            ->orderBy('sb.priority_order')
            ->orderBy('sb.id')
            ->get();
    }
}
