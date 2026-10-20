<?php

namespace App\Services\BankRegister;

/**
 * Single list of the Bank Statement Register permissions, shared by the permission
 * migration and the role seeder so a fresh database and an upgraded one end up identical.
 */
class BankRegisterPermissions
{
    /**
     * Permission groups => actions ("bank-statement-entry-classify" is group + action).
     *
     * @var array<string, array<int, string>>
     */
    public const GROUPS = [
        'bank-statement-import' => ['list', 'create'],
        'bank-statement-entry' => ['list', 'edit', 'delete', 'classify', 'attach', 'submit', 'approve', 'approve-high-value', 'reject', 'post', 'reverse', 'export'],
        'bank-reconciliation' => ['list', 'create', 'edit', 'complete', 'cancel'],
        'bank-entry-category' => ['list', 'create', 'edit', 'delete'],
        'bank-party-alias' => ['list', 'create', 'edit', 'delete'],
        'bank-entry-rule' => ['list', 'create', 'edit', 'delete'],
        'bank-cheque' => ['list', 'create', 'edit', 'delete'],
    ];

    /**
     * @var array<int, string>
     */
    public const REPORTS = [
        'report-bank-company-month-summary',
        'report-bank-category-month-summary',
        'report-bank-company-ledger',
        'report-bank-reconciliation-statement',
        'report-bank-unclassified',
        'report-bank-unreconciled',
        'report-bank-missing-attachments',
        'report-bank-pending-approvals',
        'report-bank-cash-flow',
        'report-bank-charges-tax',
        'report-bank-top-parties',
        'report-bank-audit-log',
    ];

    /**
     * Role => permissions it receives. super-admin and admin get everything.
     *
     * @var array<string, array<int, string>>
     */
    public const ROLE_GRANTS = [
        'accountant' => [
            'bank-statement-import-list', 'bank-statement-import-create',
            'bank-statement-entry-list', 'bank-statement-entry-edit', 'bank-statement-entry-classify',
            'bank-statement-entry-attach', 'bank-statement-entry-submit', 'bank-statement-entry-post',
            'bank-statement-entry-export',
            'bank-reconciliation-list', 'bank-reconciliation-create', 'bank-reconciliation-edit',
            'bank-entry-category-list', 'bank-entry-category-create', 'bank-entry-category-edit',
            'bank-party-alias-list', 'bank-party-alias-create', 'bank-party-alias-edit',
            'bank-entry-rule-list', 'bank-entry-rule-create', 'bank-entry-rule-edit',
            'bank-cheque-list', 'bank-cheque-create', 'bank-cheque-edit',
        ],
        'bank-checker' => [
            'bank-statement-entry-list', 'bank-statement-entry-approve', 'bank-statement-entry-reject',
            'bank-statement-entry-post', 'bank-statement-entry-reverse', 'bank-statement-entry-export',
            'bank-reconciliation-list', 'bank-reconciliation-create', 'bank-reconciliation-edit', 'bank-reconciliation-complete',
            'bank-cheque-list',
            'report-bank-pending-approvals', 'report-bank-reconciliation-statement', 'report-bank-audit-log',
        ],
        'bank-senior-approver' => [
            'bank-statement-entry-list', 'bank-statement-entry-approve', 'bank-statement-entry-approve-high-value',
            'bank-statement-entry-reject', 'bank-statement-entry-export',
            'report-bank-pending-approvals', 'report-bank-audit-log',
        ],
        'auditor' => [
            'bank-statement-entry-list', 'bank-statement-entry-export', 'bank-reconciliation-list', 'bank-cheque-list',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::GROUPS as $group => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$group}-{$action}";
            }
        }

        return array_merge($names, self::REPORTS);
    }

    /**
     * Report permissions every bank role that sees reports receives (accountant, checker, auditor).
     *
     * @return array<int, string>
     */
    public static function grantsFor(string $role): array
    {
        $grants = self::ROLE_GRANTS[$role] ?? [];

        if (in_array($role, ['accountant', 'auditor'], true)) {
            $grants = array_merge($grants, self::REPORTS);
        }

        return array_values(array_unique($grants));
    }
}
