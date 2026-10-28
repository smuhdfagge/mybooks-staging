<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A web form sent twice saves once (audit MB-09). The form carries a token
 * made when it was shown (<x-form-token />); the first successful save
 * remembers where it went, for 24 hours, in the same table as the API's
 * Idempotency-Key. The same token again (a double click, a refresh that
 * resends, the back button) goes to that first result instead of saving a
 * second payment. A save that failed (validation) frees the token, so the
 * corrected form can be sent again.
 */
class ReplayWebForm
{
    public const FIELD = 'form_token';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input(self::FIELD);
        $user = $request->user();

        if ($request->isMethodSafe() || $user === null || ! is_string($token) || ! preg_match('/^[A-Za-z0-9-]{16,64}$/', $token)) {
            return $next($request);
        }

        $key = 'form:'.$token;
        $route = $request->method().' '.($request->route()?->uri() ?? $request->path());

        $existing = IdempotencyKey::where('user_id', $user->getAuthIdentifier())->where('key', $key)->first();
        // Expired, or its first request died without an answer (still empty after 10 minutes): start afresh.
        if ($existing !== null && ($existing->isExpired()
            || ($existing->status_code === null && $existing->created_at?->lt(now()->subMinutes(10))))) {
            $existing->delete();
            $existing = null;
        }
        if ($existing !== null) {
            return $this->replay($existing);
        }

        try {
            $record = IdempotencyKey::create([
                'user_id' => $user->getAuthIdentifier(),
                'key' => $key,
                'route' => $route,
                'request_hash' => hash('sha256', $route),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same form sent twice at once: the other request is saving it.
            $other = IdempotencyKey::where('user_id', $user->getAuthIdentifier())->where('key', $key)->first();

            return $this->replay($other);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $record->delete();

            throw $e;
        }

        // Saved: a redirect with no errors flashed. Anything else frees the token.
        $session = $request->hasSession() ? $request->session() : null;
        $saved = $response->isRedirection() && ! $session?->has('errors') && ! $session?->has('error');
        if ($saved && ($location = $response->headers->get('Location'))) {
            $record->update(['status_code' => $response->getStatusCode(), 'response_body' => $location, 'content_type' => 'redirect']);
        } else {
            $record->delete();
        }

        return $response;
    }

    private function replay(?IdempotencyKey $record): Response
    {
        if ($record === null || $record->status_code === null || ! $record->response_body) {
            return back()->with('error', 'That form is already being saved. Wait a moment, then check the list before sending it again.');
        }

        return redirect()->to($record->response_body)
            ->with('success', 'This was already saved; it wasn\'t saved a second time.');
    }
}
