{{-- Seasonal hours editor (owner and admin). Expects $business, $periods, $regular, $hasRegular, $formRoute. --}}
<div class="mb-6 flex items-start gap-3 rounded-2xl border border-gold-200 bg-gold-50/60 p-4 text-sm text-navy-900">
    <x-icon name="moon" class="mt-0.5 size-5 shrink-0 text-gold-700" />
    <p>{{ __('seasons.owner.intro') }}</p>
</div>

@if($periods->isEmpty())
    <x-empty-state icon="calendar" :title="__('seasons.owner.none_title')" :text="__('seasons.owner.none_text')" />
@else
    <div class="space-y-6">
        @foreach($periods as $row)
            @php $p = $row['period']; $running = $p->isRunning(); $days = (int) now(\App\Models\Business::TIMEZONE)->startOfDay()->diffInDays($p->starts_on, false); @endphp
            <section class="card overflow-hidden" x-data="{ mode: @js($row['mode']) }" aria-labelledby="season-{{ $p->id }}">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-navy-50 p-5">
                    <div>
                        <h2 id="season-{{ $p->id }}" class="text-base font-semibold text-navy-900">{{ $p->t('name') }}</h2>
                        <p class="mt-0.5 text-sm text-muted-strong">{{ $p->dateRange() }}</p>
                    </div>
                    @if($running)
                        <span class="badge badge-success">{{ __('seasons.running_now') }}</span>
                    @else
                        <span class="badge badge-info">{{ trans_choice('seasons.starts_in', $days, ['count' => $days]) }}</span>
                    @endif
                </header>
                <form method="POST" action="{{ $formRoute($p) }}" class="p-5" x-data="hoursEditor(@js($row['state']))">
                    @csrf @method('PUT')
                    <fieldset>
                        <legend class="sr-only">{{ __('seasons.owner.mode_label') }}</legend>
                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach(['regular' => 'clock', 'custom' => 'moon', 'closed' => 'circle-x'] as $mode => $icon)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-navy-100 p-3 transition has-[:checked]:border-gold-400 has-[:checked]:bg-gold-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-gold-400">
                                    <input type="radio" name="mode" value="{{ $mode }}" class="sr-only" x-model="mode">
                                    <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0 text-gold-700" />
                                    <span><span class="block text-sm font-semibold text-navy-900">{{ __('seasons.owner.mode.'.$mode) }}</span><span class="block text-xs text-muted-strong">{{ $mode === 'custom' && $p->kind === 'ramadan' ? __('seasons.owner.mode_hint.custom_ramadan') : __('seasons.owner.mode_hint.'.$mode) }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="mt-5" x-show="mode === 'custom'" x-cloak>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm text-muted-strong">{{ __('seasons.owner.custom_hint') }}</p>
                            @if($hasRegular)
                                <button type="button" class="btn btn-ghost btn-sm text-gold-700" @click="load(@js($regular))"><x-icon name="copy" class="size-3.5" />{{ __('seasons.owner.copy_regular') }}</button>
                            @endif
                        </div>
                        <div x-bind:inert="mode !== 'custom'">
                            @include('partials.hours-editor', ['prefix' => 'hours'])
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button class="btn btn-primary"><x-icon name="check" class="size-4" />{{ __('common.save') }}</button>
                    </div>
                </form>
            </section>
        @endforeach
    </div>
@endif
