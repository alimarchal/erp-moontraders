<?php

namespace App\Http\Requests;

use App\Enums\TicketType;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TicketRequest extends FormRequest
{
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
            default => $rules + $this->priceUpdateRules(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function productRule(bool $onlyInactive = false): array
    {
        $supplierId = $this->scopedSupplierId();

        return [
            'required',
            'integer',
            Rule::exists('products', 'id')
                ->whereNull('deleted_at')
                ->when($supplierId, fn ($rule) => $rule->where('supplier_id', $supplierId))
                ->using(fn ($query) => $onlyInactive ? $query->whereNot('is_active', true) : $query->where('is_active', true)),
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
            'items.*.product_id' => [...$this->productRule(onlyInactive: true), 'distinct'],
            'items.*.new_is_active' => ['required', Rule::in(['0', '1', 0, 1, true, false])],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
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
            if ($this->ticketType() !== TicketType::PriceUpdate || $validator->errors()->isNotEmpty()) {
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

        if (($row['batch_scope'] ?? 'all') === 'selected') {
            $valid = StockBatch::whereIn('id', $row['batch_ids'] ?? [])->where('product_id', $product->id)->count();

            if ($valid !== count(array_unique($row['batch_ids'] ?? []))) {
                $validator->errors()->add("items.$index.batch_ids", "Selected batches do not belong to {$product->product_name}.");
            }
        }
    }
}
