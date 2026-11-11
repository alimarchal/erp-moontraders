<?php

namespace App\Services\BankRegister;

use App\Models\BankStatementEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Removes the Bank Register's former memo ledger (the "Bank Register" account type, root 6000) for good: its journals,
 * its accounts and the account type. The register now posts to the live chart of accounts (BankRegisterAccounts).
 *
 * Those journals only ever touched memo accounts, so deleting them cannot change any live balance; this is checked
 * first and nothing happens if a single line is on another account. Statement lines that were posted to the memo
 * ledger go back to "Ready to post" (or "To classify") with their receipt numbers kept, so a real entry is posted
 * again into the live books instead of being lost.
 *
 * Posted journals are protected by database triggers. Only for the delete itself the delete-guards of journal_entries
 * and journal_entry_details are lifted, then re-created from the database's own definitions (MySQL / MariaDB) or
 * re-enabled (PostgreSQL); if they cannot be put back the whole run fails.
 */
class MemoLedgerRemoval
{
    public const TYPE_NAME = 'Bank Register';

    public const REPORT_GROUP = 'BankRegister';

    /** The delete guards of MySQL / MariaDB (created by the double-entry triggers migration). */
    private const MYSQL_GUARDS = ['trg_block_posted_journal_deletes', 'trg_mysql_prevent_hard_delete', 'trg_block_posted_detail_deletes'];

    /**
     * @return array{accounts: int, journals: int, lines: int, entries_unposted: int, reconciliation_items: int}
     */
    public function run(): array
    {
        $result = ['accounts' => 0, 'journals' => 0, 'lines' => 0, 'entries_unposted' => 0, 'reconciliation_items' => 0];

        $accountIds = $this->memoAccountIds();

        if ($accountIds->isEmpty()) {
            $this->dropAccountType();

            return $result;
        }

        $journalIds = DB::table('journal_entry_details')->whereIn('chart_of_account_id', $accountIds)->distinct()->pluck('journal_entry_id');

        $foreign = DB::table('journal_entry_details')->whereIn('journal_entry_id', $journalIds)->whereNotIn('chart_of_account_id', $accountIds)->count();

        if ($foreign > 0) {
            throw new RuntimeException("{$foreign} line(s) of the Bank Register journals are on accounts outside the register. Nothing was removed; check these journals by hand.");
        }

        $detailIds = DB::table('journal_entry_details')->whereIn('journal_entry_id', $journalIds)->pluck('id');

        DB::transaction(function () use ($journalIds, $detailIds, &$result) {
            $result['reconciliation_items'] = DB::table('bank_reconciliation_items')->whereIn('journal_entry_detail_id', $detailIds)->delete();
            $result['entries_unposted'] = $this->unpostEntries($journalIds);
            DB::table('attachments')->whereIn('journal_entry_id', $journalIds)->update(['journal_entry_id' => null]);
        });

        $result['lines'] = $detailIds->count();
        $result['journals'] = $journalIds->count();
        $this->deleteJournals($journalIds);

        DB::transaction(function () use ($accountIds, &$result) {
            if (Schema::hasColumn('bank_accounts', 'register_chart_of_account_id')) {
                DB::table('bank_accounts')->whereIn('register_chart_of_account_id', $accountIds)->update(['register_chart_of_account_id' => null]);
            }
            DB::table('bank_entry_categories')->whereIn('default_chart_of_account_id', $accountIds)->update(['default_chart_of_account_id' => null]);
            DB::table('account_balance_snapshots')->whereIn('chart_of_account_id', $accountIds)->delete();
            $result['accounts'] = $this->deleteAccounts($accountIds);
            $this->dropAccountType();
        });

        return $result;
    }

    /**
     * Every account of the memo ledger: the ones with its account type plus anything under its root.
     *
     * @return Collection<int, int>
     */
    private function memoAccountIds(): Collection
    {
        $typeIds = DB::table('account_types')->where('report_group', self::REPORT_GROUP)->orWhere('type_name', self::TYPE_NAME)->pluck('id');
        $ids = DB::table('chart_of_accounts')->whereIn('account_type_id', $typeIds)->pluck('id');

        // children created under the root with another type would otherwise block the root's delete
        do {
            $children = DB::table('chart_of_accounts')->whereIn('parent_id', $ids)->whereNotIn('id', $ids)->pluck('id');
            $ids = $ids->merge($children);
        } while ($children->isNotEmpty());

        return $ids->unique()->values();
    }

