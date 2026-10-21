<x-layouts.dashboard area="admin" :title="__('admin.reviews.title')" :subtitle="__('admin.reviews.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.reviews.title')]]">
    @include('admin.partials.status-tabs', ['route' => 'admin.reviews.index', 'cases' => \App\Enums\ModerationStatus::cases(), 'current' => $status, 'counts' => $counts])
    <x-dash.filters :action="route('admin.reviews.index')">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="min-w-56 flex-1"><label class="label" for="r-q">{{ __('common.search') }}</label><input id="r-q" type="search" name="q" value="{{ request('q') }}" class="input"></div>
        <div class="w-36"><label class="label" for="r-rating">{{ __('search.min_rating') }}</label>
            <select id="r-rating" name="rating" class="select"><option value="">{{ __('common.all') }}</option>@for($i = 5; $i >= 1; $i--)<option value="{{ $i }}" @selected((int) request('rating') === $i)>{{ $i }} ★</option>@endfor</select></div>
        <label class="flex items-center gap-2 pb-2.5 text-sm"><input type="checkbox" class="checkbox" name="reported" value="1" @checked(request('reported') === '1')>{{ __('admin.reviews.reported_only') }}</label>
    </x-dash.filters>

    <div class="space-y-4">
        @forelse($reviews as $review)
            <article class="card p-5">
                <div class="flex flex-wrap items-start gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-semibold text-gold-700" aria-label="{{ trans_choice('reviews.stars', $review->rating, ['count' => $review->rating]) }}">{{ str_repeat('★', $review->rating) }}<span class="text-navy-100">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                            <x-status-badge :status="$review->status" />
                            @if($review->reports_count > 0)<span class="badge badge-danger"><x-icon name="flag" class="size-3" />{{ __('admin.reviews.reports', ['count' => $review->reports_count]) }}</span>@endif
                        </div>
                        @if($review->title)<h3 class="mt-2 font-semibold text-navy-900">{{ $review->title }}</h3>@endif
                        <p class="mt-1 text-sm leading-7 whitespace-pre-line text-charcoal" dir="auto">{{ $review->body }}</p>
                        <p class="mt-2 text-xs text-muted-strong">
                            {{ $review->user?->name }} <span dir="ltr">({{ $review->user?->email }})</span> · {{ __('admin.reviews.on') }}
                            <a href="{{ $review->business ? route('admin.businesses.show', $review->business->id) : '#' }}" class="link">{{ $review->business?->t('name') }}</a> · {{ $review->created_at?->diffForHumans() }}
                        </p>
                        @foreach($review->reports as $report)
                            <p class="mt-2 rounded-lg bg-danger-soft/60 px-3 py-1.5 text-xs text-danger"><x-icon name="flag" class="inline size-3" /> {{ $report->reason->label() }}@if($report->details) — {{ $report->details }}@endif</p>
                        @endforeach
                        @if($review->moderation_note)<p class="mt-2 text-xs text-muted">{{ __('admin.reviews.note') }}: {{ $review->moderation_note }} — {{ $review->moderator?->name }}</p>@endif
                    </div>
                    <form method="POST" action="{{ route('admin.reviews.moderate', $review->id) }}" class="flex w-full flex-col gap-2 sm:w-64" x-data="{ status: '' }">
                        @csrf
                        <input type="text" name="note" maxlength="1000" class="input py-2 text-xs" placeholder="{{ __('admin.reviews.note_placeholder') }}" aria-label="{{ __('admin.reviews.note') }}">
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(\App\Enums\ModerationStatus::cases() as $s)
                                @continue($s === \App\Enums\ModerationStatus::Pending || $s === $review->status)
                                <button name="status" value="{{ $s->value }}" class="btn btn-sm {{ $s === \App\Enums\ModerationStatus::Approved ? 'btn-primary' : 'btn-outline' }}">{{ __('admin.reviews.actions.'.$s->value) }}</button>
                            @endforeach
                        </div>
                    </form>
                </div>
                <div class="mt-3 flex justify-end border-t border-navy-50 pt-3">
                    <form method="POST" action="{{ route('admin.reviews.destroy', $review->id) }}" data-confirm="{{ __('admin.reviews.confirm_delete') }}" data-confirm-label="{{ __('common.delete') }}">@csrf @method('DELETE')
                        <button class="btn btn-ghost btn-sm text-danger"><x-icon name="trash-2" class="size-4" />{{ __('common.delete') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <x-empty-state icon="star" :title="__('admin.reviews.empty')" />
        @endforelse
    </div>
    {{ $reviews->links() }}
</x-layouts.dashboard>
