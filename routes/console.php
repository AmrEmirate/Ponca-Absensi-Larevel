<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cron Job: Mark ALPA setiap hari pukul 00:00 WIB
Schedule::command('attendance:mark-alpa')->dailyAt('00:00')->timezone('Asia/Jakarta');
