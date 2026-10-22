{{-- Weekly hours rows (inside an element with x-data="hoursEditor(state)"). Inputs are named {prefix}[day][…]. --}}
@php $prefix ??= 'hours'; @endphp
        <div class="divide-y divide-navy-50">
            <template x-for="(d, i) in days" :key="d.day">
                <div class="grid gap-3 py-3 md:grid-cols-[9rem_1fr] md:items-start">
                    <label class="flex items-center gap-2.5 pt-2 text-sm font-medium text-navy-900">
                        <input type="checkbox" class="checkbox" x-model="d.enabled" :name="`{{ $prefix }}[${d.day}][enabled]`" value="1">
                        <span x-text="d.label"></span>
                    </label>
                    <template x-if="d.enabled"><div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" x-model="d.closed" :name="`{{ $prefix }}[${d.day}][closed]`" value="1">{{ __('business.closed') }}</label>
                        <label class="inline-flex items-center gap-2 text-sm" x-show="!d.closed"><input type="checkbox" class="checkbox" x-model="d.all_day" :name="`{{ $prefix }}[${d.day}][all_day]`" value="1">{{ __('business.open_24h') }}</label>
                        <template x-if="!d.closed && !d.all_day">
                            <div class="flex w-full flex-wrap items-center gap-2 md:w-auto">
                                <template x-for="(p, j) in d.periods" :key="j">
                                    <div class="flex items-center gap-1.5" dir="ltr">
                                        <input type="time" class="input w-32 py-2" x-model="p.opens" :name="`{{ $prefix }}[${d.day}][periods][${j}][opens]`" :aria-label="`${d.label} {{ __('business_form.opens') }}`">
                                        <span class="text-muted">–</span>
                                        <input type="time" class="input w-32 py-2" x-model="p.closes" :name="`{{ $prefix }}[${d.day}][periods][${j}][closes]`" :aria-label="`${d.label} {{ __('business_form.closes') }}`">
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
