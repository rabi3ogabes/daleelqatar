{{--
    Shared listing form body (owner dashboard + admin dashboard).
    Expects: $business (Business|null), $categories (root categories with children), $municipalities, $areaOptions
    The surrounding <form> (with enctype="multipart/form-data") is provided by the including view.
--}}
@php
    $b = $business ?? null;
    $nav = app(\App\Support\NavigationData::class);
    $categories ??= $nav->rootCategories();
    $municipalities ??= $nav->municipalities();
    $areaOptions ??= $nav->areaOptions();
    $categoryOptions = $categories->mapWithKeys(fn ($root) => [
        $root->t('name') => [$root->id => $root->t('name').' — '.__('business_form.general')] + $root->children->mapWithKeys(fn ($c) => [$c->id => $c->t('name')])->all(),
    ])->all();
    $secondary = old('category_ids', $b?->relationLoaded('categories') ? $b->categories->pluck('id')->all() : ($b?->categories()->pluck('categories.id')->all() ?? []));

    // Opening hours editor state
    $days = [6, 0, 1, 2, 3, 4, 5]; // Saturday-first week, common in Qatar
    $existing = $b ? $b->openingHours->groupBy('day_of_week') : collect();
    $hoursState = collect($days)->map(function ($d) use ($existing) {
        $old = old('hours.'.$d);
        $rows = $existing->get($d, collect());
        $closed = $old ? ! empty($old['closed']) : ($rows->isNotEmpty() && $rows->first()->is_closed);
        $allDay = $old ? ! empty($old['all_day']) : ($rows->isNotEmpty() && $rows->first()->isAllDay());
        $periods = $old['periods'] ?? $rows->filter(fn ($r) => ! $r->is_closed && ! $r->isAllDay())->map(fn ($r) => ['opens' => substr((string) $r->opens_at, 0, 5), 'closes' => substr((string) $r->closes_at, 0, 5)])->values()->all();
        $enabled = $old ? ! empty($old['enabled']) : $rows->isNotEmpty();

        return ['day' => $d, 'label' => __('business.days.'.$d), 'enabled' => $enabled, 'closed' => $closed, 'all_day' => $allDay, 'periods' => $periods ?: [['opens' => '09:00', 'closes' => '22:00']]];
    })->values();

    $services = old('services', $b ? $b->services->map(fn ($s) => $s->only(['name_ar', 'name_en', 'description_ar', 'description_en', 'price_from']))->all() : []);
    $social = old('social', $b ? $b->socialLinks->mapWithKeys(fn ($l) => [$l->platform->value => $l->url])->all() : []);
    $section = 'card p-5 sm:p-6';
    $heading = 'mb-1 text-base font-semibold text-navy-900';
    $sub = 'mb-5 text-sm text-muted-strong';
@endphp

