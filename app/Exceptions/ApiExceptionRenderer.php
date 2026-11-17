<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every API error in one shape:
 *   { "message": string, "errors"?: { field: [string] } }
 * Validation errors are the only ones that carry "errors".
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422),

            $e instanceof AuthenticationException => response()->json([
                'message' => 'Unauthenticated.',
            ], 401),

            $e instanceof AuthorizationException => response()->json([
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
            ], 403),

            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => response()->json([
                'message' => 'Resource not found.',
            ], 404),

            $e instanceof MethodNotAllowedHttpException => response()->json([
                'message' => 'Method not allowed.',
            ], 405),

            $e instanceof ThrottleRequestsException => response()->json([
                'message' => 'Too many attempts. Please try again later.',
            ], 429, $e->getHeaders()),

            $e instanceof HttpException => response()->json([
                'message' => $e->getMessage() ?: 'Request failed.',
            ], $e->getStatusCode(), $e->getHeaders()),

            default => response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Server error.',
            ], 500),
        };
    }
}
