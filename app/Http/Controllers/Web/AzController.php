<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\AzIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** A–Z company directory: every published listing reachable within two clicks of the homepage. */
class AzController extends Controller
{
    public function index(): View
    {
        $counts = AzIndex::counts();

        seo()->title(__('directory.az.title'))->description(__('directory.az.seo_description'))->noindex(array_sum($counts) === 0);

        return view('web.directory.az', ['counts' => $counts, 'letter' => null, 'results' => null]);
    }

    public function letter(string $letter): View|RedirectResponse
    {
        $upper = mb_strtoupper($letter);
        if ($upper !== $letter && AzIndex::isLetter($upper)) {
            return redirect()->route('directory.az.letter', ['letter' => $upper], 301);
        }
        abort_unless(AzIndex::isLetter($letter), 404);

        $counts = AzIndex::counts();
        $results = AzIndex::query($letter)
            ->with(['category:id,name_ar,name_en,slug,icon', 'municipality:id,name_ar,name_en,slug'])
            ->select(['businesses.id', 'businesses.slug', 'businesses.name_ar', 'businesses.name_en', 'businesses.category_id', 'businesses.municipality_id', 'businesses.is_verified'])
            ->paginate(60)->withQueryString();
        abort_if($results->isEmpty() && $results->currentPage() > 1, 404);

        $label = $letter === AzIndex::OTHER ? __('directory.az.other') : $letter;
        seo()->title(__('directory.az.title_letter', ['letter' => $label]).($results->currentPage() > 1 ? ' — '.__('seo.page_n', ['page' => $results->currentPage()]) : ''))
            ->description(__('directory.az.seo_description'))
            ->alternates([
                'ar' => route('ar.directory.az.letter', ['letter' => $letter]),
                'en' => route('en.directory.az.letter', ['letter' => $letter]),
            ])
            ->noindex($results->isEmpty());

        return view('web.directory.az', ['counts' => $counts, 'letter' => $letter, 'label' => $label, 'results' => $results]);
    }
}
