<x-layouts.dashboard area="admin" :title="__('admin.categories.title')" :subtitle="__('admin.categories.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.categories.title')]]">
    <x-slot:actions><a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('admin.categories.create') }}</a></x-slot:actions>

    <div class="space-y-4">
        @forelse($roots as $root)
            <section class="card overflow-hidden" x-data="{ open: false }">
                <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                    <span class="flex size-10 items-center justify-center rounded-xl {{ $root->is_active ? 'bg-navy-900 text-gold-300' : 'bg-navy-100 text-muted' }}"><x-icon :name="$root->icon ?? 'layout-grid'" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold text-navy-900">
                            {{ $root->t('name') }}
                            <span class="text-xs font-normal text-muted">{{ $root->t('name', app()->getLocale() === 'ar' ? 'en' : 'ar') }}</span>
                            @unless($root->is_active)<span class="badge badge-muted">{{ __('admin.inactive') }}</span>@endunless
                            @if($root->is_featured)<span class="badge badge-gold">{{ __('admin.categories.featured_home') }}</span>@endif
                        </p>
                        <p class="text-xs text-muted-strong"><span dir="ltr">/{{ $root->slug }}</span> · {{ trans_choice('home.businesses_count', $root->businesses_count, ['count' => $root->businesses_count]) }} · {{ __('admin.categories.children_count', ['count' => $root->children_count]) }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <form method="POST" action="{{ route('admin.categories.move', [$root, 'up']) }}">@csrf<button class="btn-icon size-8 text-muted hover:bg-navy-50" aria-label="{{ __('admin.move_up') }}" @disabled($loop->first)><x-icon name="arrow-up" class="size-4" /></button></form>
                        <form method="POST" action="{{ route('admin.categories.move', [$root, 'down']) }}">@csrf<button class="btn-icon size-8 text-muted hover:bg-navy-50" aria-label="{{ __('admin.move_down') }}" @disabled($loop->last)><x-icon name="arrow-down" class="size-4" /></button></form>
                        <a href="{{ route('admin.categories.create', ['parent' => $root->id]) }}" class="btn btn-ghost btn-sm"><x-icon name="plus" class="size-4" /><span class="hidden sm:inline">{{ __('admin.categories.add_child') }}</span></a>
                        <a href="{{ route('admin.categories.edit', $root) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('common.edit') }}"><x-icon name="pencil" class="size-4" /></a>
                        @if($root->children->isNotEmpty())
                            <button type="button" class="btn btn-ghost btn-sm" @click="open = !open" :aria-expanded="open.toString()"><x-icon name="chevron-down" class="size-4 transition" ::class="open && 'rotate-180'" /><span class="sr-only">{{ __('admin.categories.subcategories') }}</span></button>
                        @endif
                    </div>
                </div>
                @if($root->children->isNotEmpty())
                    <ul x-show="open" x-collapse x-cloak class="divide-y divide-navy-50 border-t border-navy-50 bg-navy-50/30">
                        @foreach($root->children as $child)
                            <li class="flex flex-wrap items-center gap-3 px-4 py-2.5 ps-10 sm:ps-16">
                                <span class="min-w-0 flex-1 text-sm">
                                    <span class="font-medium text-navy-900">{{ $child->t('name') }}</span>
                                    @unless($child->is_active)<span class="badge badge-muted ms-1">{{ __('admin.inactive') }}</span>@endunless
                                    <span class="block text-xs text-muted-strong"><span dir="ltr">/{{ $child->slug }}</span> · {{ trans_choice('home.businesses_count', $child->businesses_count, ['count' => $child->businesses_count]) }}</span>
                                </span>
                                <form method="POST" action="{{ route('admin.categories.move', [$child, 'up']) }}">@csrf<button class="btn-icon size-8 text-muted hover:bg-white" aria-label="{{ __('admin.move_up') }}" @disabled($loop->first)><x-icon name="arrow-up" class="size-4" /></button></form>
                                <form method="POST" action="{{ route('admin.categories.move', [$child, 'down']) }}">@csrf<button class="btn-icon size-8 text-muted hover:bg-white" aria-label="{{ __('admin.move_down') }}" @disabled($loop->last)><x-icon name="arrow-down" class="size-4" /></button></form>
                                <a href="{{ route('admin.categories.edit', $child) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('common.edit') }}"><x-icon name="pencil" class="size-4" /></a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $child) }}" data-confirm="{{ __('admin.categories.confirm_delete') }}" data-confirm-label="{{ __('common.delete') }}">@csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-danger" aria-label="{{ __('common.delete') }}"><x-icon name="trash-2" class="size-4" /></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <x-empty-state icon="folder-tree" :title="__('admin.categories.empty')" />
        @endforelse
    </div>
</x-layouts.dashboard>
