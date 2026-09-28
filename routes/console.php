<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Notification Commands
|--------------------------------------------------------------------------
*/

// Send payment reminders daily at 8:00 AM
Schedule::command('notifications:send-payment-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

// Send bill reminders daily at 8:00 AM
Schedule::command('notifications:send-bill-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

// Send low stock alerts daily at 9:00 AM
Schedule::command('notifications:send-low-stock-alerts')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Scheduled Recurring Transaction Processing
|--------------------------------------------------------------------------
*/

// Process recurring invoices, bills, and expenses daily at 6:00 AM
Schedule::command('transactions:process-recurring')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/recurring-transactions.log'));

/*
|--------------------------------------------------------------------------
| Data Retention & Purging
|--------------------------------------------------------------------------
*/

// Run data retention purge on the 1st of every month at 2:00 AM
Schedule::command('retention:purge --force')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/retention-purge.log'));

/*
|--------------------------------------------------------------------------
| Queue worker for shared hosting
|--------------------------------------------------------------------------
| Large payroll batches, imports and exports run on the queue. If the server
| can't keep "php artisan queue:work" running (shared hosting), set
| QUEUE_WORK_FROM_SCHEDULER=true and the scheduler empties the queue every
| minute instead.
*/
if (config('mybooks.queue_work_from_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
}
