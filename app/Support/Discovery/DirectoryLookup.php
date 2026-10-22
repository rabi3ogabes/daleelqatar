<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Models\Business;
use App\Models\ScrapeSource;
use App\Support\Deadline;
use App\Support\Import\Normalizer;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds one company on another directory: searches the directory by name,
 * scores the results against the listing (name, then shared phone or
 * e-mail), and reads the best page. A weak match is never used on its own —
 * the administrator is shown the candidates to choose from instead.
 */
final class DirectoryLookup
{
    /** Name + contact score from which a result is taken as the same company. */
    public const SURE = 0.8;

    /** Results under this score are not even offered as candidates. */
    private const MINIMUM = 0.35;

    /** Legal forms and filler words that directories write differently. */
    private const NOISE = ['wll', 'llc', 'spc', 'qpsc', 'qsc', 'co', 'company', 'est', 'establishment', 'trading', 'and', 'the', 'for', 'of', 'w', 'l', 'c'];

    public function __construct(private readonly Fetcher $fetcher) {}

    /** Directories that can be searched (a search address is set). @return Collection<int, ScrapeSource> */
    public static function sources(): Collection
    {
        return ScrapeSource::query()->orderBy('name')->get()->filter(fn (ScrapeSource $s) => str_contains((string) $s->setting('search_url'), '{query}'))->values();
    }

    /**
     * @return array{status: 'matched'|'candidates'|'none', match: ?array{url: string, title: string, score: float, record: array},
     *               candidates: list<array{url: string, title: string, score: float}>}
     *
     * @throws DiscoveryException when the directory cannot be reached
     */
    public function find(Business $business, ScrapeSource $source, ?string $url = null): array
    {
        // The administrator chose the page (pasted or picked): read it, but still check it is the same company.
        if ($url !== null) {
            if (! UrlGuard::sameSite($url, $source->base_url)) {
                throw new DiscoveryException('other_site');
            }
            $read = $this->read($url, $source);
            if ($read === null) {
                return ['status' => 'none', 'match' => null, 'candidates' => []];
            }
            $score = self::score($business, $read['record']['name_en'] ?? $read['record']['name_ar'], $read['record']);

            return ['status' => 'matched', 'match' => ['url' => $read['url'], 'title' => (string) ($read['record']['name_en'] ?? $read['record']['name_ar']), 'score' => $score, 'record' => $read['record'], 'chosen' => true], 'candidates' => []];
        }

        $candidates = [];
        foreach (self::queries($business) as $query) {
            if (! Deadline::has(12)) {
                break;
            }
            $page = $this->fetcher->get(str_replace('{query}', rawurlencode($query), (string) $source->setting('search_url')), $source->base_url, obeyRobots: true);
            foreach (self::results($page->body, $page->url, Urls::pattern($source->url_pattern), $source->base_url) as $result) {
                $score = self::score($business, $result['title']);
                if ($score >= self::MINIMUM && ! isset($candidates[$result['url']])) {
                    $candidates[$result['url']] = $result + ['score' => round($score, 2)];
                }
            }
            if ($candidates !== []) {
                break;
            }
        }
        uasort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $candidates = array_values(array_slice($candidates, 0, 6));

        // Read the most likely pages: a shared phone or e-mail can confirm a less obvious name.
        foreach (array_slice($candidates, 0, 2) as $candidate) {
            if (! Deadline::has(10)) {
                break;
            }
            $read = $this->read($candidate['url'], $source);
            if ($read === null) {
                continue;
            }
            $score = self::score($business, $read['record']['name_en'] ?? $candidate['title'], $read['record']);
            if ($score >= self::SURE) {
                return ['status' => 'matched', 'match' => ['url' => $read['url'], 'title' => (string) ($read['record']['name_en'] ?? $candidate['title']), 'score' => round($score, 2), 'record' => $read['record']], 'candidates' => $candidates];
            }
        }

        return ['status' => $candidates === [] ? 'none' : 'candidates', 'match' => null, 'candidates' => $candidates];
    }

    /** @return array{url: string, record: array}|null */
    private function read(string $url, ScrapeSource $source): ?array
    {
        $page = $this->fetcher->get($url, $source->base_url);
        if (! $page->isHtml()) {
            return null;
        }
        $extracted = Extractor::extract($page->body, $page->url, (array) ($source->selectors ?? []));

        return $extracted['record'] === null ? null : ['url' => $page->url, 'record' => $extracted['record']];
    }

