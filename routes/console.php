<?php

use Illuminate\Support\Facades\Schedule;

// Daily backup at 2 AM India time. Needs one cron job on the server: php artisan schedule:run (every minute),
// or run "php artisan smiledesk:backup" once a day directly from cron.
Schedule::command('smiledesk:backup')->dailyAt('02:00');
