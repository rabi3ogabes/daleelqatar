<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\BusinessManager;
use App\Support\Enrichment\LogoFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Logo studio: finds a company's real logo on its website (or the domain of
 * its e-mail), or saves a branded logo the admin designed in the browser.
 * Nothing changes until the admin picks one.
 */
class LogoController extends Controller
{
    public const STYLES = ['navy', 'ivory', 'gold', 'white'];

    public function __construct(private readonly LogoFinder $finder, private readonly BusinessManager $manager) {}

    public function find(Business $business): JsonResponse
    {
        abort_if($business->trashed(), 404);
        $result = $this->finder->find($business);

        $message = match (true) {
            $result['candidates'] !== [] => trans_choice('logos.found', count($result['candidates']), ['count' => count($result['candidates'])]),
            $result['sites'] === [] => __('logos.no_website'),
            ! in_array(true, array_column($result['sites'], 'reachable'), true) => __('logos.unreachable'),
            default => __('logos.none_found'),
        };

        return response()->json($result + ['message' => $message]);
    }

    public function apply(Request $request, Business $business): JsonResponse
    {
        abort_if($business->trashed(), 404);
        $data = $request->validate([
            'candidate' => ['nullable', 'required_without:logo', 'string', 'alpha_num', 'max:40'],
            'logo' => ['nullable', 'required_without:candidate', 'file', 'mimetypes:image/png', 'max:4096', 'dimensions:min_width=200,min_height=200,max_width=2400,max_height=2400'],
            'origin' => ['required', Rule::in(['website', 'generated'])],
            'style' => ['nullable', Rule::in(self::STYLES)],
            'source_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $metadata = ['origin' => $data['origin']];
        if (! empty($data['candidate'])) {
            $candidate = $this->finder->pull($business, $data['candidate']);
            if ($candidate === null || $candidate['kind'] !== 'raster') {
                return response()->json(['message' => __('logos.expired')], 422);
            }
            $png = $candidate['bytes'];
            $metadata['source_url'] = $candidate['url'];
        } else {
            $png = (string) file_get_contents($request->file('logo')->getRealPath());
            if ($data['origin'] === 'website') {
                // A vector logo drawn by the browser: tidy it like any other website logo.
                $png = LogoFinder::process($png)['png'] ?? null;
                $metadata['source_url'] = isset($data['source_url']) && preg_match('#^https?://#i', $data['source_url']) ? $data['source_url'] : null;
            } else {
                $metadata['style'] = $data['style'] ?? null;
            }
            if ($png === null) {
                return response()->json(['message' => __('logos.invalid')], 422);
            }
        }

        $this->manager->setLogoPng($business, $png, array_filter($metadata));

        session()->flash('success', __('logos.saved'));

        return response()->json(['message' => __('logos.saved'), 'logo' => $business->logoUrl(), 'redirect' => route('admin.businesses.show', $business->id)]);
    }
}
