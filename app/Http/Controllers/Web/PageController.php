<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return $this->page('about');
    }

    public function privacy(): View
    {
        return $this->page('privacy');
    }

    public function terms(): View
    {
        return $this->page('terms');
    }

    public function faq(): View
    {
        $faqs = Faq::query()->active()->get()->groupBy('audience');

        seo()->title(__('pages.faq_title'))->description(__('pages.faq_subtitle'))->jsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->flatten()->map(fn (Faq $f) => [
                '@type' => 'Question',
                'name' => $f->t('question'),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->t('answer')],
            ])->values()->all(),
        ]);

        return view('web.pages.faq', ['faqs' => $faqs]);
    }

    public function addBusiness(): View
    {
        seo()->title(__('pages.add_title'))->description(__('pages.add_subtitle'));

        return view('web.pages.add-business', [
            'faqs' => Faq::query()->active()->where('audience', 'owners')->limit(4)->get(),
        ]);
    }

    private function page(string $slug): View
    {
        $page = Page::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        seo()->title($page->t('meta_title') ?: $page->t('title'))
            ->description($page->t('meta_description') ?: strip_tags($page->renderedBody()));

        return view('web.pages.show', ['page' => $page]);
    }
}
