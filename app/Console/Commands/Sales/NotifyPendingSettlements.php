<?php

namespace App\Console\Commands\Sales;

use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\User;
use App\Notifications\PendingSettlementsNotice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[Signature('settlements:notify-pending')]
#[Description('Email, in Urdu, every goods issue dated before today whose sale is not yet posted: draft settlements, issues on a van with no settlement, and draft issues. Each user gets their own; super admins and admins get the whole list.')]
class NotifyPendingSettlements extends Command
{
    private const SECTIONS = ['draft_settlements', 'unsettled_issues', 'draft_issues'];

    public function handle(): int
    {
        $rows = collect()
            ->merge($this->draftSettlements())
            ->merge($this->issuesWithoutSettlement())
            ->merge($this->draftIssues());

        if ($rows->isEmpty()) {
            $this->info('Nothing pending: every goods issue dated before today has a posted settlement.');

            return self::SUCCESS;
        }

        $overviewRecipients = User::query()
            ->where('is_active', 'Yes')
            ->whereNotNull('email')
            ->where(fn ($query) => $query->where('is_super_admin', 'Yes')
                ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['super-admin', 'admin'])))
            ->get();

        foreach ($overviewRecipients as $recipient) {
            $recipient->notify(new PendingSettlementsNotice($this->toSections($rows), isOverview: true));
        }

        $owners = User::query()
            ->whereKey($rows->pluck('owner_id')->filter()->unique())
            ->whereKeyNot($overviewRecipients->modelKeys())
            ->where('is_active', 'Yes')
            ->whereNotNull('email')
            ->get();

        foreach ($owners as $owner) {
            $owner->notify(new PendingSettlementsNotice($this->toSections($rows->where('owner_id', $owner->id)), isOverview: false));
        }

        $this->info(sprintf(
            'Pending: %d draft settlement(s), %d issue(s) without a settlement, %d draft issue(s). Notified %d admin(s) and %d user(s).',
            $rows->where('section', 'draft_settlements')->count(),
            $rows->where('section', 'unsettled_issues')->count(),
            $rows->where('section', 'draft_issues')->count(),
            $overviewRecipients->count(),
            $owners->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Settlements dated before today still saved as draft: the sale is entered but not in the books.
     * Today's are left out, as the day's settling is still under way when the notice goes.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function draftSettlements(): Collection
    {
        $settlements = SalesSettlement::with(['goodsIssue', 'employee', 'vehicle'])
            ->where('status', 'draft')
            ->whereDate('settlement_date', '<', today())
            ->orderBy('settlement_date')
            ->get();
        $creators = User::whereKey($settlements->pluck('created_by')->filter()->unique())->pluck('name', 'id');

        return $settlements->map(fn (SalesSettlement $settlement) => [
            'section' => 'draft_settlements',
            'owner_id' => $settlement->created_by,
            'number' => $settlement->settlement_number,
            'url' => route('sales-settlements.edit', $settlement),
            'goods_issue' => $settlement->goodsIssue?->issue_number,
            'date' => $settlement->settlement_date,
            'salesman' => $settlement->employee?->name,
            'vehicle' => $settlement->vehicle?->vehicle_number,
            'amount' => (float) $settlement->total_sales_amount,
            'owner' => $creators[$settlement->created_by] ?? '—',
            'days' => $this->daysSince($settlement->settlement_date),
        ]);
    }

    /**
     * Issues whose stock is on the van but which have no draft or posted settlement.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function issuesWithoutSettlement(): Collection
    {
        return $this->issueRows(
            GoodsIssue::where('status', 'issued')
                ->whereDoesntHave('settlement', fn ($query) => $query->whereIn('status', ['draft', 'posted'])),
            'unsettled_issues',
        );
    }

    /**
     * Issues still in draft: nothing has left the warehouse yet.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function draftIssues(): Collection
    {
        return $this->issueRows(GoodsIssue::where('status', 'draft'), 'draft_issues');
    }

    /**
     * @param  Builder<GoodsIssue>  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function issueRows($query, string $section): Collection
    {
        return $query->with(['employee', 'vehicle', 'issuedBy'])
            ->whereDate('issue_date', '<', today())
            ->orderBy('issue_date')
            ->get()
            ->map(fn (GoodsIssue $issue) => [
                'section' => $section,
                'owner_id' => $issue->issued_by,
                'number' => $issue->issue_number,
                'url' => route('goods-issues.show', $issue),
                'goods_issue' => $issue->issue_number,
                'date' => $issue->issue_date,
                'salesman' => $issue->employee?->name,
                'vehicle' => $issue->vehicle?->vehicle_number,
                'amount' => (float) $issue->total_value,
                'owner' => $issue->issuedBy?->name ?? '—',
                'days' => $this->daysSince($issue->issue_date),
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{draft_settlements: list<array<string, mixed>>, unsettled_issues: list<array<string, mixed>>, draft_issues: list<array<string, mixed>>}
     */
    private function toSections(Collection $rows): array
    {
        return collect(self::SECTIONS)
            ->mapWithKeys(fn (string $section) => [$section => $rows->where('section', $section)->values()->all()])
            ->all();
    }

    private function daysSince(mixed $date): int
    {
        return $date ? (int) max(0, Carbon::parse($date)->startOfDay()->diffInDays(today())) : 0;
    }
}
