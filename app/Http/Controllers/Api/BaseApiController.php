<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseApiController extends Controller
{
    /**
     * API Version
     */
    protected const API_VERSION = '1.0';

    /**
     * Maximum allowed per_page value to prevent DoS via large result sets.
     */
    protected const MAX_PER_PAGE = 100;

    /**
     * Validate and sanitize sort parameters against an allowed column whitelist.
     *
     * @return array{string, string} [$sortBy, $sortOrder]
     */
    protected function validateSortParameters(Request $request, array $allowedColumns, string $default = 'created_at'): array
    {
        $sortBy = $request->input('sort_by', $default);
        $sortBy = in_array($sortBy, $allowedColumns, true) ? $sortBy : $default;
        $sortOrder = $request->input('sort_order', 'asc') === 'desc' ? 'desc' : 'asc';

        return [$sortBy, $sortOrder];
    }

    /**
     * Get a validated per_page value, capped at MAX_PER_PAGE.
     */
    protected function validatedPerPage(Request $request, int $default = 15): int
    {
        return min(max((int) $request->input('per_page', $default), 1), static::MAX_PER_PAGE);
    }

    /**
     * Add API version headers to response
     */
    protected function withVersionHeaders(JsonResponse $response): JsonResponse
    {
        return $response->withHeaders([
            'X-API-Version' => self::API_VERSION,
            'X-API-Deprecated' => 'false',
        ]);
    }

    /**
     * Return a success response
     */
    protected function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        $response = response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);

        return $this->withVersionHeaders($response);
    }

    /**
     * Return a created response
     */
    protected function created($data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Return an error response
     */
    protected function error(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return $this->withVersionHeaders(response()->json($response, $code));
    }

    /**
     * Return a not found response
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Return an unauthorized response
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * Return a forbidden response
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Return a validation error response
     */
    protected function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * Get the current authenticated user's tenant ID
     */
    protected function getTenantId(): ?int
    {
        return auth()->user()?->tenant_id;
    }

    /**
     * Get paginated response
     */
    protected function paginated($paginator, string $message = 'Success'): JsonResponse
    {
        $response = response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);

        return $this->withVersionHeaders($response);
    }
}
