<?php

namespace App\Imports;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomerImport implements ToCollection, WithHeadingRow
{
    public const MODE_SKIP = 'skip';

    public const MODE_UPDATE = 'update';

    private const CODE_PREFIX = 'CUST-';

    /**
     * Heading aliases (slugified) mapped to the canonical field they fill.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'code' => 'customer_code',
        'shop_code' => 'customer_code',
        'name' => 'customer_name',
        'shop_name' => 'customer_name',
        'shop' => 'customer_name',
        'customer' => 'customer_name',
        'trade_name' => 'business_name',
        'mobile' => 'phone',
        'mobile_no' => 'phone',
        'phone_no' => 'phone',
        'cnic' => 'owner_cnic',
        'area' => 'sub_locality',
        'locality' => 'sub_locality',
        'channel' => 'channel_type',
        'category' => 'customer_category',
        'status' => 'is_active',
        'sales_rep' => 'sales_rep',
        'salesman' => 'sales_rep',
    ];

    /** @var array<int, array{row: int, action: string, code: string, name: string, message: string}> */
    private array $report = [];

    /** @var array<string, int> */
    private array $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    /** @var array<string, int|null> lookup key => customer id (null = created earlier in this file) */
    private array $codeIndex = [];

    /** @var array<string, int|null> */
    private array $phoneIndex = [];

    /** @var array<string, int|null> */
    private array $emailIndex = [];

    /** @var array<string, int|null> */
    private array $nameIndex = [];

    /** @var array<int, bool> customer ids that are soft-deleted */
    private array $trashed = [];

    private int $nextCodeNumber = 1;

    /** @var array<string, int> */
    private array $salesReps = [];

