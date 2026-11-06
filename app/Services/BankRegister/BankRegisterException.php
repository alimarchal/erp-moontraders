<?php

namespace App\Services\BankRegister;

use RuntimeException;

/**
 * A business-rule failure the user can fix (missing ledger account, self approval, wrong status ...).
 * Controllers turn it into a flash message instead of an error page.
 */
class BankRegisterException extends RuntimeException {}
