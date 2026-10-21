<?php

use App\Exceptions\WorkflowException;
use App\Http\Middleware\ApiLocale;
use App\Http\Middleware\CacheGuestPages;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->append([SetLocale::class, SecurityHeaders::class, CacheGuestPages::class]);
        // Anonymous analytics beacons are also sent from CDN-cached pages, which carry no session.
        $middleware->validateCsrfTokens(except: ['business/*/track', 'en/business/*/track', 'business/*/viewed', 'en/business/*/viewed']);
        $middleware->web(append: [EnsureAccountIsActive::class]);
        $middleware->api(prepend: [ApiLocale::class]);

        $middleware->alias([
            'locale' => SetLocale::class,
            'active' => EnsureAccountIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('account.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([WorkflowException::class]);

        $isApi = fn (Request $request) => $request->is('api/*');

        // Business-rule violations: friendly message on the web, 422 on the API.
        $exceptions->render(function (WorkflowException $e, Request $request) use ($isApi) {
            if ($isApi($request) || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'workflow_error'], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        // Consistent JSON error envelope for every API response: { message, code, errors? }
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            [$status, $code, $message, $errors] = match (true) {
                $e instanceof ValidationException => [422, 'validation_failed', __('api.validation_failed'), $e->errors()],
                $e instanceof AuthenticationException => [401, 'unauthenticated', __('api.unauthenticated'), null],
                $e instanceof AuthorizationException => [403, 'forbidden', __('api.forbidden'), null],
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'not_found', __('api.not_found'), null],
                $e instanceof ThrottleRequestsException => [429, 'too_many_requests', __('api.too_many_requests'), null],
                // Policy denials surface as AccessDeniedHttpException with an English framework message.
                $e instanceof HttpExceptionInterface && $e->getStatusCode() === 403 => [403, 'forbidden', __('api.forbidden'), null],
                $e instanceof HttpExceptionInterface && $e->getStatusCode() === 404 => [404, 'not_found', __('api.not_found'), null],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http_error', $e->getMessage() ?: __('api.error'), null],
                default => [500, 'server_error', config('app.debug') ? $e->getMessage() : __('api.server_error'), null],
            };

            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json(array_filter([
                'message' => $message,
                'code' => $code,
                'errors' => $errors,
            ], fn ($v) => $v !== null), $status, $headers);
        });
    })->create();
