<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Vendor;
use App\Services\Statements\StatementBuilder;
use App\Services\Statements\StatementPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a customer or supplier their statement with the PDF attached
 * (session 10). Queued like invoice and quotation emails; the statement is
 * built when the email goes out. The database copy is the record that it
 * was sent.
 */
class StatementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type,
        public ?string $from,
        public string $to,
        public string $subject,
        public string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var Customer|Vendor $notifiable */
        $statement = app(StatementBuilder::class)->build($notifiable, $this->type, $this->from, $this->to);
        $tenant = $notifiable->tenant;

        $message = (new MailMessage)
            ->subject($this->subject)
            ->greeting("Hello {$notifiable->name},");
        foreach (preg_split('/\R{2,}/', trim($this->body)) ?: [] as $paragraph) {
            if (trim($paragraph) !== '') {
                $message->line(trim($paragraph));
            }
        }

        return $message
            ->salutation('Regards, '.($tenant->name ?? config('app.name')))
            ->attachData(app(StatementPdf::class)->render($statement), $statement->fileName(), ['mime' => 'application/pdf']);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'statement_sent',
            'statement_type' => $this->type,
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'message' => "Statement emailed to {$notifiable->email}",
        ];
    }
}
