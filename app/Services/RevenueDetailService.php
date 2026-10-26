<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Models\RevenueDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevenueDetailService
{
    /** Pending Claims Debtors: the company owes us this revenue until it is received. */
    public const RECEIVABLE_ACCOUNT = '1112';

    /** Other Revenue: every revenue category is credited here. */
    public const REVENUE_ACCOUNT = '4260';

    public function __construct(private AccountingService $accountingService) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, data: ?RevenueDetail, message: string}
     */
    public function createRevenue(array $data): array
    {
        try {
            $revenue = RevenueDetail::create($data);

            return [
                'success' => true,
                'data' => $revenue,
                'message' => 'Revenue created successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create revenue detail', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create revenue: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, data: ?RevenueDetail, message: string}
     */
    public function updateRevenue(RevenueDetail $revenue, array $data): array
    {
        try {
            $revenue->update($data);

            return [
                'success' => true,
                'data' => $revenue->fresh(),
                'message' => 'Revenue updated successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update revenue detail', [
                'revenue_id' => $revenue->id,
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to update revenue: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, data: ?RevenueDetail, message: string}
     */
    public function postRevenue(RevenueDetail $revenue): array
    {
        try {
            DB::beginTransaction();

            if ($revenue->isPosted()) {
                throw new \Exception('Revenue is already posted.');
            }

            $journalEntry = $this->createJournalEntry($revenue);

            $revenue->update([
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $revenue->fresh(),
                'message' => 'Revenue posted successfully with journal entry.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post revenue detail', [
                'revenue_id' => $revenue->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to post revenue: '.$e->getMessage(),
            ];
        }
    }

    /**
     * DR Pending Claims Debtors (1112), CR Other Revenue (4260), dated on the revenue's transaction date.
     *
     * @throws \Exception when an account is missing or the journal cannot be created
     */
    public function createJournalEntry(RevenueDetail $revenue): JournalEntry
    {
        $receivableId = ChartOfAccount::where('account_code', self::RECEIVABLE_ACCOUNT)->value('id');
        $revenueId = ChartOfAccount::where('account_code', self::REVENUE_ACCOUNT)->value('id');

        if (! $receivableId || ! $revenueId) {
            throw new \Exception('GL accounts '.self::RECEIVABLE_ACCOUNT.' and '.self::REVENUE_ACCOUNT.' must exist before posting revenue.');
        }

        $costCenterId = CostCenter::query()->orderBy('id')->value('id') ?? 1;
        $amount = round((float) $revenue->amount, 2);
        $revenue->loadMissing('revenueCategory');
        $label = $revenue->revenueCategory?->name ?? 'Revenue';
        $text = "Revenue - {$label}".($revenue->description ? " - {$revenue->description}" : '');

        $result = $this->accountingService->createJournalEntry([
            'entry_date' => Carbon::parse($revenue->transaction_date)->toDateString(),
            'reference' => "REV-{$revenue->id}",
            'description' => $text,
            'reference_type' => RevenueDetail::class,
            'reference_id' => $revenue->id,
            'lines' => [
                ['account_id' => $receivableId, 'debit' => $amount, 'credit' => 0, 'description' => $text, 'cost_center_id' => $costCenterId],
                ['account_id' => $revenueId, 'debit' => 0, 'credit' => $amount, 'description' => $text, 'cost_center_id' => $costCenterId],
            ],
            'auto_post' => true,
        ]);

        if (! $result['success']) {
            throw new \Exception('Failed to create journal entry: '.$result['message']);
        }

        return $result['data'];
    }
}
