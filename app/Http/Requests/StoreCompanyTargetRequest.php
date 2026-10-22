<?php

namespace App\Http\Requests;

use App\Models\CompanyTarget;
use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A user tied to one company can only ever submit that company.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user && ! $user->hasAllSupplierAccess()) {
            $this->merge(['supplier_id' => $user->supplier_id]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supplierId = (int) $this->input('supplier_id');

        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->using(fn ($query) => $query->where('disabled', false))],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.employee_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('employees', 'id')->where('supplier_id', $supplierId),
            ],
            'lines.*.target_label' => ['nullable', 'string', 'max:255'],
            'lines.*.target_amount' => ['required', 'numeric', 'min:'.CompanyTarget::MINIMUM_AMOUNT, 'max:9999999999999.99'],
        ];
    }

    /**
     * The same supplier cannot have two sheets for the same dates.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->hasAny(['supplier_id', 'period_start', 'period_end'])) {
                    return;
                }

                $duplicate = CompanyTarget::query()
                    ->where('supplier_id', $this->input('supplier_id'))
                    ->whereDate('period_start', $this->input('period_start'))
                    ->whereDate('period_end', $this->input('period_end'))
                    ->when($this->route('company_target'), fn ($query, CompanyTarget $current) => $query->whereKeyNot($current->getKey()))
                    ->exists();

                if ($duplicate) {
                    $name = Supplier::query()->whereKey($this->input('supplier_id'))->value('supplier_name');
                    $validator->errors()->add('period_start', "{$name} already has a target sheet for these dates. Edit that one instead.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'company',
            'period_start' => 'start date',
            'period_end' => 'end date',
            'lines' => 'salesman rows',
            'lines.*.employee_id' => 'salesman',
            'lines.*.target_label' => 'target',
            'lines.*.target_amount' => 'total',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one salesman row.',
            'lines.min' => 'Add at least one salesman row.',
            'lines.*.employee_id.distinct' => 'A salesman is listed twice.',
            'lines.*.employee_id.exists' => 'A selected salesman does not belong to this company.',
            'lines.*.target_amount.min' => 'Total cannot be less than '.CompanyTarget::MINIMUM_AMOUNT.'.',
        ];
    }
}
