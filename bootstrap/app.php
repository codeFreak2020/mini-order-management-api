<?php

use App\Exceptions\InsufficientStockException;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use App\Support\HttpStatus;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply the "api" rate limiter (throttle:api) to every /api route.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always render JSON for /api/* routes (and any request that expects JSON).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
        $exceptions->render(function (
            AuthenticationException|NotFoundHttpException|ValidationException|InsufficientStockException $e,
            Request $request,
        ) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof AuthenticationException => ApiResponse::error(ApiMessages::UNAUTHENTICATED, HttpStatus::UNAUTHORIZED),
                $e instanceof NotFoundHttpException => ApiResponse::error(ApiMessages::RESOURCE_NOT_FOUND, HttpStatus::NOT_FOUND),
                $e instanceof ValidationException => ApiResponse::error(ApiMessages::VALIDATION_FAILED, HttpStatus::UNPROCESSABLE_ENTITY, $e->errors()),
                $e instanceof InsufficientStockException => ApiResponse::error($e->getMessage(), HttpStatus::UNPROCESSABLE_ENTITY, [
                    'items' => [$e->getMessage()],
                ]),
                default => null,
            };
        });
    })->create();
