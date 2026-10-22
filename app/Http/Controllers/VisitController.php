<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Business;
use App\Support\Analytics\PageKey;
use App\Support\Analytics\Visit;
use App\Support\Insights\Impressions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Collects the site's own visitor analytics from a small script on every public
 * page (so pages served from the CDN cache are counted, and crawlers are not).
 * Runs without a session or cookies; stores no IP address and no user agent.
 */
class VisitController extends Controller
{
    /** How long one visitor's repeat views of a page count once on the public counter. */
    private const COUNT_ONCE_MINUTES = 30;

    /** A results page reports the listings it showed (see Impressions). */
    public function impressions(Request $request): JsonResponse
    {
        $data = $request->validate(['l' => ['required', 'string', 'max:1200']]);
        if (Visit::isBot($request->userAgent())) {
            return response()->json(['ok' => false]);
        }
        $ids = Impressions::verify($data['l']);
        if ($ids !== null) {
            Impressions::record($ids);
        }

        return response()->json(['ok' => $ids !== null]);
    }

    public function view(Request $request): JsonResponse
    {
        $data = $request->validate([
            'k' => ['nullable', 'string', 'max:120'],
            'g' => ['nullable', 'string', 'max:40'],
            'p' => ['required', 'string', 'max:2000'],
            't' => ['nullable', 'string', 'max:500'],
            'r' => ['nullable', 'string', 'max:2000'],
            'u' => ['nullable', 'string', 'max:200'],
            'v' => ['nullable', 'string', 'max:64'],
            's' => ['nullable', 'string', 'max:64'],
            'n' => ['nullable', 'boolean'],
        ]);
        $ua = $request->userAgent();
        if (Visit::isBot($ua)) {
            return response()->json(['views' => null]);
        }

        $key = PageKey::verify($data['k'] ?? null, $data['g'] ?? null) ? $data['k'] : null;
        $token = fn (string $raw) => substr(hash_hmac('sha256', 'visitor:'.$raw, (string) config('app.key')), 0, 20);
        // A random token kept by the browser; without one, a daily-rotating hash of the connection.
        $visitor = $token(preg_match('/^[A-Za-z0-9_-]{8,64}$/', (string) ($data['v'] ?? '')) ? $data['v'] : ($request->ip().'|'.$ua.'|'.now()->toDateString()));
        $session = $token('s:'.(preg_match('/^[A-Za-z0-9_-]{8,64}$/', (string) ($data['s'] ?? '')) ? $data['s'] : $visitor.now()->format('YmdH')));

        $views = $key !== null ? $this->count($key, $visitor) : null;

        $id = null;
        if (setting('analytics.track_visits', true)) {
            $path = '/'.ltrim((string) parse_url($data['p'], PHP_URL_PATH), '/');
            $source = Visit::source($data['r'] ?? null, isset($data['u']) ? Str::limit(strip_tags($data['u']), 60, '') : null, $request->getHost());
            $id = DB::table('page_views')->insertGetId([
                'visitor' => $visitor,
                'session' => $session,
                'is_new' => (bool) ($data['n'] ?? false),
                'path' => Str::limit(rawurldecode($path), 300, ''),
                'page_key' => $key,
                'title' => isset($data['t']) ? Str::limit(trim(strip_tags($data['t'])), 200, '') : null,
                'locale' => str_starts_with($path, '/en') && ($path === '/en' || str_starts_with($path, '/en/')) ? 'en' : 'ar',
                'channel' => $source['channel'],
                'referrer' => $source['referrer'],
                'utm_source' => isset($data['u']) && $data['u'] !== '' ? Str::limit(Str::lower(strip_tags($data['u'])), 60, '') : null,
                ...Visit::place($request),
                ...Visit::agent($ua),
                'created_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        return response()->json([
            'id' => $id,
            'token' => $id ? self::token($id) : null,
            'views' => $views,
            'show' => $views !== null && setting('analytics.public_counts', true) && $views >= (int) setting('analytics.public_counts_min', 10),
        ]);
    }

    /** "Still here": keeps the visit in the real-time view and measures time spent on the page. */
    public function ping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'token' => ['required', 'string', 'max:40'],
            'e' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);
        if (! hash_equals(self::token((int) $data['id']), $data['token'])) {
            return response()->json(['ok' => false], 403);
        }
        DB::table('page_views')->where('id', $data['id'])->where('created_at', '>=', now()->subHours(6))->update([
            'last_seen_at' => now(),
            'engaged_seconds' => DB::raw('GREATEST(engaged_seconds, '.min(3600, (int) ($data['e'] ?? 0)).')'),
        ]);

        return response()->json(['ok' => true]);
    }

    public static function token(int $id): string
    {
        return substr(hash_hmac('sha256', 'pv:'.$id, (string) config('app.key')), 0, 24);
    }

    /** The page's public view counter, counting each visitor once per half hour. */
    private function count(string $key, string $visitor): int
    {
        $fresh = Cache::add('pv:'.$key.':'.$visitor, 1, now()->addMinutes(self::COUNT_ONCE_MINUTES));
        if (! $fresh) {
            return (int) (DB::table('page_stats')->where('page_key', $key)->value('views') ?? 0);
        }
        // A listing keeps the views it had before page analytics existed.
        $start = str_starts_with($key, 'business:') ? (int) Business::query()->whereKey((int) substr($key, 9))->value('views_count') : 0;
        $row = DB::selectOne(
            'INSERT INTO page_stats (page_key, views, updated_at) VALUES (?, ?, ?)
             ON CONFLICT (page_key) DO UPDATE SET views = page_stats.views + 1, updated_at = excluded.updated_at RETURNING views',
            [$key, $start + 1, now()],
        );

        return (int) ($row->views ?? 0);
    }
}
