<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Support\Deadline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * Polite HTTP client for the crawler: identifies itself honestly, obeys
 * robots.txt (including Crawl-delay), waits between requests to the same
 * host, never leaves the site it was given and refuses private addresses.
 */
final class Fetcher
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const TOKEN = 'DaleelQatarBot';

    /** @var array<string, float> host => microtime of the last request */
    private static array $lastRequest = [];

    /** @var array<string, RobotsTxt> */
    private array $robots = [];

    public function __construct(private readonly int $delayMs = 1500, private readonly int $timeout = 12) {}

    public static function userAgent(): string
    {
        return self::TOKEN.'/1.0 (+'.rtrim((string) config('app.url'), '/').'/about)';
    }

    /**
     * @param  string|null  $site  when given, the URL (and any redirect) must stay on this site
     *
     * @throws DiscoveryException
     */
    public function get(string $url, ?string $site = null, bool $obeyRobots = true): FetchResult
    {
        if ($site !== null && ! UrlGuard::sameSite($url, $site)) {
            throw new DiscoveryException('other_site');
        }
        $ip = UrlGuard::check($url);

        if ($obeyRobots && ! $this->robotsFor($url)->allows($url)) {
            throw new DiscoveryException('robots_disallowed');
        }

        return $this->request($url, $ip, $site ?? $url);
    }

    public function robotsFor(string $url): RobotsTxt
    {
        $origin = self::origin($url);
        if (isset($this->robots[$origin])) {
            return $this->robots[$origin];
        }

        $cached = Cache::get('discovery:robots:'.sha1($origin));
        if (! is_array($cached)) {
            try {
                $result = $this->request($origin.'/robots.txt', UrlGuard::check($origin), $origin, true);
                $cached = ['state' => 'ok', 'body' => Str::limit($result->body, 500_000, '')];
            } catch (DiscoveryException $e) {
                // RFC 9309: a missing robots.txt allows everything, an unreachable one allows nothing.
                $cached = ['state' => str_starts_with($e->getMessage(), 'http_4') ? 'none' : 'fail', 'body' => ''];
            }
            Cache::put('discovery:robots:'.sha1($origin), $cached, $cached['state'] === 'fail' ? 600 : 6 * 3600);
        }

        return $this->robots[$origin] = match ($cached['state']) {
            'ok' => RobotsTxt::parse((string) $cached['body'], self::TOKEN),
            'none' => RobotsTxt::allowAll(),
            default => RobotsTxt::disallowAll(),
        };
    }

    /** Milliseconds to wait before the next request to this URL's host. */
    public function waitFor(string $url): int
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $last = self::$lastRequest[$host] ?? null;
        if ($last === null) {
            return 0;
        }

        return max(0, (int) ceil($this->delayFor($url) - (microtime(true) - $last) * 1000));
    }

    private function delayFor(string $url): int
    {
        $delay = $this->delayMs;
        $origin = self::origin($url);
        if (isset($this->robots[$origin]) && $this->robots[$origin]->crawlDelay !== null) {
            $delay = max($delay, (int) min(30_000, $this->robots[$origin]->crawlDelay * 1000));
        }

        return $delay;
    }

    private function request(string $url, string $ip, string $site, bool $plain = false): FetchResult
    {
        if (! Deadline::has(3 + $this->waitFor($url) / 1000)) {
            throw new DiscoveryException('out_of_time');
        }
        if (($wait = $this->waitFor($url)) > 0) {
            usleep($wait * 1000);
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        self::$lastRequest[$host] = microtime(true);

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $port = parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::userAgent(),
                'Accept' => $plain ? 'text/plain,*/*;q=0.5' : 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.5',
                'Accept-Language' => 'en,ar;q=0.8',
            ])
                ->timeout((int) ceil(Deadline::timeout($this->timeout)))
                ->connectTimeout((int) ceil(Deadline::timeout(6)))
                ->withOptions([
                    // Pin the connection to the address that passed the private-network check (no DNS rebinding).
                    'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)]],
                    'allow_redirects' => [
                        'max' => 3,
                        'protocols' => ['http', 'https'],
                        'on_redirect' => function ($request, $response, UriInterface $uri) use ($site) {
                            if (! UrlGuard::sameSite((string) $uri, $site)) {
                                throw new DiscoveryException('redirect_other_site');
                            }
                            UrlGuard::check((string) $uri);
                        },
                    ],
                    'on_headers' => function (ResponseInterface $response) {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            throw new DiscoveryException('too_large');
                        }
                    },
                ])
                ->get($url);
        } catch (DiscoveryException $e) {
            throw $e;
        } catch (Throwable $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof DiscoveryException) {
                throw $previous;
            }

            throw new DiscoveryException('network_error', true);
        }

        $status = $response->status();
        if ($status >= 400) {
            throw new DiscoveryException('http_'.$status, $status >= 500 || $status === 429);
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw new DiscoveryException('too_large');
        }
        if (str_starts_with($body, "\x1f\x8b")) {
            $body = (string) @gzdecode($body, self::MAX_BYTES * 5);
        }

        $effective = (string) ($response->effectiveUri() ?? $url);

        return new FetchResult($effective !== '' ? $effective : $url, $status, $body, Str::lower($response->header('Content-Type')));
    }

    public static function origin(string $url): string
    {
        $p = parse_url($url);
        $port = isset($p['port']) ? ':'.$p['port'] : '';

        return Str::lower(($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').$port);
    }
}
