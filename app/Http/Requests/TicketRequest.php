<?php

namespace App\Http\Requests;

use App\Enums\TicketType;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Ticket;
use App\Services\TicketEntryForms;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TicketRequest extends FormRequest
{
    public const ADJUSTMENT_TYPES = ['damage', 'theft', 'count_variance', 'expiry', 'recall', 'other'];

    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * The type of an existing ticket can never change.
     */
    public function ticketType(): ?TicketType
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket ? $ticket->type : TicketType::tryFrom((string) $this->input('type'));
    }

    protected function prepareForValidation(): void
    {
        $type = $this->ticketType();

        if ($type?->isSimpleEntry() && is_array($this->input('data'))) {
            $this->merge(['data' => TicketEntryForms::normalize($type, $this->input('data'))]);
        }

        $sku = $this->input('sku');

        if (is_array($sku)) {
            $sku['product_code'] = isset($sku['product_code']) ? strtoupper(trim((string) $sku['product_code'])) : null;
            $sku['is_powder'] = filter_var($sku['is_powder'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $this->merge(['sku' => $sku]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required', Rule::enum(TicketType::class)],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];

        return match ($this->ticketType()) {
            TicketType::NewSku => $rules + $this->newSkuRules(),
            TicketType::ReactivateSku => $rules + $this->reactivateRules(),
            TicketType::StockAdjustment => $rules + $this->stockAdjustmentRules(),
            TicketType::LedgerEntry, TicketType::ClaimEntry, TicketType::NewCustomer => $rules + $this->entryRules(),
            default => $rules + $this->priceUpdateRules(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function productRule(?bool $onlyActive = true): array
    {
        $supplierId = $this->scopedSupplierId();

        return [
            'required',
            'integer',
            Rule::exists('products', 'id')
                ->whereNull('deleted_at')
                ->when($supplierId, fn ($rule) => $rule->where('supplier_id', $supplierId))
                ->using(fn ($query) => $onlyActive === null ? $query : $query->where('is_active', $onlyActive)),
        ];
    }

    /**
     * Company users may only raise tickets for their own company's products.
     */
    private function scopedSupplierId(): ?int
    {
        $user = $this->user();

        return (! $user->isTicketAdmin() && $user->supplier_id) ? (int) $user->supplier_id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function priceUpdateRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [...$this->productRule(), 'distinct'],
            'items.*.batch_scope' => ['required', Rule::in(['all', 'selected'])],
            'items.*.batch_ids' => ['required_if:items.*.batch_scope,selected', 'array'],
            'items.*.batch_ids.*' => ['integer', 'exists:stock_batches,id'],
            'items.*.unit_sell_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.expiry_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.reorder_level' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reactivateRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [...$this->productRule(onlyActive: null), 'distinct'],
            'items.*.new_is_active' => ['required', Rule::in(['0', '1', 0, 1, true, false])],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Ledger entry, claim entry and new customer use the rules of the screens that create them;
     * company users are tied to their own supplier.
     *
     * @return array<string, mixed>
     */
    private function entryRules(): array
    {
        $rules = TicketEntryForms::rules($this->ticketType());
        $supplierId = $this->scopedSupplierId();

        if ($supplierId !== null && isset($rules['data.supplier_id'])) {
            $rules['data.supplier_id'] = [...(array) $rules['data.supplier_id'], Rule::in([$supplierId])];
        }

        return $rules;
    }

    /**
     * Same rules as the Stock Adjustments screen, plus the company scope.
     *
     * @return array<string, mixed>
     */
    private function stockAdjustmentRules(): array
    {
        $supplierId = $this->scopedSupplierId();

        return [
            'adjustment_date' => ['required', 'date', 'before_or_equal:today'],
            'supplier_id' => ['required', 'exists:suppliers,id', Rule::when($supplierId !== null, Rule::in([$supplierId]))],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->using(fn ($query) => $query->where('disabled', false))],
            'adjustment_type' => ['required', Rule::in(self::ADJUSTMENT_TYPES)],
            'reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')->where('supplier_id', $this->input('supplier_id'))],
            'items.*.stock_batch_id' => ['required', 'integer', 'exists:stock_batches,id'],
            'items.*.system_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.actual_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.uom_id' => ['required', 'exists:uoms,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function newSkuRules(): array
    {
        $supplierId = $this->scopedSupplierId();

        return [
            'sku' => ['required', 'array'],
            'sku.product_code' => ['required', 'string', 'max:191', Rule::unique('products', 'product_code')],
            'sku.product_name' => ['required', 'string', 'max:191'],
            'sku.description' => ['nullable', 'string'],
            'sku.category_id' => ['nullable', 'exists:categories,id'],
            'sku.supplier_id' => [
                $supplierId ? 'required' : 'nullable',
                'exists:suppliers,id',
                Rule::when($supplierId !== null, Rule::in([$supplierId])),
            ],
            'sku.uom_id' => ['required', 'exists:uoms,id'],
            'sku.sales_uom_id' => ['nullable', 'exists:uoms,id'],
            'sku.uom_conversion_factor' => ['nullable', 'numeric', 'min:0.001', 'max:999999'],
            'sku.weight' => ['nullable', 'numeric', 'min:0'],
            'sku.pack_size' => ['nullable', 'string', 'max:120'],
            'sku.barcode' => ['nullable', 'string', 'max:191', Rule::unique('products', 'barcode')],
            'sku.brand' => ['nullable', 'string', 'max:120'],
            'sku.valuation_method' => ['required', Rule::in(Product::VALUATION_METHODS)],
            'sku.reorder_level' => ['nullable', 'numeric', 'min:0'],
            'sku.unit_sell_price' => ['nullable', 'numeric', 'min:0'],
            'sku.cost_price' => ['nullable', 'numeric', 'min:0'],
            'sku.expiry_price' => ['nullable', 'numeric', 'min:0'],
            'sku.is_powder' => ['nullable', 'boolean'],
            'sku.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->ticketType() === TicketType::StockAdjustment) {
                $this->validateAdjustmentRows($validator);

                return;
            }

            if ($this->ticketType() === TicketType::ReactivateSku) {
                foreach ($this->input('items', []) as $index => $row) {
                    $product = Product::findOrFail($row['product_id']);

                    if ((bool) $row['new_is_active'] === (bool) $product->is_active) {
                        $validator->errors()->add("items.$index.new_is_active", "{$product->product_name} is already ".($product->is_active ? 'Active' : 'Inactive').'. Choose the opposite status.');
                    }
                }

                return;
            }

            if ($this->ticketType() !== TicketType::PriceUpdate) {
                return;
            }

            foreach ($this->input('items', []) as $index => $row) {
                $this->validatePriceRow($validator, $index, $row);
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function validatePriceRow(Validator $validator, int|string $index, array $row): void
    {
        $product = Product::findOrFail($row['product_id']);
        $fields = ['unit_sell_price', 'cost_price', 'expiry_price', 'reorder_level'];

        $perBatch = ($row['batch_scope'] ?? 'all') === 'selected';

        // Specific batches may legitimately differ from the product price, so any entered selling price counts.
        $changes = collect($fields)->filter(function (string $field) use ($row, $product, $perBatch) {
            $value = $row[$field] ?? null;

            if ($value === null || $value === '') {
                return false;
            }

            return ($perBatch && $field === 'unit_sell_price') || (float) $value !== (float) $product->{$field};
        });

        if ($changes->isEmpty()) {
            $validator->errors()->add("items.$index.unit_sell_price", "Enter at least one new value different from the current one for {$product->product_name}.");
        }

        if ($perBatch && (! isset($row['unit_sell_price']) || $row['unit_sell_price'] === '')) {
            $validator->errors()->add("items.$index.unit_sell_price", "Enter the new selling price for the selected batches of {$product->product_name}, or choose All batches.");
        }

        if ($perBatch) {
            $valid = StockBatch::whereIn('id', $row['batch_ids'] ?? [])->where('product_id', $product->id)->count();

            if ($valid !== count(array_unique($row['batch_ids'] ?? []))) {
                $validator->errors()->add("items.$index.batch_ids", "Selected batches do not belong to {$product->product_name}.");
            }
        }
    }

    /**
     * Every line needs a batch of its own product that really sits in the chosen warehouse,
     * and must actually change the quantity.
     */
    private function validateAdjustmentRows(Validator $validator): void
    {
        $warehouseId = (int) $this->input('warehouse_id');
        $seen = [];

        foreach ($this->input('items', []) as $index => $row) {
            $batch = StockBatch::query()->whereKey($row['stock_batch_id'])->where('product_id', $row['product_id'])->first();

            if (! $batch) {
                $validator->errors()->add("items.$index.stock_batch_id", 'The batch does not belong to the selected product.');

                continue;
            }

            if (isset($seen[$batch->id])) {
                $validator->errors()->add("items.$index.stock_batch_id", 'The same batch is listed twice.');
            }
            $seen[$batch->id] = true;

            $onHand = (float) DB::table('current_stock_by_batch')->where('stock_batch_id', $batch->id)->where('warehouse_id', $warehouseId)->sum('quantity_on_hand');

            if ($onHand <= 0) {
                $validator->errors()->add("items.$index.stock_batch_id", "Batch {$batch->batch_code} has no stock in the selected warehouse.");
            } elseif (abs((float) $row['actual_quantity'] - $onHand) < 0.0005) {
                $validator->errors()->add("items.$index.actual_quantity", "Batch {$batch->batch_code}: the counted quantity equals the system quantity, so there is nothing to adjust.");
            }
        }
    }
}
