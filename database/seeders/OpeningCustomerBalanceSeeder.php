<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class OpeningCustomerBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip if required FK data is not seeded (production-only data seeder).
        if (! DB::table('users')->where('id', 1)->exists()) {
            return;
        }

        $fixedDate = '2026-03-31 17:00:00';

        $accountsPath = database_path('seeders/data/accounts.json');
        if (File::exists($accountsPath)) {
            $accounts = json_decode(File::get($accountsPath), true);
            foreach (array_chunk($accounts, 100) as $chunk) {
                $insertData = [];
                foreach ($chunk as $item) {
                    $insertData[] = [
                        'id' => $item['id'],
                        'account_number' => $item['account_number'],
                        'customer_id' => $item['customer_id'],
                        'employee_id' => $item['employee_id'],
                        'opened_date' => Carbon::parse($item['opened_date'])->format('Y-m-d'),
                        'status' => $item['status'] ?? 'active',
                        'notes' => $item['notes'] ?? null,
                        'created_by' => $item['created_by'] ?? 1,
                        'created_at' => isset($item['created_at']) ? Carbon::parse($item['created_at'])->format('Y-m-d H:i:s') : $fixedDate,
                        'updated_at' => isset($item['updated_at']) ? Carbon::parse($item['updated_at'])->format('Y-m-d H:i:s') : $fixedDate,
                        'deleted_at' => ! empty($item['deleted_at']) ? Carbon::parse($item['deleted_at'])->format('Y-m-d H:i:s') : null,
                    ];
                }
                DB::table('customer_employee_accounts')->insertOrIgnore($insertData);
            }
        }

        $transactionsPath = database_path('seeders/data/transactions.json');
        if (File::exists($transactionsPath)) {
            $transactions = json_decode(File::get($transactionsPath), true);
            foreach (array_chunk($transactions, 100) as $chunk) {
                $insertData = [];
                foreach ($chunk as $item) {
                    $insertData[] = [
                        'id' => $item['id'],
                        'customer_employee_account_id' => $item['customer_employee_account_id'],
                        'transaction_date' => Carbon::parse($item['transaction_date'])->format('Y-m-d'),
                        'transaction_type' => $item['transaction_type'],
                        'reference_number' => $item['reference_number'],
                        'description' => $item['description'],
                        'debit' => $item['debit'],
                        'credit' => $item['credit'],
                        'notes' => $item['notes'] ?? null,
                        'created_by' => $item['created_by'] ?? 1,
                        'journal_entry_id' => $item['journal_entry_id'] ?? null,
                        'posted_at' => ! empty($item['posted_at']) ? Carbon::parse($item['posted_at'])->format('Y-m-d H:i:s') : null,
                        'posted_by' => $item['posted_by'] ?? null,
                        'created_at' => isset($item['created_at']) ? Carbon::parse($item['created_at'])->format('Y-m-d H:i:s') : $fixedDate,
                        'updated_at' => isset($item['updated_at']) ? Carbon::parse($item['updated_at'])->format('Y-m-d H:i:s') : $fixedDate,
                        'deleted_at' => ! empty($item['deleted_at']) ? Carbon::parse($item['deleted_at'])->format('Y-m-d H:i:s') : null,
                    ];
                }
                DB::table('customer_employee_account_transactions')->insertOrIgnore($insertData);
            }
        }

        $ledgerRegistersPath = database_path('seeders/data/ledger_registers.json');
        if (File::exists($ledgerRegistersPath)) {
            $ledgerRegisters = json_decode(File::get($ledgerRegistersPath), true);
            foreach (array_chunk($ledgerRegisters, 100) as $chunk) {
                $insertData = [];
                foreach ($chunk as $item) {
                    $insertData[] = [
                        'id' => $item['id'],
                        'supplier_id' => $item['supplier_id'],
                        'transaction_date' => Carbon::parse($item['transaction_date'])->format('Y-m-d'),
                        'document_type' => $item['document_type'],
                        'document_number' => $item['document_number'] ?? null,
                        'sap_code' => $item['sap_code'] ?? null,
                        'online_amount' => $item['online_amount'] ?? 0,
                        'invoice_amount' => $item['invoice_amount'] ?? 0,
                        'expenses_amount' => $item['expenses_amount'] ?? 0,
                        'za_point_five_percent_amount' => $item['za_point_five_percent_amount'] ?? 0,
                        'claim_adjust_amount' => $item['claim_adjust_amount'] ?? 0,
                        'balance' => $item['balance'] ?? 0,
                        'remarks' => $item['remarks'] ?? null,
                        'posted_at' => ! empty($item['posted_at']) ? Carbon::parse($item['posted_at'])->format('Y-m-d H:i:s') : null,
                        'posted_by' => $item['posted_by'] ?? null,
                        'journal_entry_id' => $item['journal_entry_id'] ?? null,
                        'created_by' => $item['created_by'] ?? 1,
                        'updated_by' => $item['updated_by'] ?? 1,
                        'created_at' => isset($item['created_at']) ? Carbon::parse($item['created_at'])->format('Y-m-d H:i:s') : $fixedDate,
                        'updated_at' => isset($item['updated_at']) ? Carbon::parse($item['updated_at'])->format('Y-m-d H:i:s') : $fixedDate,
                        'deleted_at' => ! empty($item['deleted_at']) ? Carbon::parse($item['deleted_at'])->format('Y-m-d H:i:s') : null,
                    ];
                }
                DB::table('supplier_ledger_registers')->insertOrIgnore($insertData);
            }
        }
    }
}
