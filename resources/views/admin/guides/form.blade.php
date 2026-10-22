@php
    $editing = $guide->exists;
    $categoryOptions = $categories->mapWithKeys(fn ($root) => [
        $root->t('name') => [$root->id => $root->t('name').' — '.__('business_form.general')] + $root->children->mapWithKeys(fn ($c) => [$c->id => $c->t('name')])->all(),
    ])->all();
    $state = [
        'category' => (string) old('category_id', $guide->category_id ?? ''),
        'municipality' => (string) old('municipality_id', $guide->municipality_id ?? ''),
        'area' => (string) old('area_id', $guide->area_id ?? ''),
        'open24' => (bool) old('open_24h', $guide->open_24h),
        'verified' => (bool) old('verified_only', $guide->verified_only),
        'pinned' => $pinned,
        'previewUrl' => route('admin.guides.preview'),
    ];
@endphp
<x-layouts.dashboard area="admin" :title="$editing ? $guide->t('title') : __('guides.admin.create')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('guides.admin.title'), 'url' => route('admin.guides.index')], ['label' => $editing ? $guide->t('title') : __('guides.admin.create')]]">
    @if($editing)
        <x-slot:actions><a href="{{ route('guides.show', $guide) }}" target="_blank" class="btn btn-outline"><x-icon name="external-link" class="size-4" />{{ __('guides.admin.view') }}</a></x-slot:actions>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.guides.update', $guide) : route('admin.guides.store') }}" class="grid gap-6 xl:grid-cols-3" x-data="guideEditor(@js($state), @js($areaOptions))">
        @csrf @if($editing) @method('PUT') @endif
        <div class="space-y-6 xl:col-span-2">
            <x-dash.panel :title="__('guides.admin.content')" :subtitle="__('guides.admin.content_hint')">
                <div class="grid gap-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <x-form.input name="title_ar" dir="rtl" :label="__('guides.admin.title_ar')" :value="$guide->title_ar" required maxlength="160" />
                        <x-form.input name="title_en" dir="ltr" :label="__('guides.admin.title_en')" :value="$guide->title_en" required maxlength="160" />
                    </div>
                    <x-form.textarea name="intro_ar" dir="rtl" rows="6" :label="__('guides.admin.intro_ar')" :value="$guide->intro_ar" maxlength="4000" :hint="__('guides.admin.intro_hint', ['min' => \App\Support\Guides\GuideBuilder::MIN_INTRO])" />
                    <x-form.textarea name="intro_en" dir="ltr" rows="6" :label="__('guides.admin.intro_en')" :value="$guide->intro_en" maxlength="4000" />
                    <div class="grid gap-5 md:grid-cols-2">
                        <x-form.textarea name="meta_description_ar" dir="rtl" rows="2" :label="__('guides.admin.meta_ar')" :value="$guide->meta_description_ar" maxlength="300" />
                        <x-form.textarea name="meta_description_en" dir="ltr" rows="2" :label="__('guides.admin.meta_en')" :value="$guide->meta_description_en" maxlength="300" />
                    </div>
                </div>
            </x-dash.panel>

            <x-dash.panel :title="__('guides.admin.rules')" :subtitle="__('guides.admin.rules_hint')">
                <div class="grid gap-5">
                    <x-form.select name="category_id" :label="__('quotes.form.category')" :options="$categoryOptions" :placeholder="__('common.all')" x-model="category" @change="refresh()" />
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="g_municipality" class="label">{{ __('business_form.municipality') }}</label>
                            <select id="g_municipality" name="municipality_id" class="select" x-model="municipality" @change="areaChanged(); refresh()">
                                <option value="">{{ __('quotes.anywhere') }}</option>
                                @foreach($municipalities as $m)<option value="{{ $m->id }}">{{ $m->t('name') }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="g_area" class="label">{{ __('business_form.area') }}</label>
                            <select id="g_area" name="area_id" class="select" x-model="area" :disabled="!municipality" @change="refresh()">
                                <option value="">{{ __('common.all') }}</option>
                                <template x-for="a in areaList" :key="a.id"><option :value="String(a.id)" x-text="a.name" :selected="String(a.id) === area"></option></template>
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-start gap-2.5 rounded-xl border border-navy-100 p-3 text-sm"><input type="checkbox" name="open_24h" value="1" class="checkbox mt-0.5" x-model="open24" @change="refresh()"><span><span class="block font-medium text-navy-900">{{ __('guides.open_24h') }}</span><span class="text-xs text-muted-strong">{{ __('guides.admin.open_24h_hint') }}</span></span></label>
                        <label class="flex items-start gap-2.5 rounded-xl border border-navy-100 p-3 text-sm"><input type="checkbox" name="verified_only" value="1" class="checkbox mt-0.5" x-model="verified" @change="refresh()"><span><span class="block font-medium text-navy-900">{{ __('guides.verified_only') }}</span><span class="text-xs text-muted-strong">{{ __('guides.admin.verified_hint') }}</span></span></label>
                    </div>
                    <x-form.input name="max_items" type="number" min="3" max="30" dir="ltr" :label="__('guides.admin.max_items')" :value="$guide->max_items ?? 12" class="max-w-40" />
                </div>
            </x-dash.panel>
        </div>

        <aside class="space-y-6">
            <x-dash.panel :title="__('guides.admin.publishing')">
                <x-form.select name="status" :value="$guide->status ?? 'draft'" :options="['draft' => __('guides.admin.draft'), 'published' => __('guides.admin.published')]" />
                <p class="mt-3 text-xs text-muted-strong">{{ __('guides.admin.index_rule', ['min' => \App\Support\Guides\GuideBuilder::MIN_LISTINGS, 'chars' => \App\Support\Guides\GuideBuilder::MIN_INTRO]) }}</p>
                <button class="btn btn-primary mt-4 w-full"><x-icon name="check" class="size-4" />{{ __('common.save') }}</button>
            </x-dash.panel>

            <x-dash.panel :title="__('guides.admin.preview')" :subtitle="__('guides.admin.preview_hint')" aria-live="polite">
                <p class="text-sm"><span class="text-2xl font-bold tabular-nums text-navy-900" x-text="loading ? '…' : count"></span> <span class="text-muted-strong">{{ __('guides.admin.qualifying') }}</span></p>
                <p class="mt-2 rounded-xl bg-warning-soft px-3 py-2 text-xs text-warning" x-show="!loading && count < min" x-cloak>{{ __('guides.admin.too_few', ['min' => \App\Support\Guides\GuideBuilder::MIN_LISTINGS]) }}</p>
                <template x-for="(id, i) in pinned.map(p => p.id)" :key="id"><input type="hidden" name="pinned[]" :value="id"></template>
                <ul class="mt-4 max-h-[28rem] space-y-1 overflow-y-auto">
                    <template x-for="item in items" :key="item.id">
                        <li class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-navy-50">
                            <button type="button" class="btn-icon size-8 shrink-0" :class="isPinned(item.id) ? 'text-gold-600' : 'text-muted hover:text-gold-600'" @click="toggle(item)" :aria-pressed="isPinned(item.id)" :aria-label="@js(__('guides.admin.pin'))">
                                <x-icon name="star" class="size-4" />
                            </button>
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm text-navy-900" x-text="item.name"></span><span class="block truncate text-xs text-muted" x-text="item.meta"></span></span>
                            <template x-if="isPinned(item.id)"><span class="badge badge-gold" x-text="'#' + (pinIndex(item.id) + 1)"></span></template>
                        </li>
                    </template>
                </ul>
                <p class="mt-3 text-xs text-muted-strong">{{ __('guides.admin.pin_hint') }}</p>
            </x-dash.panel>
        </aside>
    </form>
</x-layouts.dashboard>
