<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use Closure;
use Illuminate\Support\Str;

/**
 * Keeps the crawler on the public internet: only http(s) URLs whose host
 * resolves exclusively to public addresses are fetched, so an administrator
 * (or a hostile page) can never point it at internal services.
 */
final class UrlGuard
{
    /** Test hook: fn (string $host): list<string> */
    private static ?Closure $resolver = null;

    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @return string the IP address the request must be pinned to
     *
     * @throws DiscoveryException when the URL is not allowed
     */
    public static function check(string $url): string
    {
        $parts = parse_url($url);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new DiscoveryException('url_not_allowed');
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443, 8080, 8443], true)) {
            throw new DiscoveryException('url_not_allowed');
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            throw new DiscoveryException('private_address');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);
        if ($ips === []) {
            throw new DiscoveryException('dns_failed');
        }
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                throw new DiscoveryException('private_address');
            }
        }

        return $ips[0];
    }

    public static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            // Carrier-grade NAT and IPv6 unique-local / link-local ranges are not covered by the flags above.
            && ! preg_match('/^(100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|0\.|f[cd][0-9a-f]{2}:|fe[89ab][0-9a-f]:)/i', $ip);
    }

    /** "www.example.com" and "example.com" count as the same site. */
    public static function sameSite(string $a, string $b): bool
    {
        $norm = fn (string $url) => Str::lower(preg_replace('/^www\d?\./i', '', (string) parse_url($url, PHP_URL_HOST)) ?? '');

        return $norm($a) !== '' && $norm($a) === $norm($b);
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return array_values((self::$resolver)($host));
        }

        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
