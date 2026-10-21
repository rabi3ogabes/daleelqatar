<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\AuditLogger;
use App\Services\MediaService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BannerController extends Controller
{
    public const PLACEMENTS = ['home_promo'];

    public function __construct(private readonly AuditLogger $audit, private readonly MediaService $media) {}

    public function index(): View
    {
        return view('admin.content.banners-index', ['banners' => Banner::query()->orderBy('placement')->orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        return view('admin.content.banner-form', ['banner' => new Banner(['placement' => 'home_promo', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $banner = Banner::query()->create($this->validated($request));
        $this->storeImage($request, $banner);
        $this->audit->log('banner.created', $banner);

        return redirect()->route('admin.banners.index')->with('success', __('admin.content.banner_created'));
    }

    public function edit(Banner $banner): View
    {
        return view('admin.content.banner-form', ['banner' => $banner]);
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $original = $banner->getAttributes();
        $banner->update($this->validated($request));
        $this->storeImage($request, $banner);
        $this->audit->logChanges('banner.updated', $banner, $original);

        return redirect()->route('admin.banners.index')->with('success', __('common.updated'));
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $this->audit->log('banner.deleted', $banner, $banner->only(['title_ar', 'title_en']));
        $this->media->delete($banner->image_path);
        $banner->delete();

        return back()->with('success', __('common.deleted'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'placement' => ['required', Rule::in(self::PLACEMENTS)],
            'title_ar' => ['required', 'string', 'max:160'],
            'title_en' => ['required', 'string', 'max:160'],
            'subtitle_ar' => ['nullable', 'string', 'max:300'],
            'subtitle_en' => ['nullable', 'string', 'max:300'],
            'cta_label_ar' => ['nullable', 'string', 'max:60'],
            'cta_label_en' => ['nullable', 'string', 'max:60'],
            // Absolute http(s) URL or a site-relative path — never javascript: or other schemes.
            'link_url' => ['nullable', 'string', 'max:300', function (string $attr, mixed $value, Closure $fail) {
                if ($value && ! safe_external_url($value) && ! preg_match('#^/(?!/)[\w\-./?=&%\#]*$#u', $value)) {
                    $fail(__('admin.content.invalid_link'));
                }
            }],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.(int) setting('uploads.max_image_kb', 5120)],
        ]);
        unset($data['image']);
        $data['sort_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function storeImage(Request $request, Banner $banner): void
    {
        if ($request->hasFile('image')) {
            $stored = $this->media->storeImage($request->file('image'), 'banners', 2000, 900);
            $old = $banner->image_path;
            $banner->forceFill(['image_path' => $stored['path']])->save();
            $this->media->delete($old);
        }
    }
}
