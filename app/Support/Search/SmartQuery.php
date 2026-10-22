<?php

declare(strict_types=1);

namespace App\Support\Search;

use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Support\Categories\CategoryClassifier;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Reads plain-language searches — "pharmacy open now in Al Wakrah",
 * "صيدلية مفتوحة الآن في الوكرة", "kids dentist Lusail" — into the directory's
 * own filters. Built-in rules understand most searches instantly; an optional
 * AI step handles the rest. Either way only a category, a place and simple
 * flags are taken: the results always come from the directory itself.
 */
final class SmartQuery
{
    private const OPEN_NOW = ['open now', 'opened now', 'open right now', 'now open', 'currently open', 'open at the moment', 'مفتوح الان', 'مفتوحه الان', 'مفتوح حاليا', 'مفتوحه حاليا', 'يعمل الان', 'شغال الان', 'الان'];

    private const VERIFIED = ['verified', 'موثق', 'موثقه', 'معتمد'];

    /** Words that only connect the parts of a sentence. */
    private const FILLER = ['in', 'at', 'near', 'nearby', 'around', 'me', 'the', 'a', 'an', 'best', 'top', 'good', 'find', 'looking', 'for', 'i', 'need', 'want', 'please', 'any', 'some', 'qatar', 'doha area',
        'في', 'قرب', 'بالقرب', 'من', 'عند', 'منطقه', 'افضل', 'احسن', 'ابحث', 'عن', 'اريد', 'ابي', 'ابغى', 'ودي', 'لو', 'سمحت', 'قطر', 'ال'];

    /** Words too general to mean one sub-category: they search the whole parent category. */
    private const GENERIC = ['restaurant', 'cuisine', 'dining', 'eatery', 'diner', 'food', 'مطعم', 'مطاعم', 'ماكولات', 'clinic', 'doctor', 'doctors', 'medical', 'salon', 'beauty', 'shop', 'store'];

    public function __construct(private readonly CategoryClassifier $classifier, private readonly ?SearchInterpreter $ai = null) {}

    public static function enabled(): bool
    {
        return (bool) setting('search.smart', true);
    }

    public function interpret(string $query): ?Interpretation
    {
        $query = trim($query);
        if (mb_strlen($query) < 3 || mb_strlen($query) > 120) {
            return null;
        }

        // A business's own name ("Doha Bank", "Al Wakrah Pharmacy") is searched as typed.
        if (self::isBusinessName($query)) {
            return null;
        }

        $result = $this->rules($query);
        if ($result->understood()) {
            return $result;
        }

        // Sentences the rules could not read go to the AI, once per wording (cached).
        if ($this->ai && $this->ai->available() && setting('search.smart_ai', true) && count(preg_split('/\s+/u', $query) ?: []) >= 3) {
            return $this->fromAi($query);
        }

        return null;
    }

    public static function isBusinessName(string $query): bool
    {
        $like = '%'.addcslashes(ArabicNormalizer::normalize($query), '%_\\').'%';

        return Business::query()->published()
            ->where(fn ($w) => $w->whereRaw(ArabicNormalizer::sql('businesses.name_en').' ILIKE ?', [$like])
                ->orWhereRaw(ArabicNormalizer::sql('businesses.name_ar').' ILIKE ?', [$like]))
            ->exists();
    }

    public function rules(string $query): Interpretation
    {
        $text = ' '.self::norm($query).' ';

        $openNow = false;
        foreach (self::OPEN_NOW as $phrase) {
            $p = self::norm($phrase);
            if (str_contains($text, ' '.$p.' ')) {
                $openNow = true;
                $text = str_replace(' '.$p.' ', ' ', $text);
            }
        }
        $verified = false;
        foreach (self::VERIFIED as $word) {
            $w = self::norm($word);
            if (str_contains($text, ' '.$w.' ')) {
                $verified = true;
                $text = str_replace(' '.$w.' ', ' ', $text);
            }
        }

        [$municipality, $area, $text] = $this->place($text);
        [$category, $text] = $this->category($text);

        $words = array_values(array_filter(preg_split('/\s+/u', trim($text)) ?: [], fn ($w) => $w !== '' && ! in_array($w, array_map([self::class, 'norm'], self::FILLER), true) && mb_strlen($w) > 1));
        $remainder = $words ? implode(' ', $words) : null;

        return new Interpretation($category, $municipality, $area, $openNow, $verified, $remainder);
    }

    /** @return array{0: ?Municipality, 1: ?Area, 2: string} */
    private function place(string $text): array
    {
        $best = null;
        foreach (self::places() as $place) {
            foreach ($place['names'] as $name) {
                $hit = $place['arabic'] ? self::arabicPos($text, $name) : (str_contains($text, ' '.$name.' ') ? [' '.$name.' ', ' '] : null);
                if ($hit && (! $best || mb_strlen($name) > mb_strlen($best['name']))) {
                    $best = ['name' => $name, 'place' => $place, 'hit' => $hit];
                }
            }
        }
        if (! $best) {
            return [null, null, $text];
        }
        $text = str_replace($best['hit'][0], $best['hit'][1], $text);
        $p = $best['place'];
        if ($p['type'] === 'area') {
            $area = Area::query()->active()->find($p['id']);

            return [$area?->municipality, $area, $text];
        }

        return [Municipality::query()->active()->find($p['id']), null, $text];
    }

