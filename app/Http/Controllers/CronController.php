<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ScrapeSource;
use App\Support\Discovery\DiscoveryRunner;
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

    /** Total crawl time per cron call, below the function's 60-second limit. */
    private const DISCOVERY_SECONDS = 45.0;

    public function maintenance(Request $request): JsonResponse
    {
        $this->authorizeCron($request);

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

        // Optional daily enrichment (Admin → Settings → Google & integrations); suggestions still need approval.
        $daily = min(200, (int) setting('integrations.enrichment_daily', 0));
        if ($daily > 0) {
            try {
                $results['daleel:enrich'] = Artisan::call('daleel:enrich', ['--limit' => $daily, '--seconds' => 40]) === 0 ? 'ok' : 'failed';
            } catch (Throwable $e) {
                report($e);
                $results['daleel:enrich'] = 'failed';
            }
        }

        $failed = in_array('failed', $results, true);

        return response()->json(['status' => $failed ? 'failed' : 'ok', 'commands' => $results], $failed ? 500 : 200);
    }

    /**
     * Daily auto-discovery: gives every enabled source a share of the time
     * budget, least recently crawled first, so all sources make progress.
     */
    public function discovery(Request $request, DiscoveryRunner $runner): JsonResponse
    {
        $this->authorizeCron($request);
        @set_time_limit(60);

        $started = microtime(true);
        $sources = ScrapeSource::query()->where('is_enabled', true)
            ->orderByRaw('last_run_at asc nulls first')->get();

        $results = [];
        foreach ($sources as $i => $source) {
            $left = self::DISCOVERY_SECONDS - (microtime(true) - $started);
            if ($left < 8) {
                break;
            }
            $share = max(8.0, $left / ($sources->count() - $i));
            try {
                $results[$source->slug] = $runner->run($source, min($share, $left));
            } catch (Throwable $e) {
                report($e);
                $results[$source->slug] = ['status' => 'failed'];
            }
        }

        return response()->json(['status' => 'ok', 'sources' => $results]);
    }

    private function authorizeCron(Request $request): void
    {
        $secret = (string) config('security.cron_secret');
        abort_if($secret === '', 404);
        abort_unless(hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 401);
    }
}
