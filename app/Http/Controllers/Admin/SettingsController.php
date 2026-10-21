<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function edit(SettingsService $settings, ?string $group = null): View
    {
        $groups = SettingsService::groups();
        $group = in_array($group, $groups, true) ? $group : $groups[0];

        $fields = collect(SettingsService::defaults())->filter(fn ($d) => $d['group'] === $group)
            ->map(fn ($d, $key) => $d + ['key' => $key, 'input' => self::inputName($key), 'current' => $settings->get($key)]);

        return view('admin.settings.edit', [
            'groups' => $groups,
            'group' => $group,
            'fields' => $fields,
            'health' => $this->health(),
        ]);
    }

    public function update(Request $request, SettingsService $settings, MediaService $media, AuditLogger $audit, string $group): RedirectResponse
    {
        abort_unless(in_array($group, SettingsService::groups(), true), 404);
        $defs = collect(SettingsService::defaults())->filter(fn ($d) => $d['group'] === $group);
        $maxKb = (int) setting('uploads.max_image_kb', 5120);

        $rules = [];
        foreach ($defs as $key => $d) {
            $in = 's.'.self::inputName($key);
            match ($d['type']) {
                'text_i18n' => [$rules[$in.'.ar'] = ['nullable', 'string', 'max:300'], $rules[$in.'.en'] = ['nullable', 'string', 'max:300']],
                'textarea_i18n' => [$rules[$in.'.ar'] = ['nullable', 'string', 'max:2000'], $rules[$in.'.en'] = ['nullable', 'string', 'max:2000']],
                'boolean' => $rules[$in] = ['nullable', 'boolean'],
                'integer' => $rules[$in] = ['required', 'integer', 'min:0', 'max:1000000'],
                'email' => $rules[$in] = ['nullable', 'email:rfc', 'max:191'],
                'url' => $rules[$in] = ['nullable', 'url:http,https', 'max:300'],
                'image' => $rules['file_'.self::inputName($key)] = ['nullable', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.$maxKb],
                default => $rules[$in] = ['nullable', 'string', 'max:300'],
            };
        }
        $request->validate($rules);

        $changed = [];
        foreach ($defs as $key => $d) {
            $input = self::inputName($key);
            $old = $settings->get($key);
            $new = match ($d['type']) {
                'text_i18n', 'textarea_i18n' => ['ar' => $request->input("s.$input.ar"), 'en' => $request->input("s.$input.en")],
                'boolean' => $request->boolean("s.$input"),
                'integer' => (int) $request->input("s.$input"),
                'image' => $old,
                default => $request->input("s.$input") ?: null,
            };

            if ($d['type'] === 'image') {
                if ($request->hasFile('file_'.$input)) {
                    $new = $media->storeImage($request->file('file_'.$input), 'settings', 1200, 1200, 90)['path'];
                    $media->delete($old);
                } elseif ($request->boolean('remove_'.$input)) {
                    $media->delete($old);
                    $new = null;
                }
            }

            if ($new !== $old) {
                $settings->set($key, $new, $request->user()->id);
                $changed[$key] = ['from' => $old, 'to' => $new];
            }
        }

        if ($changed) {
            $audit->log('settings.updated', null, array_map(fn ($c) => $c['from'], $changed), array_map(fn ($c) => $c['to'], $changed), ['group' => $group]);
            Cache::forget('sitemap.xml');
        }

        return redirect()->route('admin.settings.edit', $group)->with('success', $changed ? __('admin.settings.saved') : __('admin.settings.no_changes'));
    }

    public static function inputName(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** Read-only operational indicators (no secrets). */
    private function health(): array
    {
        $db = 'unknown';
        try {
            $db = (string) (DB::selectOne('select version() as v')->v ?? 'unknown');
            $db = preg_replace('/ on .*$/', '', $db) ?? $db;
        } catch (Throwable) {
        }

        $mediaDisk = (string) config('filesystems.media_disk');
        $writable = is_writable(storage_path('framework')) && is_writable(storage_path('logs'));

        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'database' => config('database.default').' — '.$db,
            'queue' => (string) config('queue.default'),
            'cache' => (string) config('cache.default'),
            'media_disk' => $mediaDisk.' ('.(config("filesystems.disks.$mediaDisk.driver") ?? '?').')',
            'storage_writable' => $writable,
            'pending_jobs' => rescue(fn () => DB::table('jobs')->count(), null, false),
            'failed_jobs' => rescue(fn () => DB::table('failed_jobs')->count(), null, false),
            'mail' => (string) config('mail.default'),
            'disk_ok' => rescue(fn () => Storage::disk('local')->put('.health', 'ok') && Storage::disk('local')->delete('.health'), false, false),
        ];
    }
}