    /** @return array{0: ?Category, 1: string} */
    private function category(string $text): array
    {
        $matches = $this->classifier->matches($text);
        if ($matches === []) {
            // Category names themselves ("Pharmacies", "صيدليات").
            foreach (Category::query()->active()->get(['id', 'slug', 'name_ar', 'name_en', 'parent_id']) as $c) {
                foreach ([$c->name_en, $c->name_ar] as $name) {
                    $n = self::norm((string) $name);
                    if (mb_strlen($n) >= 4 && str_contains($text, ' '.$n.' ')) {
                        return [$c, str_replace(' '.$n.' ', ' ', $text)];
                    }
                }
            }

            return [null, $text];
        }

        uasort($matches, fn ($a, $b) => $b[1] <=> $a[1]);
        $slug = (string) array_key_first($matches);
        $keyword = (string) $matches[$slug][0];
        $category = Category::query()->active()->where('slug', $slug)->first();
        if ($category && $category->parent_id && in_array(self::norm($keyword), array_map([self::class, 'norm'], self::GENERIC), true)) {
            $category = $category->parent ?? $category;
        }

        // Take the keyword out of the text; an Arabic keyword may carry a prefix (الصيدليه).
        $k = self::norm($keyword);
        $text = preg_replace('/\s\S*'.preg_quote($k, '/').'\S*\s/u', ' ', $text, 1) ?? $text;

        return [$category, $text];
    }

    private function fromAi(string $query): ?Interpretation
    {
        $key = 'smartq.'.sha1(self::norm($query));
        $cached = Cache::get($key);
        if ($cached === null) {
            try {
                $cached = $this->ai->interpret($query, $this->taxonomy(), $this->placeList()) ?? [];
            } catch (Throwable $e) {
                report($e);
                // Do not ask again for an hour; the rules still work meanwhile.
                Cache::put($key, [], now()->addHour());

                return null;
            }
            Cache::put($key, $cached, now()->addDays(30));
        }
        if ($cached === []) {
            return null;
        }

        $category = ! empty($cached['category']) ? Category::query()->active()->where('slug', $cached['category'])->first() : null;
        $area = ! empty($cached['area']) ? Area::query()->active()->where('slug', $cached['area'])->first() : null;
        $municipality = $area?->municipality ?? (! empty($cached['municipality']) ? Municipality::query()->active()->where('slug', $cached['municipality'])->first() : null);
        $keywords = trim((string) ($cached['keywords'] ?? ''));
        $result = new Interpretation($category, $municipality, $area, (bool) ($cached['open_now'] ?? false), false, $keywords !== '' ? mb_substr($keywords, 0, 60) : null, 'ai');

        return $result->understood() ? $result : null;
    }

    /** @return array<string, string> slug => "Parent › Category" */
    private function taxonomy(): array
    {
        return Category::query()->active()->with('parent:id,name_en')->get(['id', 'slug', 'name_en', 'parent_id'])
            ->mapWithKeys(fn ($c) => [$c->slug => trim(($c->parent ? $c->parent->name_en.' › ' : '').$c->name_en)])->all();
    }

    /** @return array<string, string> slug => name */
    private function placeList(): array
    {
        return Municipality::query()->active()->get(['slug', 'name_en'])->mapWithKeys(fn ($m) => ['m:'.$m->slug => $m->name_en])
            ->merge(Area::query()->active()->get(['slug', 'name_en'])->mapWithKeys(fn ($a) => ['a:'.$a->slug => $a->name_en]))->all();
    }

    /** @return list<array{type: string, id: int, names: list<string>, arabic: bool}> */
    private static function places(): array
    {
        return Cache::remember('smartq.places', now()->addHour(), function () {
            $out = [];
            $add = function (string $type, $row) use (&$out) {
                foreach (['en' => false, 'ar' => true] as $locale => $arabic) {
                    $name = self::norm((string) $row->{'name_'.$locale});
                    if (mb_strlen($name) < 3) {
                        continue;
                    }
                    $names = [$name];
                    // "Al Wakrah" → also "Wakrah"; "الوكرة" → also "وكره".
                    $short = $arabic ? preg_replace('/^ال/u', '', $name) : preg_replace('/^(al|el|ad|ar|as|ash|az)[\s\-]+/u', '', $name);
                    if ($short && $short !== $name && mb_strlen($short) >= 4) {
                        $names[] = $short;
                    }
                    $out[] = ['type' => $type, 'id' => $row->id, 'names' => $names, 'arabic' => $arabic];
                }
            };
            Municipality::query()->active()->get(['id', 'name_ar', 'name_en'])->each(fn ($m) => $add('municipality', $m));
            Area::query()->active()->get(['id', 'name_ar', 'name_en'])->each(fn ($a) => $add('area', $a));

            return $out;
        });
    }

    /** Arabic place names may carry a prefix: بالوكره، في الوكره، للوكره. @return array{0: string, 1: string}|null */
    private static function arabicPos(string $text, string $name): ?array
    {
        if (preg_match('/\s((?:و|ب|ل|ف)?(?:ال)?'.preg_quote(preg_replace('/^ال/u', '', $name), '/').')\s/u', $text, $m)) {
            return [' '.$m[1].' ', ' '];
        }

        return null;
    }

    public static function norm(string $text): string
    {
        $text = ArabicNormalizer::normalize(CategoryClassifier::normalizeArabic($text));
        $text = preg_replace('/[^\p{L}\p{N}\s\/]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
