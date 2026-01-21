<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/customers.json');

        if (! file_exists($jsonPath)) {
            $this->command->warn('customers.json not found, skipping customer seeding');

            return;
        }

        $customers = json_decode(file_get_contents($jsonPath), true);

        if (! $customers) {
            $this->command->warn('Failed to parse customers.json');

            return;
        }

        $this->command->info('Seeding '.count($customers).' customers...');

        // Insert in chunks for better performance
        $chunks = array_chunk($customers, 100);

        foreach ($chunks as $chunk) {
            $insertData = [];

            foreach ($chunk as $customer) {
                $insertData[] = [
                    'customer_code' => $customer['customer_code'],
                    'customer_name' => $customer['customer_name'],
                    'business_name' => $customer['business_name'],
                    'it_status' => $customer['it_status'] ?? false,
                    'ntn' => $customer['ntn'] ?? null,
                    'channel_type' => $this->normalizeChannelType($customer['channel_type'] ?? 'General Store'),
                    'address' => $customer['address'] ?? null,
                    'sub_locality' => $customer['sub_locality'] ?? null,
                    'city' => $customer['city'] ?? 'Muzaffarabad',
                    'state' => 'Azad Kashmir',
                    'country' => 'Pakistan',
                    'phone' => $customer['phone'] ?? null,
                    'email' => $customer['email'] ?? null,
                    'credit_limit' => $customer['credit_limit'] ?? 50000.00,
                    'payment_terms' => $customer['payment_terms'] ?? 30,
                    'customer_category' => $this->determineCategory($customer['channel_type'] ?? ''),
                    'credit_used' => 0,
                    'is_active' => $customer['is_active'] ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('customers')->insert($insertData);
        }

        $this->command->info('✓ Successfully seeded '.count($customers).' customers');
    }

    private function normalizeChannelType(string $type): string
    {
        $validTypes = [
            'General Store',
            'Pharmacy',
            'Wholesale',
            'Bakery',
            'Hotel & Accommodation',
            'Minimart',
            '3rd Party',
            'Petromart',
            'Other',
        ];

        $type = trim($type);

        if (in_array($type, $validTypes)) {
            return $type;
        }

        return 'Other';
    }

    private function determineCategory(string $channelType): string
    {
        return match ($channelType) {
            'Wholesale' => 'A',
            'Minimart' => 'A',
            'Pharmacy' => 'B',
            'Bakery' => 'B',
            'Hotel & Accommodation' => 'B',
            'Petromart' => 'B',
            'General Store' => 'C',
            default => 'C'
        };
    }
}
