<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's own password reset email, put on the queue.
 *
 * Out of the box it is sent during the request, so anything wrong with the
 * mail server — a refused password, a timeout — is thrown at whoever asked
 * to reset their password, as a 500 page. That is what happened on
 * mybooks.cloud: the SMTP password was wrong and /forgot-password broke.
 *
 * Queued, a mail problem makes the email late rather than taking the page
 * down, and the queue retries it instead of losing it. Every other email
 * MyBooks sends already works this way.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
