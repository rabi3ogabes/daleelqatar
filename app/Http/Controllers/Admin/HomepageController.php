<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomepageController extends Controller
{
    public function edit(SettingsService $settings): View
    {
        return view('admin.content.homepage', [
            'heroTitle' => (array) $settings->get('home.hero_title', []),
            'heroSubtitle' => (array) $settings->get('home.hero_subtitle', []),
            'heroImage' => media_url($settings->get('home.hero_image_path')) ?? asset('images/doha-skyline.svg'),
            'customImage' => (bool) $settings->get('home.hero_image_path'),
            'showOffers' => $settings->bool('home.show_offers'),
            'showTrust' => $settings->bool('home.show_trust'),
        ]);
    }

    public function update(Request $request, SettingsService $settings, MediaService $media, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'hero_title.ar' => ['required', 'string', 'max:160'],
            'hero_title.en' => ['required', 'string', 'max:160'],
            'hero_subtitle.ar' => ['nullable', 'string', 'max:300'],
            'hero_subtitle.en' => ['nullable', 'string', 'max:300'],
            'hero_image' => ['nullable', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.(int) setting('uploads.max_image_kb', 5120), 'dimensions:min_width=1200,min_height=500'],
        ]);
        $uid = $request->user()->id;

        $settings->set('home.hero_title', ['ar' => $data['hero_title']['ar'], 'en' => $data['hero_title']['en']], $uid);
        $settings->set('home.hero_subtitle', ['ar' => $data['hero_subtitle']['ar'] ?? null, 'en' => $data['hero_subtitle']['en'] ?? null], $uid);
        $settings->set('home.show_offers', $request->boolean('show_offers'), $uid);
        $settings->set('home.show_trust', $request->boolean('show_trust'), $uid);

        $old = $settings->get('home.hero_image_path');
        if ($request->hasFile('hero_image')) {
            $stored = $media->storeImage($request->file('hero_image'), 'settings', 2400, 1400, 84);
            $settings->set('home.hero_image_path', $stored['path'], $uid);
            $media->delete($old);
        } elseif ($request->boolean('reset_image') && $old) {
            $settings->set('home.hero_image_path', null, $uid);
            $media->delete($old);
        }

        $audit->log('settings.updated', null, [], ['keys' => ['home.hero_title', 'home.hero_subtitle', 'home.hero_image_path', 'home.show_offers', 'home.show_trust']], ['group' => 'home']);

        return back()->with('success', __('admin.content.homepage_saved'));
    }
}
