<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->map(function ($line) {
                return [
                    'account_id' => $line['account_id'] ?? null,
                    'debit' => $this->sanitizeAmount($line['debit'] ?? 0),
                    'credit' => $this->sanitizeAmount($line['credit'] ?? 0),
                    'description' => $line['description'] ?? null,
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                ];
            })
            ->filter(function ($line) {
                return !empty($line['account_id'])
                    || $line['debit'] !== 0.0
                    || $line['credit'] !== 0.0;
            })
            ->values();

        $this->merge([
            'lines' => $lines->all(),
            'auto_post' => $this->boolean('auto_post'),
            'fx_rate_to_base' => $this->sanitizeAmount(
                $this->input('fx_rate_to_base', $this->input('fx_rate', 1))
            ),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'currency_id' => [
                'required',
                Rule::exists('currencies', 'id')->where('is_active', true),
            ],
            'fx_rate_to_base' => ['nullable', 'numeric', 'gte:0'],
            'accounting_period_id' => ['nullable', 'exists:accounting_periods,id'],
            'auto_post' => ['boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => [
                'required',
                Rule::exists('chart_of_accounts', 'id')->where(function ($query) {
                    $query->where('is_group', false)->where('is_active', true);
                }),
            ],
            'lines.*.debit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.cost_center_id' => [
                'nullable',
                Rule::exists('cost_centers', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lines = collect($this->input('lines', []));

            if ($lines->count() < 2) {
                $validator->errors()->add('lines', 'At least two lines are required for a journal entry.');
                return;
            }

            $totalDebits = $lines->sum(fn ($line) => round((float) ($line['debit'] ?? 0), 2));
            $totalCredits = $lines->sum(fn ($line) => round((float) ($line['credit'] ?? 0), 2));

            if (abs($totalDebits - $totalCredits) > 0.01) {
                $validator->errors()->add('lines', 'Debits and credits must balance.');
            }

            $lines->each(function ($line, $index) use ($validator) {
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'A line cannot have both debit and credit amounts.');
                }

                if ($debit <= 0 && $credit <= 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'Each line must have a debit or credit amount greater than zero.');
                }
            });
        });
    }

    /**
     * Return the validated data with normalised line items.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        $validated['lines'] = collect($validated['lines'] ?? [])
            ->map(fn ($line) => [
                'account_id' => (int) $line['account_id'],
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'description' => $line['description'] ?? null,
                'cost_center_id' => isset($line['cost_center_id'])
                    ? (int) $line['cost_center_id']
                    : null,
            ])
            ->values()
            ->all();

        $validated['auto_post'] = (bool) ($validated['auto_post'] ?? false);
        $validated['fx_rate_to_base'] = round((float) ($validated['fx_rate_to_base'] ?? 1), 6);

        return $validated;
    }

    /**
     * Convert input amount to float, stripping extraneous characters.
     */
    protected function sanitizeAmount(mixed $value): float
    {
        if (is_string($value)) {
            $value = preg_replace('/[^\d.\-]/', '', $value);
        }

        return (float) ($value ?? 0);
    }
}
