<?php

namespace App\Http\Controllers;

use App\Jobs\PullBankFeed;
use App\Models\BankFeedConnection;
use App\Services\BankFeeds\BankFeedException;
use App\Services\BankFeeds\BankFeedLinker;
use App\Services\BankFeeds\MonoProvider;
use App\Services\BankFeeds\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Events from Mono (session 17). Set the webhook URL on the Mono dashboard
 * to https://<your domain>/webhooks/bank-feeds/mono and put the secret you
 * choose there in MONO_WEBHOOK_SECRET: Mono sends it back in the
 * mono-webhook-secret header (https://docs.mono.co/docs/webhooks, checked
 * 2026-10-09). Without that secret every call is refused.
 *
 *  - account_connected: the customer finished linking (also reached by the
 *    redirect; whichever comes first completes it);
 *  - account_updated: new data is ready, so pull it (or, when it says
 *    REAUTHORISATION_REQUIRED, ask the business to log in to the bank again);
 *  - account_reauthorized: working again.
 *
 * The answer is always 200 for a genuine call, so Mono does not retry events
 * that MyBooks has nothing to do for.
 */
class BankFeedWebhookController extends Controller
{
    public function mono(Request $request, MonoProvider $mono, BankFeedLinker $linker): JsonResponse
    {
        if (! $mono->verifyWebhook($request)) {
            Log::warning('Mono webhook refused (bad or missing secret)', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid'], 401);
        }

        $event = $mono->parseWebhook((array) $request->json()->all());

        try {
            $connection = $event->type === WebhookEvent::CONNECTED ? null : $this->connection($event);
            match ($event->type) {
                WebhookEvent::CONNECTED => $event->ref ? $linker->complete($event->ref, null, $event->accountId) : null,
                WebhookEvent::UPDATED => $connection && $event->hasNewData !== false ? PullBankFeed::dispatch($connection->id) : null,
                WebhookEvent::REAUTH_REQUIRED => $connection ? $linker->markNeedsReauthorisation($connection) : null,
                WebhookEvent::REAUTHORISED => $connection ? $linker->reauthorised($connection) : null,
                default => null,
            };
        } catch (BankFeedException $e) {
            // Nothing the sender can fix by retrying.
            Log::warning('Mono webhook could not be applied', ['type' => $event->type, 'error' => $e->getMessage()]);
        }

        return response()->json(['received' => true]);
    }

    private function connection(WebhookEvent $event): ?BankFeedConnection
    {
        if (! $event->accountId) {
            return null;
        }

        return BankFeedConnection::withoutGlobalScopes()->where('provider_account_id', $event->accountId)
            ->where('status', '!=', 'unlinked')->first();
    }
}
