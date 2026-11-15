<?php

namespace App\Support;

use App\Models\SalesSettlementCheque;

final class CashNotes
{
    public const NOTES = SalesSettlementCheque::CLEARED_NOTES;

    /**
     * @param  array<int,int>  $counts  note value => number of notes (may be negative after minus())
     */
    private function __construct(private readonly array $counts, private readonly float $coins) {}

    /**
     * @param  array<int|string,mixed>  $entered  note values plus 'coins'
     */
    public static function fromInput(array $entered): self
    {
        $counts = [];
        foreach (self::NOTES as $note) {
            $counts[$note] = (int) self::clean($entered[$note] ?? 0);
        }

        return new self($counts, self::clean($entered['coins'] ?? 0));
    }

    /**
     * @param  object|array<string,mixed>  $row
     */
    public static function fromColumns(object|array $row, string $prefix = 'denom_'): self
    {
        $values = is_array($row) ? $row : (method_exists($row, 'getAttributes') ? $row->getAttributes() : get_object_vars($row));

        $counts = [];
        foreach (self::NOTES as $note) {
            $counts[$note] = (int) ($values[$prefix.$note] ?? 0);
        }

        return new self($counts, (float) ($values[$prefix.'coins'] ?? 0));
    }

    public function total(): float
    {
        $total = $this->coins;
        foreach ($this->counts as $note => $count) {
            $total += $note * $count;
        }

        return (float) $total;
    }

    public function count(int $note): int
    {
        return $this->counts[$note] ?? 0;
    }

    public function coins(): float
    {
        return $this->coins;
    }

    /**
     * @return array<string,int|float>
     */
    public function columns(string $prefix = 'denom_'): array
    {
        $columns = [];
        foreach ($this->counts as $note => $count) {
            $columns[$prefix.$note] = $count;
        }
        $columns[$prefix.'coins'] = $this->coins;

        return $columns;
    }

    public function plus(self $other): self
    {
        return $this->combine($other, 1);
    }

    public function minus(self $other): self
    {
        return $this->combine($other, -1);
    }

    public function isEmpty(): bool
    {
        return $this->coins == 0 && array_sum($this->counts) === 0;
    }

    private function combine(self $other, int $sign): self
    {
        $counts = [];
        foreach (self::NOTES as $note) {
            $counts[$note] = $this->count($note) + $sign * $other->count($note);
        }

        return new self($counts, $this->coins + $sign * $other->coins);
    }

    private static function clean(mixed $value): float
    {
        return is_numeric($value) && $value > 0 ? (float) $value : 0.0;
    }
}
