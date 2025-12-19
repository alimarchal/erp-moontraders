<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Customer;

// Removed legacy CustomerLedger references after migration to customer_employee_account_transactions

// Legacy CustomerController retained intentionally empty to avoid autoload issues.
// All customer-related APIs were migrated to CustomerEmployeeAccountController.
// This file can be safely deleted once all references are fully removed.
