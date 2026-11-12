<?php

use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
});

it('shows the issue with its stage, settlement, accounting and timeline, and no related links', function () {
    $issue = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now(), 'posted_at' => now()]);
    $settlement = SalesSettlement::factory()->create(['goods_issue_id' => $issue->id, 'status' => 'draft']);

    $this->actingAs($this->user)->get(route('goods-issues.show', $issue))
        ->assertSuccessful()
        ->assertSee('Settlement draft · '.$settlement->settlement_number)
        ->assertSeeInOrder(['Items issued', 'Settlement', 'Accounting', 'Timeline'])
        ->assertSee('Download as PDF (A4)')
        ->assertSee('data-topic="gd-stage"', false)
        ->assertDontSee('id="gi-related"', false);
});

it('tells how long an unsettled issue has been on the van', function () {
    $issue = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()->subDays(3), 'posted_at' => now()->subDays(3)]);

    $this->actingAs($this->user)->get(route('goods-issues.show', $issue))
        ->assertSuccessful()
        ->assertSee('On van · 3 days');
});

it('downloads one issue as an A4 PDF, portrait by default and landscape on request', function () {
    $issue = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()]);

    $portrait = $this->actingAs($this->user)->get(route('goods-issues.show', [$issue, 'format' => 'pdf']));
    $portrait->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($portrait->headers->get('content-disposition'))->toContain('goods-issue-'.$issue->issue_number);

    $this->actingAs($this->user)->get(route('goods-issues.show', [$issue, 'format' => 'pdf', 'orientation' => 'landscape']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});
