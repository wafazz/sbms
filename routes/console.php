<?php

use Illuminate\Support\Facades\Schedule;

// Runs once a day. Cron on the server must call `php artisan schedule:run` every minute.
Schedule::command('invoices:mark-overdue')->dailyAt('00:10')->withoutOverlapping();