    private function unpostEntries(Collection $journalIds): int
    {
        $entries = BankStatementEntry::withTrashed()->whereIn('journal_entry_id', $journalIds)->get();

        foreach ($entries as $entry) {
            $entry->forceFill([
                'status' => $entry->bank_entry_category_id ? BankStatementEntry::STATUS_CLASSIFIED : BankStatementEntry::STATUS_UNCLASSIFIED,
                'journal_entry_id' => null,
                'journal_entry_detail_id' => null,
                'bank_reconciliation_id' => null,
                'posted_by' => null,
                'posted_at' => null,
            ])->saveQuietly();
        }

        // lines that were reversed point at no journal any more but still say "Reversed"
        BankStatementEntry::withTrashed()->where('status', BankStatementEntry::STATUS_REVERSED)->whereNull('journal_entry_id')->whereNull('sales_settlement_id')
            ->get()->each(fn (BankStatementEntry $entry) => $entry->forceFill([
                'status' => $entry->bank_entry_category_id ? BankStatementEntry::STATUS_CLASSIFIED : BankStatementEntry::STATUS_UNCLASSIFIED,
            ])->saveQuietly());

        return $entries->count();
    }

    private function deleteJournals(Collection $journalIds): void
    {
        if ($journalIds->isEmpty()) {
            return;
        }

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => $this->deleteOnMysql($journalIds),
            'pgsql' => $this->deleteOnPostgres($journalIds),
            default => $this->deleteRows($journalIds),
        };
    }

    /**
     * MySQL / MariaDB cannot switch a trigger off, so the three delete guards are dropped and created again from
     * SHOW CREATE TRIGGER. A trial trigger is created first: when the database user may not create triggers
     * (binary logging without log_bin_trust_function_creators) the run stops before anything is dropped.
     */
    private function deleteOnMysql(Collection $journalIds): void
    {
        $this->assertCanCreateTriggers();

        $definitions = [];

        foreach (self::MYSQL_GUARDS as $name) {
            $row = DB::selectOne('SHOW CREATE TRIGGER '.$name);

            if ($row) {
                $statement = (array) $row;
                $definitions[$name] = preg_replace('/\sDEFINER\s*=\s*\S+@\S+\s/i', ' ', $statement['SQL Original Statement']);
            }
        }

        try {
            foreach (array_keys($definitions) as $name) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$name);
            }

            // the journals point at each other (reverses / reversed by, RESTRICT): they go in one pass
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            try {
                $this->deleteRows($journalIds);
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
        } finally {
            foreach ($definitions as $name => $sql) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$name);
                DB::unprepared($sql);
            }
        }

        $missing = array_diff(array_keys($definitions), array_map(fn ($row) => ((array) $row)['Trigger'], DB::select('SHOW TRIGGERS')));

        if ($missing !== []) {
            throw new RuntimeException('These triggers could not be put back: '.implode(', ', $missing).'. Restore them before using the system.');
        }
    }

    private function assertCanCreateTriggers(): void
    {
        $probe = 'trg_bank_register_cleanup_probe';

        try {
            DB::unprepared("DROP TRIGGER IF EXISTS {$probe}");
            DB::unprepared("CREATE TRIGGER {$probe} BEFORE INSERT ON bank_receipt_sequences FOR EACH ROW BEGIN END");
            DB::unprepared("DROP TRIGGER IF EXISTS {$probe}");
        } catch (Throwable $e) {
            throw new RuntimeException('The database user cannot create triggers, so the journal guards could not be put back after the delete. Nothing was removed. Run SET GLOBAL log_bin_trust_function_creators = 1 (or use a user with the TRIGGER privilege), migrate, then set it back to 0. ('.$e->getMessage().')', 0, $e);
        }
    }

    /**
     * PostgreSQL: replica mode skips user triggers and foreign-key checks for this transaction only.
     */
    private function deleteOnPostgres(Collection $journalIds): void
    {
        DB::transaction(function () use ($journalIds) {
            DB::statement("SET LOCAL session_replication_role = 'replica'");
            $this->deleteRows($journalIds);
            DB::statement("SET LOCAL session_replication_role = 'origin'");
        });
    }

    private function deleteRows(Collection $journalIds): void
    {
        foreach ($journalIds->chunk(500) as $chunk) {
            DB::table('journal_entry_details')->whereIn('journal_entry_id', $chunk)->delete();
            DB::table('journal_entries')->whereIn('id', $chunk)->delete();
        }
    }

    /**
     * Children before parents (parent_id is RESTRICT).
     */
    private function deleteAccounts(Collection $accountIds): int
    {
        $deleted = 0;
        $remaining = $accountIds->all();

        while ($remaining !== []) {
            $parents = DB::table('chart_of_accounts')->whereIn('parent_id', $remaining)->pluck('parent_id')->unique()->all();
            $leaves = array_values(array_diff($remaining, $parents));

            if ($leaves === []) {
                throw new RuntimeException('The Bank Register accounts form a loop and cannot be deleted.');
            }

            $deleted += DB::table('chart_of_accounts')->whereIn('id', $leaves)->delete();
            $remaining = array_values(array_diff($remaining, $leaves));
        }

        return $deleted;
    }

    private function dropAccountType(): void
    {
        DB::table('account_types')->where('report_group', self::REPORT_GROUP)->orWhere('type_name', self::TYPE_NAME)->delete();
    }
}
