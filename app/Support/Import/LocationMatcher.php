<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Models\Area;
use App\Models\Municipality;
use App\Support\Search\ArabicNormalizer;
use Illuminate\Support\Str;

/**
 * Resolves free-text cities and addresses ("Al Wakra", "Ar Rayyān",
 * "Porto Arabia, The Pearl", "P.O.Box 1404, Doha, Qatar") to a municipality
 * and, when possible, an area of this directory.
 */
final class LocationMatcher
{
    /** Extra spellings seen in Qatari directories → area or municipality slug. */
    private const ALIASES = [
        'al wakra' => 'al-wakrah', 'wakrah' => 'al-wakrah', 'wakra' => 'al-wakrah', 'al wakrah' => 'al-wakrah',
        'ar rayyan' => 'al-rayyan', 'rayyan' => 'al-rayyan', 'al rayan' => 'al-rayyan', 'ar rayan' => 'al-rayyan',
        'al shahaniya' => 'al-shahaniya', 'shahaniya' => 'al-shahaniya', 'al shahaniyah' => 'al-shahaniya',
        'umm salal muhammad' => 'umm-salal-mohammed', 'umm salal mohammed' => 'umm-salal-mohammed', 'umm salal ali' => 'umm-salal-ali',
        'as sadd' => 'al-sadd', 'al sadd' => 'al-sadd', 'sadd' => 'al-sadd',
        'new al rayyan' => 'al-wajba', 'wajba' => 'al-wajba',
        'madinat al shamal' => 'madinat-al-shamal', 'al ruwais' => 'al-ruwais', 'ruwais' => 'al-ruwais',
        'the pearl' => 'the-pearl-qatar', 'pearl qatar' => 'the-pearl-qatar', 'porto arabia' => 'the-pearl-qatar', 'medina centrale' => 'the-pearl-qatar', 'qanat quartier' => 'the-pearl-qatar',
        'mesaieed' => 'mesaieed', 'masaeed' => 'mesaieed', 'umm said' => 'mesaieed',
        'madinat khalifah' => 'madinat-khalifa', 'madinat khalifa' => 'madinat-khalifa',
        'an najmah' => 'najma', 'najmah' => 'najma', 'al najma' => 'najma',
        'al wukayr' => 'al-wukair', 'al wakair' => 'al-wukair',
        'ras laffan industrial city' => 'ras-laffan',
        'fereej bin mahmoud' => 'bin-mahmoud', 'fereej bin mahmood' => 'bin-mahmoud',
        'musheirab' => 'msheireb', 'mushaireb' => 'msheireb', 'msheireb downtown' => 'msheireb',
        'marina lusail' => 'lusail', 'lusail city' => 'lusail',
        'industrial area' => 'industrial-area', 'sanaiya' => 'industrial-area', 'street industrial area' => 'industrial-area',
        'dafna' => 'al-dafna', 'al dafna' => 'al-dafna', 'westbay' => 'west-bay',
        'education city' => 'education-city', 'aspire' => 'aspire-zone',
        'al gharafa' => 'al-gharrafa', 'gharrafa' => 'al-gharrafa',
        'al aziziyah' => 'al-aziziya', 'aziziya' => 'al-aziziya',
        'al hilal' => 'al-hilal', 'old airport' => 'old-airport', 'al matar al qadeem' => 'old-airport',
        'abu hamour' => 'abu-hamour', 'al thumama' => 'al-thumama', 'thumama' => 'al-thumama',
        'al mansoura' => 'al-mansoura', 'mansoura' => 'al-mansoura',
        'al muntazah' => 'al-muntazah', 'muntazah' => 'al-muntazah',
        'al markhiya' => 'al-markhiya', 'markhiya' => 'al-markhiya',
        'al duhail' => 'al-duhail', 'duhail' => 'al-duhail',
        'al kheesa' => 'al-kheesa', 'kheesa' => 'al-kheesa',
        'al khor' => 'al-khor', 'al thakhira' => 'al-thakhira', 'dukhan' => 'dukhan',
        'muaither' => 'muaither', 'al luqta' => 'al-luqta', 'al waab' => 'al-waab', 'ain khaled' => 'ain-khaled',
        'izghawa' => 'izghawa', 'simaisma' => 'simaisma', 'umm qarn' => 'umm-qarn',
        'ad dawhah' => 'doha', 'ad dawah' => 'doha', 'dawhah' => 'doha', 'umm ghuwailina' => 'doha', 'umm ghuwaylinah' => 'doha', 'al nasr' => 'doha', 'rawdat al khail' => 'doha', 'al hitmi' => 'doha',
        'c ring road' => 'doha', 'd ring road' => 'doha', 'salwa road' => 'doha', 'al saad' => 'al-sadd', 'corniche' => 'doha',
    ];

