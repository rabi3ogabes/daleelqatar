<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessImage;
use App\Services\BusinessManager;
use App\Services\MediaService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Logo, cover and gallery management. Every action is ownership-checked. */
class BusinessMediaController extends Controller
{
    public function __construct(private readonly BusinessManager $manager, private readonly SettingsService $settings) {}

    public function logo(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $request->validate(['logo' => $this->imageRules(true)]);
        $this->manager->setLogo($business, $request->file('logo'));

        return $this->back($business, 'account.businesses.logo_updated');
    }

    public function removeLogo(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $this->manager->removeLogo($business);

        return $this->back($business, 'account.businesses.logo_removed');
    }

    public function cover(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $request->validate(['cover' => $this->imageRules(true)]);
        $this->manager->setCover($business, $request->file('cover'));

        return $this->back($business, 'account.businesses.cover_updated');
    }

    public function removeCover(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $this->manager->removeCover($business);

        return $this->back($business, 'account.businesses.cover_removed');
    }

    public function gallery(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $max = $this->settings->int('uploads.max_gallery_images', 12);
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.$max],
            'images.*' => $this->imageRules(true),
        ]);

        $this->manager->addGalleryImages($business, $request->file('images'));

        return $this->back($business, 'business_form.images_uploaded', '#gallery');
    }

    public function destroyImage(Request $request, Business $business, BusinessImage $image): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        abort_unless($image->business_id === $business->id, 404);
        $this->manager->deleteImage($image);

        return $this->back($business, 'business_form.image_deleted', '#gallery');
    }

    /** Moves one image up or down, or applies a full order when "order[]" is sent. */
    public function reorder(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $data = $request->validate([
            'order' => ['sometimes', 'array'],
            'order.*' => ['integer'],
            'image' => ['required_without:order', 'integer'],
            'direction' => ['required_with:image', 'in:up,down'],
        ]);

        $ids = $business->images()->pluck('id')->all();
        if (isset($data['order'])) {
            $order = array_values(array_intersect(array_map('intval', $data['order']), $ids));
        } else {
            $order = $ids;
            $pos = array_search((int) $data['image'], $order, true);
            abort_if($pos === false, 404);
            $swap = $data['direction'] === 'up' ? $pos - 1 : $pos + 1;
            if (isset($order[$swap])) {
                [$order[$pos], $order[$swap]] = [$order[$swap], $order[$pos]];
            }
        }
        $this->manager->reorderImages($business, $order);

        return $this->back($business, 'account.businesses.gallery_reordered', '#gallery');
    }

    private function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($business->isOwnedBy($request->user()), 404);
        $this->authorize('update', $business);
    }

    private function imageRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable', 'file', 'image',
            'mimetypes:'.implode(',', MediaService::IMAGE_MIMES),
            'max:'.$this->settings->int('uploads.max_image_kb', 5120),
            'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000',
        ];
    }

    private function back(Business $business, string $message, string $anchor = ''): RedirectResponse
    {
        return redirect()->to(route('account.businesses.edit', $business->uuid).$anchor)->with('success', __($message));
    }
}
