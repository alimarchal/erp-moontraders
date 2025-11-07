<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Trait for manually logging accounting changes when database triggers aren't sufficient.
 * 
 * Use this trait when:
 * - You need to log application-level changes (not just database changes)
 * - You need custom audit messages
 * - You're making bulk changes that bypass Eloquent
 */
trait AuditsAccountingChanges
{
    /**
     * Manually create an audit log entry
     *
     * @param string $tableName The table being audited
     * @param int $recordId The ID of the record
     * @param string $action INSERT, UPDATE, DELETE, or custom action
     * @param array|null $oldValues Previous values (optional)
     * @param array|null $newValues New values (optional)
     * @param array|null $changedFields List of changed fields (optional)
     * @return void
     */
    protected function logAuditChange(
        string $tableName,
        int $recordId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $changedFields = null
    ): void {
        try {
            DB::table('accounting_audit_log')->insert([
                'table_name' => $tableName,
                'record_id' => $recordId,
                'action' => strtoupper($action),
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'changed_fields' => $changedFields ? json_encode($changedFields) : null,
                'user_id' => Auth::id(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the application
            \Log::error('Failed to create audit log entry: ' . $e->getMessage(), [
                'table' => $tableName,
                'record_id' => $recordId,
                'action' => $action,
            ]);
        }
    }

    /**
     * Log a custom audit event (for non-CRUD operations)
     *
     * @param string $action Custom action name (e.g., 'PERIOD_CLOSED', 'ENTRY_REVERSED')
     * @param string $tableName The primary table involved
     * @param int $recordId The primary record ID
     * @param array $metadata Additional context about the action
     * @return void
     */
    protected function logCustomAuditEvent(
        string $action,
        string $tableName,
        int $recordId,
        array $metadata = []
    ): void {
        $this->logAuditChange(
            tableName: $tableName,
            recordId: $recordId,
            action: $action,
            newValues: $metadata
        );
    }

    /**
     * Get audit history for a specific record
     *
     * @param string $tableName
     * @param int $recordId
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    protected function getAuditHistory(string $tableName, int $recordId, int $limit = 50)
    {
        return DB::table('accounting_audit_log')
            ->where('table_name', $tableName)
            ->where('record_id', $recordId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent changes by a specific user
     *
     * @param int|null $userId
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    protected function getUserAuditHistory(?int $userId = null, int $limit = 100)
    {
        $userId = $userId ?? Auth::id();

        return DB::table('accounting_audit_log')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
