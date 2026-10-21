<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Console\Commands\PruneExpired;
use Throwable;

class CronController extends Controller
{
    /**
     * Housekeeping that `schedule:run` performs on a regular server. Vercel
     * has no long-running scheduler, so a Vercel Cron Job calls this endpoint
     * with "Authorization: Bearer <CRON_SECRET>". Without a configured secret
     * the endpoint does not exist.
     */
    private const COMMANDS = [
        'daleel:sitemap' => [],
        'sanctum:prune-expired' => ['--hours' => 24],
        'queue:prune-failed' => ['--hours' => 720],
        'auth:clear-resets' => [],
        'model:prune' => [],
    ];

    public function maintenance(Request $request): JsonResponse
    {
        $secret = (string) config('security.cron_secret');
        abort_if($secret === '', 404);
        abort_unless(hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 401);

        // Sanctum only registers its command for console runs; this is an HTTP request.
        if (! array_key_exists('sanctum:prune-expired', Artisan::all())) {
            Artisan::registerCommand(app(PruneExpired::class));
        }

        $results = [];
        foreach (self::COMMANDS as $command => $arguments) {
            try {
                $results[$command] = Artisan::call($command, $arguments) === 0 ? 'ok' : 'failed';
            } catch (Throwable $e) {
                report($e);
                $results[$command] = 'failed';
            }
        }

        $failed = in_array('failed', $results, true);

        return response()->json(['status' => $failed ? 'failed' : 'ok', 'commands' => $results], $failed ? 500 : 200);
    }
}
