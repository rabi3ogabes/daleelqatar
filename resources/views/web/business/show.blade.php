@php
    $name = $business->t('name');
    $altName = $business->t('name', app()->getLocale() === 'ar' ? 'en' : 'ar');
    $trackUrl = route('business.track', $business);
    $crumbs = [['label' => __('nav.categories'), 'url' => route('categories.index')]];
    if ($business->category) { $crumbs[] = ['label' => $business->category->t('name'), 'url' => route('categories.show', $business->category)]; }
    $crumbs[] = ['label' => $name];
    $hoursByDay = $business->openingHours->groupBy('day_of_week');
    $gallery = $business->images->map(fn ($i) => ['src' => $i->url(), 'alt' => $i->t('caption') ?: $name])->values();
    $website = safe_external_url($business->website);
@endphp
<x-layouts.app>
    {{-- Cover --}}
    <section class="relative h-56 overflow-hidden bg-navy-900 sm:h-72 lg:h-80">
        @if($business->coverUrl())
            <img src="{{ $business->coverUrl() }}" alt="{{ $name }}" fetchpriority="high" class="absolute inset-0 size-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-navy-950/20 to-navy-950/30"></div>
        @else
            <img src="{{ asset('images/doha-skyline.svg') }}" alt="" class="absolute inset-0 size-full object-cover opacity-70" aria-hidden="true">
            <div class="absolute inset-0 bg-gradient-to-t from-navy-950 via-navy-950/40 to-navy-950/20"></div>
        @endif
        <div class="relative container-app pt-6"><x-breadcrumbs :items="$crumbs" dark /></div>
    </section>

    <div class="container-app relative -mt-20 pb-16 sm:-mt-24">
        {{-- Header card --}}
        <section class="card relative p-5 sm:p-7" aria-labelledby="biz-name">
            @if(! $business->isPublished())
                <div class="mb-5 flex items-center gap-2 rounded-xl bg-warning-soft px-4 py-2.5 text-sm text-warning"><x-icon name="eye-off" class="size-4" />{{ __('business.not_published') }} — <x-status-badge :status="$business->status" /></div>
            @endif
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:gap-5">
                    <div class="-mt-14 shrink-0 sm:-mt-16">
                        @if($business->logoUrl())
                            <img src="{{ $business->logoUrl() }}" alt="{{ $name }}" class="size-24 rounded-2xl border-4 border-white bg-white object-contain shadow-lift sm:size-28">
                        @else
                            <span class="pattern-lattice flex size-24 items-center justify-center rounded-2xl border-4 border-white bg-navy-900 text-gold-300 shadow-lift sm:size-28"><x-icon :name="$business->category?->icon ?? 'store'" class="size-10" :stroke="1.4" /></span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($business->category)<a href="{{ route('categories.show', $business->category) }}" class="text-sm font-medium text-gold-700 hover:underline">{{ $business->category->t('name') }}</a>@endif
                            @if($business->isFeaturedNow())<span class="badge badge-gold"><x-icon name="sparkles" class="size-3" />{{ __('business.featured') }}</span>@endif
                            @if($business->is_demo)<span class="badge badge-muted" title="{{ __('common.sample_listing_hint') }}">{{ __('common.sample_listing') }}</span>@endif
                        </div>
                        <h1 id="biz-name" class="mt-1 flex flex-wrap items-center gap-2 text-2xl font-bold sm:text-3xl">
                            {{ $name }}
                            @if($business->is_verified)
                                <span class="badge badge-verified py-1" title="{{ __('business.verified_hint') }}"><x-icon name="badge-check" class="size-4" />{{ __('business.verified') }}</span>
                            @endif
                        </h1>
                        @if($altName && $altName !== $name)<p class="mt-0.5 text-sm text-muted-strong" lang="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">{{ $altName }}</p>@endif
                        @if($business->t('tagline'))<p class="mt-3 text-[0.95rem] text-charcoal">{{ $business->t('tagline') }}</p>@endif
                        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-muted-strong">
                            @if($business->locationLabel())<span class="inline-flex items-center gap-1.5"><x-icon name="map-pin" class="size-4 text-gold-600" />{{ $business->locationLabel() }}</span>@endif
                            <x-rating :value="$business->rating_avg" :count="$business->reviews_count" size="lg" />
                            @if($openNow !== null)
                                <span @class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold', 'bg-success-soft text-success' => $openNow, 'bg-navy-50 text-muted-strong' => ! $openNow])>
                                    <span class="size-1.5 rounded-full bg-current"></span>{{ $openNow ? __('business.open_now') : __('business.closed_now') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Primary actions --}}
                <div class="flex flex-wrap gap-2 lg:w-80 lg:justify-end">
                    @if($business->telUrl())
                        <a href="{{ $business->telUrl() }}" @click="dqTrack(@js($trackUrl), 'call')" class="btn btn-primary flex-1 sm:flex-none"><x-icon name="phone" class="size-4" />{{ __('business.call') }}</a>
                    @endif
                    @if($business->whatsappUrl())
                        <a href="{{ $business->whatsappUrl() }}" target="_blank" rel="noopener noreferrer" @click="dqTrack(@js($trackUrl), 'whatsapp')" class="btn flex-1 bg-[#128C4A] text-white hover:bg-[#0f7a40] sm:flex-none"><x-icon name="whatsapp" class="size-4" />{{ __('business.whatsapp') }}</a>
                    @endif
                    @if($business->directionsUrl())
                        <a href="{{ $business->directionsUrl() }}" target="_blank" rel="noopener noreferrer" @click="dqTrack(@js($trackUrl), 'directions')" class="btn btn-outline flex-1 sm:flex-none"><x-icon name="navigation" class="size-4" />{{ __('business.directions') }}</a>
                    @endif
                    <div class="flex gap-2">
                        @if($business->isPublished())
                            <x-favorite-button :business="$business" :favorited="$isFavorite" class="!btn-icon !size-11 !rounded-xl border border-navy-200 !bg-white !shadow-none" />
                        @endif
                        <button type="button" x-data="share(@js(route('business.show', $business)), @js($name), @js(__('common.link_copied')), @js($trackUrl))" @click="share()" class="btn-icon size-11 rounded-xl border border-navy-200 bg-white text-navy-900 hover:bg-navy-50" aria-label="{{ __('business.share') }}"><x-icon name="share-2" class="size-[1.15rem]" /></button>
                    </div>
                </div>
            </div>
        </section>

        <x-flash class="mt-6" />

        <div class="mt-8 grid gap-8 lg:grid-cols-12">
            {{-- Main column --}}
            <div class="space-y-8 lg:col-span-8">
                @if($business->t('description'))
                    <section class="card card-pad" aria-labelledby="about-h">
                        <h2 id="about-h" class="text-lg font-semibold">{{ __('business.about') }}</h2>
                        <div class="divider-gold mt-2"></div>
                        <div class="prose-dq mt-4 whitespace-pre-line">{{ $business->t('description') }}</div>
                        @if($business->categories->isNotEmpty())
                            <div class="mt-5 flex flex-wrap gap-2">
                                @foreach($business->categories as $cat)
                                    <a href="{{ route('categories.show', $cat) }}" class="badge badge-neutral hover:bg-gold-50">{{ $cat->t('name') }}</a>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif

                @if($business->services->isNotEmpty())
                    <section class="card card-pad" aria-labelledby="services-h">
                        <h2 id="services-h" class="text-lg font-semibold">{{ __('business.services') }}</h2>
                        <div class="divider-gold mt-2"></div>
                        <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                            @foreach($business->services as $service)
                                <li class="flex items-start gap-3 rounded-xl border border-navy-50 bg-navy-50/40 p-3.5">
                                    <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-gold-50 text-gold-700 ring-1 ring-gold-100"><x-icon name="check" class="size-4" :stroke="2.2" /></span>
                                    <div class="min-w-0">
                                        <p class="font-medium text-navy-900">{{ $service->t('name') }}</p>
                                        @if($service->t('description'))<p class="mt-0.5 text-sm text-muted-strong">{{ $service->t('description') }}</p>@endif
                                        @if($service->price_from !== null)<p class="mt-1 text-xs font-semibold text-gold-700">{{ __('common.from_price', ['price' => format_number((float) $service->price_from, 0).' '.__('common.qar')]) }}</p>@endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if($gallery->isNotEmpty())
                    <section class="card card-pad" aria-labelledby="gallery-h" x-data="{ open: false, i: 0, imgs: @js($gallery) }" @keydown.escape.window="open = false" @keydown.arrow-right.window="open && (i = (i + 1) % imgs.length)" @keydown.arrow-left.window="open && (i = (i - 1 + imgs.length) % imgs.length)">
                        <h2 id="gallery-h" class="text-lg font-semibold">{{ __('business.gallery') }}</h2>
                        <div class="divider-gold mt-2"></div>
                        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($gallery as $idx => $img)
                                <button type="button" @click="open = true; i = {{ $idx }}" class="group relative aspect-[4/3] overflow-hidden rounded-xl bg-navy-50 {{ $idx === 0 ? 'col-span-2 row-span-2 sm:aspect-auto' : '' }}">
                                    <img src="{{ $img['src'] }}" alt="{{ $img['alt'] }}" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105">
                                </button>
                            @endforeach
                        </div>
                        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[90] flex items-center justify-center bg-navy-950/95 p-4" role="dialog" aria-modal="true" aria-label="{{ __('business.gallery') }}" x-trap.noscroll="open">
                            <button type="button" class="absolute end-4 top-4 btn-icon text-white hover:bg-white/10" @click="open = false"><x-icon name="x" class="size-6" /><span class="sr-only">{{ __('common.close') }}</span></button>
                            <button type="button" class="absolute start-3 btn-icon text-white hover:bg-white/10" @click="i = (i - 1 + imgs.length) % imgs.length"><x-icon name="chevron-left" class="size-7" /><span class="sr-only">{{ __('common.previous') }}</span></button>
                            <img :src="imgs[i].src" :alt="imgs[i].alt" class="max-h-[85vh] max-w-full rounded-xl object-contain">
                            <button type="button" class="absolute end-3 btn-icon text-white hover:bg-white/10" @click="i = (i + 1) % imgs.length"><x-icon name="chevron-right" class="size-7" /><span class="sr-only">{{ __('common.next') }}</span></button>
                            <p class="absolute bottom-5 text-sm text-white/70" x-text="`${i + 1} / ${imgs.length}`"></p>
                        </div>
                    </section>
                @endif

                @if($business->activeOffers->isNotEmpty())
                    <section aria-labelledby="offers-h">
                        <h2 id="offers-h" class="text-lg font-semibold">{{ __('business.offers') }}</h2>
                        <div class="divider-gold mt-2 mb-5"></div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            @foreach($business->activeOffers as $offer)
                                @php $offer->setRelation('business', $business); @endphp
                                <x-offer-card :offer="$offer" />
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Reviews --}}
                @if($reviewsEnabled)
                    <section id="reviews" class="card card-pad scroll-mt-24" aria-labelledby="reviews-h">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 id="reviews-h" class="text-lg font-semibold">{{ __('business.reviews') }}</h2>
                                <div class="divider-gold mt-2"></div>
                            </div>
                        </div>

                        @if($business->reviews_count > 0)
                            <div class="mt-6 grid gap-6 rounded-2xl bg-navy-50/50 p-5 sm:grid-cols-[10rem_1fr] sm:items-center">
                                <div class="text-center">
                                    <p class="text-5xl font-bold text-navy-900">{{ number_format((float) $business->rating_avg, 1) }}</p>
                                    <x-rating :value="$business->rating_avg" :count="$business->reviews_count" :show-count="false" class="mt-2 justify-center" />
                                    <p class="mt-1 text-xs text-muted-strong">{{ trans_choice('business.reviews_count', $business->reviews_count, ['count' => $business->reviews_count]) }}</p>
                                </div>
                                <ul class="space-y-1.5" aria-label="{{ __('business.reviews') }}">
                                    @for($r = 5; $r >= 1; $r--)
                                        @php $c = (int) ($distribution[$r] ?? 0); $pct = $business->reviews_count ? round(100 * $c / $business->reviews_count) : 0; @endphp
                                        <li class="flex items-center gap-3 text-xs">
                                            <span class="w-14 shrink-0 text-muted-strong">{{ trans_choice('reviews.stars', $r, ['count' => $r]) }}</span>
                                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-navy-100"><span class="block h-full rounded-full bg-gold-500" style="width: {{ $pct }}%"></span></span>
                                            <span class="w-8 text-end tabular-nums text-muted-strong">{{ $c }}</span>
                                        </li>
                                    @endfor
                                </ul>
                            </div>
                        @endif

                        <div class="mt-6 space-y-5">
                            @forelse($reviews as $review)
                                <article class="border-b border-navy-50 pb-5 last:border-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex size-10 items-center justify-center rounded-full bg-navy-900 text-sm font-semibold text-gold-300">{{ mb_substr($review->authorDisplayName(), 0, 1) }}</span>
                                            <div>
                                                <p class="text-sm font-semibold text-navy-900">{{ $review->authorDisplayName() }}</p>
                                                <p class="text-xs text-muted">{{ format_date($review->created_at) }}</p>
                                            </div>
                                        </div>
                                        <x-rating :value="(float) $review->rating" :count="1" :show-count="false" />
                                    </div>
                                    @if($review->title)<h3 class="mt-3 font-semibold text-navy-900">{{ $review->title }}</h3>@endif
                                    <p class="mt-2 text-sm leading-7 whitespace-pre-line text-charcoal" lang="{{ $review->locale }}">{{ $review->body }}</p>
                                    @if($review->owner_reply)
                                        <div class="mt-3 rounded-xl border-s-4 border-gold-400 bg-gold-50/60 p-3.5">
                                            <p class="text-xs font-semibold text-gold-800">{{ __('reviews.owner_reply') }}</p>
                                            <p class="mt-1 text-sm whitespace-pre-line text-charcoal">{{ $review->owner_reply }}</p>
                                        </div>
                                    @endif
                                    @auth
                                        @if(auth()->id() !== $review->user_id)
                                            <details class="mt-2">
                                                <summary class="inline-flex cursor-pointer items-center gap-1 text-xs text-muted hover:text-danger"><x-icon name="flag" class="size-3.5" />{{ __('reviews.report') }}</summary>
                                                <form method="POST" action="{{ route('reviews.report', $review) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                                                    @csrf
                                                    <select name="reason" class="select !py-2 text-sm sm:w-56" required aria-label="{{ __('fields.reason') }}">
                                                        @foreach($reviewReportReasons as $reason)<option value="{{ $reason->value }}">{{ $reason->label() }}</option>@endforeach
                                                    </select>
                                                    <input name="details" class="input !py-2 text-sm" maxlength="1000" placeholder="{{ __('fields.details') }}" aria-label="{{ __('fields.details') }}">
                                                    <button class="btn btn-danger-outline btn-sm">{{ __('reviews.report_review') }}</button>
                                                </form>
                                            </details>
                                        @endif
                                    @endauth
                                </article>
                            @empty
                                <p class="rounded-xl bg-navy-50/50 p-5 text-center text-sm text-muted-strong">{{ __('business.no_reviews') }}</p>
                            @endforelse
                            {{ $reviews->fragment('reviews')->links() }}
                        </div>

                        {{-- Write a review --}}
                        <div class="mt-8 border-t border-navy-50 pt-6">
                            @guest
                                <a href="{{ route('login') }}" class="btn btn-outline"><x-icon name="pencil" class="size-4" />{{ __('reviews.login_to_review') }}</a>
                            @else
                                @if($ownReview)
                                    <p class="flex items-center gap-2 text-sm text-muted-strong"><x-icon name="info" class="size-4 text-gold-600" />
                                        {{ $ownReview->status === \App\Enums\ModerationStatus::Pending ? __('reviews.your_review_pending') : __('reviews.already_reviewed') }}</p>
                                @elseif($business->isOwnedBy(auth()->user()))
                                    <p class="text-sm text-muted-strong">{{ __('reviews.own_business') }}</p>
                                @elseif(! auth()->user()->hasVerifiedEmail())
                                    <p class="text-sm text-muted-strong">{{ __('reviews.verify_to_review') }}</p>
                                @elseif($business->isPublished())
                                    <h3 class="font-semibold text-navy-900">{{ __('business.write_review') }}</h3>
                                    <p class="mt-1 text-xs text-muted-strong">{{ __('reviews.guidelines') }}</p>
                                    <form method="POST" action="{{ route('business.reviews.store', $business) }}" class="mt-4 space-y-4" x-data="{ rating: {{ (int) old('rating', 0) }}, hover: 0 }">
                                        @csrf
                                        <fieldset>
                                            <legend class="label">{{ __('reviews.your_rating') }} <span class="text-danger">*</span></legend>
                                            <div class="flex items-center gap-1" dir="ltr" @mouseleave="hover = 0">
                                                @for($s = 1; $s <= 5; $s++)
                                                    <label class="cursor-pointer" @mouseenter="hover = {{ $s }}">
                                                        <input type="radio" name="rating" value="{{ $s }}" class="peer sr-only" x-model.number="rating" required>
                                                        <svg viewBox="0 0 24 24" class="size-8 transition peer-focus-visible:rounded peer-focus-visible:ring-2 peer-focus-visible:ring-gold-400" :class="(hover || rating) >= {{ $s }} ? 'text-gold-500' : 'text-navy-100'" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55L2.48 9.42l6.58-.96z"/></svg>
                                                        <span class="sr-only">{{ trans_choice('reviews.stars', $s, ['count' => $s]) }}</span>
                                                    </label>
                                                @endfor
                                            </div>
                                            @error('rating')<p class="error">{{ $message }}</p>@enderror
                                        </fieldset>
                                        <x-form.input name="title" :label="__('reviews.title')" maxlength="150" />
                                        <x-form.textarea name="body" :label="__('reviews.body')" :placeholder="__('reviews.body_placeholder')" required rows="4" maxlength="3000" />
                                        <button class="btn btn-primary"><x-icon name="send" class="size-4" />{{ __('reviews.submit') }}</button>
                                    </form>
                                @endif
                            @endguest
                        </div>
                    </section>
                @endif
            </div>

            {{-- Sidebar --}}
            <aside class="space-y-6 lg:col-span-4">
                <section class="card card-pad" aria-labelledby="contact-h">
                    <h2 id="contact-h" class="font-semibold">{{ __('business.contact') }}</h2>
                    <ul class="mt-4 space-y-3 text-sm">
                        @if($business->phone)
                            <li><a href="{{ $business->telUrl() }}" @click="dqTrack(@js($trackUrl), 'call')" class="flex items-center gap-3 rounded-xl p-2 -m-2 hover:bg-navy-50"><span class="flex size-9 items-center justify-center rounded-lg bg-gold-50 text-gold-700"><x-icon name="phone" class="size-4" /></span><span dir="ltr" class="font-medium text-navy-900">{{ $business->phone }}</span></a></li>
                        @endif
                        @if($business->whatsappUrl())
                            <li><a href="{{ $business->whatsappUrl() }}" target="_blank" rel="noopener noreferrer" @click="dqTrack(@js($trackUrl), 'whatsapp')" class="flex items-center gap-3 rounded-xl p-2 -m-2 hover:bg-navy-50"><span class="flex size-9 items-center justify-center rounded-lg bg-[#E7F6EE] text-[#128C4A]"><x-icon name="whatsapp" class="size-4" /></span><span dir="ltr" class="font-medium text-navy-900">{{ $business->whatsapp }}</span></a></li>
                        @endif
                        @if($business->email)
                            <li><a href="mailto:{{ $business->email }}" class="flex items-center gap-3 rounded-xl p-2 -m-2 hover:bg-navy-50"><span class="flex size-9 items-center justify-center rounded-lg bg-gold-50 text-gold-700"><x-icon name="mail" class="size-4" /></span><span class="truncate font-medium text-navy-900" dir="ltr">{{ $business->email }}</span></a></li>
                        @endif
                        @if($website)
                            <li><a href="{{ $website }}" target="_blank" rel="noopener noreferrer nofollow" @click="dqTrack(@js($trackUrl), 'website')" class="flex items-center gap-3 rounded-xl p-2 -m-2 hover:bg-navy-50"><span class="flex size-9 items-center justify-center rounded-lg bg-gold-50 text-gold-700"><x-icon name="globe" class="size-4" /></span><span class="truncate font-medium text-navy-900" dir="ltr">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($website, '/')) }}</span></a></li>
                        @endif
                        @if(! $business->phone && ! $business->whatsapp && ! $business->email && ! $website)
                            <li class="text-muted-strong">{{ __('business.no_contact') }}</li>
                        @endif
                    </ul>
                    @if($business->socialLinks->isNotEmpty())
                        <div class="mt-5 flex flex-wrap gap-2 border-t border-navy-50 pt-4">
                            @foreach($business->socialLinks as $link)
                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer nofollow" class="flex size-10 items-center justify-center rounded-xl bg-navy-900 text-gold-300 transition hover:bg-gold-400 hover:text-navy-950"><x-icon :name="$link->platform->value" class="size-4.5" /><span class="sr-only">{{ $link->platform->label() }}</span></a>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="card card-pad" aria-labelledby="hours-h">
                    <div class="flex items-center justify-between">
                        <h2 id="hours-h" class="font-semibold">{{ __('business.hours') }}</h2>
                        <span class="text-xs text-muted">{{ __('business.hours_note') }}</span>
                    </div>
                    @if($hoursByDay->isNotEmpty())
                        <dl class="mt-4 space-y-1 text-sm">
                            @foreach([6, 0, 1, 2, 3, 4, 5] as $day)
                                @php $rows = $hoursByDay->get($day, collect()); @endphp
                                <div @class(['flex justify-between gap-3 rounded-lg px-2.5 py-1.5', 'bg-gold-50 font-semibold text-navy-900' => $day === $today])>
                                    <dt>{{ __('business.days.'.$day) }} @if($day === $today)<span class="text-xs font-normal text-gold-700">· {{ __('business.today') }}</span>@endif</dt>
                                    <dd class="text-end">
                                        @if($rows->isEmpty())<span class="text-muted">—</span>
                                        @else{{ $rows->map->formatted()->implode(', ') }}@endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <p class="mt-3 text-sm text-muted-strong">{{ __('business.hours_not_provided') }}</p>
                    @endif
                </section>

                <section class="card overflow-hidden" aria-labelledby="loc-h">
                    <div class="card-pad pb-4">
                        <h2 id="loc-h" class="font-semibold">{{ __('business.location') }}</h2>
                        @if($business->t('address') || $business->locationLabel())
                            <p class="mt-3 flex items-start gap-2 text-sm text-charcoal"><x-icon name="map-pin" class="mt-0.5 size-4 text-gold-600" />{{ $business->t('address') ?: $business->locationLabel() }}</p>
                        @endif
                    </div>
                    @if($business->latitude !== null && $business->longitude !== null)
                        @php $d = 0.006; $bbox = ($business->longitude - $d).','.($business->latitude - $d).','.($business->longitude + $d).','.($business->latitude + $d); @endphp
                        <iframe title="{{ __('business.map_title', ['name' => $name]) }}" loading="lazy" class="h-56 w-full border-0" referrerpolicy="no-referrer"
                            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&amp;layer=mapnik&amp;marker={{ $business->latitude }},{{ $business->longitude }}"></iframe>
                        <div class="p-4"><a href="{{ $business->directionsUrl() }}" target="_blank" rel="noopener noreferrer" @click="dqTrack(@js($trackUrl), 'directions')" class="btn btn-outline w-full"><x-icon name="navigation" class="size-4" />{{ __('business.open_in_maps') }}</a></div>
                    @endif
                </section>

                @if($canClaim)
                    <section class="relative overflow-hidden rounded-2xl bg-navy-900 p-6 text-white">
                        <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
                        <div class="relative">
                            <h2 class="font-semibold !text-white">{{ __('business.claim') }}</h2>
                            <p class="mt-1.5 text-sm text-white/70">{{ __('business.claim_text') }}</p>
                            <a href="{{ route('business.claim', $business) }}" class="btn btn-gold mt-4"><x-icon name="key-round" class="size-4" />{{ __('business.claim_cta') }}</a>
                        </div>
                    </section>
                @endif

                @if($business->isPublished())
                    <details class="card group p-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-muted-strong">
                            <span class="inline-flex items-center gap-2"><x-icon name="flag" class="size-4" />{{ __('business.report') }}</span>
                            <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                        </summary>
                        <form method="POST" action="{{ route('business.report', $business) }}" class="mt-4 space-y-3">
                            @csrf
                            <p class="text-xs text-muted-strong">{{ __('business.report_intro') }}</p>
                            <x-form.select name="reason" :label="__('fields.reason')" required :options="collect($reportReasons)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" />
                            <x-form.textarea name="details" :label="__('fields.details')" rows="3" maxlength="1500" />
                            @guest<x-form.input name="reporter_email" type="email" :label="__('fields.email').' ('.__('common.optional').')'" dir="ltr" />@endguest
                            <div class="hidden" aria-hidden="true"><input type="text" name="company_website" tabindex="-1" autocomplete="off"></div>
                            <button class="btn btn-danger-outline w-full">{{ __('common.send') }}</button>
                        </form>
                    </details>
                @endif

                <p class="px-1 text-xs text-muted">
                    @if($business->published_at){{ __('business.listed_since', ['date' => format_date($business->published_at, 'MMMM YYYY')]) }}@endif
                </p>
            </aside>
        </div>

        @if($similar->isNotEmpty())
            <section class="mt-16" aria-labelledby="similar-h">
                <x-section-header :title="__('business.similar')" :href="$business->category ? route('categories.show', $business->category) : null" id="similar-h" />
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($similar as $item)
                        <x-business-card :business="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
