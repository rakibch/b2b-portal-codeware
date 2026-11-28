<?php

use Illuminate\Support\Facades\Schedule;

// Requires: * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
Schedule::command('rfqs:expire')->dailyAt('00:10')->withoutOverlapping()->onOneServer();
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();
Schedule::command('auth:clear-resets')->everyFifteenMinutes()->onOneServer();
