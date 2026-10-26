<?php

namespace App\Services;

use RuntimeException;

/**
 * A repair would have moved the general ledger away from the stock value.
 */
class LedgerDriftException extends RuntimeException {}
