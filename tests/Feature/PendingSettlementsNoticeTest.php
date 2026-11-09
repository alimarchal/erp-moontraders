<?php

use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\User;
use App\Notifications\PendingSettlementsNotice;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->superAdmin = User::factory()->create(['is_super_admin' => 'Yes', 'is_active' => 'Yes']);
    $this->admin = User::factory()->create(['is_super_admin' => 'No', 'is_active' => 'Yes']);
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
    $this->retiredAdmin = User::factory()->create(['is_super_admin' => 'Yes', 'is_active' => 'No']);
    $this->clerk = User::factory()->create(['name' => 'AKASH', 'is_super_admin' => 'No', 'is_active' => 'Yes']);
    $this->loader = User::factory()->create(['name' => 'ABDULLAH', 'is_super_admin' => 'No', 'is_active' => 'Yes']);
});

/**
 * A settlement saved as draft by $user for yesterday, against an issue already on the van.
 */
function draftSettlementBy(User $user, ?string $date = null): SalesSettlement
{
    $date ??= today()->subDay()->toDateString();

    Auth::login($user);
    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => GoodsIssue::factory()->create(['status' => 'issued', 'issued_by' => $user->id, 'issue_date' => $date]),
        'settlement_date' => $date,
        'status' => 'draft',
        'total_sales_amount' => 1622933.60,
    ]);
    Auth::logout();

    return $settlement;
}

it('emails each user their own unposted documents from before today, and every active admin all of them', function () {
    $draftSettlement = draftSettlementBy($this->clerk);
    $issueWithoutSettlement = GoodsIssue::factory()->create(['status' => 'issued', 'issued_by' => $this->loader->id, 'issue_date' => today()->subDays(3)]);
    $draftIssue = GoodsIssue::factory()->create(['status' => 'draft', 'issued_by' => $this->loader->id, 'issue_date' => today()->subDay()]);

    // Today's work is still under way when the notice goes, so it is left out.
    draftSettlementBy($this->clerk, today()->toDateString());
    GoodsIssue::factory()->create(['status' => 'draft', 'issued_by' => $this->loader->id, 'issue_date' => today()]);

    $settled = draftSettlementBy($this->loader);
    $settled->update(['status' => 'posted']);

    artisan('settlements:notify-pending')->assertSuccessful();

    foreach ([$this->superAdmin, $this->admin] as $admin) {
        Notification::assertSentTo($admin, PendingSettlementsNotice::class, fn (PendingSettlementsNotice $notice) => $notice->isOverview
            && collect($notice->sections['draft_settlements'])->pluck('number')->all() === [$draftSettlement->settlement_number]
            && collect($notice->sections['unsettled_issues'])->pluck('number')->all() === [$issueWithoutSettlement->issue_number]
            && collect($notice->sections['draft_issues'])->pluck('number')->all() === [$draftIssue->issue_number]);
    }

    Notification::assertSentTo($this->clerk, PendingSettlementsNotice::class, fn (PendingSettlementsNotice $notice) => ! $notice->isOverview
        && collect($notice->sections['draft_settlements'])->pluck('number')->all() === [$draftSettlement->settlement_number]
        && $notice->sections['unsettled_issues'] === []
        && $notice->sections['draft_issues'] === []);

    Notification::assertSentTo($this->loader, PendingSettlementsNotice::class, fn (PendingSettlementsNotice $notice) => $notice->sections['draft_settlements'] === []
        && count($notice->sections['unsettled_issues']) === 1
        && count($notice->sections['draft_issues']) === 1);

    Notification::assertNotSentTo($this->retiredAdmin, PendingSettlementsNotice::class);
    Notification::assertCount(4);
});

it('sends nothing when every goods issue has a posted settlement', function () {
    draftSettlementBy($this->clerk)->update(['status' => 'posted']);

    artisan('settlements:notify-pending')->assertSuccessful();

    Notification::assertNothingSent();
});

it('writes the notice right to left in Urdu, with a link to open the settlement', function () {
    $settlement = draftSettlementBy($this->clerk);

    artisan('settlements:notify-pending');

    $notice = Notification::sent($this->clerk, PendingSettlementsNotice::class)->first();
    $mail = $notice->toMail($this->clerk);
    $html = (string) $mail->render();

    expect($mail->subject)->toStartWith('نوٹس: 1 سیل سیٹلمنٹ پوسٹ نہیں ہوئی')
        ->and($html)->toContain('dir="rtl"')
        ->toContain('Noto Nastaliq Urdu')
        ->toContain('السلام علیکم AKASH صاحب')
        ->toContain('سیل سیٹلمنٹ ڈرافٹ میں ہے، پوسٹ نہیں ہوئی')
        ->toContain(route('sales-settlements.edit', $settlement))
        ->toContain('1,622,934');
});

it('runs every morning at seven', function () {
    $notice = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command, 'settlements:notify-pending'));

    expect($notice->expression)->toBe('0 7 * * *');
});
