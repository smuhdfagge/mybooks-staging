<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's own "confirm your email address" message, put on the queue, for
 * the same reason as ResetPasswordNotification: sent during the request, a
 * mail server problem would break registration itself rather than just
 * delaying the email.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
