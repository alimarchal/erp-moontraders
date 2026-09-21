<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DatabaseTriggerGuard
{
    /**
     * Lift the named triggers off a table for the duration of a correction, and return the
     * closure that puts them back.
     *
     * Dropping a guard is only safe if it can be recreated, and on MySQL that is not a given:
     * with binary logging on and no SUPER privilege, CREATE TRIGGER fails with error 1419. So
     * the privilege is probed first and the guards are left alone when it is missing — a
     * correction not made is recoverable, an accounting control silently switched off is not.
     *
     * @param  list<string>  $triggers
     * @return callable(): void
     *
     * @throws RuntimeException when the triggers could not be recreated afterwards
     */
    public function suspend(string $table, array $triggers): callable
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach ($triggers as $trigger) {
                DB::statement("ALTER TABLE {$table} DISABLE TRIGGER {$trigger}");
            }

            return function () use ($table, $triggers): void {
                foreach ($triggers as $trigger) {
                    DB::statement("ALTER TABLE {$table} ENABLE TRIGGER {$trigger}");
                }
            };
        }

        $this->assertTriggersCanBeCreated($table);

        $definitions = [];

        foreach ($triggers as $trigger) {
            $row = DB::selectOne(
                'SELECT ACTION_TIMING, EVENT_MANIPULATION, ACTION_STATEMENT FROM information_schema.TRIGGERS
                 WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?',
                [$trigger]
            );

            if (! $row) {
                continue;
            }

            // Rebuilt without the DEFINER clause, so restoring does not need SUPER.
            $definitions[$trigger] = sprintf(
                'CREATE TRIGGER `%s` %s %s ON `%s` FOR EACH ROW %s',
                $trigger, $row->ACTION_TIMING, $row->EVENT_MANIPULATION, $table, $row->ACTION_STATEMENT
            );

            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }

        return function () use ($definitions): void {
            $failed = [];

            foreach ($definitions as $trigger => $sql) {
                try {
                    DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
                    DB::unprepared($sql);
                } catch (Throwable $e) {
                    $failed[$trigger] = $e->getMessage();
                }
            }

            if ($failed !== []) {
                throw new RuntimeException(
                    'Could not restore '.implode(', ', array_keys($failed)).'. '
                    ."Run `php artisan db:sync-objects` as soon as possible. First error: {$failed[array_key_first($failed)]}"
                );
            }
        };
    }

    /**
     * Prove the connection may create a trigger on this table before any guard is dropped,
     * by creating and removing an empty one. Callers that write other tables first should
     * call this up front, so a missing privilege stops them before anything changes.
     *
     * @throws RuntimeException
     */
    public function assertTriggersCanBeCreated(string $table): void
    {
        $probe = 'trg_probe_'.bin2hex(random_bytes(6));

        try {
            DB::unprepared("CREATE TRIGGER `{$probe}` BEFORE UPDATE ON `{$table}` FOR EACH ROW BEGIN END");
        } catch (Throwable $e) {
            throw new RuntimeException(
                "This connection cannot create triggers on `{$table}`, so a guard lifted now could not be put "
                .'back. Nothing was changed. Grant the database user the TRIGGER privilege, or set '
                ."log_bin_trust_function_creators = 1, then retry. Underlying error: {$e->getMessage()}"
            );
        }

        DB::unprepared("DROP TRIGGER IF EXISTS `{$probe}`");
    }
}
