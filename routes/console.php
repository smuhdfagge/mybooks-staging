<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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

// Hand waiting SMS / WhatsApp messages to the queue: ones held over quiet
// hours (21:00-07:00 Lagos) and retries (session 16)
Schedule::command('messages:send-queued')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Pull new bank transactions for linked bank accounts (session 17). Mono
// itself refreshes each account about once a day and calls the webhook when
// there is more; this catches anything the webhook missed.
Schedule::command('bankfeeds:sync')
    ->everyThreeHours()
    ->withoutOverlapping()
    ->onOneServer();

// NRS e-invoicing: ask about documents left pending, send failed ones again
// and warn about B2C invoices near the 24-hour limit (session 18)
Schedule::command('einvoice:retry')
    ->hourly()
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

// Post the automatic reversals of journals whose "reverse on" date has come
// (accruals, S8), daily at 00:20 so they are in before the working day.
Schedule::command('journals:post-reversals')
    ->dailyAt('00:20')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/journal-reversals.log'));

// Release the months due on prepaid expense and deferred revenue schedules
// (S9), daily at 00:25. A month is released on its last day.
Schedule::command('accruals:release')
    ->dailyAt('00:25')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/accrual-schedules.log'));

// Mark quotations past their expiry date as expired, daily at 00:30
Schedule::command('quotations:expire')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Data Retention & Purging
|--------------------------------------------------------------------------
*/

// Erase businesses closed by their owner more than 30 days ago (O7)
Schedule::command('tenants:purge-closed')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/tenant-purge.log'));

// Run data retention purge on the 1st of every month at 2:00 AM
Schedule::command('retention:purge --force')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/retention-purge.log'));

// Drop API idempotency keys older than 24 hours (I5)
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();

// Housekeeping (O6): forget failed jobs after 30 days, and check the
// activity log's tamper-evidence chain every week.
Schedule::command('queue:prune-failed', ['--hours' => 720])
    ->dailyAt('03:20')
    ->onOneServer();

Schedule::command('logs:verify')
    ->weeklyOn(0, '04:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/log-verify.log'))
    ->onFailure(fn () => Log::error('Activity log integrity check failed; see storage/logs/log-verify.log.'));

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

// Charge saved cards for subscriptions ending tomorrow, retry failed
// charges and warn about cards that expire first (session 15)
Schedule::command('subscriptions:auto-renew')
    ->dailyAt('06:00')
    ->withoutOverlapping(60)
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
