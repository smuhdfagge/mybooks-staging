<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Every error under /api is answered in the documented shape
 * {success: false, message, errors?}, whatever the Accept header says, and
 * internal messages are never shown (I6).
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        [$status, $message, $errors, $headers] = self::describe($e);

        $body = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        if ($status >= 500 && config('app.debug')) {
            $body['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
        }

        return response()->json($body, $status, $headers + [
            'X-API-Version' => '1.0',
            'X-API-Deprecated' => 'false',
        ]);
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, mixed>|null, 3: array<string, string>}
     */
    private static function describe(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return [$e->status, $e->getMessage(), $e->errors(), []];
        }
        if ($e instanceof AuthenticationException) {
            return [401, 'Unauthenticated.', null, []];
        }
        if ($e instanceof AuthorizationException) {
            return [403, $e->getMessage() ?: 'This action is unauthorized.', null, []];
        }
        if ($e instanceof ModelNotFoundException) {
            return [404, 'Resource not found.', null, []];
        }
        // Rule failures are the client's to fix, not server errors.
        if ($e instanceof BusinessRuleException || $e instanceof UnbalancedJournalException) {
            return [422, $e->getMessage(), null, []];
        }
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            // Laravel's 404 text names the model class or the route; hide it.
            $message = $status === 404 ? 'Resource not found.' : $e->getMessage();

            /** @var array<string, string> $headers */
            $headers = $e->getHeaders();

            return [$status, $message !== '' ? $message : (Response::$statusTexts[$status] ?? 'Error'), null, $headers];
        }

        return [500, 'Server error. Please try again later.', null, []];
    }
}
