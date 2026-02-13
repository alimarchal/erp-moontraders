<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeSalaryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'transaction_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'transaction_type' => ['required', 'in:Salary,Advance,AdvanceRecovery,Deduction,Bonus,Loan,LoanRecovery,Expense,ExpenseReimbursement,Shortage,ShortageRecovery,Incentive,OvertimePay,FineDeduction,SalaryPayment,Adjustment'],
            'description' => ['nullable', 'string'],
            'salary_month' => ['nullable', 'string', 'max:255'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'debit' => ['required', 'numeric', 'min:0'],
            'credit' => ['required', 'numeric', 'min:0'],
            'debit_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'credit_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'payment_method' => ['nullable', 'in:cash,cheque,bank_transfer'],
            'cheque_number' => ['nullable', 'required_if:payment_method,cheque', 'string', 'max:255'],
            'cheque_date' => ['nullable', 'required_if:payment_method,cheque', 'date'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'status' => ['required', 'in:Pending,Approved,Paid,Cancelled'],
            'sales_settlement_id' => ['nullable', 'exists:sales_settlements,id'],
            'employee_salary_id' => ['nullable', 'exists:employee_salaries,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
