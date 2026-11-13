<?php

return [
    /*
    | Enforce each customer's credit limit on settlement credit sales. Off by default: credit is recorded as entered.
    | When true (SS_ENFORCE_CREDIT_LIMIT=true), a settlement is refused if a credit sale would take a customer past
    | their credit limit (what they already owe plus this settlement's credit). A customer with no limit (0) is not limited.
    */
    'enforce_credit_limit' => (bool) env('SS_ENFORCE_CREDIT_LIMIT', false),
];
