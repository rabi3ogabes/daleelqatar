@php $editing = $faq->exists; @endphp
<x-layouts.dashboard area="admin" :title="$editing ? __('admin.content.faq_edit') : __('admin.content.faq_create')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.content.faqs'), 'url' => route('admin.faqs.index')], ['label' => $editing ? __('admin.content.faq_edit') : __('admin.content.faq_create')]]">
    <form method="POST" action="{{ $editing ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf @if($editing) @method('PUT') @endif
        <x-dash.panel>
            <div class="grid gap-5">
                <x-form.bilingual name="question" :label="__('admin.content.question')" :model="$faq" required maxlength="300" />
                <x-form.bilingual name="answer" type="textarea" rows="5" :label="__('admin.content.answer')" :model="$faq" required maxlength="5000" />
            </div>
        </x-dash.panel>
        <aside>
            <div class="card space-y-5 p-5">
                <x-form.select name="audience" :label="__('admin.content.audience')" :options="collect(\App\Http\Controllers\Admin\FaqController::AUDIENCES)->mapWithKeys(fn ($a) => [$a => __('pages.faq_'.$a)])->all()" :value="$faq->audience" required />
                <x-form.input name="sort_order" type="number" min="0" :label="__('admin.sort_order')" :value="$faq->sort_order" />
                <x-form.toggle name="is_active" :label="__('admin.active')" :checked="$faq->is_active" />
                <button class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('common.save') }}</button>
                <a href="{{ route('admin.faqs.index') }}" class="btn btn-ghost w-full">{{ __('common.cancel') }}</a>
            </div>
        </aside>
    </form>
</x-layouts.dashboard>
