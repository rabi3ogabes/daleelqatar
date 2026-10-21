<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A directory website the crawler collects new listings from. */
class ScrapeSource extends Model
{
    protected $fillable = ['name', 'slug', 'base_url', 'discovery', 'start_urls', 'url_pattern', 'selectors', 'settings', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'selectors' => 'array',
            'settings' => 'array',
            'stats' => 'array',
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'last_discovered_at' => 'datetime',
        ];
    }

    public function urls(): HasMany
    {
        return $this->hasMany(ScrapeUrl::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /** @return list<string> */
    public function startUrls(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->start_urls) ?: [])));
    }

    public function sourceKey(): string
    {
        return 'crawl:'.$this->slug;
    }

    public function host(): string
    {
        return (string) parse_url($this->base_url, PHP_URL_HOST);
    }
}
