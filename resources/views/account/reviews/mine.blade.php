<x-layouts.account :title="__('account.reviews.mine_title')" :subtitle="__('account.reviews.mine_subtitle')" :breadcrumbs="[['label' => __('account.reviews.mine_title')]]">
    @if($reviews->isEmpty())
        <x-empty-state icon="message-square" :title="__('account.reviews.mine_empty_title')" :text="__('account.reviews.mine_empty_text')">
            <a href="{{ route('search') }}" class="btn btn-primary">{{ __('nav.explore') }}</a>
        </x-empty-state>
    @else
        <div class="space-y-4">
            @foreach($reviews as $review)
                <article class="card p-5">
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            @if($review->business?->isPublished())
                                <a href="{{ route('business.show', $review->business) }}" class="font-semibold text-navy-900 hover:text-gold-800">{{ $review->business->t('name') }}</a>
                            @else
                                <p class="font-semibold text-navy-900">{{ $review->business?->t('name') }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-muted">{{ format_date($review->created_at) }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-rating :value="$review->rating" :count="1" :show-count="false" />
                            <x-status-badge :status="$review->status" />
                        </div>
                    </header>
                    @if($review->title)<h3 class="mt-3 font-semibold text-navy-900">{{ $review->title }}</h3>@endif
                    <p class="mt-2 text-sm leading-7 whitespace-pre-line text-charcoal">{{ $review->body }}</p>
                    @if($review->owner_reply)
                        <div class="mt-4 rounded-xl border-s-4 border-gold-400 bg-gold-50/60 p-4">
                            <p class="text-xs font-semibold text-gold-800">{{ __('reviews.owner_reply') }}</p>
                            <p class="mt-1 text-sm whitespace-pre-line">{{ $review->owner_reply }}</p>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('account.my-reviews.destroy', $review->uuid) }}" data-confirm="{{ __('account.reviews.delete_confirm') }}" class="mt-4">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger-outline btn-sm"><x-icon name="trash-2" class="size-3.5" />{{ __('account.reviews.delete') }}</button>
                    </form>
                </article>
            @endforeach
        </div>
        {{ $reviews->links() }}
    @endif
</x-layouts.account>
