<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FaqController extends Controller
{
    public const AUDIENCES = ['general', 'users', 'owners'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $faqs = Faq::query()->when(in_array($request->query('audience'), self::AUDIENCES, true), fn ($q) => $q->where('audience', $request->query('audience')))
            ->orderBy('audience')->orderBy('sort_order')->orderBy('id')->get()->groupBy('audience');

        return view('admin.content.faqs-index', ['faqs' => $faqs]);
    }

    public function create(): View
    {
        return view('admin.content.faq-form', ['faq' => new Faq(['audience' => 'general', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $faq = Faq::query()->create($this->validated($request));
        $this->audit->log('faq.created', $faq);

        return redirect()->route('admin.faqs.index')->with('success', __('admin.content.faq_created'));
    }

    public function edit(Faq $faq): View
    {
        return view('admin.content.faq-form', ['faq' => $faq]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $original = $faq->getAttributes();
        $faq->update($this->validated($request));
        $this->audit->logChanges('faq.updated', $faq, $original);

        return redirect()->route('admin.faqs.index')->with('success', __('common.updated'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->audit->log('faq.deleted', $faq, $faq->only(['question_ar', 'question_en']));
        $faq->delete();

        return back()->with('success', __('common.deleted'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(self::AUDIENCES)],
            'question_ar' => ['required', 'string', 'max:300'],
            'question_en' => ['required', 'string', 'max:300'],
            'answer_ar' => ['required', 'string', 'max:5000'],
            'answer_en' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $data['sort_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
