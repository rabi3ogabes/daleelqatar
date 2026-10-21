<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;

/**
 * Full public listing profile.
 *
 * @mixin Business
 */
class BusinessResource extends BusinessSummaryResource
{
    public ?bool $favorited = null;

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->t('description'),
            'keywords' => $this->keywords,
            'address' => $this->t('address'),
            'coordinates' => $this->latitude !== null && $this->longitude !== null
                ? ['latitude' => $this->latitude, 'longitude' => $this->longitude] : null,
            'contact' => [
                'phone' => $this->phone,
                'whatsapp' => $this->whatsapp,
                'whatsapp_url' => $this->whatsappUrl(),
                'email' => $this->email,
                'website' => safe_external_url($this->website),
                'directions_url' => $this->directionsUrl(),
            ],
            'social_links' => $this->whenLoaded('socialLinks', fn () => $this->socialLinks->map(fn ($l) => [
                'platform' => $l->platform->value, 'url' => $l->url,
            ])->values()),
            'opening_hours' => $this->whenLoaded('openingHours', fn () => $this->openingHours->map(fn ($h) => [
                'day_of_week' => $h->day_of_week,
                'opens_at' => $h->opens_at ? substr((string) $h->opens_at, 0, 5) : null,
                'closes_at' => $h->closes_at ? substr((string) $h->closes_at, 0, 5) : null,
                'is_closed' => (bool) $h->is_closed,
                'is_all_day' => $h->isAllDay(),
            ])->values()),
            'timezone' => Business::TIMEZONE,
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($s) => [
                'name' => $s->t('name'),
                'description' => $s->t('description'),
                'price_from' => $s->price_from !== null ? (float) $s->price_from : null,
                'currency' => 'QAR',
                'translations' => ['name' => $s->translations('name'), 'description' => $s->translations('description')],
            ])->values()),
            'gallery' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url(),
                'width' => $img->width,
                'height' => $img->height,
                'caption' => $img->t('caption'),
            ])->values()),
            'secondary_categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => [
                'id' => $c->id, 'slug' => $c->slug, 'name' => $c->t('name'),
            ])->values()),
            'offers' => OfferResource::collection($this->whenLoaded('activeOffers')),
            'favorites_count' => (int) $this->favorites_count,
            'is_favorited' => $this->favorited,
            'translations' => [
                'name' => $this->translations('name'),
                'tagline' => $this->translations('tagline'),
                'description' => $this->translations('description'),
                'address' => $this->translations('address'),
            ],
        ]);
    }

    public function withFavorited(?bool $favorited): static
    {
        $this->favorited = $favorited;

        return $this;
    }
}
