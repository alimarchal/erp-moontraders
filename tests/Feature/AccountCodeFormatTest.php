<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

it('seeds 4-digit account codes', function () {
    $this->refreshDatabase();

    Artisan::call('db:seed');

    $codes = DB::table('chart_of_accounts')->pluck('account_code');

    expect($codes)->not->toBeEmpty();
    $codes->each(function ($code) {
        expect($code)->toMatch('/^\d{4}$/');
    });
});
