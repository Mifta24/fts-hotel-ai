<?php

use App\Core\Member\Models\MemberLoginCode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:extend')->daily();
Schedule::command('bookings:expire-pending')->hourly()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [MemberLoginCode::class]])->daily();
Schedule::command('bookings:complete-stays')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('members:award-points')->dailyAt('03:00')->withoutOverlapping();