    /** @var array<string, array{0:int,1:?int}>|null normalised name => [municipality_id, area_id] */
    private ?array $lookup = null;

    /** @var list<string> */
    private array $keysByLength = [];

    /** @return array{municipality_id: ?int, area_id: ?int} */
    public function match(?string $city, ?string $address = null): array
    {
        $this->load();

        foreach ([$city, $address] as $text) {
            $norm = self::normalize((string) $text);
            if ($norm === '') {
                continue;
            }
            if (isset($this->lookup[$norm])) {
                return $this->result($this->lookup[$norm]);
            }
            foreach ($this->keysByLength as $key) {
                if (str_contains(' '.$norm.' ', ' '.$key.' ')) {
                    return $this->result($this->lookup[$key]);
                }
            }
        }

        return ['municipality_id' => null, 'area_id' => null];
    }

    /** @param array{0:int,1:?int} $hit */
    private function result(array $hit): array
    {
        return ['municipality_id' => $hit[0], 'area_id' => $hit[1]];
    }

    private function load(): void
    {
        if ($this->lookup !== null) {
            return;
        }
        $this->lookup = [];
        $bySlug = [];

        foreach (Municipality::query()->get(['id', 'slug', 'name_en', 'name_ar']) as $m) {
            $hit = [$m->id, null];
            $bySlug[$m->slug] = $hit;
            foreach ([$m->name_en, $m->name_ar, str_replace('-', ' ', $m->slug)] as $name) {
                if (($k = self::normalize((string) $name)) !== '') {
                    $this->lookup[$k] = $hit;
                }
            }
        }
        foreach (Area::query()->get(['id', 'municipality_id', 'slug', 'name_en', 'name_ar']) as $a) {
            $hit = [$a->municipality_id, $a->id];
            $bySlug[$a->slug] = $hit;
            foreach ([$a->name_en, $a->name_ar, str_replace('-', ' ', $a->slug)] as $name) {
                if (($k = self::normalize((string) $name)) !== '' && ! isset($this->lookup[$k])) {
                    $this->lookup[$k] = $hit;
                }
            }
        }
        foreach (self::ALIASES as $alias => $slug) {
            if (isset($bySlug[$slug])) {
                $this->lookup[self::normalize($alias)] = $bySlug[$slug];
            }
        }

        // Generic words that would match everything are not usable keys on their own.
        unset($this->lookup['qatar'], $this->lookup['city'], $this->lookup['town']);

        $this->keysByLength = array_keys($this->lookup);
        usort($this->keysByLength, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $this->keysByLength = array_values(array_filter($this->keysByLength, fn ($k) => mb_strlen($k) >= 4));
    }

    public static function normalize(string $text): string
    {
        $text = Str::lower(trim($text));
        if ($text === '') {
            return '';
        }
        if (Normalizer::hasArabic($text)) {
            $text = ArabicNormalizer::normalize($text);
        } else {
            $text = Str::ascii($text);
        }
        $text = preg_replace('/\b(al|el|ad|ar|as|an|ash|at)-/u', '$1 ', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
