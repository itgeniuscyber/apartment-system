<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rent:apply-scheduled-rates')
    ->dailyAt('00:05')
    ->timezone('Africa/Kampala')
    ->withoutOverlapping();

Schedule::command('rent:send-reminders')
    ->dailyAt('08:00')
    ->timezone('Africa/Kampala')
    ->withoutOverlapping();

Schedule::command('loans:send-reminders')
    ->dailyAt('08:15')
    ->timezone('Africa/Kampala')
    ->withoutOverlapping();
