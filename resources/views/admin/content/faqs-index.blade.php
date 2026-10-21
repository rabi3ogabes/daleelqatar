<x-layouts.dashboard area="admin" :title="__('admin.content.faqs')" :subtitle="__('admin.content.faqs_hint')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.content.faqs')]]">
    <x-slot:actions><a href="{{ route('admin.faqs.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('admin.content.faq_create') }}</a></x-slot:actions>
    <div class="space-y-6">
        @forelse($faqs as $audience => $items)
            <x-dash.panel :title="__('pages.faq_'.$audience)" :padding="false">
                <ul class="divide-y divide-navy-50">
                    @foreach($items as $faq)
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-navy-50 text-xs font-semibold text-navy-700 tabular-nums">{{ $faq->sort_order }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-navy-900">{{ $faq->t('question') }}</span>
                                <span class="block truncate text-xs text-muted-strong">{{ $faq->t('answer') }}</span>
                            </span>
                            @unless($faq->is_active)<span class="badge badge-muted">{{ __('admin.inactive') }}</span>@endunless
                            <a href="{{ route('admin.faqs.edit', $faq) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('common.edit') }}"><x-icon name="pencil" class="size-4" /></a>
                            <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" data-confirm="{{ __('admin.content.confirm_delete') }}" data-confirm-label="{{ __('common.delete') }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-danger" aria-label="{{ __('common.delete') }}"><x-icon name="trash-2" class="size-4" /></button></form>
                        </li>
                    @endforeach
                </ul>
            </x-dash.panel>
        @empty
            <x-empty-state icon="circle-help" :title="__('dashboard.no_data')" />
        @endforelse
    </div>
</x-layouts.dashboard>
