<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingPeriodSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $currentYear = $now->year;
        $currentQuarter = $now->quarter;

        $periods = [];

        for ($year = 2025; $year <= 2026; $year++) {
            $fiscalYearStatus = $this->determineYearStatus($year, $currentYear);

            $periods[] = [
                'name' => "Fiscal Year {$year}",
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => $fiscalYearStatus,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            for ($quarter = 1; $quarter <= 4; $quarter++) {
                $quarterDates = $this->getQuarterDates($year, $quarter);
                $quarterStatus = $this->determineQuarterStatus($year, $quarter, $currentYear, $currentQuarter);

                $periods[] = [
                    'name' => "Q{$quarter} {$year}",
                    'start_date' => $quarterDates['start'],
                    'end_date' => $quarterDates['end'],
                    'status' => $quarterStatus,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('accounting_periods')->insert($periods);
    }

    private function determineYearStatus(int $year, int $currentYear): string
    {
        if ($year < $currentYear) {
            return 'closed';
        } elseif ($year == $currentYear) {
            return 'open';
        } else {
            return 'open';
        }
    }

    private function determineQuarterStatus(int $year, int $quarter, int $currentYear, int $currentQuarter): string
    {
        if ($year < $currentYear) {
            return 'closed';
        } elseif ($year == $currentYear && $quarter < $currentQuarter) {
            return 'closed';
        } else {
            return 'open';
        }
    }

    private function getQuarterDates(int $year, int $quarter): array
    {
        return match ($quarter) {
            1 => ['start' => "{$year}-01-01", 'end' => "{$year}-03-31"],
            2 => ['start' => "{$year}-04-01", 'end' => "{$year}-06-30"],
            3 => ['start' => "{$year}-07-01", 'end' => "{$year}-09-30"],
            4 => ['start' => "{$year}-10-01", 'end' => "{$year}-12-31"],
        };
    }
}
