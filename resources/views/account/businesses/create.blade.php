<x-layouts.account :title="__('account.businesses.create_title')" :subtitle="__('account.businesses.create_subtitle')"
    :breadcrumbs="[['label' => __('account.businesses.title'), 'url' => route('account.businesses.index')], ['label' => __('account.businesses.create_title')]]">
    <form method="POST" action="{{ route('account.businesses.store') }}" enctype="multipart/form-data" class="mx-auto max-w-5xl" novalidate>
        @csrf
        @include('partials.business-form.fields', ['business' => null])
        <div class="sticky bottom-0 z-20 -mx-4 mt-6 border-t border-navy-100 bg-white/90 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border sm:shadow-soft">
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('account.businesses.index') }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" />{{ __('account.businesses.save_and_continue') }}</button>
            </div>
        </div>
    </form>
</x-layouts.account>
