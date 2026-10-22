<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Exceptions\WorkflowException;
use App\Models\Business;
use App\Models\BusinessImage;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates listings and their related records. Shared by the owner
 * dashboard, the admin dashboard and the REST API so business rules live in
 * exactly one place.
 */
class BusinessManager
{
    public function __construct(
        private readonly SlugGenerator $slugs,
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
        private readonly ListingWorkflow $workflow,
    ) {}

    public function create(User $actor, array $data, ?User $owner = null): Business
    {
        if (! $actor->isStaff()) {
            $limit = $this->settings->int('listings.max_per_owner', 10);
            if ($limit > 0 && $actor->businesses()->count() >= $limit) {
                throw new WorkflowException(__('workflow.owner_limit', ['max' => $limit]));
            }
        }

        return DB::transaction(function () use ($actor, $data, $owner) {
            $business = new Business;
            $business->fill($this->attributes($data));
            $business->slug = $this->slugs->unique(Business::class, $data['slug'] ?? $data['name_en'] ?? $data['name_ar']);
            $business->status = BusinessStatus::Draft;
            $business->owner_id = $actor->isStaff() ? $owner?->id : $actor->id;
            $business->save();

            $this->syncRelations($business, $data);

            if ($business->owner_id && ($ownerUser = $business->owner) && ! $ownerUser->hasRole(Permissions::BUSINESS_OWNER)) {
                $ownerUser->assignRole(Permissions::BUSINESS_OWNER);
            }

            $this->audit->log('business.created', $business, [], ['name_en' => $business->name_en, 'status' => $business->status->value], [], $actor->id);

            return $business;
        });
    }

    public function update(Business $business, User $actor, array $data): Business
    {
        return DB::transaction(function () use ($business, $actor, $data) {
            $original = $business->getAttributes();
            $business->fill($this->attributes($data));

            if (isset($data['slug']) && $actor->isStaff() && $data['slug'] !== $business->slug) {
                $business->slug = $this->slugs->unique(Business::class, $data['slug'], $business->id);
            }

            $business->save();
            $this->syncRelations($business, $data);
            $this->audit->logChanges('business.updated', $business, $original);

            if (! $actor->isStaff() && $business->status === BusinessStatus::Approved
                && $this->settings->bool('listings.reapprove_on_edit') && $business->wasChanged()) {
                $this->workflow->requireReapproval($business, $actor);
            }

            return $business->refresh();
        });
    }

    public function delete(Business $business, User $actor): void
    {
        DB::transaction(function () use ($business, $actor) {
            $this->audit->log('business.deleted', $business, ['name_en' => $business->name_en, 'status' => $business->status->value], [], [], $actor->id);
            $business->delete();
        });
    }

    private function attributes(array $data): array
    {
        $attrs = Arr::only($data, (new Business)->getFillable());
        foreach (['website'] as $url) {
            if (array_key_exists($url, $attrs)) {
                $attrs[$url] = safe_external_url($attrs[$url]);
            }
        }

        return $attrs;
    }

