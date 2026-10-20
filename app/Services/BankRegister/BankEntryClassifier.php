<?php

namespace App\Services\BankRegister;

use App\Models\BankCheque;
use App\Models\BankEntryRule;
use App\Models\BankPartyAlias;
use App\Models\BankStatementEntry;
use App\Models\Company;
use Illuminate\Support\Collection;

/**
 * Fills in company / category on a statement line from, in order: the party alias table, the
 * auto-classify rules and the cheque register. It never overwrites what a person already chose.
 */
class BankEntryClassifier
{
    /** @var Collection<string, BankPartyAlias>|null */
    private ?Collection $aliases = null;

    /** @var Collection<int, BankEntryRule>|null */
    private ?Collection $rules = null;

    /** @var array<int, Collection<int, BankCheque>> */
    private array $cheques = [];

    /**
     * Forget cached aliases, rules and cheques (call after they change within one request).
     */
    public function refresh(): self
    {
        $this->aliases = null;
        $this->rules = null;
        $this->cheques = [];

        return $this;
    }

    /**
     * @return bool true when anything on the entry changed
     */
    public function classify(BankStatementEntry $entry, bool $save = true): bool
    {
        if ($entry->isLocked() || in_array($entry->status, [BankStatementEntry::STATUS_PENDING_APPROVAL], true)) {
            return false;
        }

        $changed = false;

        $changed = $this->applyAlias($entry) || $changed;
        $changed = $this->applyRules($entry) || $changed;
        $changed = $this->applyCheque($entry) || $changed;

        if ($entry->bank_entry_category_id && $entry->status === BankStatementEntry::STATUS_UNCLASSIFIED) {
            $entry->status = BankStatementEntry::STATUS_CLASSIFIED;
            $entry->classified_at = now();
            $entry->classified_by = auth()->id();
            $changed = true;
        }

        if ($changed && $save) {
            $entry->save();
        }

        return $changed;
    }

    /**
     * Run the classifier over every unclassified line (optionally limited to one import).
     *
     * @return int number of lines that changed
     */
    public function classifyPending(?int $importId = null): int
    {
        $this->refresh();
        $changed = 0;

        BankStatementEntry::query()
            ->whereIn('status', [BankStatementEntry::STATUS_UNCLASSIFIED, BankStatementEntry::STATUS_REJECTED])
            ->when($importId, fn ($query) => $query->where('import_id', $importId))
            ->orderBy('id')
            ->chunkById(500, function ($entries) use (&$changed) {
                foreach ($entries as $entry) {
                    if ($this->classify($entry)) {
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    private function applyAlias(BankStatementEntry $entry): bool
    {
        if ($entry->bank_party_alias_id || ! $entry->party_name_in_statement) {
            return false;
        }

        $alias = $this->aliases()->get(BankPartyAlias::normalize($entry->party_name_in_statement));

        if (! $alias) {
            return false;
        }

        $entry->bank_party_alias_id = $alias->id;
        $entry->company_id ??= $alias->company_id;

        if (! $entry->counterparty_id && $alias->counterparty_id) {
            $entry->counterparty_type = $alias->counterparty_type;
            $entry->counterparty_id = $alias->counterparty_id;
        }

        return true;
    }

    private function applyRules(BankStatementEntry $entry): bool
    {
        if ($entry->bank_entry_category_id) {
            return false;
        }

        $rule = $this->rules()->first(fn (BankEntryRule $rule) => $rule->matches($entry));

        if (! $rule) {
            return false;
        }

        $entry->bank_entry_category_id = $rule->bank_entry_category_id;
        $entry->company_id ??= $rule->company_id;

        return true;
    }

    /**
     * An issued/received cheque whose number appears in the reference and whose amount matches
     * is marked cleared and linked to the statement line.
     */
    private function applyCheque(BankStatementEntry $entry): bool
    {
        $direction = $entry->direction === 'debit' ? 'issued' : 'received';
        $numbers = $this->chequeNumbersIn($entry);

        if ($numbers === []) {
            return false;
        }

        $cheque = $this->pendingCheques($entry->bank_account_id)
            ->first(fn (BankCheque $cheque) => $cheque->direction === $direction
                && in_array(ltrim($cheque->cheque_no, '0'), $numbers, true)
                && abs((float) $cheque->amount - $entry->amount) < 0.01);

        if (! $cheque) {
            return false;
        }

        $cheque->update([
            'status' => 'cleared',
            'cleared_date' => $entry->posting_date,
            'bank_statement_entry_id' => $entry->id,
        ]);
        $this->cheques[$entry->bank_account_id] = $this->pendingCheques($entry->bank_account_id)->reject(fn ($c) => $c->id === $cheque->id)->values();

        $entry->company_id ??= $cheque->company_id;

        if (! $entry->counterparty_id && $cheque->counterparty_id) {
            $entry->counterparty_type = $cheque->counterparty_type;
            $entry->counterparty_id = $cheque->counterparty_id;
        }

        return true;
    }

    /**
     * @return array<int, string> digit runs of 4+ characters, leading zeros removed
     */
    private function chequeNumbersIn(BankStatementEntry $entry): array
    {
        preg_match_all('/\d{4,}/', (string) $entry->reference, $matches);

        return collect($matches[0])->map(fn (string $number) => ltrim($number, '0'))->filter()->unique()->values()->all();
    }

    /**
     * @return Collection<string, BankPartyAlias>
     */
    private function aliases(): Collection
    {
        return $this->aliases ??= BankPartyAlias::query()->where('is_active', true)->get()->keyBy('alias_name');
    }

    /**
     * @return Collection<int, BankEntryRule>
     */
    private function rules(): Collection
    {
        return $this->rules ??= BankEntryRule::query()->active()->orderBy('priority')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, BankCheque>
     */
    private function pendingCheques(int $bankAccountId): Collection
    {
        return $this->cheques[$bankAccountId] ??= BankCheque::query()
            ->where('bank_account_id', $bankAccountId)
            ->where('status', 'pending')
            ->get();
    }

    /**
     * Resolve a company by exact name, used when the sheet carries a principal's name.
     */
    public function companyByName(?string $name): ?Company
    {
        if (! $name) {
            return null;
        }

        $matches = Company::query()->whereRaw('LOWER(company_name) = ?', [mb_strtolower(trim($name))])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
