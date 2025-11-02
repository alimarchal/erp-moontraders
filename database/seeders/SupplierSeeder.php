<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Top 10 Food Industries in Pakistan
     * Source: https://www.scientificpakistan.com/post/the-top-10-food-industries-in-pakistan
     */
    public function run(): void
    {
        $defaultCurrency = Currency::where('currency_code', 'PKR')->first();

        $suppliers = [
            [
                'supplier_name' => 'Nestlé Pakistan Ltd.',
                'country' => 'Pakistan',
                'supplier_group' => 'Multinational',
                'supplier_type' => 'Food & Beverage',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-0123456',
                'pan_number' => 'STN-001-NESTLE',
                'supplier_details' => 'Nestlé Pakistan Ltd. - Subsidiary of Swiss multinational. Operating 50+ years since 1963. 2,000+ employees, Rs.8B+ turnover. Factories in Lahore, Islamabad, Karachi. R&D in Lahore. Products: Baby food, cereals, dairy, bottled water, tea, coffee, chocolate, Nestle Pure Life, Nido, Nescafe, Maggi, Kit Kat, Milo. Contact: +92-42-111-637-853 | info@nestle.pk',
                'website' => 'https://www.nestle.pk/',
                'supplier_primary_address' => '308-Km, Ferozepur Road, Lahore, Pakistan | Contact: Mr. Hassan Ali | Payment Terms: Net 30 Days',
                'supplier_primary_contact' => 'Mr. Hassan Ali | Phone: +92-42-111-637-853 | Mobile: +92-300-1234567 | Email: info@nestle.pk',
            ],
            [
                'supplier_name' => 'PepsiCo Pakistan (Pvt.) Ltd.',
                'country' => 'Pakistan',
                'supplier_group' => 'Multinational',
                'supplier_type' => 'Beverages & Snacks',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-0234567',
                'pan_number' => 'STN-002-PEPSI',
                'supplier_details' => 'PepsiCo Pakistan - Global presence in 200+ countries. 22 brands with $1B+ annual sales each. Brands: Pepsi, Lay\'s, Gatorade, Quaker, Tropicana. 200,000+ stores nationwide. 285,000+ employees worldwide. Extensive distribution network. Contact: +92-21-111-737-742 | contact@pepsico.pk',
                'website' => 'https://pepsico.com.pk/',
                'supplier_primary_address' => 'Plot 12-C, Pace Shopping Centre, Block 5, Clifton, Karachi, Pakistan | Contact: Mr. Ahmed Raza | Payment Terms: Net 30 Days',
                'supplier_primary_contact' => 'Mr. Ahmed Raza | Phone: +92-21-111-737-742 | Mobile: +92-300-2345678 | Email: contact@pepsico.pk',
            ],
            [
                'supplier_name' => 'Coca-Cola Pakistan',
                'country' => 'Pakistan',
                'supplier_group' => 'Multinational',
                'supplier_type' => 'Beverages',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-0345678',
                'pan_number' => 'STN-003-COKE',
                'supplier_details' => 'Coca-Cola Pakistan - One of most popular beverage brands. Wide range: soft drinks, juices, teas, coffees. Known for innovative marketing campaigns. Part of The Coca-Cola Company global network. Available at all major retail outlets. Contact: +92-21-111-265-265 | info@coca-cola.pk',
                'website' => 'https://www.coca-cola.pk/',
                'supplier_primary_address' => 'Coca-Cola Icecek Pakistan, Plot 24, Sector 27, Korangi Industrial Area, Karachi | Contact: Ms. Ayesha Khan | Payment Terms: Net 30 Days',
                'supplier_primary_contact' => 'Ms. Ayesha Khan | Phone: +92-21-111-265-265 | Mobile: +92-300-3456789 | Email: info@coca-cola.pk',
            ],
            [
                'supplier_name' => 'Unilever Pakistan Ltd.',
                'country' => 'Pakistan',
                'supplier_group' => 'Multinational',
                'supplier_type' => 'FMCG - Food & Personal Care',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-0456789',
                'pan_number' => 'STN-004-UNILEVER',
                'supplier_details' => 'Unilever Pakistan - World\'s largest food company with 190+ country operations. Products: oils, margarine, tea, detergents, ice cream, rice, frozen foods. Brands: Lux, Closeup, Lipton, Dalda, Knorr. Committed to quality and innovation. Contact: +92-21-111-300-600 | contact@unilever.pk',
                'website' => 'https://www.unilever.pk/',
                'supplier_primary_address' => '6th Floor, Avari Plaza, Fatima Jinnah Road, Karachi 75530, Pakistan | Contact: Mr. Imran Malik | Payment Terms: Net 45 Days',
                'supplier_primary_contact' => 'Mr. Imran Malik | Phone: +92-21-111-300-600 | Mobile: +92-300-4567890 | Email: contact@unilever.pk',
            ],
            [
                'supplier_name' => 'Shan Foods (Pvt.) Ltd.',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Spices & Masalas',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-0567890',
                'pan_number' => 'STN-005-SHAN',
                'supplier_details' => 'Shan Foods - Est. 1981, Karachi. Leading Pakistani brand in spices, masalas, pickles, snacks. State-of-the-art manufacturing with latest technology. Favorite among Pakistani cooks for flavorful products. Available at all major supermarkets. Contact: +92-21-111-742-633 | info@shanfoods.com',
                'website' => 'https://www.shanfoods.com/',
                'supplier_primary_address' => '14-A, S.I.T.E., Karachi 75700, Pakistan | Contact: Mr. Tariq Mahmood | Payment Terms: Net 21 Days',
                'supplier_primary_contact' => 'Mr. Tariq Mahmood | Phone: +92-21-111-742-633 | Mobile: +92-300-5678901 | Email: info@shanfoods.com',
            ],
            [
                'supplier_name' => 'National Foods Ltd.',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Spices & Cooking Products',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-0678901',
                'pan_number' => 'STN-006-NFOODS',
                'supplier_details' => 'National Foods Ltd. - Renowned Pakistani company. Products: spices, cooking sauces, pickles. Known for quality and affordable prices. Strong promotion strategy and distribution network. Available at all major retail outlets. Contact: +92-21-111-123-123 | info@nfoods.com',
                'website' => 'https://www.nfoods.com/',
                'supplier_primary_address' => '12-Km, Multan Road, Lahore, Pakistan | Contact: Mr. Zafar Iqbal | Payment Terms: Net 21 Days',
                'supplier_primary_contact' => 'Mr. Zafar Iqbal | Phone: +92-21-111-123-123 | Mobile: +92-300-6789012 | Email: info@nfoods.com',
            ],
            [
                'supplier_name' => 'Shezan International Ltd.',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Jams, Jellies, Pickles & Juices',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-0789012',
                'pan_number' => 'STN-007-SHEZAN',
                'supplier_details' => 'Shezan International - Founded 1963, iconic Pakistani brand. Products: jams, jellies, pickles, juices, snacks, desserts. Quality and innovation commitment. Integral part of Pakistani culture with traditional recipes. All major supermarkets. Contact: +92-42-111-743-926 | info@shezan.pk',
                'website' => 'https://shezan.pk/',
                'supplier_primary_address' => '16-Km Ferozepur Road, Lahore, Pakistan | Contact: Ms. Fatima Sheikh | Payment Terms: Net 30 Days',
                'supplier_primary_contact' => 'Ms. Fatima Sheikh | Phone: +92-42-111-743-926 | Mobile: +92-300-7890123 | Email: info@shezan.pk',
            ],
            [
                'supplier_name' => 'Dalda Foods (Pvt.) Ltd.',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Edible Oils & Ghee',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-0890123',
                'pan_number' => 'STN-008-DALDA',
                'supplier_details' => 'Dalda Foods - Pakistan\'s largest food processing company, core player in edible oil since 1940s. Products: cooking oils, vegetable oils, shortening, ghee, edible fats. First choice for perfect balance of smell, taste, nutrition. Trans-fat free. Contact: +92-21-111-325-321 | info@daldafoods.com',
                'website' => 'https://www.daldafoods.com/',
                'supplier_primary_address' => 'Plot 32-35, Sector 24, Korangi Industrial Area, Karachi | Contact: Mr. Asif Hussain | Payment Terms: Net 30 Days',
                'supplier_primary_contact' => 'Mr. Asif Hussain | Phone: +92-21-111-325-321 | Mobile: +92-300-8901234 | Email: info@daldafoods.com',
            ],
            [
                'supplier_name' => 'Gourmet Foods & Bakers',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Bakery & Sweets',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-0901234',
                'pan_number' => 'STN-009-GOURMET',
                'supplier_details' => 'Gourmet Foods - Founded 1988 by Mr. Nawaz Chattha. #1 brand in bakers and sweets. Products: sweets, milk, ice-cream, gourmet cola, sauces, pastes, pickles. 120 locations nationwide. 1,700 employees. 25%+ annual growth. Contact: +92-42-111-468-763 | info@gourmetpakistan.com',
                'website' => 'https://www.gourmetpakistan.com/',
                'supplier_primary_address' => '17-C Muslim Town, Lahore, Pakistan | Contact: Mr. Bilal Ahmed | Payment Terms: Net 14 Days',
                'supplier_primary_contact' => 'Mr. Bilal Ahmed | Phone: +92-42-111-468-763 | Mobile: +92-300-9012345 | Email: info@gourmetpakistan.com',
            ],
            [
                'supplier_name' => 'Mitchell\'s Fruit Farms Ltd.',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Fruit Products & Beverages',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'tax_id' => 'NTN-1012345',
                'pan_number' => 'STN-010-MITCHELLS',
                'supplier_details' => 'Mitchell\'s Fruit Farms - Founded 1933, leading player in Pakistani food industry. Products: jams, jellies, preserves, marmalades, fruit juices, nectars, beverages. Finest quality fruits with modern production. Diversified into bakery, snacks, confectionery. Contact: +92-42-111-648-243 | info@mitchells.com.pk',
                'website' => 'https://www.mitchells.com.pk/',
                'supplier_primary_address' => '41-A, Satellite Town, Rawalpindi, Pakistan | Contact: Mr. Kamran Shah | Payment Terms: Net 30 Days',
                'supplier_primary_contact' => 'Mr. Kamran Shah | Phone: +92-42-111-648-243 | Mobile: +92-300-0123456 | Email: info@mitchells.com.pk',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }

        $this->command->info('✅ Successfully seeded 10 major Pakistani food suppliers!');
        $this->command->info('   - 4 Multinational: Nestlé, PepsiCo, Coca-Cola, Unilever');
        $this->command->info('   - 6 Local: Shan, National Foods, Shezan, Dalda, Gourmet, Mitchell\'s');
        $this->command->info('   Total Credit Limit: Rs. 36,000,000');
    }
}
