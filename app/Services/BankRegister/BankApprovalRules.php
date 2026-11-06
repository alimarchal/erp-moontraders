<?php

namespace App\Services\BankRegister;

use App\Models\BankEntryApproval;
use App\Models\BankStatementEntry;
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
