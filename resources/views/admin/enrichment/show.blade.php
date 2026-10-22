@php
    use App\Support\Import\Normalizer;
    $fields = (array) $suggestion->fields;
    $match = (array) ($suggestion->match ?? []);
    $pending = $suggestion->isPending();
    $sourceTone = ['google' => 'badge-info', 'website' => 'badge-success', 'email' => 'badge-success', 'ai' => 'badge-gold', 'inferred' => 'badge-muted', 'directory' => 'badge-verified'];
    $sourceIcon = ['google' => 'map-pin', 'website' => 'globe', 'email' => 'mail', 'ai' => 'sparkles', 'inferred' => 'info', 'directory' => 'radar'];
    $directory = (array) ($suggestion->notes['directory'] ?? []);
    $notes = (array) $suggestion->notes;
    // A suggestion made only from a directory has nothing to say about Google or the website.
    $onlyDirectory = $directory !== [] && array_diff((array) $suggestion->sources, ['directory', 'ai']) === [] && ! isset($notes['google']) && ! isset($notes['website']);
    $confTone = ['high' => 'badge-success', 'medium' => 'badge-warning', 'low' => 'badge-danger'];
    $rtl = ['name_ar', 'tagline_ar', 'description_ar', 'address_ar'];
    $ltr = ['phone', 'whatsapp', 'website', 'location', 'email', 'extra_phones', 'extra_emails'];
    $show = function ($v) {
        if (is_array($v)) {
            if (isset($v['lat'])) {
                return $v['lat'].', '.$v['lng'];
            }
            if (isset($v['label'])) {
                return (string) $v['label'];
            }

            return array_is_list($v) && $v !== [] && is_scalar($v[0]) ? implode(', ', $v) : null;
        }

        return filled($v) ? (string) $v : null;
    };
    $applied = (array) ($suggestion->applied_fields ?? []);
