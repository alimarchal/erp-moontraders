<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomerExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<\App\Models\Customer>  $query
     */
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Customer Code',
            'Customer Name',
            'Business Name',
            'Phone',
            'Email',
            'NTN',
            'Address',
            'Sub Locality',
            'City',
            'State',
            'Country',
            'Channel Type',
            'Category',
            'Credit Limit',
            'Credit Used',
            'Payment Terms',
            'Sales Rep',
            'Status',
        ];
    }

    /**
     * @param  \App\Models\Customer  $customer
     * @return array<int, mixed>
     */
    public function map($customer): array
    {
        return [
            $customer->customer_code,
            $customer->customer_name,
            $customer->business_name ?? '',
            $customer->phone ?? '',
            $customer->email ?? '',
            $customer->ntn ?? '',
            $customer->address ?? '',
            $customer->sub_locality ?? '',
            $customer->city ?? '',
            $customer->state ?? '',
            $customer->country ?? '',
            $customer->channel_type ?? '',
            $customer->customer_category ?? '',
            $customer->credit_limit,
            $customer->credit_used,
            $customer->payment_terms ?? '',
            $customer->salesRep?->name ?? '',
            $customer->is_active ? 'Active' : 'Inactive',
        ];
    }
}
