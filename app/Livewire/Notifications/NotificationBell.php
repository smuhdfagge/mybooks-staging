<?php

namespace App\Livewire\Notifications;

use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The bell in the header.
 *
 * Fifteen of the notifications MyBooks sends already write a row to the
 * notifications table, and nothing was reading them: the bell was a
 * button that did nothing, under a red dot that was always lit. A dot
 * that is always on says nothing, and people learn to ignore it.
 *
 * So: the dot appears only when something is unread, the list shows what
 * happened, and opening one takes you to the record it is about. Opening
 * the list marks nothing — reading a notification is clicking it, or
 * clearing them all deliberately.
 */
class NotificationBell extends Component
{
    /** How many to show in the panel before "that is enough for a dropdown". */
    private const SHOWN = 8;

    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function unreadCount(): int
    {
        return (int) (auth()->user()?->unreadNotifications()->count() ?? 0);
    }

    /** @return Collection<int, array{id: string, message: string, unread: bool, at: Carbon|null, link: string|null}> */
    public function items(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return $user->notifications()
            ->latest()
            ->limit(self::SHOWN)
            ->get()
            ->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'message' => (string) ($n->data['message'] ?? 'Something happened'),
                'unread' => $n->read_at === null,
                // Carbon, so the view can say "3 hours ago" and show the
                // exact time on hover.
                'at' => $n->created_at,
                'link' => $this->linkFor($n),
            ])
            ->values();
    }

    public function openNotification(string $id): void
    {
        $user = auth()->user();
        $notification = $user?->notifications()->whereKey($id)->first();

        if (! $notification) {
            return;
        }

        $notification->markAsRead();
        $link = $this->linkFor($notification);

        $this->open = false;

        if ($link) {
            $this->redirect($link, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    /** Refresh when something elsewhere says a notification arrived. */
    #[On('notification-received')]
    public function refreshBell(): void {}

    /**
     * Where a notification points, when it points anywhere.
     *
     * Checked against the user's own permissions, because a link that
     * lands on "403 Forbidden" is worse than no link: the notification is
     * telling them about something they are not allowed to open.
     */
    private function linkFor(DatabaseNotification $notification): ?string
    {
        $data = $notification->data;
        $user = auth()->user();

        // Two of them carry their own address already.
        if (! empty($data['url']) && is_string($data['url'])) {
            return $data['url'];
        }

        $can = fn (string $permission) => $user?->can($permission) ?? false;

        return match ($data['type'] ?? '') {
            'invoice_overdue', 'invoice_payment_reminder', 'invoice_sent' => isset($data['invoice_id']) && $can('view invoices')
                ? route('invoices.show', $data['invoice_id'])
                : null,
            'bill_due' => isset($data['bill_id']) && $can('view bills')
                ? route('bills.show', $data['bill_id'])
                : null,
            'payment_received' => isset($data['payment_id']) && $can('view payments-received')
                ? route('payments-received.show', $data['payment_id'])
                : null,
            'leave_request' => isset($data['leave_id']) && $can('view leaves')
                ? route('leaves.show', $data['leave_id'])
                : null,
            'low_stock' => $can('view items') ? route('items.index') : null,
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.notifications.notification-bell', [
            'count' => $this->unreadCount(),
            'items' => $this->open ? $this->items() : collect(),
        ]);
    }
}
