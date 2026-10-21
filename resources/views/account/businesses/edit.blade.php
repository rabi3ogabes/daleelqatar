@php
    $canSubmit = auth()->user()->can('submit', $business);
@endphp
<x-layouts.account :title="__('account.businesses.edit_title', ['name' => $business->t('name')])"
    :breadcrumbs="[['label' => __('account.businesses.title'), 'url' => route('account.businesses.index')], ['label' => $business->t('name'), 'url' => route('account.businesses.show', $business->uuid)], ['label' => __('common.edit')]]">
    <x-slot:actions>
        <x-status-badge :status="$business->status" />
        <a href="{{ route('account.businesses.show', $business->uuid) }}" class="btn btn-outline"><x-icon name="eye" class="size-4" />{{ __('account.businesses.preview') }}</a>
    </x-slot:actions>

    <div class="mx-auto max-w-5xl">
        @if($business->status === \App\Enums\BusinessStatus::Rejected && $business->rejection_reason)
            <div class="mb-6 rounded-2xl border border-danger/20 bg-danger-soft p-4 text-sm text-danger" role="alert">
                <p class="font-semibold">{{ __('account.businesses.rejection_reason') }}</p>
                <p class="mt-1">{{ $business->rejection_reason }}</p>
            </div>
        @elseif($business->status === \App\Enums\BusinessStatus::Pending)
            <div class="mb-6 flex items-center gap-3 rounded-2xl border border-warning/20 bg-warning-soft p-4 text-sm text-warning"><x-icon name="clock" class="size-5" />{{ __('account.businesses.pending_note') }}</div>
        @endif

        <form method="POST" action="{{ route('account.businesses.update', $business->uuid) }}" enctype="multipart/form-data" novalidate id="business-form">
            @csrf
            @method('PUT')
            @include('partials.business-form.fields')
        </form>

        {{-- Gallery manager (separate forms, outside the main form) --}}
        <section id="gallery" class="card mt-6 scroll-mt-24 p-5 sm:p-6" aria-labelledby="gallery-title">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="gallery-title" class="mb-1 text-base font-semibold text-navy-900">{{ __('business_form.gallery') }}</h2>
                    <p class="text-sm text-muted-strong">{{ __('business_form.gallery_hint', ['max' => $maxGallery]) }} <span class="tabular-nums">({{ $business->images->count() }}/{{ $maxGallery }})</span></p>
                </div>
                @if($business->images->count() < $maxGallery)
                    <form method="POST" action="{{ route('account.businesses.gallery.store', $business->uuid) }}" enctype="multipart/form-data" x-data="{ n: 0 }" class="flex items-center gap-2">
                        @csrf
                        <label class="btn btn-outline cursor-pointer">
                            <x-icon name="upload" class="size-4" /><span x-text="n ? n + ' ✓' : @js(__('business_form.gallery_add'))">{{ __('business_form.gallery_add') }}</span>
                            <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" @change="n = $event.target.files.length">
                        </label>
                        <button type="submit" class="btn btn-primary" x-show="n > 0" x-cloak>{{ __('common.upload') }}</button>
                    </form>
                @endif
            </div>
            @error('images')<p class="error mb-3">{{ $message }}</p>@enderror
            @error('images.*')<p class="error mb-3">{{ $message }}</p>@enderror
            @if($business->images->isEmpty())
                <p class="rounded-xl border border-dashed border-navy-200 p-8 text-center text-sm text-muted-strong">{{ __('business_form.gallery_empty') }}</p>
            @else
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($business->images as $image)
                        <li class="group relative overflow-hidden rounded-xl border border-navy-100 bg-navy-50">
                            <img src="{{ $image->url() }}" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover">
                            <div class="absolute inset-x-2 bottom-2 flex items-center justify-between gap-1">
                                <div class="flex gap-1">
                                    @foreach(['up' => 'arrow-up', 'down' => 'arrow-down'] as $dir => $icon)
                                        @if(($dir === 'up' && ! $loop->parent->first) || ($dir === 'down' && ! $loop->parent->last))
                                            <form method="POST" action="{{ route('account.businesses.gallery.reorder', $business->uuid) }}">
                                                @csrf<input type="hidden" name="image" value="{{ $image->id }}"><input type="hidden" name="direction" value="{{ $dir }}">
                                                <button class="flex size-8 items-center justify-center rounded-lg bg-white/90 text-navy-900 shadow hover:bg-white" title="{{ __('account.businesses.move_'.$dir) }}"><x-icon :name="$icon" class="size-4" /><span class="sr-only">{{ __('account.businesses.move_'.$dir) }}</span></button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                                <form method="POST" action="{{ route('account.businesses.gallery.destroy', [$business->uuid, $image->id]) }}" data-confirm="{{ __('common.confirm_title') }}">
                                    @csrf @method('DELETE')
                                    <button class="flex size-8 items-center justify-center rounded-lg bg-white/90 text-danger shadow hover:bg-white" title="{{ __('common.delete') }}"><x-icon name="trash-2" class="size-4" /><span class="sr-only">{{ __('common.delete') }}</span></button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Sticky save bar (targets the main form) --}}
        <div class="sticky bottom-0 z-20 -mx-4 mt-6 border-t border-navy-100 bg-white/90 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border sm:shadow-soft">
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('account.businesses.show', $business->uuid) }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="submit" form="business-form" class="btn btn-outline btn-lg"><x-icon name="check" class="size-5" />{{ __('common.save_changes') }}</button>
                    @if($canSubmit)
                        <button type="submit" form="business-form" name="after" value="submit" class="btn btn-gold btn-lg"><x-icon name="send" class="size-5" />{{ __('account.businesses.submit') }}</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.account>
