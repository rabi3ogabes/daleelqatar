<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API counterpart of the "verified" middleware: JSON 403 with a stable error code. */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('api_v1.email_unverified'),
                'code' => 'email_unverified',
            ], 403);
        }

        return $next($request);
    }
}
