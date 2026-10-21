@php
    $rtl = app()->getLocale() === 'ar';
    $groups = [
        'arabic' => array_keys(\App\Support\AzIndex::ARABIC),
        'latin' => \App\Support\AzIndex::latin(),
    ];
    if (! $rtl) {
        $groups = array_reverse($groups, true);
    }
    $crumbs = $letter === null
        ? [['label' => __('directory.az.nav')]]
        : [['label' => __('directory.az.nav'), 'url' => route('directory.az')], ['label' => $label]];
@endphp
<x-layouts.app>
    <x-page-hero :title="$letter === null ? __('directory.az.title') : __('directory.az.title_letter', ['letter' => $label])"
        :subtitle="$letter === null ? __('directory.az.subtitle') : __('directory.az.count', ['count' => format_number($results->total())])"
        icon="list" :breadcrumbs="$crumbs" />

    <section class="container-app relative z-10 -mt-8 pb-16 sm:-mt-10">
        <nav class="card p-4 sm:p-6" aria-label="{{ __('directory.az.letters') }}">
            @foreach($groups as $group => $letters)
                <div @class(['mt-5 border-t border-navy-50 pt-5' => ! $loop->first])>
                    <p class="mb-3 text-xs font-semibold tracking-wide text-muted-strong">{{ __('directory.az.'.$group) }}</p>
                    <ul class="flex flex-wrap gap-1.5" @if($group === 'latin') dir="ltr" @endif>
                        @foreach($letters as $l)
                            @php $n = $counts[$l] ?? 0; @endphp
                            <li>
                                @if($n > 0)
                                    <a href="{{ route('directory.az.letter', ['letter' => $l]) }}" title="{{ __('directory.az.count', ['count' => format_number($n)]) }}"
                                        @class(['flex size-10 items-center justify-center rounded-xl text-sm font-bold transition', 'bg-navy-900 text-gold-200 shadow-soft' => $l === $letter, 'bg-navy-50 text-navy-900 hover:bg-gold-100' => $l !== $letter])
                                        @if($l === $letter) aria-current="page" @endif>{{ $l }}</a>
                                @else
                                    <span class="flex size-10 items-center justify-center rounded-xl text-sm font-medium text-navy-200" aria-hidden="true">{{ $l }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
            @if(($counts[\App\Support\AzIndex::OTHER] ?? 0) > 0)
                <div class="mt-5 border-t border-navy-50 pt-5">
                    <a href="{{ route('directory.az.letter', ['letter' => \App\Support\AzIndex::OTHER]) }}"
                        @class(['inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold transition', 'bg-navy-900 text-gold-200' => $letter === \App\Support\AzIndex::OTHER, 'bg-navy-50 text-navy-900 hover:bg-gold-100' => $letter !== \App\Support\AzIndex::OTHER])>
                        <span dir="ltr">0–9 #</span> {{ __('directory.az.other') }}
                    </a>
                </div>
            @endif
        </nav>

        @if($results !== null)
            <div class="mt-8">
                @if($results->isEmpty())
                    <x-empty-state icon="search" :title="__('directory.az.empty')" />
                @else
                    <ul class="grid gap-x-8 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($results as $b)
                            <li class="border-b border-navy-50">
                                <a href="{{ route('business.show', $b) }}" class="group flex items-start gap-3 rounded-lg px-2 py-3 hover:bg-white">
                                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-gold-50 text-gold-700"><x-icon :name="$b->category?->icon ?: 'store'" class="size-4" /></span>
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5 font-semibold text-navy-900 group-hover:text-gold-700">
                                            <span class="truncate">{{ $b->t('name') }}</span>
                                            @if($b->is_verified)<x-icon name="badge-check" class="size-4 shrink-0 text-gold-600" />@endif
                                        </span>
                                        <span class="block truncate text-xs text-muted-strong">{{ collect([$b->category?->t('name'), $b->municipality?->t('name')])->filter()->implode(' · ') ?: __('directory.na') }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    {{ $results->links() }}
                @endif
            </div>
        @endif
    </section>
</x-layouts.app>