<div class="space-y-6">
    {{-- Basics --}}
    <section class="{{ $section }}" aria-labelledby="bf-basics">
        <h2 id="bf-basics" class="{{ $heading }}">{{ __('business_form.basics') }}</h2>
        <p class="{{ $sub }}">{{ __('business_form.basics_hint') }}</p>
        <div class="grid gap-5">
            <x-form.bilingual name="name" :label="__('business_form.name')" :model="$b" required maxlength="160" />
            <x-form.bilingual name="tagline" :label="__('business_form.tagline')" :model="$b" maxlength="200" :hint="__('business_form.tagline_hint')" />
            <div class="grid gap-5 md:grid-cols-2">
                <x-form.select name="category_id" :label="__('business_form.category')" :options="$categoryOptions" :value="$b?->category_id" :placeholder="__('business_form.choose_category')" required />
                <div>
                    <label for="f_category_ids" class="label">{{ __('business_form.secondary_categories') }} <span class="font-normal text-muted">({{ __('common.optional') }})</span></label>
                    <select id="f_category_ids" name="category_ids[]" multiple size="4" class="select !bg-none">
                        @foreach($categoryOptions as $group => $opts)
                            <optgroup label="{{ $group }}">
                                @foreach($opts as $id => $label)<option value="{{ $id }}" @selected(in_array($id, array_map('intval', (array) $secondary), true))>{{ $label }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="hint">{{ __('business_form.secondary_hint') }}</p>
                    @error('category_ids')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <x-form.bilingual name="description" type="textarea" rows="6" :label="__('business_form.description')" :model="$b" :hint="__('business_form.description_hint')" maxlength="5000" />
            <x-form.input name="keywords" :label="__('business_form.keywords')" :value="$b?->keywords" :hint="__('business_form.keywords_hint')" maxlength="500" />
        </div>
    </section>

    {{-- Location --}}
    <section class="{{ $section }}" aria-labelledby="bf-location">
        <h2 id="bf-location" class="{{ $heading }}">{{ __('business_form.location') }}</h2>
        <p class="{{ $sub }}">{{ __('business_form.location_hint') }}</p>
        <div class="grid gap-5" x-data="locationPicker(@js($areaOptions), @js(old('municipality_id', $b?->municipality_id)), @js(old('area_id', $b?->area_id)))">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="f_municipality_id" class="label">{{ __('business_form.municipality') }}</label>
                    <select id="f_municipality_id" name="municipality_id" class="select @error('municipality_id') input-invalid @enderror" x-model="municipality" @change="changed()">
                        <option value="">{{ __('business_form.choose_municipality') }}</option>
                        @foreach($municipalities as $m)<option value="{{ $m->id }}">{{ $m->t('name') }}</option>@endforeach
                    </select>
                    @error('municipality_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="f_area_id" class="label">{{ __('business_form.area') }}</label>
                    <select id="f_area_id" name="area_id" class="select @error('area_id') input-invalid @enderror" x-model="area" :disabled="!municipality">
                        <option value="">{{ __('business_form.choose_area') }}</option>
                        <template x-for="a in options" :key="a.id"><option :value="String(a.id)" x-text="a.name" :selected="String(a.id) === area"></option></template>
                    </select>
                    @error('area_id')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <x-form.bilingual name="address" :label="__('business_form.address')" :model="$b" maxlength="300" />
            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input name="latitude" type="number" step="0.0000001" dir="ltr" :label="__('business_form.latitude')" :value="$b?->latitude" placeholder="25.2854" />
                <x-form.input name="longitude" type="number" step="0.0000001" dir="ltr" :label="__('business_form.longitude')" :value="$b?->longitude" placeholder="51.5310" />
            </div>
            <p class="-mt-2 flex items-start gap-2 text-xs text-muted-strong"><x-icon name="info" class="size-4 text-gold-600" />{{ __('business_form.coordinates_hint') }}</p>
        </div>
    </section>

    {{-- Contact --}}
    <section class="{{ $section }}" aria-labelledby="bf-contact">
        <h2 id="bf-contact" class="{{ $heading }}">{{ __('business_form.contact') }}</h2>
        <p class="{{ $sub }}">{{ __('business_form.contact_hint') }}</p>
        <div class="grid gap-5 md:grid-cols-2">
            <x-form.input name="phone" type="tel" dir="ltr" icon="phone" :label="__('business.call')" :value="$b?->phone" placeholder="+974 4000 0000" />
            <x-form.input name="whatsapp" type="tel" dir="ltr" icon="whatsapp" :label="__('business.whatsapp')" :value="$b?->whatsapp" placeholder="+974 5000 0000" />
            <x-form.input name="email" type="email" dir="ltr" icon="mail" :label="__('business.email')" :value="$b?->email" />
            <x-form.input name="website" type="url" dir="ltr" icon="globe" :label="__('business.website')" :value="$b?->website" placeholder="https://" />
        </div>
        <h3 class="mt-7 mb-3 text-sm font-semibold text-navy-900">{{ __('business.social') }}</h3>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach(\App\Enums\SocialPlatform::cases() as $platform)
                <x-form.input :name="'social['.$platform->value.']'" type="url" dir="ltr" :icon="$platform === \App\Enums\SocialPlatform::X ? 'brand-x' : $platform->value" :label="$platform->label()" :value="$social[$platform->value] ?? null" placeholder="https://{{ $platform->hosts()[0] }}/…" />
            @endforeach
        </div>
    </section>

    {{-- Opening hours --}}
    <section class="{{ $section }}" aria-labelledby="bf-hours" x-data="hoursEditor(@js($hoursState))">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="bf-hours" class="{{ $heading }}">{{ __('business.hours') }}</h2>
                <p class="text-sm text-muted-strong">{{ __('business_form.hours_hint') }}</p>
            </div>
        </div>
        <input type="hidden" name="hours[_present]" value="1">
        <div class="divide-y divide-navy-50">
            <template x-for="(d, i) in days" :key="d.day">
                <div class="grid gap-3 py-3 md:grid-cols-[9rem_1fr] md:items-start">
                    <label class="flex items-center gap-2.5 pt-2 text-sm font-medium text-navy-900">
                        <input type="checkbox" class="checkbox" x-model="d.enabled" :name="`hours[${d.day}][enabled]`" value="1">
                        <span x-text="d.label"></span>
                    </label>
                    <template x-if="d.enabled"><div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" x-model="d.closed" :name="`hours[${d.day}][closed]`" value="1">{{ __('business.closed') }}</label>
                        <label class="inline-flex items-center gap-2 text-sm" x-show="!d.closed"><input type="checkbox" class="checkbox" x-model="d.all_day" :name="`hours[${d.day}][all_day]`" value="1">{{ __('business.open_24h') }}</label>
                        <template x-if="!d.closed && !d.all_day">
                            <div class="flex w-full flex-wrap items-center gap-2 md:w-auto">
                                <template x-for="(p, j) in d.periods" :key="j">
                                    <div class="flex items-center gap-1.5" dir="ltr">
                                        <input type="time" class="input w-32 py-2" x-model="p.opens" :name="`hours[${d.day}][periods][${j}][opens]`" :aria-label="`${d.label} {{ __('business_form.opens') }}`">
                                        <span class="text-muted">–</span>
                                        <input type="time" class="input w-32 py-2" x-model="p.closes" :name="`hours[${d.day}][periods][${j}][closes]`" :aria-label="`${d.label} {{ __('business_form.closes') }}`">
                                        <button type="button" x-show="d.periods.length > 1" @click="removePeriod(i, j)" class="btn-icon size-8 text-muted hover:text-danger" aria-label="{{ __('common.remove') }}"><x-icon name="x" class="size-4" /></button>
                                    </div>
                                </template>
                                <button type="button" x-show="d.periods.length < 2" @click="addPeriod(i)" class="btn btn-ghost btn-sm"><x-icon name="plus" class="size-3.5" />{{ __('business_form.split_shift') }}</button>
                            </div>
                        </template>
                        <button type="button" @click="copyToAll(i)" class="btn btn-ghost btn-sm ms-auto text-gold-700"><x-icon name="copy" class="size-3.5" />{{ __('business_form.copy_all') }}</button>
                    </div></template>
                    <p x-show="!d.enabled" class="pt-2 text-xs text-muted">{{ __('business_form.hours_unset') }}</p>
                </div>
            </template>
        </div>
        @error('hours')<p class="error">{{ $message }}</p>@enderror
    </section>

    {{-- Services --}}
    <section class="{{ $section }}" aria-labelledby="bf-services" x-data="repeater(@js(array_values($services)), { name_ar: '', name_en: '', description_ar: '', description_en: '', price_from: '' })">
        <h2 id="bf-services" class="{{ $heading }}">{{ __('business.services') }}</h2>
        <p class="{{ $sub }}">{{ __('business_form.services_hint') }}</p>
        <div class="space-y-3">
            <template x-for="(row, i) in rows" :key="i">
                <div class="grid gap-3 rounded-xl border border-navy-100 bg-navy-50/30 p-3 md:grid-cols-[1fr_1fr_8rem_auto] md:items-start">
                    <input type="text" class="input" dir="rtl" lang="ar" maxlength="160" x-model="row.name_ar" :name="`services[${i}][name_ar]`" placeholder="{{ __('business_form.service_name_ar') }}" aria-label="{{ __('business_form.service_name_ar') }}">
                    <input type="text" class="input" dir="ltr" lang="en" maxlength="160" x-model="row.name_en" :name="`services[${i}][name_en]`" placeholder="{{ __('business_form.service_name_en') }}" aria-label="{{ __('business_form.service_name_en') }}">
                    <input type="number" class="input" dir="ltr" min="0" step="0.01" x-model="row.price_from" :name="`services[${i}][price_from]`" placeholder="{{ __('business_form.price_from') }}" aria-label="{{ __('business_form.price_from') }}">
                    <button type="button" @click="remove(i)" class="btn btn-ghost btn-sm text-danger" aria-label="{{ __('common.remove') }}"><x-icon name="trash-2" class="size-4" /></button>
                </div>
            </template>
        </div>
        <button type="button" @click="add()" class="btn btn-outline btn-sm mt-4"><x-icon name="plus" class="size-4" />{{ __('business_form.add_service') }}</button>
    </section>

    {{-- Logo & cover --}}
    <section class="{{ $section }}" aria-labelledby="bf-media">
        <h2 id="bf-media" class="{{ $heading }}">{{ __('business_form.media') }}</h2>
        <p class="{{ $sub }}">{{ __('business_form.media_hint') }}</p>
        <div class="grid gap-6 md:grid-cols-[auto_1fr]">
            <x-form.image name="logo" :label="__('business_form.logo')" :current="$b?->logoUrl()" shape="square" :hint="__('business_form.logo_hint')" />
            <x-form.image name="cover" :label="__('business_form.cover')" :current="$b?->coverUrl()" :hint="__('business_form.cover_hint')" />
        </div>
    </section>

    {{-- SEO (optional) --}}
    <details class="{{ $section }} group">
        <summary class="flex cursor-pointer list-none items-center justify-between">
            <span><span class="{{ $heading }} block">{{ __('business_form.seo') }}</span><span class="text-sm text-muted-strong">{{ __('business_form.seo_hint') }}</span></span>
            <x-icon name="chevron-down" class="size-5 text-muted transition group-open:rotate-180" />
        </summary>
        <div class="mt-5 grid gap-5">
            <x-form.bilingual name="meta_title" :label="__('business_form.meta_title')" :model="$b" maxlength="160" />
            <x-form.bilingual name="meta_description" type="textarea" rows="2" :label="__('business_form.meta_description')" :model="$b" maxlength="300" />
        </div>
    </details>
</div>
