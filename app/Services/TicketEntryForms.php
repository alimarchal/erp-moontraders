<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\TicketType;
use App\Http\Requests\StoreClaimRegisterRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\StoreLedgerRegisterRequest;
use App\Models\Customer;

/**
 * Field lists for the ticket types that only ask for one small record (supplier ledger entry,
 * claim register entry, new customer). The same list drives the form, the ticket page and the
 * approver e-mail, and the validation reuses the rules of the screens that really create the record.
 */
class TicketEntryForms
{
    /**
     * @param  array<int|string, string>  $suppliers  id => name
     * @return array<int, array{name: string, label: string, type: string, required?: bool, options?: array<int|string, string>, span?: int, default?: mixed}>
     */
    public static function fields(TicketType $type, array $suppliers = []): array
    {
        return match ($type) {
            TicketType::LedgerEntry => [
                ['name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'required' => true, 'options' => $suppliers],
                ['name' => 'transaction_date', 'label' => 'Transaction date', 'type' => 'date', 'required' => true, 'default' => now()->toDateString()],
                ['name' => 'document_type', 'label' => 'Document type', 'type' => 'select', 'options' => collect(DocumentType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->value.' — '.$c->label()])->all()],
                ['name' => 'document_number', 'label' => 'Document number', 'type' => 'text'],
                ['name' => 'sap_code', 'label' => 'SAP code', 'type' => 'text'],
                ['name' => 'online_amount', 'label' => 'Online amount', 'type' => 'number'],
                ['name' => 'opening_balance', 'label' => 'Opening balance', 'type' => 'number'],
                ['name' => 'invoice_amount', 'label' => 'Invoice amount', 'type' => 'number'],
                ['name' => 'expenses_amount', 'label' => 'Expenses amount', 'type' => 'number'],
                ['name' => 'za_point_five_percent_amount', 'label' => '0.50% (ZA) amount', 'type' => 'number'],
                ['name' => 'claim_adjust_amount', 'label' => 'Claim adjust amount', 'type' => 'number'],
                ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'text', 'span' => 4],
            ],
            TicketType::ClaimEntry => [
                ['name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'required' => true, 'options' => $suppliers],
                ['name' => 'transaction_date', 'label' => 'Transaction date', 'type' => 'date', 'required' => true, 'default' => now()->toDateString()],
                ['name' => 'transaction_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => ['claim' => 'Claim', 'recovery' => 'Recovery'], 'default' => 'claim'],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true],
                ['name' => 'reference_number', 'label' => 'Reference number', 'type' => 'text'],
                ['name' => 'claim_month', 'label' => 'Claim month', 'type' => 'text'],
                ['name' => 'date_of_dispatch', 'label' => 'Date of dispatch', 'type' => 'date'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'span' => 2],
                ['name' => 'notes', 'label' => 'Notes', 'type' => 'text', 'span' => 4],
            ],
            TicketType::NewCustomer => [
                ['name' => 'customer_code', 'label' => 'Customer code', 'type' => 'text', 'required' => true],
                ['name' => 'customer_name', 'label' => 'Customer name', 'type' => 'text', 'required' => true, 'span' => 2],
                ['name' => 'business_name', 'label' => 'Business name', 'type' => 'text'],
                ['name' => 'channel_type', 'label' => 'Channel type', 'type' => 'select', 'required' => true, 'options' => array_combine(Customer::CHANNEL_TYPES, Customer::CHANNEL_TYPES)],
                ['name' => 'customer_category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => array_combine(Customer::CUSTOMER_CATEGORIES, Customer::CUSTOMER_CATEGORIES)],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['name' => 'ntn', 'label' => 'NTN', 'type' => 'text'],
                ['name' => 'owner_cnic', 'label' => 'Owner CNIC', 'type' => 'text'],
                ['name' => 'address', 'label' => 'Address', 'type' => 'text', 'span' => 2],
                ['name' => 'sub_locality', 'label' => 'Sub locality', 'type' => 'text'],
                ['name' => 'city', 'label' => 'City', 'type' => 'text'],
                ['name' => 'state', 'label' => 'State', 'type' => 'text'],
                ['name' => 'country', 'label' => 'Country', 'type' => 'text', 'default' => 'Pakistan'],
                ['name' => 'credit_limit', 'label' => 'Credit limit', 'type' => 'number'],
                ['name' => 'payment_terms', 'label' => 'Payment terms (days)', 'type' => 'number'],
                ['name' => 'notes', 'label' => 'Notes', 'type' => 'text', 'span' => 2],
            ],
            default => [],
        };
    }

    /**
     * Rules of the real screen, keyed under `data.`, limited to the fields this ticket asks for.
     *
     * @return array<string, mixed>
     */
    public static function rules(TicketType $type): array
    {
        $rules = match ($type) {
            TicketType::LedgerEntry => (new StoreLedgerRegisterRequest)->rules(),
            TicketType::ClaimEntry => (new StoreClaimRegisterRequest)->rules(),
            TicketType::NewCustomer => (new StoreCustomerRequest)->rules(),
            default => [],
        };

        $names = array_column(self::fields($type), 'name');

        return collect($rules)->only($names)->mapWithKeys(fn ($rule, $field) => ["data.$field" => is_string($rule) ? explode('|', $rule) : $rule])->all()
            + ['data' => ['required', 'array']];
    }

    /**
     * Same clean-up the real request applies before validating.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(TicketType $type, array $data): array
    {
        $data = array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $data);

        if ($type === TicketType::LedgerEntry) {
            foreach (['online_amount', 'opening_balance', 'invoice_amount', 'expenses_amount', 'za_point_five_percent_amount', 'claim_adjust_amount'] as $amount) {
                $data[$amount] = $data[$amount] ?? 0;
            }
        }

        if ($type === TicketType::NewCustomer) {
            $data['customer_code'] = isset($data['customer_code']) ? strtoupper($data['customer_code']) : null;
            $data['email'] = isset($data['email']) ? strtolower($data['email']) : null;
            $data['country'] ??= 'Pakistan';
            $data['is_active'] = true;
        }

        return $data;
    }
}
