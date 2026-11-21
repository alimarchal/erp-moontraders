<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeSupplierProducts extends Command
{
    protected $signature = 'products:purge-supplier
        {supplier : Supplier id, name or short name (e.g. EBM)}
        {--force : Delete for real; without it the command only lists what would go}';

    protected $description = 'Permanently delete every product of one company (soft-deleted ones included) so its SKU list '
        .'can be imported again from scratch. Refuses when any of those products is used anywhere (GRN, stock, sales, '
        .'price logs, ...), so only a company that has not started trading can be purged.';

    public function handle(): int
    {
        $supplier = $this->findSupplier((string) $this->argument('supplier'));

        if ($supplier === null) {
            $this->error("No supplier matches '{$this->argument('supplier')}'.");

            return self::FAILURE;
        }

        $products = Product::withTrashed()->where('supplier_id', $supplier->id)->orderBy('product_code')->get(['id', 'product_code', 'deleted_at']);

        if ($products->isEmpty()) {
            $this->info("{$supplier->supplier_name} has no products. Nothing to delete.");

            return self::SUCCESS;
        }

        $usage = $this->usage($products->pluck('id'));

        if ($usage !== []) {
            $this->error("{$supplier->supplier_name}'s products are already used, so nothing was deleted:");
            $this->table(['Table', 'Rows'], collect($usage)->map(fn (int $rows, string $table) => [$table, $rows])->values()->all());

            return self::FAILURE;
        }

        $this->info("{$supplier->supplier_name} (id {$supplier->id}): {$products->count()} product(s), none used anywhere.");

        if (! $this->option('force')) {
            $this->table(['Product Code', 'Deleted?'], $products->map(fn (Product $p) => [$p->product_code, $p->deleted_at ? 'soft-deleted' : ''])->all());
            $this->warn('Dry run. Run again with --force to delete them permanently.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($products) {
            DB::table('product_tax_mappings')->whereIn('product_id', $products->pluck('id'))->delete();
            Product::withTrashed()->whereKey($products->pluck('id'))->forceDelete();
        });

        $this->info("Deleted {$products->count()} product(s) of {$supplier->supplier_name}. Import the new SKU file now.");

        return self::SUCCESS;
    }

    private function findSupplier(string $value): ?Supplier
    {
        if (ctype_digit($value)) {
            return Supplier::query()->find((int) $value);
        }

        return Supplier::query()
            ->whereRaw('LOWER(short_name) = ?', [mb_strtolower($value)])
            ->orWhereRaw('LOWER(supplier_name) = ?', [mb_strtolower($value)])
            ->first();
    }

    /**
     * Rows in other tables that point at these products, by table. Every table with a
     * product_id style column is checked, foreign key or not.
     *
     * @param  Collection<int, int>  $productIds
     * @return array<string, int>
     */
    private function usage(Collection $productIds): array
    {
        $usage = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, ['products', 'product_tax_mappings'], true)) {
                continue;
            }

            $columns = collect(Schema::getColumnListing($table))->filter(fn (string $column) => $column === 'product_id' || str_ends_with($column, '_product_id'));

            foreach ($columns as $column) {
                $rows = DB::table($table)->whereIn($column, $productIds)->count();
                if ($rows > 0) {
                    $usage["{$table}.{$column}"] = $rows;
                }
            }
        }

        return $usage;
    }
}
