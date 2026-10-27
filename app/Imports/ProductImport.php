<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Services\ProductPricingService;
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
        'conversion_fector' => 'conversion_factor',
        'conversion' => 'conversion_factor',
        'conv_factor' => 'conversion_factor',
        'uom_conversion_factor' => 'conversion_factor',
        'reorder' => 'reorder_level',
    ];

    /** Columns the importer understands. Any other heading in the file is reported as ignored. */
    private const KNOWN_COLUMNS = [
        'product_code', 'product_name', 'brand', 'barcode', 'category', 'supplier', 'uom', 'sales_uom', 'valuation_method',
        'sell_price', 'cost_price', 'reorder_level', 'conversion_factor', 'pack_size', 'weight', 'expiry_price',
        'description', 'powder', 'status',
    ];

    /** @var list<string> headings of the file that match no known column */
    private array $ignoredColumns = [];

    /** @var array<int, array{row: int, action: string, code: string, name: string, message: string}> */
    private array $report = [];

    /** @var array<string, int> */
    private array $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => 0];

    /** @var array<string, Product> product code => existing product (deleted ones included) */
    private array $existing = [];

    /** @var array<string, bool> product codes already handled earlier in this file */
    private array $seen = [];

    /** @var array<string, string> lower-cased barcode => product code that owns it */
    private array $barcodes = [];

    /** Columns an update may change, with the label shown in the result. */
    private const FIELD_LABELS = [
        'product_name' => 'Name', 'brand' => 'Brand', 'barcode' => 'Barcode', 'category_id' => 'Category', 'supplier_id' => 'Supplier',
        'uom_id' => 'UOM', 'sales_uom_id' => 'Sales UOM', 'valuation_method' => 'Valuation', 'unit_sell_price' => 'Sell Price',
        'cost_price' => 'Cost Price', 'reorder_level' => 'Reorder Level', 'expiry_price' => 'Expiry Price',
        'uom_conversion_factor' => 'Conversion Factor', 'weight' => 'Weight', 'pack_size' => 'Pack Size',
        'description' => 'Description', 'is_powder' => 'Powder', 'is_active' => 'Status',
    ];

    private const NUMERIC_FIELDS = ['unit_sell_price', 'cost_price', 'reorder_level', 'expiry_price', 'uom_conversion_factor', 'weight'];

    /** @var array<string, int> */
    private array $categories = [];

    /** @var array<string, int> */
    private array $suppliers = [];

    /** @var array<string, int> */
    private array $uoms = [];

    /**
     * @param  bool  $updateExisting  change products whose code already exists instead of skipping them;
     *                                only columns filled in the file are touched
     * @param  string  $updateScope  'all' (every filled column) or 'prices' (sell, cost and expiry price only)
     */
    public function __construct(
        private bool $dryRun = false,
        private ?int $supplierScope = null,
        private bool $updateExisting = false,
        private ?int $userId = null,
        private string $updateScope = 'all',
    ) {
        Product::withTrashed()->get()->each(function (Product $p) {
            $this->existing[strtoupper($p->product_code)] = $p;
            if ($p->barcode) {
                $this->barcodes[mb_strtolower($p->barcode)] = strtoupper($p->product_code);
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
        $this->ignoredColumns = $this->unknownHeadings($rows->first());

        foreach ($rows as $offset => $raw) {
            $row = $this->normalise($raw->toArray());

            if (! array_filter($row, fn ($v) => $v !== null)) {
                continue;
            }

            $this->processRow($offset + 2, $row);
        }
    }

    /**
     * Headings of the file that were not recognised, so a typo such as "Conversion Fector" is
     * shown instead of the column being dropped silently.
     *
     * @return list<string>
     */
    public function ignoredColumns(): array
    {
        return $this->ignoredColumns;
    }

    /**
     * @return list<string>
     */
    private function unknownHeadings(?Collection $firstRow): array
    {
        if ($firstRow === null) {
            return [];
        }

        return collect($firstRow->keys())
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->reject(fn (string $key) => in_array(self::ALIASES[$key] ?? $key, self::KNOWN_COLUMNS, true))
            ->map(fn (string $key) => str_replace('_', ' ', $key))
            ->values()
            ->all();
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

        if (isset($this->seen[$code])) {
            $this->log($rowNumber, 'skipped', $row, 'Duplicate (same product code appears earlier in the file).', $code);

            return;
        }

        $this->seen[$code] = true;

        if (isset($this->existing[$code])) {
            if ($this->updateExisting) {
                $this->updateExistingProduct($rowNumber, $row, $code, $this->existing[$code]);
            } else {
                $this->log($rowNumber, 'skipped', $row, 'Duplicate (same product code).', $code);
            }

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

        if (($attributes['barcode'] ?? null) !== null) {
            $this->barcodes[mb_strtolower($attributes['barcode'])] = $code;
        }

        $this->log($rowNumber, 'created', $row, 'New product.', $code);
    }

    /**
     * Change an existing product from the file. Blank cells and missing columns keep what is
     * stored; only values that differ are written, and the result lists old and new values.
     *
     * @param  array<string, string|null>  $row
     */
    private function updateExistingProduct(int $rowNumber, array $row, string $code, Product $product): void
    {
        if ($product->trashed()) {
            $this->log($rowNumber, 'skipped', $row, 'This product was deleted. Restore it before updating.', $code);

            return;
        }

        if ($this->supplierScope !== null && (int) $product->supplier_id !== $this->supplierScope) {
            $this->log($rowNumber, 'error', $row, 'You can only update products of your own supplier.', $code);

            return;
        }

        if ($this->updateScope === 'prices') {
            // Other columns of the file are ignored, so a bad name or category cannot block a rate update.
            $row = array_intersect_key($row, array_flip(['product_code', 'product_name', 'sell_price', 'cost_price', 'expiry_price']));
            unset($row['product_name']);
        }

        $attributes = $this->attributes($row, $code, $error, $product);
        if ($error !== null) {
            $this->log($rowNumber, 'error', $row, $error, $code);

            return;
        }

        unset($attributes['product_code']);
        $changes = [];

        foreach ($attributes as $field => $new) {
            $old = $product->getAttribute($field);
            $same = in_array($field, self::NUMERIC_FIELDS, true)
                ? ($old !== null && abs((float) $old - (float) $new) < 0.0005)
                : (is_bool($new) ? (bool) $old === $new : trim((string) $old) === trim((string) $new));

            if (! $same) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        if ($changes === []) {
            $this->log($rowNumber, 'unchanged', $row, 'Already up to date.', $code);

            return;
        }

        $message = $this->describeChanges($changes);

        if (isset($changes['unit_sell_price'])) {
            $batches = app(ProductPricingService::class)->batchIdsWithStock($product)->count();
            $message .= $batches > 0 ? " — new sell price also applies to {$batches} stock batch(es)" : '';
        }

        if (! $this->dryRun) {
            $this->saveChanges($product, $changes);
        }

        $this->log($rowNumber, 'updated', $row, $message, $code);
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    private function saveChanges(Product $product, array $changes): void
    {
        $oldPrices = [
            'selling_price' => $product->unit_sell_price,
            'expiry_price' => $product->expiry_price,
            'cost_price' => $product->cost_price,
        ];

        $product->update(array_map(fn ($change) => $change['new'], $changes));

        $pricing = app(ProductPricingService::class);
        $impacted = collect();

        if (isset($changes['unit_sell_price'])) {
            $impacted = $pricing->cascadeSellingPrice($product, $product->unit_sell_price);
            $pricing->logChange($product, 'selling_price', $oldPrices['selling_price'], $product->unit_sell_price, $this->userId, $impacted);
        }

        if (isset($changes['expiry_price'])) {
            $pricing->logChange($product, 'expiry_price', $oldPrices['expiry_price'], $product->expiry_price, $this->userId);
        }

        if (isset($changes['cost_price'])) {
            $pricing->logChange($product, 'cost_price', $oldPrices['cost_price'], $product->cost_price, $this->userId);
        }
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    private function describeChanges(array $changes): string
    {
        return collect($changes)->map(function (array $change, string $field) {
            $show = fn ($value) => match (true) {
                $value === null || $value === '' => '—',
                is_bool($value) => $value ? 'Yes' : 'No',
                in_array($field, self::NUMERIC_FIELDS, true) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.'),
                $field === 'category_id' => Category::whereKey($value)->value('name') ?? $value,
                $field === 'supplier_id' => Supplier::whereKey($value)->value('supplier_name') ?? $value,
                in_array($field, ['uom_id', 'sales_uom_id'], true) => Uom::whereKey($value)->value('uom_name') ?? $value,
                default => (string) $value,
            };

            return self::FIELD_LABELS[$field].' '.$show($change['old']).' → '.$show($change['new']);
        })->implode('; ');
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function attributes(array $row, string $code, ?string &$error, ?Product $existing = null): array
    {
        $error = null;
        $updating = $existing !== null;

        if (! $updating && ! isset($row['product_name'])) {
            $error = 'Product Name is required.';
        }

        $uomName = $row['uom'] ?? ($updating ? null : 'Piece');
        $uomId = $uomName !== null ? $this->lookup($this->uoms, $uomName, 'UOM', $error) : null;
        $salesUomId = isset($row['sales_uom']) ? $this->lookup($this->uoms, $row['sales_uom'], 'Sales UOM', $error) : null;
        $categoryId = isset($row['category']) ? $this->lookup($this->categories, $row['category'], 'Category', $error) : null;
        $supplierId = isset($row['supplier']) ? $this->lookup($this->suppliers, $row['supplier'], 'Supplier', $error) : null;

        if ($this->supplierScope !== null) {
            if ($supplierId !== null && $supplierId !== $this->supplierScope) {
                $error ??= 'You can only import products for your own supplier.';
            }
            $supplierId = $updating ? $supplierId : $this->supplierScope;
        }

        $method = $updating ? null : Product::VALUATION_METHODS[0];
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
        if ($barcode !== null && isset($this->barcodes[mb_strtolower($barcode)]) && $this->barcodes[mb_strtolower($barcode)] !== $code) {
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
            'is_powder' => $updating ? (isset($row['powder']) ? $this->toBool($row['powder']) : null) : $this->toBool($row['powder'] ?? 'No'),
            'is_active' => $updating ? (isset($row['status']) ? $this->toBool($row['status']) : null) : (! isset($row['status']) || $this->toBool($row['status'])),
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
            'updated' => 'updated',
            'unchanged' => 'unchanged',
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
