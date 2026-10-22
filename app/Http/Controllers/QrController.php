<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Business;
use App\Support\Analytics\Visit;
use App\Support\Insights\Impressions;
use App\Support\Insights\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/** The printable poster with a QR code, and the short address its QR code opens. */
class QrController extends Controller
{
    /** /qr/{slug}: counts the scan and opens the listing in the visitor's language. */
    public function scan(Request $request, string $slug): RedirectResponse
    {
        $business = Business::query()->published()->where('slug', $slug)->first();
        if (! $business) {
            return redirect()->to(URL::to('/'));
        }

        if (! Visit::isBot($request->userAgent())) {
            Impressions::record([$business->id], 'qr_scans');
        }

        $locale = $request->getPreferredLanguage(['ar', 'en']) ?? 'ar';
        $target = route($locale.'.business.show', $business).'?'.http_build_query(['utm_source' => 'qr', 'utm_medium' => 'poster']);

        return redirect()->to($target)->header('Cache-Control', 'no-store');
    }

    /** Owner: their own listing. */
    public function poster(Request $request, Business $business): View
    {
        abort_unless($business->isOwnedBy($request->user()) && $business->isPublished(), 404);

        return $this->render($request, $business, route('account.businesses.show', $business->uuid));
    }

    /** Staff: any published listing. */
    public function adminPoster(Request $request, Business $business): View
    {
        abort_unless($business->isPublished(), 404);

        return $this->render($request, $business, route('admin.businesses.show', $business->id));
    }

    public function svg(Request $request, Business $business): Response
    {
        abort_unless($business->isPublished() && ($business->isOwnedBy($request->user()) || Gate::allows('businesses.update')), 404);

        return response(QrCode::svg(QrCode::url($business), 1024), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="daleelqatar-'.$business->slug.'-qr.svg"',
        ]);
    }

    private function render(Request $request, Business $business, string $back): View
    {
        $business->load(['category:id,name_ar,name_en', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id']);
        $format = in_array($request->query('format'), ['a4', 'card'], true) ? $request->query('format') : 'a4';
        $url = QrCode::url($business);

        return view('posters.business', [
            'business' => $business,
            'format' => $format,
            'qr' => QrCode::svg($url, $format === 'card' ? 560 : 720),
            'url' => $url,
            'displayUrl' => preg_replace('#^https?://(www\.)?#', '', $url),
            'back' => $back,
            'svgUrl' => $business->isOwnedBy($request->user())
                ? route('account.businesses.qr', $business->uuid)
                : route('admin.businesses.qr', $business->id),
        ]);
    }
}
