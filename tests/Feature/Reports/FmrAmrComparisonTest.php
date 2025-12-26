<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('fmr amr comparison report page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.fmr-amr-comparison.index'))
        ->assertSuccessful();
});

test('fmr amr comparison report loads with date filters', function () {
    $this->actingAs($this->user)
        ->get(route('reports.fmr-amr-comparison.index', [
            'filter' => [
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
            ],
        ]))
        ->assertSuccessful();
});

test('fmr amr comparison report requires authentication', function () {
    $this->get(route('reports.fmr-amr-comparison.index'))
        ->assertRedirect(route('login'));
});