    private function syncRelations(Business $business, array $data): void
    {
        if (array_key_exists('category_ids', $data)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $data['category_ids']), fn ($id) => $id !== (int) $business->category_id)));
            $business->categories()->sync(array_slice($ids, 0, 3));
        }

        if (array_key_exists('hours', $data)) {
            $this->syncHours($business, (array) ($data['hours'] ?? []));
        }

        if (array_key_exists('services', $data)) {
            $this->syncServices($business, (array) ($data['services'] ?? []));
        }

        if (array_key_exists('social', $data)) {
            $this->syncSocial($business, (array) ($data['social'] ?? []));
        }
    }

    /**
     * $hours = [dayOfWeek => ['closed' => bool, 'all_day' => bool, 'periods' => [['opens' => 'HH:MM', 'closes' => 'HH:MM']]]]
     * Days omitted entirely mean "hours not provided".
     */
    public function syncHours(Business $business, array $hours): void
    {
        $business->openingHours()->delete();
        $rows = [];
        foreach ($hours as $day => $config) {
            $day = (int) $day;
            if ($day < 0 || $day > 6 || ! is_array($config)) {
                continue;
            }
            if (! empty($config['closed'])) {
                $rows[] = ['day_of_week' => $day, 'is_closed' => true, 'opens_at' => null, 'closes_at' => null];

                continue;
            }
            if (! empty($config['all_day'])) {
                $rows[] = ['day_of_week' => $day, 'is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '24:00'];

                continue;
            }
            foreach (array_slice((array) ($config['periods'] ?? []), 0, 2) as $period) {
                if (! empty($period['opens']) && ! empty($period['closes']) && $period['opens'] !== $period['closes']) {
                    $rows[] = ['day_of_week' => $day, 'is_closed' => false, 'opens_at' => $period['opens'], 'closes_at' => $period['closes']];
                }
            }
        }
        $business->openingHours()->createMany($rows);
        $business->unsetRelation('openingHours');
    }

    public function syncServices(Business $business, array $services): void
    {
        $business->services()->delete();
        $rows = [];
        foreach (array_values(array_slice($services, 0, 30)) as $i => $s) {
            if (blank($s['name_ar'] ?? null) && blank($s['name_en'] ?? null)) {
                continue;
            }
            $rows[] = [
                'name_ar' => $s['name_ar'] ?: $s['name_en'],
                'name_en' => $s['name_en'] ?: $s['name_ar'],
                'description_ar' => $s['description_ar'] ?? null,
                'description_en' => $s['description_en'] ?? null,
                'price_from' => isset($s['price_from']) && $s['price_from'] !== '' ? $s['price_from'] : null,
                'sort_order' => $i,
            ];
        }
        $business->services()->createMany($rows);

        // Denormalised for full-text search
        $business->forceFill([
            'services_text' => collect($rows)->map(fn ($r) => $r['name_ar'].' '.$r['name_en'])->implode(' ') ?: null,
        ])->saveQuietly();
    }

    public function syncSocial(Business $business, array $social): void
    {
        $business->socialLinks()->delete();
        foreach ($social as $platform => $url) {
            if ($url = safe_external_url($url)) {
                $business->socialLinks()->create(['platform' => $platform, 'url' => $url]);
            }
        }
    }

    public function setLogo(Business $business, UploadedFile $file, array $metadata = []): void
    {
        $stored = $this->media->storeImage($file, "businesses/{$business->uuid}/logo", 600, 600, 88);
        $old = $business->logo_path;
        $business->forceFill(['logo_path' => $stored['path']])->save();
        $this->media->delete($old);
        $this->audit->log('business.logo.updated', $business, metadata: $metadata);
    }

    /** Stores ready PNG bytes (a processed logo) as the listing's logo. */
    public function setLogoPng(Business $business, string $png, array $metadata = []): void
    {
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, $png);
        try {
            $this->setLogo($business, new UploadedFile($path, 'logo.png', 'image/png', null, true), $metadata);
        } finally {
            @unlink($path);
        }
    }

    public function setCover(Business $business, UploadedFile $file): void
    {
        $stored = $this->media->storeImage($file, "businesses/{$business->uuid}/cover", 2000, 1200);
        $old = $business->cover_path;
        $business->forceFill(['cover_path' => $stored['path']])->save();
        $this->media->delete($old);
        $this->audit->log('business.cover.updated', $business);
    }

    public function removeLogo(Business $business): void
    {
        $this->media->delete($business->logo_path);
        $business->forceFill(['logo_path' => null])->save();
    }

    public function removeCover(Business $business): void
    {
        $this->media->delete($business->cover_path);
        $business->forceFill(['cover_path' => null])->save();
    }

    /** @param list<UploadedFile> $files */
    public function addGalleryImages(Business $business, array $files): int
    {
        $max = $this->settings->int('uploads.max_gallery_images', 12);
        $existing = $business->images()->count();
        $room = max(0, $max - $existing);
        if ($room === 0) {
            throw new WorkflowException(__('workflow.gallery_full', ['max' => $max]));
        }

        $added = 0;
        $order = (int) $business->images()->max('sort_order');
        foreach (array_slice($files, 0, $room) as $file) {
            $stored = $this->media->storeImage($file, "businesses/{$business->uuid}/gallery");
            $business->images()->create([
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'width' => $stored['width'],
                'height' => $stored['height'],
                'size_bytes' => $stored['size'],
                'sort_order' => ++$order,
            ]);
            $added++;
        }
        $this->audit->log('business.gallery.added', $business, [], ['count' => $added]);

        return $added;
    }

    public function deleteImage(BusinessImage $image): void
    {
        $this->media->delete($image->path, $image->disk);
        $this->audit->log('business.gallery.deleted', $image->business, ['path' => $image->path]);
        $image->delete();
    }

    /** @param list<int> $orderedIds */
    public function reorderImages(Business $business, array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $i => $id) {
            $business->images()->whereKey((int) $id)->update(['sort_order' => $i]);
        }
    }
}
