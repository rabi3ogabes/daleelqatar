<x-layouts.account :title="__('account.reviews.title')" :subtitle="__('account.reviews.subtitle')" :breadcrumbs="[['label' => __('account.reviews.title')]]">
    @if($businesses->count() > 1)
        <x-slot:actions>
            <form method="GET" action="{{ route('account.reviews.index') }}">
                <label for="rb" class="sr-only">{{ __('account.offers.business') }}</label>
                <select id="rb" name="business" class="select w-56" onchange="this.form.submit()">
                    <option value="">{{ __('account.reviews.filter_business') }}</option>
                    @foreach($businesses as $b)<option value="{{ $b->uuid }}" @selected($filter?->id === $b->id)>{{ $b->t('name') }}</option>@endforeach
                </select>
            </form>
        </x-slot:actions>
    @endif

    @unless($repliesEnabled)
        <div class="mb-5 flex items-center gap-3 rounded-2xl border border-info/20 bg-info-soft p-4 text-sm text-info"><x-icon name="info" class="size-5" />{{ __('account.reviews.replies_disabled') }}</div>
    @endunless

    @if($reviews->isEmpty())
        <x-empty-state icon="star" :title="__('account.reviews.empty_title')" :text="__('account.reviews.empty_text')" />
    @else
        <div class="space-y-4">
            @foreach($reviews as $review)
                <article class="card p-5" x-data="{ editing: {{ $review->owner_reply ? 'false' : 'false' }} }">
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-full bg-navy-900 text-sm font-semibold text-gold-300">{{ mb_substr($review->authorDisplayName(), 0, 1) }}</span>
                            <div>
                                <p class="text-sm font-semibold text-navy-900">{{ $review->authorDisplayName() }}</p>
                                <p class="text-xs text-muted">{{ __('account.reviews.on', ['business' => $review->business?->t('name')]) }} · {{ format_date($review->created_at) }}</p>
                            </div>
                        </div>
                        <x-rating :value="$review->rating" :count="1" :show-count="false" />
                    </header>
                    @if($review->title)<h3 class="mt-4 font-semibold text-navy-900">{{ $review->title }}</h3>@endif
                    <p class="mt-2 text-sm leading-7 whitespace-pre-line text-charcoal">{{ $review->body }}</p>

                    @if($review->owner_reply)
                        <div class="mt-4 rounded-xl border-s-4 border-gold-400 bg-gold-50/60 p-4" x-show="!editing">
                            <p class="text-xs font-semibold text-gold-800">{{ __('reviews.owner_reply') }} · {{ format_date($review->owner_replied_at) }}</p>
                            <p class="mt-1 text-sm whitespace-pre-line text-charcoal">{{ $review->owner_reply }}</p>
                        </div>
                    @endif

                    @if($repliesEnabled)
                        <div class="mt-4">
                            <button type="button" class="btn btn-ghost btn-sm" x-show="!editing" @click="editing = true; $nextTick(() => $refs.reply.focus())">
                                <x-icon name="message-square" class="size-4" />{{ $review->owner_reply ? __('account.reviews.edit_reply') : __('account.reviews.reply') }}
                            </button>
                            <form method="POST" action="{{ route('account.reviews.reply', $review->uuid) }}" x-show="editing" x-cloak class="space-y-3">
                                @csrf
                                <label for="reply-{{ $review->id }}" class="sr-only">{{ __('account.reviews.reply') }}</label>
                                <textarea id="reply-{{ $review->id }}" x-ref="reply" name="owner_reply" rows="3" maxlength="2000" class="textarea" placeholder="{{ __('account.reviews.reply_placeholder') }}">{{ $review->owner_reply }}</textarea>
                                <div class="flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('account.reviews.save_reply') }}</button>
                                    <button type="button" class="btn btn-ghost btn-sm" @click="editing = false">{{ __('common.cancel') }}</button>
                                </div>
                            </form>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
        {{ $reviews->links() }}
    @endif
</x-layouts.account>
