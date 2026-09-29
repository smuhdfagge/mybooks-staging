<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Queue worker for shared hosting
|--------------------------------------------------------------------------
| Emails, large payroll batches, imports and exports run on the queue. The
| scheduler empties it every minute unless QUEUE_WORK_FROM_SCHEDULER=false
| (only worth doing when Supervisor keeps "php artisan queue:work" running).
| --timeout lets the longest jobs (600s) finish.
*/
if (config('mybooks.queue_work_from_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --timeout=600 --tries=1')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
}

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
| Billing
|--------------------------------------------------------------------------
*/

// Expire ended subscriptions and send 7-day and 1-day renewal reminders
Schedule::command('subscriptions:expire')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Backups (finding O1)
|--------------------------------------------------------------------------
| Database and uploaded files, daily, to the disks in BACKUP_DISKS.
*/
if (config('mybooks.backup.enabled')) {
    Schedule::command('mybooks:backup')
        ->dailyAt('01:30')
        ->withoutOverlapping(120)
        ->onOneServer();
}
