<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails server errors to the people who look after MyBooks (finding O2).
 *
 * No extra packages: errors are already written to the log; this makes
 * sure someone hears about them. Each distinct error (class, file, line)
 * is sent at most once an hour, and no more than max_per_hour emails go
 * out in total, so a failing page can't flood the inbox.
 *
 * The email carries the error, where it happened, the page path and the
 * user and business IDs. No form input, query string, cookies or headers,
 * so no customer or payroll data leaves the server.
 */
class ErrorAlerter
{
    public function report(Throwable $e): void
    {
        $to = config('mybooks.error_alerts.email');
        if (! $to || ! config('mybooks.error_alerts.enabled')) {
            return;
        }

        try {
            $signature = sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine());

            // One email per distinct error per hour...
            if (! Cache::add('error-alert:'.$signature, 1, now()->addHour())) {
                return;
            }
            // ...and a ceiling on all alerts in the hour.
            $countKey = 'error-alert:count:'.now()->format('YmdH');
            Cache::add($countKey, 0, now()->addHours(2));
            if (Cache::increment($countKey) > (int) config('mybooks.error_alerts.max_per_hour', 20)) {
                return;
            }

            Mail::raw($this->body($e), function ($message) use ($to, $e) {
                $message->to($to)->subject(sprintf(
                    '[%s] %s: %s',
                    config('app.name', 'MyBooks'),
                    class_basename($e),
                    str($e->getMessage())->limit(80)
                ));
            });
        } catch (Throwable $alertFailure) {
            // Never let the alert itself break the request or loop back here.
            Log::warning('Could not send the error alert: '.$alertFailure->getMessage());
        }
    }

    protected function body(Throwable $e): string
    {
        $request = app()->runningInConsole() ? null : request();
        $user = $request?->user();

        $lines = [
            'Error:    '.get_class($e),
            'Message:  '.str($e->getMessage())->limit(500),
            'Where:    '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine(),
            'When:     '.now()->toDateTimeString().' ('.config('app.timezone').')',
            'Server:   '.config('app.url').' ('.app()->environment().')',
        ];

        if ($request) {
            $lines[] = 'Page:     '.$request->method().' /'.ltrim($request->path(), '/');
            $lines[] = 'Route:    '.($request->route()?->getName() ?? '-');
        } else {
            $lines[] = 'Page:     console / queue';
        }

        $lines[] = 'User ID:  '.($user ? $user->id : '-');
        $lines[] = 'Business: '.($user ? $user->tenant_id : '-');
        $lines[] = '';
        $lines[] = 'The full stack trace is in storage/logs on the server. This error';
        $lines[] = 'will not be emailed again for an hour.';

        return implode("\n", $lines);
    }
}
