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
    private array $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => 0];

    /** Columns the importer understands. Any other heading in the file is reported as ignored. */
    private const KNOWN_COLUMNS = [
        'customer_code', 'customer_name', 'business_name', 'phone', 'email', 'ntn', 'owner_cnic', 'address', 'sub_locality', 'city',
        'state', 'country', 'channel_type', 'customer_category', 'credit_limit', 'payment_terms', 'sales_rep', 'it_status', 'is_active',
    ];

    private const FIELD_LABELS = [
        'customer_name' => 'Name', 'business_name' => 'Business', 'phone' => 'Phone', 'email' => 'Email', 'ntn' => 'NTN',
        'owner_cnic' => 'CNIC', 'address' => 'Address', 'sub_locality' => 'Sub locality', 'city' => 'City', 'state' => 'State',
        'country' => 'Country', 'channel_type' => 'Channel', 'customer_category' => 'Category', 'credit_limit' => 'Credit limit',
        'payment_terms' => 'Payment terms', 'sales_rep_id' => 'Sales rep', 'it_status' => 'IT status', 'is_active' => 'Status',
    ];

    /** @var list<string> */
    private array $ignoredColumns = [];

    /** @var array<string, int|null> customer code => customer id (null = created earlier in this file) */
    private array $codeIndex = [];

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
        $this->ignoredColumns = collect($rows->first()?->keys() ?? [])
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->reject(fn (string $key) => in_array(self::ALIASES[$key] ?? $key, self::KNOWN_COLUMNS, true))
            ->map(fn (string $key) => str_replace('_', ' ', $key))
            ->values()
            ->all();

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
     * Headings of the file that were not recognised.
     *
     * @return list<string>
     */
    public function ignoredColumns(): array
    {
        return $this->ignoredColumns;
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

        $customer = Customer::query()->whereKey($matchId)->first();
        $candidates = array_filter($attributes, fn ($v, $k) => $k !== 'customer_code' && $v !== null, ARRAY_FILTER_USE_BOTH);
        $changes = [];

        foreach ($candidates as $field => $new) {
            $old = $customer?->getAttribute($field);
            $same = match (true) {
                is_bool($new) => (bool) $old === $new,
                in_array($field, ['credit_limit'], true) => $old !== null && abs((float) $old - (float) $new) < 0.005,
                $field === 'payment_terms', $field === 'sales_rep_id' => (int) $old === (int) $new && $old !== null,
                default => trim((string) $old) === trim((string) $new),
            };

            if (! $same) {
                $changes[$field] = $new;
            }
        }

        if ($changes === []) {
            $this->log($rowNumber, 'unchanged', $row, "Already up to date (matched by {$matchedBy}).");

            return;
        }

        $message = collect($changes)->map(function ($new, string $field) use ($customer) {
            $show = fn ($value) => match (true) {
                $value === null || $value === '' => '—',
                is_bool($value) => $value ? 'Yes' : 'No',
                $field === 'sales_rep_id' => User::whereKey($value)->value('name') ?? $value,
                default => (string) $value,
            };

            return self::FIELD_LABELS[$field].' '.$show($customer?->getAttribute($field)).' → '.$show($new);
        })->implode('; ');

        if (! $this->dryRun) {
            $customer?->update($changes);
        }

        $this->log($rowNumber, 'updated', $row, $message);
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
     * Only the customer code decides whether a row is a duplicate.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: int|null, 1: string|null}
     */
    private function findDuplicate(array $attributes): array
    {
        $code = $attributes['customer_code'] ?? null;

        if ($code !== null && array_key_exists($code, $this->codeIndex)) {
            return [$this->codeIndex[$code], 'customer code'];
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function remember(array $attributes, ?int $id): void
    {
        $this->codeIndex[$attributes['customer_code']] = $id;
    }

    private function buildIndexes(): void
    {
        Customer::withTrashed()
            ->get(['id', 'customer_code', 'deleted_at'])
            ->each(function (Customer $c) {
                $this->codeIndex[strtoupper($c->customer_code)] = $c->id;
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
            'unchanged' => 'unchanged',
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
