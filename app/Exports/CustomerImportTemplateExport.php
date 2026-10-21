<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Customer Code', 'Customer Name', 'Business Name', 'Phone', 'Email', 'NTN', 'Owner CNIC',
            'IT Status', 'Address', 'Sub Locality', 'City', 'State', 'Country', 'Channel Type',
            'Category', 'Credit Limit', 'Payment Terms', 'Sales Rep', 'Status',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [
            ['', 'Al Rehman Store', '', '0300-1234567', '', '', '', 'No', 'Main Bazar', 'Satellite Town', 'Muzaffarabad', 'Azad Kashmir', 'Pakistan', Customer::CHANNEL_TYPES[0], 'C', 50000, 30, '', 'Active'],
        ];
    }
}
