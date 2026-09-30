<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional Idempotency-Key header on API writes (I5). The first successful
 * response for a key is stored for 24 hours; a retry with the same key and
 * the same request gets that response again instead of creating a second
 * payment, invoice or journal. Reusing a key for a different request is
 * refused, and a retry while the first request is still running gets 409.
 */
class EnsureIdempotency
{
    public const HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);
        $user = $request->user();

        if ($request->isMethodSafe() || $key === null || $key === '' || $user === null) {
            return $next($request);
        }

        if (strlen($key) > 255) {
            return $this->error('The Idempotency-Key header may not be longer than 255 characters.', 422);
        }

        $route = $request->method().' '.($request->route()?->uri() ?? $request->path());
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($this->sorted($request->all())));

        $existing = IdempotencyKey::where('user_id', $user->getAuthIdentifier())->where('key', $key)->first();
        // An expired key, or one whose first request died without an answer
        // (still empty after 10 minutes), starts afresh.
        if ($existing !== null && ($existing->isExpired()
            || ($existing->status_code === null && $existing->created_at?->lt(now()->subMinutes(10))))) {
            $existing->delete();
            $existing = null;
        }

        if ($existing !== null) {
            return $this->replay($existing, $route, $hash);
        }

        try {
            $record = IdempotencyKey::create([
                'user_id' => $user->getAuthIdentifier(),
                'key' => $key,
                'route' => $route,
                'request_hash' => $hash,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another request with this key got in first.
            $other = IdempotencyKey::where('user_id', $user->getAuthIdentifier())->where('key', $key)->first();

            return $other !== null
                ? $this->replay($other, $route, $hash)
                : $this->error('A request with this Idempotency-Key is still being processed.', 409);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $record->delete();

            throw $e;
        }

        // Only a successful result is kept; after an error the client may
        // fix the request and retry with the same key.
        if ($response->isSuccessful()) {
            $record->update([
                'status_code' => $response->getStatusCode(),
                'response_body' => (string) $response->getContent(),
                'content_type' => $response->headers->get('Content-Type'),
            ]);
        } else {
            $record->delete();
        }

        return $response;
    }

    private function replay(IdempotencyKey $existing, string $route, string $hash): Response
    {
        if ($existing->route !== $route || ! hash_equals($existing->request_hash, $hash)) {
            return $this->error('This Idempotency-Key was already used for a different request.', 422);
        }

        if ($existing->status_code === null) {
            return $this->error('A request with this Idempotency-Key is still being processed.', 409);
        }

        return response((string) $existing->response_body, $existing->status_code, [
            'Content-Type' => $existing->content_type ?? 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }

    private function error(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function sorted(array $data): array
    {
        ksort($data);
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->sorted($v);
            }
        }

        return $data;
    }
}