    public function __construct(private string $duplicateMode = self::MODE_SKIP, private bool $dryRun = false)
    {
        $this->buildIndexes();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $offset => $raw) {
            $rowNumber = $offset + 2;
            $row = $this->normalise($raw->toArray());

            if (! array_filter($row, fn ($v) => $v !== null)) {
                continue;
            }

            $this->processRow($rowNumber, $row);
        }
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    /**
     * @return array<int, array{row: int, action: string, code: string, name: string, message: string}>
     */
    public function report(): array
    {
        return $this->report;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function processRow(int $rowNumber, array $row): void
    {
        $name = $row['customer_name'] ?? null;
        if ($name === null) {
            $this->log($rowNumber, 'error', $row, 'Customer Name is required.');

            return;
        }

        $attributes = $this->validated($row, $error);
        if ($error !== null) {
            $this->log($rowNumber, 'error', $row, $error);

            return;
        }

        [$matchId, $matchedBy] = $this->findDuplicate($attributes);

        if ($matchedBy !== null && $matchId !== null && ($this->trashed[$matchId] ?? false)) {
            $this->log($rowNumber, 'error', $row, "Matches a deleted customer (by {$matchedBy}). Restore or rename it first.");

            return;
        }

        if ($matchedBy !== null) {
            $this->handleDuplicate($rowNumber, $row, $attributes, $matchId, $matchedBy);

            return;
        }

        $attributes['customer_code'] ??= $this->generateCode();

        if (! $this->dryRun) {
            $customer = Customer::create(array_filter($attributes, fn ($v) => $v !== null) + ['is_active' => true]);
            $id = $customer->id;
        } else {
            $id = null;
        }

        $this->remember($attributes, $id);
        $this->log($rowNumber, 'created', $row, 'New customer'.($row['customer_code'] ?? null ? '' : ' (code generated)').'.', $attributes['customer_code']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $attributes
     */
    private function handleDuplicate(int $rowNumber, array $row, array $attributes, ?int $matchId, string $matchedBy): void
    {
        if ($this->duplicateMode !== self::MODE_UPDATE || $matchId === null) {
            $this->log($rowNumber, 'skipped', $row, "Duplicate (same {$matchedBy}).");

            return;
        }

        $changes = array_filter($attributes, fn ($v, $k) => $k !== 'customer_code' && $v !== null, ARRAY_FILTER_USE_BOTH);

        if (! $this->dryRun) {
            Customer::query()->whereKey($matchId)->first()?->update($changes);
        }

        $this->log($rowNumber, 'updated', $row, "Existing customer updated (matched by {$matchedBy}).");
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function validated(array $row, ?string &$error): array
    {
        $error = null;

        $channel = $row['channel_type'] ?? null;
        if ($channel !== null) {
            $channel = collect(Customer::CHANNEL_TYPES)->first(fn ($t) => strcasecmp($t, $channel) === 0);
            if ($channel === null) {
                $error = 'Channel Type must be one of: '.implode(', ', Customer::CHANNEL_TYPES).'.';
            }
        }

        $category = isset($row['customer_category']) ? strtoupper($row['customer_category']) : null;
        if ($category !== null && ! in_array($category, Customer::CUSTOMER_CATEGORIES, true)) {
            $error ??= 'Category must be A, B, C or D.';
        }

        $email = isset($row['email']) ? strtolower($row['email']) : null;
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error ??= "Email '{$email}' is not valid.";
        }

        foreach (['credit_limit' => [0, null], 'payment_terms' => [0, 365]] as $field => [$min, $max]) {
            $value = $row[$field] ?? null;
            if ($value !== null && (! is_numeric($value) || $value < $min || ($max !== null && $value > $max))) {
                $error ??= str_replace('_', ' ', ucfirst($field)).' must be a number'
                    .($max !== null ? " between {$min} and {$max}" : " of {$min} or more").'.';
            }
        }

        $salesRepId = null;
        if (isset($row['sales_rep'])) {
            $salesRepId = $this->salesReps[mb_strtolower($row['sales_rep'])] ?? null;
            if ($salesRepId === null) {
                $error ??= "Sales Rep '{$row['sales_rep']}' not found (use the user's name or email).";
            }
        }

        $attributes = [
            'customer_code' => isset($row['customer_code']) ? strtoupper($row['customer_code']) : null,
            'customer_name' => $row['customer_name'],
            'business_name' => $row['business_name'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $email,
            'ntn' => $row['ntn'] ?? null,
            'owner_cnic' => $row['owner_cnic'] ?? null,
            'address' => $row['address'] ?? null,
            'sub_locality' => $row['sub_locality'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'country' => $row['country'] ?? null,
            'channel_type' => $channel,
            'customer_category' => $category,
            'credit_limit' => isset($row['credit_limit']) ? (float) $row['credit_limit'] : null,
            'payment_terms' => isset($row['payment_terms']) ? (int) $row['payment_terms'] : null,
            'sales_rep_id' => $salesRepId,
        ];

        if (isset($row['it_status'])) {
            $attributes['it_status'] = $this->toBool($row['it_status']);
        }
        if (isset($row['is_active'])) {
            $attributes['is_active'] = $this->toBool($row['is_active']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: int|null, 1: string|null}
     */
    private function findDuplicate(array $attributes): array
    {
        $checks = [
            ['customer code', $this->codeIndex, $attributes['customer_code'] ?? null],
            ['email', $this->emailIndex, $attributes['email'] ?? null],
            ['phone', $this->phoneIndex, $this->phoneKey($attributes['phone'] ?? null)],
            ['name and area', $this->nameIndex, $this->nameKey($attributes)],
        ];

        foreach ($checks as [$label, $index, $key]) {
            if ($key !== null && array_key_exists($key, $index)) {
                return [$index[$key], $label];
            }
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function remember(array $attributes, ?int $id): void
    {
        $this->codeIndex[$attributes['customer_code']] = $id;
        if (($attributes['email'] ?? null) !== null) {
            $this->emailIndex[$attributes['email']] = $id;
        }
        if (($key = $this->phoneKey($attributes['phone'] ?? null)) !== null) {
            $this->phoneIndex[$key] = $id;
        }
        if (($key = $this->nameKey($attributes)) !== null) {
            $this->nameIndex[$key] = $id;
        }
    }

    private function buildIndexes(): void
    {
        Customer::withTrashed()
            ->get(['id', 'customer_code', 'customer_name', 'email', 'phone', 'sub_locality', 'city', 'deleted_at'])
            ->each(function (Customer $c) {
                $this->codeIndex[strtoupper($c->customer_code)] = $c->id;
                if ($c->email) {
                    $this->emailIndex[strtolower($c->email)] = $c->id;
                }
                if (($key = $this->phoneKey($c->phone)) !== null) {
                    $this->phoneIndex[$key] ??= $c->id;
                }
                if (($key = $this->nameKey($c->only(['customer_name', 'sub_locality', 'city']))) !== null) {
                    $this->nameIndex[$key] ??= $c->id;
                }
                $this->trashed[$c->id] = $c->deleted_at !== null;

                if (preg_match('/^'.preg_quote(self::CODE_PREFIX, '/').'(\d+)$/i', $c->customer_code, $m)) {
                    $this->nextCodeNumber = max($this->nextCodeNumber, (int) $m[1] + 1);
                }
            });

        User::query()->get(['id', 'name', 'email'])->each(function (User $u) {
            $this->salesReps[mb_strtolower($u->name)] = $u->id;
            $this->salesReps[mb_strtolower($u->email)] = $u->id;
        });
    }

    private function generateCode(): string
    {
        do {
            $code = self::CODE_PREFIX.str_pad((string) $this->nextCodeNumber++, 4, '0', STR_PAD_LEFT);
        } while (array_key_exists($code, $this->codeIndex));

        return $code;
    }

    private function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 7 ? substr($digits, -10) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function nameKey(array $attributes): ?string
    {
        $name = preg_replace('/\s+/', ' ', mb_strtolower(trim((string) ($attributes['customer_name'] ?? ''))));
        if ($name === '') {
            return null;
        }

        return $name.'|'.mb_strtolower(trim((string) ($attributes['sub_locality'] ?? ''))).'|'.mb_strtolower(trim((string) ($attributes['city'] ?? '')));
    }

    private function toBool(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['yes', 'y', 'true', '1', 'active'], true);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string|null>
     */
    private function normalise(array $raw): array
    {
        $row = [];
        foreach ($raw as $key => $value) {
            $field = self::ALIASES[$key] ?? $key;
            $value = is_string($value) ? trim($value) : $value;
            $row[$field] = ($value === '' || $value === null) ? null : (is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function log(int $rowNumber, string $action, array $row, string $message, ?string $code = null): void
    {
        $counter = match ($action) {
            'created' => 'created',
            'updated' => 'updated',
            'skipped' => 'skipped',
            default => 'errors',
        };
        $this->counts[$counter]++;

        $this->report[] = [
            'row' => $rowNumber,
            'action' => $action,
            'code' => $code ?? (string) ($row['customer_code'] ?? ''),
            'name' => (string) ($row['customer_name'] ?? ''),
            'message' => $message,
        ];
    }
}