@endphp
<x-layouts.dashboard area="admin" :title="__('enrichment.review_title')" :subtitle="$business->name_en" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('enrichment.title'), 'url' => route('admin.enrichment.index')], ['label' => $business->name_en]]">
    <x-slot:actions>
        @if($business->isPublished())
            <a href="{{ route('business.show', $business) }}" target="_blank" class="btn btn-ghost"><x-icon name="external-link" class="size-4" />{{ __('admin.businesses.view_public') }}</a>
        @endif
        <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn btn-outline"><x-icon name="pencil" class="size-4" />{{ __('enrichment.edit_listing') }}</a>
        <form method="POST" action="{{ route('admin.enrichment.business', $business->id) }}">@csrf<button class="btn btn-outline" data-busy><x-icon name="rotate-ccw" class="size-4" />{{ __('enrichment.recheck') }}</button></form>
    </x-slot:actions>
    <x-flash class="mb-5" />

    @unless($pending)
        <div class="mb-5 flex items-center gap-3 rounded-xl border border-navy-100 bg-navy-50/60 px-4 py-3 text-sm text-navy-800">
            <x-icon name="info" class="size-5 text-gold-600" />
            {{ __('enrichment.status.'.$suggestion->status) }}@if($suggestion->reviewer) · {{ $suggestion->reviewer->name }} · {{ $suggestion->reviewed_at?->diffForHumans() }}@endif
        </div>
    @endunless
    @if($business->owner_id)
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-navy-900">
            <x-icon name="triangle-alert" class="mt-0.5 size-5 text-warning" />{{ __('enrichment.owner_managed') }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- Suggested fields --}}
        <div class="min-w-0">
            @if($fields === [])
                <x-empty-state icon="sparkles" :title="__('enrichment.nothing_found')" :text="__('enrichment.nothing_found_text')" />
            @else
                <form id="approve-form" method="POST" action="{{ route('admin.enrichment.approve', $suggestion) }}"
                    x-data="{ count: 0, recount() { this.count = this.$el.querySelectorAll('input[data-apply]:checked').length } }" x-init="$nextTick(() => recount())" @change="$nextTick(() => recount())">
                    @csrf
                    <input type="hidden" name="next" value="{{ $next }}">
                    @error('fields')<p class="mb-4 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger">{{ $message }}</p>@enderror
                    @error('apply')<p class="mb-4 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger">{{ $message }}</p>@enderror

                    <div class="space-y-3">
                        @foreach($fields as $key => $f)
                            @php
                                $checked = $pending ? ($f['confidence'] ?? 'medium') !== 'low' : array_key_exists($key, $applied);
                                $current = $show($f['current'] ?? null);
                                $value = old('value.'.$key, $f['value']);
                            @endphp
                            <div @class(['card p-4 transition sm:p-5', 'opacity-70' => ! $pending && ! array_key_exists($key, $applied)]) x-data="{ on: {{ $checked ? 'true' : 'false' }} }" :class="on ? 'ring-1 ring-gold-300' : ''">
                                <div class="flex flex-wrap items-start gap-3">
                                    <label class="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                                        <input type="checkbox" name="apply[{{ $key }}]" value="1" data-apply x-model="on" @disabled(! $pending) class="mt-1 size-4 accent-navy-900">
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-navy-900">{{ __('enrichment.fields.'.$key) }}</span>
                                            @if(! empty($f['note']))<span class="mt-0.5 block text-xs leading-5 text-muted-strong">{{ __('enrichment.notes.'.$f['note'], (array) ($f['note_params'] ?? [])) }}</span>@endif
                                        </span>
                                    </label>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="badge {{ $sourceTone[$f['source']] ?? 'badge-muted' }}"><x-icon :name="$sourceIcon[$f['source']] ?? 'info'" class="size-3" />{{ __('enrichment.sources.'.$f['source']) }}</span>
                                        <span class="badge {{ $confTone[$f['confidence']] ?? 'badge-muted' }}">{{ __('enrichment.confidence.'.$f['confidence']) }}</span>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                                    <div class="rounded-xl bg-navy-50/60 px-3 py-2.5">
                                        <p class="text-[0.7rem] font-semibold tracking-wide text-muted-strong">{{ __('enrichment.current') }}</p>
                                        <p class="mt-1 text-sm break-words text-navy-800" @if(in_array($key, $rtl, true)) dir="auto" @endif>{{ $current ?? __('directory.na') }}</p>
                                    </div>
                                    <div>
                                        <p class="mb-1 text-[0.7rem] font-semibold tracking-wide text-gold-700">{{ __('enrichment.suggested') }}</p>
                                        @switch($key)
                                            @case('description_en')
                                            @case('description_ar')
                                                <textarea name="value[{{ $key }}]" rows="4" class="textarea" dir="{{ $key === 'description_ar' ? 'rtl' : 'ltr' }}" maxlength="5000" @disabled(! $pending)>{{ $value }}</textarea>
                                                @break
                                            @case('google_place')
                                                <div class="rounded-xl border border-navy-100 px-3 py-2.5 text-sm">
                                                    <p class="font-medium text-navy-900">{{ $match['name'] ?? '' }}</p>
                                                    <a href="{{ $f['value']['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-gold-700 hover:underline"><x-icon name="external-link" class="size-3.5" />{{ __('enrichment.open_in_maps') }}</a>
                                                </div>
                                                @break
                                            @case('opening_hours')
                                                <ul class="rounded-xl border border-navy-100 px-3 py-2.5 text-sm leading-6 text-navy-800" dir="auto">
                                                    @foreach((array) ($f['value']['lines'] ?? []) as $line)<li>{{ $line }}</li>@endforeach
                                                </ul>
                                                @break
                                            @case('logo')
                                                <div class="flex items-center gap-3 rounded-xl border border-navy-100 p-3">
                                                    <img src="{{ $f['value'] }}" alt="" class="size-16 rounded-lg bg-white object-contain ring-1 ring-navy-100" referrerpolicy="no-referrer" loading="lazy">
                                                    <p class="text-xs leading-5 text-muted-strong">{{ __('enrichment.logo_hint') }}</p>
                                                </div>
                                                @break
                                            @case('category')
                                                <div class="rounded-xl border border-navy-100 px-3 py-2.5 text-sm">
                                                    <p class="font-medium text-navy-900">{{ $f['value']['label'] ?? '' }}</p>
                                                    @if(! empty($f['note_params']['label']))<p class="mt-0.5 text-xs text-muted-strong" dir="auto">{{ __('enrichment.their_category', ['label' => $f['note_params']['label']]) }}</p>@endif
                                                </div>
                                                @break
                                            @case('social')
                                                <ul class="space-y-1 rounded-xl border border-navy-100 px-3 py-2.5 text-sm" dir="ltr">
                                                    @foreach((array) $f['value'] as $platform => $url)<li class="truncate"><span class="font-semibold capitalize">{{ $platform }}</span> · <a href="{{ $url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-gold-700 hover:underline">{{ $url }}</a></li>@endforeach
                                                </ul>
                                                @break
                                            @case('services')
                                                <div x-data="{ rows: @js(array_values((array) $value)) }" class="space-y-2">
                                                    <template x-for="(row, i) in rows" :key="i">
                                                        <div class="grid grid-cols-[1fr_1fr_auto] gap-2">
                                                            <input class="input" dir="ltr" :name="`value[services][${i}][name_en]`" x-model="row.name_en" maxlength="160" aria-label="{{ __('enrichment.service_en') }}" @disabled(! $pending)>
                                                            <input class="input" dir="rtl" :name="`value[services][${i}][name_ar]`" x-model="row.name_ar" maxlength="160" aria-label="{{ __('enrichment.service_ar') }}" @disabled(! $pending)>
                                                            <button type="button" class="btn-icon text-muted hover:bg-danger-soft hover:text-danger" @click="rows.splice(i, 1)" @disabled(! $pending) aria-label="{{ __('common.remove') }}"><x-icon name="x" class="size-4" /></button>
                                                        </div>
                                                    </template>
                                                    @if($pending)<button type="button" class="btn btn-ghost btn-sm" @click="rows.push({ name_en: '', name_ar: '' })"><x-icon name="plus" class="size-4" />{{ __('enrichment.add_service') }}</button>@endif
                                                </div>
                                                @break
                                            @default
                                                <input name="value[{{ $key }}]" class="input" value="{{ $show($value) }}" @if(in_array($key, $rtl, true)) dir="rtl" @elseif(in_array($key, $ltr, true)) dir="ltr" @endif @disabled(! $pending)
                                                    maxlength="{{ in_array($key, ['address_en', 'address_ar', 'website'], true) ? 300 : 200 }}">
                                                @if($key === 'location' && is_array($f['value']))
                                                    <a href="https://www.google.com/maps/search/?api=1&query={{ $f['value']['lat'] }},{{ $f['value']['lng'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-gold-700 hover:underline"><x-icon name="map-pin" class="size-3.5" />{{ __('enrichment.preview_location') }}</a>
                                                @endif
                                        @endswitch
                                        @error('fields.'.$key)<p class="error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($pending)
                        <div class="sticky bottom-0 z-20 -mx-1 mt-5 flex flex-wrap items-center gap-2 rounded-2xl border border-navy-100 bg-white/95 p-3 shadow-lift backdrop-blur">
                            <button class="btn btn-primary" :disabled="count === 0" data-busy><x-icon name="check" class="size-4" /><span x-text="@js(__('enrichment.approve_selected')).replace(':count', count)"></span></button>
                            <button type="submit" form="reject-form" class="btn btn-danger-outline"><x-icon name="circle-x" class="size-4" />{{ __('enrichment.reject_all') }}</button>
                            @if($next)<a href="{{ route('admin.enrichment.show', $next) }}" class="btn btn-ghost ms-auto">{{ __('enrichment.skip') }}<x-icon name="chevron-right" class="size-4" /></a>@endif
                        </div>
                    @endif
                </form>
                @if($pending)
                    <form id="reject-form" method="POST" action="{{ route('admin.enrichment.reject', $suggestion) }}">@csrf<input type="hidden" name="next" value="{{ $next }}"></form>
                @endif
            @endif
        </div>

        {{-- Context --}}
        <aside class="space-y-6">
            @if($directory !== [])
                <x-dash.panel :title="__('enrichment.directory.title', ['source' => $directory['source'] ?? ''])">
                    @php $score = (int) round(((float) ($directory['score'] ?? 0)) * 100); @endphp
                    <div class="flex items-center justify-between gap-2">
                        <span class="badge {{ $score >= 90 ? 'badge-success' : ($score >= 80 ? 'badge-warning' : 'badge-muted') }}">{{ ! empty($directory['chosen']) ? __('enrichment.directory.chosen') : __('enrichment.directory.matched') }}</span>
                        <span class="text-sm font-bold tabular-nums text-navy-900">{{ $score }}%</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-navy-100"><div class="h-full rounded-full {{ $score >= 90 ? 'bg-success' : 'bg-warning' }}" style="width: {{ min(100, $score) }}%"></div></div>
                    <p class="mt-4 font-semibold text-navy-900" dir="auto">{{ $directory['title'] ?? '' }}</p>
                    @if(! empty($directory['categories']))
                        <div class="mt-2 flex flex-wrap gap-1">@foreach($directory['categories'] as $label)<span class="badge badge-muted" dir="auto">{{ \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($label)) }}</span>@endforeach</div>
                    @endif
                    <dl class="mt-3 space-y-1.5 text-xs">
                        @foreach(['phones' => 'phone', 'emails' => 'mail', 'address' => 'map-pin'] as $key => $icon)
                            @if(! empty($directory[$key]))
                                <div class="flex items-start gap-2"><dt><x-icon :name="$icon" class="mt-0.5 size-3.5 text-muted" /><span class="sr-only">{{ __('enrichment.directory.'.$key) }}</span></dt>
                                    <dd class="min-w-0 break-words text-navy-800" dir="{{ $key === 'address' ? 'auto' : 'ltr' }}">{{ is_array($directory[$key]) ? implode(', ', $directory[$key]) : $directory[$key] }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                    @if(! empty($directory['image']))
                        <figure class="mt-4">
                            <a href="{{ $directory['image'] }}" target="_blank" rel="noopener noreferrer" class="group block overflow-hidden rounded-xl ring-1 ring-navy-100">
                                <img src="{{ $directory['image'] }}" alt="{{ __('enrichment.directory.card') }}" class="max-h-60 w-full bg-navy-50 object-contain transition group-hover:scale-[1.02]" referrerpolicy="no-referrer" loading="lazy">
                            </a>
                            <figcaption class="mt-1.5 text-[0.7rem] leading-4 text-muted">{{ __('enrichment.directory.card_hint') }}</figcaption>
                        </figure>
                    @endif
                    @if(! empty($directory['url']))
                        <a href="{{ $directory['url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm mt-4 w-full"><x-icon name="external-link" class="size-4" />{{ __('enrichment.directory.open', ['source' => $directory['source'] ?? '']) }}</a>
                    @endif
                    <p class="mt-3 text-[0.7rem] leading-4 text-muted">{{ __('enrichment.directory.policy') }}</p>
                </x-dash.panel>
            @endif

            @unless($onlyDirectory)
            <x-dash.panel :title="__('enrichment.google_match')">
                @if(! empty($match['place_id']) && empty($match['rejected']))
                    @php $level = $suggestion->confidenceLevel(); @endphp
                    <div class="flex items-center justify-between gap-2">
                        <span class="badge {{ $confTone[$level] ?? 'badge-muted' }}">{{ __('enrichment.confidence.'.$level) }}</span>
                        <span class="text-sm font-bold tabular-nums text-navy-900">{{ $match['score'] }}%</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-navy-100"><div class="h-full rounded-full {{ $level === 'high' ? 'bg-success' : ($level === 'medium' ? 'bg-warning' : 'bg-danger') }}" style="width: {{ $match['score'] }}%"></div></div>
                    <p class="mt-4 font-semibold text-navy-900">{{ $match['name'] }}</p>
                    <p class="mt-1 text-xs leading-5 text-muted-strong" dir="auto">{{ $match['address'] }}</p>
                    @if(! empty($match['type']))<p class="mt-1 text-xs text-muted">{{ $match['type'] }}</p>@endif
                    @if(($match['status'] ?? null) === 'CLOSED_PERMANENTLY')<p class="mt-2 badge badge-danger">{{ __('enrichment.closed_permanently') }}</p>@endif
                    @if(! empty($match['rating']))<p class="mt-2 text-xs text-muted-strong"><x-icon name="star" class="inline size-3.5 text-gold-500" /> {{ $match['rating'] }} · {{ trans_choice('enrichment.reviews_count', (int) $match['rating_count'], ['count' => format_number((int) $match['rating_count'])]) }}</p>@endif
                    <div class="mt-3 flex flex-wrap gap-1">
                        @foreach((array) ($match['reasons'] ?? []) as $reason)<span class="badge badge-muted">{{ __('enrichment.reasons.'.$reason) }}</span>@endforeach
                    </div>
                    <a href="{{ $match['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm mt-4 w-full"><x-icon name="external-link" class="size-4" />{{ __('enrichment.open_in_maps') }}</a>
                @else
                    <p class="text-sm text-muted-strong">{{ __('enrichment.notes.'.(($suggestion->notes['google'] ?? null) === 'google_not_configured' ? 'google_not_configured' : (! empty($match['rejected']) ? 'low_match' : 'no_match'))) }}</p>
                    @if(! empty($match['rejected']))
                        <div class="mt-3 rounded-xl border border-navy-100 p-3 text-sm">
                            <p class="font-medium text-navy-900">{{ $match['name'] }} <span class="text-xs text-muted">({{ $match['score'] }}%)</span></p>
                            <p class="text-xs text-muted-strong" dir="auto">{{ $match['address'] }}</p>
                            @if($pending)<form method="POST" action="{{ route('admin.enrichment.place', $suggestion) }}" class="mt-2">@csrf<input type="hidden" name="place_id" value="{{ $match['place_id'] }}"><button class="btn btn-ghost btn-sm" data-busy>{{ __('enrichment.use_place') }}</button></form>@endif
                        </div>
                    @endif
                @endif

                @if(! empty($match['candidates']) && $pending)
                    <div class="mt-5 border-t border-navy-50 pt-4">
                        <p class="text-xs font-semibold text-muted-strong">{{ __('enrichment.other_places') }}</p>
                        <ul class="mt-2 space-y-2">
                            @foreach($match['candidates'] as $c)
                                <li class="rounded-xl border border-navy-100 p-3">
                                    <p class="text-sm font-medium text-navy-900">{{ $c['name'] }} <span class="text-xs font-normal text-muted">({{ $c['score'] }}%)</span></p>
                                    <p class="text-xs text-muted-strong" dir="auto">{{ $c['address'] }}</p>
                                    <form method="POST" action="{{ route('admin.enrichment.place', $suggestion) }}" class="mt-2">@csrf<input type="hidden" name="place_id" value="{{ $c['place_id'] }}"><button class="btn btn-ghost btn-sm" data-busy><x-icon name="check" class="size-4" />{{ __('enrichment.use_place') }}</button></form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-dash.panel>

            <x-dash.panel :title="__('enrichment.website_check')">
                @php
                    $ws = $business->website_status;
                    $wsNote = $suggestion->notes['website'] ?? null;
                @endphp
                <div class="flex items-start gap-3">
                    <span @class(['flex size-9 shrink-0 items-center justify-center rounded-xl', 'bg-success-soft text-success' => in_array($ws, ['found', 'inferred'], true), 'bg-warning-soft text-warning' => in_array($ws, ['none', 'unreachable'], true), 'bg-navy-50 text-muted' => $ws === null])>
                        <x-icon :name="in_array($ws, ['found', 'inferred'], true) ? 'globe' : 'triangle-alert'" class="size-4.5" />
                    </span>
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-navy-900">{{ __('enrichment.website_status.'.($ws ?? 'unknown')) }}</p>
                        <p class="mt-1 text-xs leading-5 text-muted-strong">{{ $wsNote ? __('enrichment.notes.'.$wsNote) : __('enrichment.website_status.'.($ws ?? 'unknown').'_text') }}</p>
                        @if($business->email)<p class="mt-2 text-xs text-muted" dir="ltr">{{ $business->email }}</p>@endif
                    </div>
                </div>
            </x-dash.panel>
            @endunless

            @if(! empty($suggestion->notes['ai']) && is_string($suggestion->notes['ai']))
                <div class="rounded-xl border border-navy-100 bg-white px-4 py-3 text-xs leading-5 text-muted-strong"><x-icon name="sparkles" class="inline size-3.5 text-gold-600" /> {{ __('enrichment.notes.'.(str_starts_with($suggestion->notes['ai'], 'ai_') && \Illuminate\Support\Facades\Lang::has('enrichment.notes.'.$suggestion->notes['ai']) ? $suggestion->notes['ai'] : 'ai_failed')) }}</div>
            @endif
            <p class="px-1 text-xs leading-5 text-muted">{{ __('enrichment.review_hint') }}</p>
        </aside>
    </div>
</x-layouts.dashboard>
