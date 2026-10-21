<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToCollection, WithHeadingRow
{
    /**
     * Heading aliases (slugified) mapped to the canonical field they fill.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'sku' => 'product_code',
        'code' => 'product_code',
        'name' => 'product_name',
        'unit_sell_price' => 'sell_price',
        'selling_price' => 'sell_price',
        'is_powder' => 'powder',
        'is_active' => 'status',
    ];

    /** @var array<int, array{row: int, action: string, code: string, name: string, message: string}> */
    private array $report = [];

    /** @var array<string, int> */
    private array $counts = ['created' => 0, 'skipped' => 0, 'errors' => 0];

    /** @var array<string, bool> product code => true (existing, including deleted, or seen earlier in the file) */
    private array $codes = [];

    /** @var array<string, bool> */
    private array $barcodes = [];

    /** @var array<string, int> */
    private array $categories = [];

    /** @var array<string, int> */
    private array $suppliers = [];

    /** @var array<string, int> */
    private array $uoms = [];

    public function __construct(private bool $dryRun = false, private ?int $supplierScope = null)
    {
        Product::withTrashed()->get(['product_code', 'barcode'])->each(function (Product $p) {
            $this->codes[strtoupper($p->product_code)] = true;
            if ($p->barcode) {
                $this->barcodes[mb_strtolower($p->barcode)] = true;
            }
        });

        Category::query()->get(['id', 'name'])->each(fn (Category $c) => $this->categories[mb_strtolower($c->name)] = $c->id);
        Supplier::query()->get(['id', 'supplier_name'])->each(fn (Supplier $s) => $this->suppliers[mb_strtolower($s->supplier_name)] = $s->id);
        Uom::query()->get(['id', 'uom_name', 'symbol'])->each(function (Uom $u) {
            $this->uoms[mb_strtolower($u->uom_name)] = $u->id;
            if ($u->symbol) {
                $this->uoms[mb_strtolower($u->symbol)] ??= $u->id;
            }
        });
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $offset => $raw) {
            $row = $this->normalise($raw->toArray());

            if (! array_filter($row, fn ($v) => $v !== null)) {
                continue;
            }

            $this->processRow($offset + 2, $row);
        }
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    /**
     * @return array<int, array{row: int, action: string, code: string, name: string, message: string}>
     */
    public function report(): array
    {
        return $this->report;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function processRow(int $rowNumber, array $row): void
    {
        $code = isset($row['product_code']) ? strtoupper($row['product_code']) : null;

        if ($code === null) {
            $this->log($rowNumber, 'error', $row, 'Product Code is required.');

            return;
        }

        if (isset($this->codes[$code])) {
            $this->log($rowNumber, 'skipped', $row, 'Duplicate (same product code).', $code);

            return;
        }

        $attributes = $this->attributes($row, $code, $error);
        if ($error !== null) {
            $this->log($rowNumber, 'error', $row, $error, $code);

            return;
        }

        if (! $this->dryRun) {
            Product::create($attributes);
        }

        $this->codes[$code] = true;
        if (($attributes['barcode'] ?? null) !== null) {
            $this->barcodes[mb_strtolower($attributes['barcode'])] = true;
        }

        $this->log($rowNumber, 'created', $row, 'New product.', $code);
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function attributes(array $row, string $code, ?string &$error): array
    {
        $error = null;

        if (! isset($row['product_name'])) {
            $error = 'Product Name is required.';
        }

        $uomId = $this->lookup($this->uoms, $row['uom'] ?? 'Piece', 'UOM', $error);
        $salesUomId = isset($row['sales_uom']) ? $this->lookup($this->uoms, $row['sales_uom'], 'Sales UOM', $error) : null;
        $categoryId = isset($row['category']) ? $this->lookup($this->categories, $row['category'], 'Category', $error) : null;
        $supplierId = isset($row['supplier']) ? $this->lookup($this->suppliers, $row['supplier'], 'Supplier', $error) : null;

        if ($this->supplierScope !== null) {
            if ($supplierId !== null && $supplierId !== $this->supplierScope) {
                $error ??= 'You can only import products for your own supplier.';
            }
            $supplierId = $this->supplierScope;
        }

        $method = Product::VALUATION_METHODS[0];
        if (isset($row['valuation_method'])) {
            $method = collect(Product::VALUATION_METHODS)->first(fn ($m) => strcasecmp($m, $row['valuation_method']) === 0);
            if ($method === null) {
                $error ??= 'Valuation Method must be one of: '.implode(', ', Product::VALUATION_METHODS).'.';
            }
        }

        $numbers = [];
        foreach (['sell_price' => 'Sell Price', 'cost_price' => 'Cost Price', 'reorder_level' => 'Reorder Level', 'weight' => 'Weight', 'expiry_price' => 'Expiry Price', 'conversion_factor' => 'Conversion Factor'] as $field => $label) {
            $value = $row[$field] ?? null;
            if ($value !== null && (! is_numeric($value) || (float) $value < 0 || ($field === 'conversion_factor' && (float) $value < 0.001))) {
                $error ??= "{$label} must be a valid positive number.";
            }
            $numbers[$field] = $value !== null && is_numeric($value) ? (float) $value : null;
        }

        $barcode = $row['barcode'] ?? null;
        if ($barcode !== null && isset($this->barcodes[mb_strtolower($barcode)])) {
            $error ??= "Barcode '{$barcode}' is already used by another product.";
        }

        $attributes = [
            'product_code' => $code,
            'product_name' => $row['product_name'] ?? null,
            'brand' => $row['brand'] ?? null,
            'barcode' => $barcode,
            'category_id' => $categoryId,
            'supplier_id' => $supplierId,
            'uom_id' => $uomId,
            'sales_uom_id' => $salesUomId,
            'valuation_method' => $method,
            'unit_sell_price' => $numbers['sell_price'],
            'cost_price' => $numbers['cost_price'],
            'reorder_level' => $numbers['reorder_level'],
            'expiry_price' => $numbers['expiry_price'],
            'uom_conversion_factor' => $numbers['conversion_factor'],
            'weight' => $numbers['weight'],
            'pack_size' => $row['pack_size'] ?? null,
            'description' => $row['description'] ?? null,
            'is_powder' => $this->toBool($row['powder'] ?? 'No'),
            'is_active' => ! isset($row['status']) || $this->toBool($row['status']),
        ];

        return array_filter($attributes, fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, int>  $index
     */
    private function lookup(array $index, string $name, string $label, ?string &$error): ?int
    {
        $id = $index[mb_strtolower(trim($name))] ?? null;
        if ($id === null) {
            $error ??= "{$label} '{$name}' was not found. Create it first or fix the spelling.";
        }

        return $id;
    }

    private function toBool(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['yes', 'y', 'true', '1', 'active'], true);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string|null>
     */
    private function normalise(array $raw): array
    {
        $row = [];
        foreach ($raw as $key => $value) {
            $field = self::ALIASES[$key] ?? $key;
            $value = is_string($value) ? trim($value) : $value;
            $row[$field] = ($value === '' || $value === null)
                ? null
                : (is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function log(int $rowNumber, string $action, array $row, string $message, ?string $code = null): void
    {
        $this->counts[match ($action) {
            'created' => 'created',
            'skipped' => 'skipped',
            default => 'errors',
        }]++;

        $this->report[] = [
            'row' => $rowNumber,
            'action' => $action,
            'code' => $code ?? (string) ($row['product_code'] ?? ''),
            'name' => (string) ($row['product_name'] ?? ''),
            'message' => $message,
        ];
    }
}
