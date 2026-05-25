<?php

namespace App\Services;

use App\Models\RevenueDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevenueDetailService
{
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

            $revenue->update([
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $revenue->fresh(),
                'message' => 'Revenue posted successfully.',
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
}
