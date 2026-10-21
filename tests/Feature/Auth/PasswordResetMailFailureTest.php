<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * What happens to /forgot-password when the mail server will not take the
 * message. On mybooks.cloud the SMTP password was wrong, and because
 * Laravel sends its password reset email during the request, the person
 * asking to reset their password got a 500 page instead.
 *
 * Their own mail trouble is not their problem to look at.
 */
class PasswordResetMailFailureTest extends TestCase
{
    use RefreshDatabase;

    /** Point the mailer at a port with nothing behind it. */
    private function breakTheMailServer(): void
    {
        config([
            'queue.default' => 'sync',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 1,
        ]);
    }

    public function test_the_reset_email_is_queued_rather_than_sent_during_the_request(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertInstanceOf(ShouldQueue::class, new ResetPasswordNotification('token'));
    }

    public function test_the_verification_email_is_queued_too(): void
    {
        // Registration would break the same way, and leave a half-made
        // account behind it.
        $this->assertInstanceOf(ShouldQueue::class, new VerifyEmailNotification);
    }

    public function test_a_mail_server_that_refuses_the_message_does_not_break_the_page(): void
    {
        Log::spy();
        $this->breakTheMailServer();
        $user = User::factory()->create();

        $response = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('status');
        $response->assertSessionHasNoErrors();
    }

    public function test_it_writes_the_mail_failure_to_the_log(): void
    {
        // Swallowed quietly, nobody would know the emails had stopped.
        Log::spy();
        $this->breakTheMailServer();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_an_unknown_address_answers_the_same_way_when_mail_is_broken(): void
    {
        $this->breakTheMailServer();

        $known = $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => User::factory()->create()->email]);
        $unknown = $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'nobody@example.com']);

        // A broken mail server must not become a way of asking which
        // addresses have an account.
        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame(
            $known->getSession()->get('status'),
            $unknown->getSession()->get('status')
        );
    }
}
