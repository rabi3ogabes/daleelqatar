<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

use App\Models\Business;
use App\Support\Discovery\DiscoveryException;
use App\Support\Discovery\Fetcher;
use App\Support\Discovery\FetchResult;
use App\Support\Discovery\UrlGuard;
use App\Support\Discovery\Urls;
use App\Support\Import\Normalizer;
use DOMDocument;
use DOMXPath;

/**
 * Finds a company's website (listing, then the e-mail domain, then Google)
 * and reads its home, "About us" and "Services" pages — politely, through the
 * crawler's fetcher (robots.txt, rate limits, private-network guard).
 */
final class WebsiteReader
{
    private const PAGE_PATTERN = '/(about|who-?we-?are|our-?story|company|profile|overview|services|what-?we-?do|solutions|products|expertise|من-?نحن|عن-?الشركة|خدمات|منتجات)/iu';

    private const MAX_PAGE_CHARS = 6000;

    private const MAX_EXTRA_PAGES = 3;

    private readonly Fetcher $fetcher;

    public function __construct(int $delayMs = 400)
    {
        $this->fetcher = new Fetcher($delayMs, 10);
    }

    /**
     * Where to look, best first: the listing's website, the domain of a business e-mail
     * (info@company.qa → company.qa; never Gmail/Live/…), then the website Google knows.
     *
     * @return list<array{url: string, source: string}>
     */
    public function candidates(Business $business, ?string $googleWebsite = null): array
    {
        $out = [];
        $add = function (?string $url, string $source) use (&$out) {
            $url = Normalizer::website($url);
            $domain = Normalizer::domain($url);
            if ($url !== null && $domain !== null && ! in_array($domain, array_map(fn ($c) => Normalizer::domain($c['url']), $out), true)) {
                $out[] = ['url' => $url, 'source' => $source];
            }
        };

        $add($business->website, 'listing');
        foreach (array_filter([$business->email, ...(array) $business->extra_emails]) as $email) {
            $domain = Normalizer::emailDomain((string) $email);
            $add($domain ? 'https://'.$domain : null, 'email');
        }
        $add($googleWebsite, 'google');

        return $out;
    }

    /**
     * @return array{url: string, title: ?string, description: ?string, pages: array<string,string>, whatsapp: list<string>, phones: list<string>, social: array<string,string>}|null
     *                                                                                                                                                                                null when the site cannot be reached
     */
    public function read(string $url): ?array
    {
        $home = $this->fetchFirst(array_unique([$url, preg_replace('#^(https?://)(?!www\.)#i', '$1www.', $url), preg_replace('#^https://#i', 'http://', $url)]));
        if ($home === null) {
            return null;
        }

        [$doc, $xpath] = $this->parse($home->body);
        $result = [
            'url' => $home->url,
            'title' => $this->first($xpath, ['//meta[@property="og:site_name"]/@content', '//title']),
            'description' => $this->first($xpath, ['//meta[@name="description"]/@content', '//meta[@property="og:description"]/@content']),
            'pages' => [],
            'whatsapp' => [],
            'phones' => [],
            'social' => [],
        ];
        // Links and contacts first: menus and footers are removed when the readable text is taken.
        $this->collectContacts($xpath, $result);
        $links = $this->interestingLinks($xpath, $home->url);
        $result['pages'][$home->url] = $this->text($doc, $xpath);

        $extra = 0;
        foreach ($links as $link) {
            if ($extra >= self::MAX_EXTRA_PAGES) {
                break;
            }
            try {
                $page = $this->fetcher->get($link, $home->url);
            } catch (DiscoveryException $e) {
                if ($e->getMessage() === 'out_of_time') {
                    break; // keep what was read so far
                }

                continue;
            }
            if (! $page->isHtml()) {
                continue;
            }
            [$pdoc, $pxpath] = $this->parse($page->body);
            $this->collectContacts($pxpath, $result);
            $result['pages'][$page->url] = $this->text($pdoc, $pxpath);
            $extra++;
        }

        $result['whatsapp'] = array_values(array_unique($result['whatsapp']));
        $result['phones'] = array_values(array_unique($result['phones']));
        $result['pages'] = array_filter($result['pages'], fn ($t) => mb_strlen($t) >= 40);

        return $result;
    }

    private function fetchFirst(array $urls): ?FetchResult
    {
        foreach (array_filter($urls) as $url) {
            try {
                $result = $this->fetcher->get($url);
                if ($result->isHtml()) {
                    return $result;
                }
            } catch (DiscoveryException $e) {
                if ($e->getMessage() === 'out_of_time') {
                    throw $e; // not "unreachable": the request simply ran out of time
                }

                continue;
            }
        }

        return null;
    }

    /** @return array{0: DOMDocument, 1: DOMXPath} */
    private function parse(string $html): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [$doc, new DOMXPath($doc)];
    }

    private function first(DOMXPath $xpath, array $queries): ?string
    {
        foreach ($queries as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = $node ? trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '') : '';
            if ($value !== '') {
                return mb_substr(html_entity_decode($value, ENT_QUOTES | ENT_HTML5), 0, 300);
            }
        }

        return null;
    }

    /** Readable text of the page body without navigation, scripts and footers. */
    private function text(DOMDocument $doc, DOMXPath $xpath): string
    {
        foreach (iterator_to_array($xpath->query('//script|//style|//noscript|//svg|//nav|//footer|//header|//form|//iframe|//template') ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }
        $main = $xpath->query('//main')?->item(0) ?? $xpath->query('//body')?->item(0) ?? $doc->documentElement;
        $text = trim(preg_replace('/\s+/u', ' ', (string) $main?->textContent) ?? '');

        return mb_substr(html_entity_decode($text, ENT_QUOTES | ENT_HTML5), 0, self::MAX_PAGE_CHARS);
    }

    /** @return list<string> same-site About/Services pages */
    private function interestingLinks(DOMXPath $xpath, string $base): array
    {
        $links = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $a) {
            $href = trim((string) $a->getAttribute('href'));
            $label = trim((string) $a->textContent);
            if (! preg_match(self::PAGE_PATTERN, $href.' '.$label)) {
                continue;
            }
            $url = Urls::resolve($base, $href);
            if ($url !== null && UrlGuard::sameSite($url, $base) && rtrim($url, '/') !== rtrim($base, '/')) {
                $links[strtok($url, '#')] = true;
            }
        }

        return array_keys($links);
    }

    private function collectContacts(DOMXPath $xpath, array &$result): void
    {
        foreach ($xpath->query('//a[@href]') ?: [] as $a) {
            $href = html_entity_decode(trim((string) $a->getAttribute('href')));
            if (preg_match('#(?:wa\.me/|api\.whatsapp\.com/send/?\?(?:[^"\']*&)?phone=|whatsapp://send\?(?:[^"\']*&)?phone=)\+?(\d{8,15})#i', $href, $m)) {
                if ($phone = Normalizer::phone('+'.$m[1])) {
                    $result['whatsapp'][] = $phone;
                }
            } elseif (preg_match('#^tel:\s*([+\d][\d\s\-().]{6,})#i', $href, $m)) {
                if ($phone = Normalizer::phone($m[1])) {
                    $result['phones'][] = $phone;
                }
            } elseif (preg_match('#^https?://(?:www\.)?(facebook|instagram|linkedin|x|twitter|tiktok|youtube)\.com/[^\s"\']+#i', $href, $m)) {
                $platform = strtolower($m[1]) === 'twitter' ? 'x' : strtolower($m[1]);
                $result['social'][$platform] ??= $href;
            }
        }
    }
}
