<x-layouts.dashboard area="admin" :title="$page->t('title')" :subtitle="'/'.$page->slug" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.content.pages'), 'url' => route('admin.pages.index')], ['label' => $page->t('title')]]">
    <form method="POST" action="{{ route('admin.pages.update', $page) }}" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf @method('PUT')
        <div class="space-y-6">
            <x-dash.panel :title="__('admin.content.page_content')">
                <div class="grid gap-5">
                    <x-form.bilingual name="title" :label="__('admin.content.page_title')" :model="$page" required maxlength="200" />
                    <x-form.bilingual name="body" type="textarea" rows="16" :label="__('admin.content.body')" :model="$page" class="font-mono text-sm" />
                    <p class="flex items-start gap-2 rounded-xl bg-navy-50 px-4 py-3 text-xs text-muted-strong"><x-icon name="info" class="size-4 shrink-0 text-gold-600" />{{ __('admin.content.markdown_hint') }}</p>
                </div>
            </x-dash.panel>
            <x-dash.panel :title="__('business_form.seo')">
                <div class="grid gap-5">
                    <x-form.bilingual name="meta_title" :label="__('business_form.meta_title')" :model="$page" maxlength="160" />
                    <x-form.bilingual name="meta_description" type="textarea" rows="2" :label="__('business_form.meta_description')" :model="$page" maxlength="300" />
                </div>
            </x-dash.panel>
        </div>
        <aside>
            <div class="card space-y-5 p-5 xl:sticky xl:top-24">
                <x-form.toggle name="is_published" :label="__('admin.published')" :checked="$page->is_published" />
                @if(\Illuminate\Support\Facades\Route::has('ar.'.$page->slug))
                    <a href="{{ route($page->slug) }}" target="_blank" class="btn btn-outline w-full"><x-icon name="external-link" class="size-4" />{{ __('admin.content.preview') }}</a>
                @endif
                <button class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('common.save_changes') }}</button>
                <a href="{{ route('admin.pages.index') }}" class="btn btn-ghost w-full">{{ __('common.cancel') }}</a>
            </div>
        </aside>
    </form>
</x-layouts.dashboard>
