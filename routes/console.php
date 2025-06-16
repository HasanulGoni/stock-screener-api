<?php

use App\Jobs\TestJob;
use App\Jobs\FetchNewsData;

use App\Jobs\FetchStockData;
use App\Jobs\TriggerMomentumAlerts;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new TestJob())->everyMinute();
Schedule::call(function () {
    // This is a placeholder for any scheduled tasks that need to run
    // You can add any custom logic here if needed
    Log::info('Scheduled task executed at ' . now());
})->everyMinute();
Schedule::job(new FetchStockData())->everyMinute();
Schedule::job(new FetchNewsData())->everyFiveMinutes();
Schedule::job(new TriggerMomentumAlerts())->everyMinute();

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');
