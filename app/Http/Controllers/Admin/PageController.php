<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\AuditLogger;
use App\Support\AdsenseReadiness;
use App\Support\Sitemap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.content.pages-index', ['pages' => Page::query()->orderBy('slug')->get()]);
    }

    public function edit(Page $page): View
    {
        return view('admin.content.page-form', ['page' => $page]);
    }

    public function update(Request $request, Page $page, AuditLogger $audit): RedirectResponse
    {
        AdsenseReadiness::forget();
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:200'],
            'title_en' => ['required', 'string', 'max:200'],
            'body_ar' => ['nullable', 'string', 'max:60000'],
            'body_en' => ['nullable', 'string', 'max:60000'],
            'meta_title_ar' => ['nullable', 'string', 'max:160'],
            'meta_title_en' => ['nullable', 'string', 'max:160'],
            'meta_description_ar' => ['nullable', 'string', 'max:300'],
            'meta_description_en' => ['nullable', 'string', 'max:300'],
        ]);
        $data['is_published'] = $request->boolean('is_published');

        $original = $page->getAttributes();
        $page->fill($data);
        $page->updated_by = $request->user()->id;
        $page->save();
        $audit->logChanges('page.updated', $page, $original);
        Sitemap::flush();

        return redirect()->route('admin.pages.index')->with('success', __('common.updated'));
    }
}
