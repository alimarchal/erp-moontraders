<?php

namespace App\Services\BankRegister;

use App\Models\BankEntryApproval;
use App\Models\BankEntryAuditLog;
use App\Models\BankStatementEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Maker-checker. The limits come from config/bank.php (.env): below level 1 the maker posts alone,
 * from level 1 a checker must approve, from level 2 a second (senior) approver as well.
 * The maker can never approve their own entry unless BANK_ALLOW_SELF_APPROVAL=true.
 */
class BankEntryApprovalService
{
    public function __construct(
        private readonly BankEntryPostingService $posting,
        private readonly BankApprovalRules $rules,
    ) {}

    /**
     * Maker sends a classified entry on. Small entries are posted straight away.
     */
    public function submit(BankStatementEntry $entry, User $user): BankStatementEntry
    {
        return DB::transaction(function () use ($entry, $user) {
            $entry = BankStatementEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if (! in_array($entry->status, [
                BankStatementEntry::STATUS_CLASSIFIED,
                BankStatementEntry::STATUS_REJECTED,
                BankStatementEntry::STATUS_REVERSED,
            ], true)) {
                throw new BankRegisterException('Only a classified, rejected or reversed entry can be submitted.');
            }

            if (! $entry->bank_entry_category_id) {
                throw new BankRegisterException('Choose a category before submitting.');
            }

            $level = $this->rules->requiredLevel($entry);
            $round = $entry->approval_round + 1;

            $entry->forceFill([
                'approval_round' => $round,
                'approval_level_required' => $level,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $this->log($entry, 0, 'submitted', $user);

            if ($level === 0) {
                $this->log($entry, 0, 'auto_approved', $user, 'Below the approval limit.');
                $entry->status = BankStatementEntry::STATUS_CLASSIFIED;
                $entry->save();
                BankEntryAuditLog::record($entry, 'submitted', null, ['level_required' => 0], $user->id);

                return $this->posting->post($entry, $user);
            }

            $entry->status = BankStatementEntry::STATUS_PENDING_APPROVAL;
            $entry->save();
            BankEntryAuditLog::record($entry, 'submitted', null, ['level_required' => $level], $user->id);

            return $entry;
        });
    }

    /**
     * Checker (level 1) or senior approver (level 2) approves; the last required approval posts the entry.
     */
    public function approve(BankStatementEntry $entry, User $user, ?string $comment = null): BankStatementEntry
    {
        return DB::transaction(function () use ($entry, $user, $comment) {
            $entry = BankStatementEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status !== BankStatementEntry::STATUS_PENDING_APPROVAL) {
                throw new BankRegisterException('This entry is not waiting for approval.');
            }

            if (! config('bank.maker_checker.allow_self_approval') && $entry->submitted_by === $user->id) {
                throw new BankRegisterException('You submitted this entry, so a different person must approve it.');
            }

            $approved = $this->rules->approvedLevels($entry);

            if ($approved->contains(fn (BankEntryApproval $approval) => $approval->user_id === $user->id)) {
                throw new BankRegisterException('You have already approved this entry. A different approver is needed for the next level.');
            }

            $level = $approved->count() + 1;

            if ($level >= 2 && ! $user->can('bank-statement-entry-approve-high-value')) {
                throw new BankRegisterException('This amount needs a senior approver (high-value approval permission).');
            }

            $this->log($entry, $level, 'approved', $user, $comment);
            BankEntryAuditLog::record($entry, 'approved', null, ['level' => $level], $user->id);

            if ($level < $entry->approval_level_required) {
                return $entry;
            }

            $entry->forceFill(['approved_by' => $user->id, 'approved_at' => now()])->save();

            return $this->posting->post($entry, $user);
        });
    }

    public function reject(BankStatementEntry $entry, User $user, string $reason): BankStatementEntry
    {
        if (trim($reason) === '') {
            throw new BankRegisterException('A reason is required to reject an entry.');
        }

        return DB::transaction(function () use ($entry, $user, $reason) {
            $entry = BankStatementEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status !== BankStatementEntry::STATUS_PENDING_APPROVAL) {
                throw new BankRegisterException('This entry is not waiting for approval.');
            }

            $this->log($entry, 0, 'rejected', $user, $reason);

            $entry->forceFill([
                'status' => BankStatementEntry::STATUS_REJECTED,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            BankEntryAuditLog::record($entry, 'rejected', null, ['reason' => $reason], $user->id);

            return $entry;
        });
    }

    /**
     * Approve many entries; each is attempted on its own so one failure does not stop the rest.
     *
     * @param  array<int, int>  $ids
     * @return array{approved: int, failed: array<int, string>}
     */
    public function bulkApprove(array $ids, User $user): array
    {
        $limit = (int) config('bank.maker_checker.bulk_approval_max_entries');

        if (count($ids) > $limit) {
            throw new BankRegisterException("You can approve at most {$limit} entries at once.");
        }

        $result = ['approved' => 0, 'failed' => []];

        foreach (BankStatementEntry::query()->whereIn('id', $ids)->get() as $entry) {
            try {
                $this->approve($entry, $user);
                $result['approved']++;
            } catch (BankRegisterException $e) {
                $result['failed'][$entry->id] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * An edit to the amount, party, company or category invalidates approvals already given.
     */
    public function resetApproval(BankStatementEntry $entry, ?int $userId = null): void
    {
        if ($entry->status !== BankStatementEntry::STATUS_PENDING_APPROVAL) {
            return;
        }

        $this->log($entry, 0, 'reset', null, 'Entry edited while waiting for approval.');
        $entry->forceFill(['status' => BankStatementEntry::STATUS_CLASSIFIED, 'approval_level_required' => 0])->save();
        BankEntryAuditLog::record($entry, 'approval_reset', null, null, $userId);
    }

    private function log(BankStatementEntry $entry, int $level, string $action, ?User $user, ?string $comment = null): void
    {
        BankEntryApproval::create([
            'bank_statement_entry_id' => $entry->id,
            'round' => $entry->approval_round,
            'level' => $level,
            'action' => $action,
            'user_id' => $user?->id,
            'comment' => $comment,
            'acted_at' => now(),
        ]);
    }
}
