<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Services\Billing\PaystackGateway;
use App\Services\Billing\SubscriptionBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Where Paystack sends customers and events back to (finding C1).
 */
class BillingController extends Controller
{
    public function __construct(private SubscriptionBilling $billing) {}

    /**
     * The customer's browser comes back here after paying. The query string
     * is not trusted: the payment is looked up with Paystack directly.
     */
    public function callback(Request $request): RedirectResponse
    {
        $reference = (string) $request->query('reference', $request->query('trxref', ''));

        if ($reference === '') {
            return redirect()->route('settings.subscription')->with('error', 'No payment reference was returned.');
        }

        try {
            $payment = $this->billing->verifyAndApply($reference);
        } catch (Throwable $e) {
            Log::error('Paystack callback verification failed', ['reference' => $reference, 'error' => $e->getMessage()]);

            return redirect()->route('settings.subscription')
                ->with('error', "We couldn't confirm your payment yet. If you were charged, it will show here within a few minutes.");
        }

        if ($payment?->status === SubscriptionPayment::STATUS_SUCCESS) {
            return redirect()->route('settings.subscription')
                ->with('success', 'Payment received. Your subscription is active until '.$payment->subscription?->ends_at?->format('M d, Y').'.');
        }

        return redirect()->route('settings.subscription')
            ->with('error', 'The payment was not completed. You have not been charged for it; please try again.');
    }

    /**
     * Paystack's server calls this for each event. Only signed requests are
     * acted on.
     */
    public function webhook(Request $request, PaystackGateway $gateway): JsonResponse
    {
        $payload = $request->getContent();

        if (! $gateway->hasValidSignature($payload, $request->header('x-paystack-signature'))) {
            Log::warning('Paystack webhook with a bad signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = json_decode($payload, true) ?: [];

        if (($event['event'] ?? null) === 'charge.success') {
            $this->billing->applyCharge((array) ($event['data'] ?? []));
        }

        return response()->json(['received' => true]);
    }
}
