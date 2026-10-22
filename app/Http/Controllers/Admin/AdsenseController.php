<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdsenseReadiness;
use App\Support\Integrations;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Google AdSense readiness: everything the review looks at, and where to fix it. */
class AdsenseController extends Controller
{
    public function index(Request $request, AdsenseReadiness $readiness): View
    {
        return view('admin.adsense.index', [
            'report' => $readiness->report(fresh: $request->boolean('refresh')),
            'consoleCode' => Integrations::verificationMeta()['google-site-verification'] ?? null,
            'consoleConfirmed' => (bool) setting('integrations.search_console_verified', false),
        ]);
    }
}
