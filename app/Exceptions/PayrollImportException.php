<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A payroll Excel import that was refused, with every mistake found (each naming its sheet and row).
 */
class PayrollImportException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors[0] ?? 'The import was refused.');
    }
}
