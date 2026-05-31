<?php

namespace App\Services;

use App\Models\ProfitCategoryDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfitCategoryDetailService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, data: ?ProfitCategoryDetail, message: string}
     */
    public function createProfitCategoryDetail(array $data): array
    {
        try {
            $profitCategoryDetail = ProfitCategoryDetail::create($data);

            return [
                'success' => true,
                'data' => $profitCategoryDetail,
                'message' => 'Profit category entry created successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create profit category detail', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create profit category entry: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, data: ?ProfitCategoryDetail, message: string}
     */
    public function updateProfitCategoryDetail(ProfitCategoryDetail $profitCategoryDetail, array $data): array
    {
        try {
            $profitCategoryDetail->update($data);

            return [
                'success' => true,
                'data' => $profitCategoryDetail->fresh(),
                'message' => 'Profit category entry updated successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update profit category detail', [
                'profit_category_detail_id' => $profitCategoryDetail->id,
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to update profit category entry: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, data: ?ProfitCategoryDetail, message: string}
     */
    public function postProfitCategoryDetail(ProfitCategoryDetail $profitCategoryDetail): array
    {
        try {
            DB::beginTransaction();

            if ($profitCategoryDetail->isPosted()) {
                throw new \Exception('Profit category entry is already posted.');
            }

            $profitCategoryDetail->update([
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $profitCategoryDetail->fresh(),
                'message' => 'Profit category entry posted successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post profit category detail', [
                'profit_category_detail_id' => $profitCategoryDetail->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to post profit category entry: '.$e->getMessage(),
            ];
        }
    }
}
