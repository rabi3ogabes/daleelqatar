<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Per-request SEO metadata, filled by controllers and rendered by <x-seo/>.
 */
final class SeoMeta
{
    public ?string $title = null;

    public ?string $description = null;

    public ?string $canonical = null;

    public ?string $image = null;

    public string $type = 'website';

    public bool $noindex = false;

    /** @var list<array> */
    public array $jsonLd = [];

    /** @var array<string,string>|null Explicit hreflang alternates (locale => url) */
    public ?array $alternates = null;

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description ? trim(mb_substr(strip_tags($description), 0, 300)) : null;

        return $this;
    }

    public function canonical(?string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    public function image(?string $url): self
    {
        $this->image = $url;

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function noindex(bool $noindex = true): self
    {
        $this->noindex = $noindex;

        return $this;
    }

    public function jsonLd(array $data): self
    {
        $this->jsonLd[] = $data;

        return $this;
    }

    public function alternates(array $alternates): self
    {
        $this->alternates = $alternates;

        return $this;
    }

    public function fullTitle(): string
    {
        $site = setting_t('brand.name', __('brand.name'));

        return $this->title ? $this->title.' | '.$site : (setting_t('seo.default_title') ?? $site);
    }

    public function metaDescription(): string
    {
        return $this->description ?? (string) setting_t('seo.default_description', __('brand.subtitle'));
    }

    public function shouldIndex(): bool
    {
        return ! $this->noindex && (bool) setting('seo.indexing_enabled', true) && app()->isProduction(); // staging & local are never indexed
    }
}
