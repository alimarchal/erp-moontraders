<?php

namespace App\Services\BankRegister;

use App\Models\BankEntryApproval;
use App\Models\BankStatementEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Read-only maker-checker rules (limits from config/bank.php, i.e. .env), shared by the approval
 * and posting services so neither has to depend on the other.
 */
class BankApprovalRules
{
    /**
     * 0 = no approval, 1 = checker, 2 = checker + senior approver.
     */
    public function requiredLevel(BankStatementEntry $entry): int
    {
        $config = config('bank.maker_checker');

        if (! $config['enabled']) {
            return 0;
        }

        if ($config['applies_to'] !== 'both' && $config['applies_to'] !== $entry->direction) {
            return 0;
        }

        $amount = $entry->amount;

        if ($config['level2_threshold'] > 0 && $amount >= $config['level2_threshold']) {
            return 2;
        }

        return $config['level1_threshold'] > 0 && $amount >= $config['level1_threshold'] ? 1 : 0;
    }

    /**
     * True when no approval is needed or every required level has approved in the current round.
     */
    public function isFullyApproved(BankStatementEntry $entry): bool
    {
        $required = $this->requiredLevel($entry);

        return $required === 0 || $this->approvedLevels($entry)->count() >= $required;
    }

    /**
     * Whether this person can give the next approval of a waiting entry: not the one who submitted it (unless self
     * approval is allowed), not someone who already approved it, and a senior approver for the second level.
     */
    public function canApprove(BankStatementEntry $entry, User $user): bool
    {
        if (! $user->can('bank-statement-entry-approve') || $entry->status !== BankStatementEntry::STATUS_PENDING_APPROVAL) {
            return false;
        }

        if (! config('bank.maker_checker.allow_self_approval') && (int) $entry->submitted_by === (int) $user->id) {
            return false;
        }

        $approved = $this->approvedLevels($entry);

        if ($approved->contains(fn (BankEntryApproval $approval) => (int) $approval->user_id === (int) $user->id)) {
            return false;
        }

        return $approved->count() + 1 < 2 || $user->can('bank-statement-entry-approve-high-value');
    }

    /**
     * Reversing takes an approved entry back out of the books, so with BANK_APPROVAL_REQUIRED_FOR_REVERSAL on
     * the person who submitted an entry that needed a checker cannot reverse it alone (unless self approval is allowed).
     */
    public function reversalNeedsSecondPerson(BankStatementEntry $entry, User $user): bool
    {
        $config = config('bank.maker_checker');

        return $config['reversal_requires_approval']
            && ! $config['allow_self_approval']
            && $this->requiredLevel($entry) > 0
            && (int) $entry->submitted_by === (int) $user->id;
    }

    /**
     * @return Collection<int, BankEntryApproval>
     */
    public function approvedLevels(BankStatementEntry $entry): Collection
    {
        return BankEntryApproval::query()
            ->where('bank_statement_entry_id', $entry->id)
            ->where('round', $entry->approval_round)
            ->where('action', 'approved')
            ->get();
    }
}