    /**
     * How sure we are that a directory entry is this listing (0–1): the names, then
     * a shared phone number or e-mail address, which is strong evidence.
     */
    public static function score(Business $business, ?string $title, ?array $record = null): float
    {
        $name = max(self::nameScore($business->name_en, $title), self::nameScore($business->name_ar, $title));
        if ($record === null) {
            return $name;
        }

        $theirs = array_filter(array_map(fn ($p) => Normalizer::phone((string) $p), [$record['phone'] ?? null, $record['whatsapp'] ?? null, ...($record['extra_phones'] ?? [])]));
        $ours = array_filter(array_map(fn ($p) => Normalizer::phone((string) $p), [$business->phone, $business->whatsapp, ...(array) $business->extra_phones]));
        $theirMail = array_map('strtolower', array_filter([$record['email'] ?? null, ...($record['extra_emails'] ?? [])]));
        $ourMail = array_map('strtolower', array_filter([$business->email, ...(array) $business->extra_emails]));
        $contact = array_intersect($theirs, $ours) !== [] || array_intersect($theirMail, $ourMail) !== []
            || (($d = Normalizer::domain($record['website'] ?? null)) !== null && $d === Normalizer::domain($business->website));

        return $contact ? min(1.0, $name + 0.4) : $name;
    }

    /** Name agreement that, unlike a plain token overlap, does not call "Al Noor Trading" the same as "Al Noor Pharmacy". */
    public static function nameScore(?string $a, ?string $b): float
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);
        if ($ta === [] || $tb === []) {
            return 0.0;
        }
        if ($ta === $tb) {
            return 1.0;
        }
        $common = count(array_intersect($ta, $tb));
        $jaccard = $common / count(array_unique([...$ta, ...$tb]));
        similar_text(implode('', $ta), implode('', $tb), $percent);

        return round(0.55 * $jaccard + 0.45 * ($percent / 100), 3);
    }

    /** @return list<string> */
    private static function tokens(?string $name): array
    {
        $key = Normalizer::nameKey($name);
        if ($key === null) {
            return [];
        }
        $tokens = array_values(array_filter(explode(' ', $key), fn ($t) => $t !== '' && ! in_array($t, self::NOISE, true)));
        sort($tokens);

        return $tokens;
    }

    /**
     * What to type in the directory's search: the name without its legal form, then
     * its first words (directories often match the beginning of the name).
     *
     * @return list<string>
     */
    public static function queries(Business $business): array
    {
        $name = (string) $business->name_en;
        $clean = trim((string) preg_replace('/\b(w\.?\s?l\.?\s?l\.?|l\.?l\.?c\.?|s\.?p\.?c\.?|q\.?p\.?s\.?c\.?|q\.?s\.?c\.?|est\.?|co\.?)(?=\s|$)/i', '', $name));
        $clean = trim((string) preg_replace('/[^\pL\pN&\' -]+/u', ' ', $clean));
        $clean = trim((string) preg_replace('/\s+/', ' ', $clean));
        $words = explode(' ', $clean);
        $short = implode(' ', array_slice($words, 0, count($words) > 3 ? 2 : max(1, count($words) - 1)));

        return array_values(array_unique(array_filter([
            Str::limit($clean, 80, ''),
            mb_strlen($short) >= 4 ? $short : null,
        ])));
    }

    /**
     * Company results on a search page: links to company pages with the name shown for them
     * (a data-title on the result card, a heading inside the link, or the link text).
     *
     * @return list<array{url: string, title: string}>
     */
    public static function results(string $html, string $pageUrl, ?string $pattern, string $site): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);

        $found = [];
        $add = function (?string $href, ?string $title) use (&$found, $pageUrl, $pattern, $site) {
            $url = $href ? Urls::resolve($pageUrl, $href) : null;
            $title = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5)));
            if ($url === null || ! UrlGuard::sameSite($url, $site) || ($pattern !== null && ! preg_match($pattern, $url))
                || mb_strlen($title) < 2 || mb_strlen($title) > 160 || preg_match('/^(view|details|more|read more|see more|visit|open|contact|call|\d+)$/i', $title)) {
                return;
            }
            // Keep the most descriptive title seen for each page.
            if (! isset($found[$url]) || mb_strlen($title) > mb_strlen($found[$url])) {
                $found[$url] = $title;
            }
        };

        // Result cards that carry the company name and page as attributes (QBCD: data-title / data-route).
        foreach ($xpath->query('//*[@data-title and (@data-route or @data-url or @data-href or @data-link)]') ?: [] as $card) {
            /** @var DOMElement $card */
            $add($card->getAttribute('data-route') ?: ($card->getAttribute('data-url') ?: ($card->getAttribute('data-href') ?: $card->getAttribute('data-link'))), $card->getAttribute('data-title'));
        }
        foreach ($xpath->query('//a[@href]') ?: [] as $a) {
            /** @var DOMElement $a */
            $heading = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//*[contains(@class,"title")]', $a)?->item(0);
            $add($a->getAttribute('href'), $heading?->textContent ?: ($a->getAttribute('title') ?: $a->textContent));
        }

        return array_map(fn ($url, $title) => ['url' => $url, 'title' => $title], array_keys($found), $found);
    }
}
