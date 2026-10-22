@php
    $categoryOptions = $categories->mapWithKeys(fn ($root) => [
        $root->t('name') => [$root->id => $root->t('name').' — '.__('business_form.general')] + $root->children->mapWithKeys(fn ($c) => [$c->id => $c->t('name')])->all(),
    ])->all();
    $user = auth()->user();
    $guest = ! $user;
    $state = [
        'category' => (string) old('category_id', $category?->id ?? ''),
        'municipality' => (string) old('municipality_id', $municipalityId ?? ''),
        'area' => (string) old('area_id', $areaId ?? ''),
        'contact' => old('contact_method', 'whatsapp'),
        'business' => $origin?->slug,
        'previewUrl' => route('quotes.preview'),
        'restore' => ! old('title'),
        'guest' => $guest,
        'loginUrl' => route('login'),
    ];
    $timings = collect(\App\Models\QuoteRequest::TIMINGS)->mapWithKeys(fn ($t) => [$t => __('quotes.timing.'.$t)])->all();
@endphp
<x-layouts.app>
    <x-page-hero :title="__('quotes.page_title')" :subtitle="__('quotes.page_subtitle', ['count' => $maxRecipients])" icon="message-square-quote"
        :eyebrow="__('quotes.eyebrow')" :breadcrumbs="[['label' => __('quotes.page_title')]]" />

    <div class="container-app py-10 sm:py-12" x-data="quoteForm(@js($state), @js($areaOptions))">
        {{-- How it works --}}
        <ol class="mb-8 grid gap-3 sm:grid-cols-3" aria-label="{{ __('quotes.how_title') }}">
            @foreach(['describe' => 'pencil', 'match' => 'users', 'compare' => 'circle-check'] as $step => $icon)
                <li class="card flex items-start gap-3 p-4">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-sm font-bold text-gold-800 ring-1 ring-gold-200">{{ $loop->iteration }}</span>
                    <div>
                        <p class="text-sm font-semibold text-navy-900">{{ __('quotes.how.'.$step.'_title') }}</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-muted-strong">{{ __('quotes.how.'.$step.'_text', ['count' => $maxRecipients, 'days' => $expiryDays]) }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        <div class="grid gap-8 lg:grid-cols-12">
            <form method="POST" action="{{ route('quotes.store') }}" class="space-y-6 lg:col-span-8" x-ref="form" @submit="submit($event)" @input.debounce.400ms="save()" @change="save()" novalidate>
                @csrf
                @if($origin)<input type="hidden" name="business" value="{{ $origin->slug }}">@endif
                <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                @if($origin)
                    <div class="flex items-center gap-3 rounded-2xl border border-gold-200 bg-gold-50/60 p-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-gold-700 ring-1 ring-gold-200"><x-icon name="store" class="size-5" /></span>
                        <p class="text-sm text-navy-900">{!! __('quotes.origin_note', ['business' => '<strong>'.e($origin->t('name')).'</strong>']) !!}</p>
                    </div>
                @elseif($originUnavailable)
                    <p class="flex items-start gap-2 rounded-2xl bg-info-soft p-4 text-sm text-info"><x-icon name="info" class="mt-0.5 size-4 shrink-0" />{{ __('quotes.origin_unavailable') }}</p>
                @endif

                <section class="card p-5 sm:p-6" aria-labelledby="q-what">
                    <h2 id="q-what" class="text-base font-semibold text-navy-900">{{ __('quotes.form.what_title') }}</h2>
                    <p class="mb-5 mt-1 text-sm text-muted-strong">{{ __('quotes.form.what_hint') }}</p>
                    <div class="grid gap-5">
                        <x-form.select name="category_id" :label="__('quotes.form.category')" :options="$categoryOptions" :placeholder="__('quotes.form.choose_category')" required
                            x-model="category" @change="refresh()" />
                        <x-form.input name="title" :label="__('quotes.form.title')" required maxlength="140" :placeholder="__('quotes.form.title_placeholder')" x-ref="title" />
                        <div>
                            <x-form.textarea name="details" :label="__('quotes.form.details')" rows="6" required maxlength="2000" :hint="__('quotes.form.details_hint')"
                                :placeholder="__('quotes.form.details_placeholder')" x-ref="details" @input="detailsLength = $event.target.value.length" />
                            <p class="mt-1 text-end text-xs tabular-nums" :class="detailsLength < 20 ? 'text-muted' : 'text-success'" aria-live="polite"><span x-text="detailsLength"></span>/2000</p>
                        </div>
                    </div>
                </section>

                <section class="card p-5 sm:p-6" aria-labelledby="q-where">
                    <h2 id="q-where" class="text-base font-semibold text-navy-900">{{ __('quotes.form.where_title') }}</h2>
                    <p class="mb-5 mt-1 text-sm text-muted-strong">{{ __('quotes.form.where_hint') }}</p>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="q_municipality" class="label">{{ __('business_form.municipality') }} <span class="font-normal text-muted">({{ __('common.optional') }})</span></label>
                            <select id="q_municipality" name="municipality_id" class="select" x-model="municipality" @change="areaChanged(); refresh()">
                                <option value="">{{ __('quotes.anywhere') }}</option>
                                @foreach($municipalities as $m)<option value="{{ $m->id }}">{{ $m->t('name') }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="q_area" class="label">{{ __('business_form.area') }} <span class="font-normal text-muted">({{ __('common.optional') }})</span></label>
                            <select id="q_area" name="area_id" class="select" x-model="area" :disabled="!municipality" @change="refresh()">
                                <option value="">{{ __('business_form.choose_area') }}</option>
                                <template x-for="a in areaList" :key="a.id"><option :value="String(a.id)" x-text="a.name" :selected="String(a.id) === area"></option></template>
                            </select>
                        </div>
                    </div>
                    <fieldset class="mt-5">
                        <legend class="label">{{ __('quotes.form.timing') }}</legend>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach($timings as $value => $label)
                                <label class="flex cursor-pointer items-center justify-center rounded-xl border border-navy-100 px-3 py-2.5 text-center text-sm text-navy-900 transition has-[:checked]:border-gold-400 has-[:checked]:bg-gold-50 has-[:checked]:font-semibold has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-gold-400">
                                    <input type="radio" name="timing" value="{{ $value }}" class="sr-only" @checked(old('timing', 'this_week') === $value)>{{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('timing')<p class="error">{{ $message }}</p>@enderror
                    </fieldset>
                </section>

                <section class="card p-5 sm:p-6" aria-labelledby="q-contact">
                    <h2 id="q-contact" class="text-base font-semibold text-navy-900">{{ __('quotes.form.contact_title') }}</h2>
                    <p class="mb-5 mt-1 text-sm text-muted-strong">{{ __('quotes.form.contact_hint') }}</p>
                    <fieldset>
                        <legend class="sr-only">{{ __('quotes.form.contact_method') }}</legend>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['whatsapp' => 'whatsapp', 'phone' => 'phone', 'email' => 'mail'] as $method => $icon)
                                <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border border-navy-100 px-3 py-3 text-sm text-navy-900 transition has-[:checked]:border-gold-400 has-[:checked]:bg-gold-50 has-[:checked]:font-semibold has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-gold-400">
                                    <input type="radio" name="contact_method" value="{{ $method }}" class="sr-only" x-model="contact">
                                    <x-icon :name="$icon" class="size-5 text-gold-700" />{{ __('quotes.contact.'.$method) }}
                                </label>
                            @endforeach
                        </div>
                        @error('contact_method')<p class="error">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="mt-5" x-show="contact !== 'email'" x-cloak>
                        <x-form.input name="contact_phone" type="tel" dir="ltr" icon="phone" :label="__('quotes.form.phone')" :value="$user?->phone" placeholder="+974 5000 0000" maxlength="30" autocomplete="tel" />
                    </div>
                    <p class="mt-4 text-xs text-muted-strong" x-show="contact === 'email'" x-cloak>
                        @if($user){{ __('quotes.form.email_note', ['email' => $user->email]) }}@else{{ __('quotes.form.email_note_guest') }}@endif
                    </p>
                    <label class="mt-5 flex items-start gap-2.5 text-sm text-charcoal">
                        <input type="checkbox" name="consent" value="1" class="checkbox mt-0.5" @checked(old('consent')) required>
                        <span>{{ __('quotes.form.consent') }}</span>
                    </label>
                    @error('consent')<p class="error">{{ $message }}</p>@enderror
                </section>

                <p class="flex items-center gap-2 rounded-xl bg-navy-900 px-4 py-3 text-sm text-white lg:hidden" x-show="count !== null" x-cloak aria-hidden="true">
                    <x-icon name="users" class="size-4 text-gold-300" />
                    <span>{{ __('quotes.summary.title') }} <strong class="text-gold-300 tabular-nums" x-text="loading ? '…' : count"></strong> <span x-text="count === 1 ? @js(__('quotes.summary.business_one')) : @js(__('quotes.summary.business_many'))"></span></span>
                </p>
                <div class="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="flex items-center gap-2 text-xs text-muted-strong"><x-icon name="lock" class="size-4 text-gold-600" />{{ __('quotes.privacy_note') }}</p>
                    @if($guest)
                        <button type="submit" class="btn btn-gold btn-lg"><x-icon name="log-in" class="size-4" />{{ __('quotes.sign_in_to_send') }}</button>
                    @else
                        <button type="submit" class="btn btn-gold btn-lg" :disabled="count === 0"><x-icon name="send" class="size-4" />{{ __('quotes.send') }}</button>
                    @endif
                </div>
                @if($guest)
                    <p class="text-end text-xs text-muted-strong">{{ __('quotes.guest_note') }}</p>
                @endif
            </form>

            {{-- Live summary --}}
            <aside class="lg:col-span-4">
                <div class="space-y-4 lg:sticky lg:top-24">
                    <section class="relative overflow-hidden rounded-2xl bg-navy-900 p-5 text-white" aria-live="polite">
                        <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
                        <div class="relative">
                            <p class="text-xs font-medium uppercase tracking-wide text-gold-300">{{ __('quotes.summary.title') }}</p>
                            <template x-if="count === null">
                                <p class="mt-3 text-sm text-white/75">{{ __('quotes.summary.pick_category') }}</p>
                            </template>
                            <template x-if="count !== null">
                                <div>
                                    <p class="mt-3 flex items-baseline gap-2">
                                        <span class="text-4xl font-bold tabular-nums" x-text="loading ? '…' : count"></span>
                                        <span class="text-sm text-white/75" x-text="count === 1 ? @js(__('quotes.summary.business_one')) : @js(__('quotes.summary.business_many'))"></span>
                                    </p>
                                    <p class="mt-2 text-sm text-white/75" x-show="count > 0 && verified > 0" x-text="@js(__('quotes.summary.verified')).replace(':count', verified)"></p>
                                    <p class="mt-2 text-sm text-white/75" x-show="count > 0 && municipality && nearby < count" x-text="@js(__('quotes.summary.widened'))"></p>
                                    <p class="mt-3 rounded-xl bg-white/10 p-3 text-sm text-white" x-show="count === 0" x-cloak>{{ __('quotes.summary.none') }}</p>
                                </div>
                            </template>
                        </div>
                    </section>
                    <section class="card p-5">
                        <h2 class="text-sm font-semibold text-navy-900">{{ __('quotes.tips_title') }}</h2>
                        <ul class="mt-3 space-y-2.5 text-sm text-muted-strong">
                            @foreach(['size', 'budget', 'photos'] as $tip)
                                <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" />{{ __('quotes.tips.'.$tip) }}</li>
                            @endforeach
                        </ul>
                    </section>
                    <p class="px-1 text-xs leading-relaxed text-muted">{{ __('quotes.disclaimer') }}</p>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.app>
