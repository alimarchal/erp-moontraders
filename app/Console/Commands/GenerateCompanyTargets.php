<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\CompanyTargetService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateCompanyTargets extends Command
{
    protected $signature = 'company-targets:generate
                            {--month= : Month to create, as YYYY-MM (default: the current month)}';

    protected $description = 'Create the monthly target sheet of every active supplier with a zero target for each active salesman';

    public function handle(CompanyTargetService $targets): int
    {
        $requested = $this->option('month');

        if ($requested !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $requested)) {
            $this->error('The --month option must look like 2026-10.');

            return self::FAILURE;
        }

        $month = $requested ? Carbon::createFromFormat('Y-m-d', $requested.'-01') : now();

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $sheets = 0;
        $rows = 0;

        Supplier::query()->where('disabled', false)->orderBy('supplier_name')->each(function (Supplier $supplier) use ($targets, $start, $end, &$sheets, &$rows) {
            $result = $targets->generateForPeriod($supplier, $start, $end);
            $sheets += $result['created'] ? 1 : 0;
            $rows += $result['lines_added'];
        });

        $this->info("Company targets {$start->format('F Y')}: {$sheets} sheet(s) created, {$rows} salesman row(s) added.");

        return self::SUCCESS;
    }
}
