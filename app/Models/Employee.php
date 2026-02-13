<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'employee_code',
        'name',
        'company_name',
        'designation',
        'phone',
        'email',
        'address',
        'warehouse_id',
        'cost_center_id',
        'user_id',
        'hire_date',
        'is_active',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SalesmanLedger::class);
    }

    public function customerAccounts(): HasMany
    {
        return $this->hasMany(CustomerEmployeeAccount::class);
    }

    public function accountTransactions(): HasMany
    {
        return $this->hasManyThrough(
            CustomerEmployeeAccountTransaction::class,
            CustomerEmployeeAccount::class
        );
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function salesSettlements(): HasMany
    {
        return $this->hasMany(SalesSettlement::class);
    }

    public function salaryTransactions(): HasMany
    {
        return $this->hasMany(EmployeeSalaryTransaction::class);
    }
}
