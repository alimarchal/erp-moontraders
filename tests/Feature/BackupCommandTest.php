<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('backup');
    Storage::fake('google');
});

it('runs the database-only backup command successfully', function () {
    $this->artisan('backup:run --only-db')
        ->assertSuccessful();
});

it('stores the backup zip on the backup disk', function () {
    $this->artisan('backup:run --only-db');

    $appName = config('backup.backup.name');
    $files = Storage::disk('backup')->allFiles($appName);

    expect($files)->not->toBeEmpty()
        ->and(collect($files)->first())->toEndWith('.zip');
});

it('stores the backup zip on the google disk', function () {
    $this->artisan('backup:run --only-db');

    $appName = config('backup.backup.name');
    $files = Storage::disk('google')->allFiles($appName);

    expect($files)->not->toBeEmpty()
        ->and(collect($files)->first())->toEndWith('.zip');
});

it('schedules backup:run --only-db at 06:00 and 23:50', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'backup:run'))
        ->values();

    expect($scheduled)->toHaveCount(2);

    $times = $scheduled->map(fn ($e) => $e->expression)->sort()->values();

    expect($times[0])->toBe('45 6 * * *')
        ->and($times[1])->toBe('50 23 * * *');
});
