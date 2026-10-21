<x-layouts.dashboard area="admin" :title="__('admin.content.banners')" :subtitle="__('admin.content.banners_hint')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.content.banners')]]">
    <x-slot:actions><a href="{{ route('admin.banners.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('admin.content.banner_create') }}</a></x-slot:actions>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($banners as $banner)
            @php $live = $banner->is_active && (! $banner->starts_at || $banner->starts_at->isPast()) && (! $banner->ends_at || $banner->ends_at->isFuture()); @endphp
            <article class="card overflow-hidden">
                <div class="relative h-32 bg-navy-900">
                    @if($banner->imageUrl())<img src="{{ $banner->imageUrl() }}" alt="" class="absolute inset-0 size-full object-cover opacity-70">@else<div class="pattern-lattice absolute inset-0"></div>@endif
                    <div class="absolute inset-0 flex flex-col justify-end p-4 text-white"><p class="font-semibold">{{ $banner->t('title') }}</p>@if($banner->t('subtitle'))<p class="text-xs text-white/70">{{ $banner->t('subtitle') }}</p>@endif</div>
                </div>
                <div class="flex flex-wrap items-center gap-2 p-4 text-xs">
                    <span @class(['badge', 'badge-success' => $live, 'badge-muted' => ! $live])>{{ $live ? __('admin.content.live') : __('admin.content.not_live') }}</span>
                    <span class="text-muted-strong">{{ $banner->starts_at ? format_date($banner->starts_at) : '—' }} → {{ $banner->ends_at ? format_date($banner->ends_at) : '∞' }}</span>
                    <span class="ms-auto flex gap-1">
                        <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('common.edit') }}"><x-icon name="pencil" class="size-4" /></a>
                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" data-confirm="{{ __('admin.content.confirm_delete') }}" data-confirm-label="{{ __('common.delete') }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-danger" aria-label="{{ __('common.delete') }}"><x-icon name="trash-2" class="size-4" /></button></form>
                    </span>
                </div>
            </article>
        @empty
            <x-empty-state icon="megaphone" :title="__('admin.content.no_banners')" class="md:col-span-2" />
        @endforelse
    </div>
</x-layouts.dashboard>
