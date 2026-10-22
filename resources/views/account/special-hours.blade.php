@php $crumbs = $layout === 'admin'
    ? [['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.nav.businesses'), 'url' => route('admin.businesses.index')], ['label' => $business->t('name'), 'url' => $back], ['label' => __('seasons.owner.title')]]
    : [['label' => __('account.businesses.title'), 'url' => route('account.businesses.index')], ['label' => $business->t('name'), 'url' => $back], ['label' => __('seasons.owner.title')]]; @endphp
@if($layout === 'admin')
    <x-layouts.dashboard area="admin" :title="__('seasons.owner.title')" :subtitle="$business->t('name')" :nav="\App\Support\AdminNavigation::for(auth()->user())" :breadcrumbs="$crumbs">
        @include('partials.special-hours-body')
    </x-layouts.dashboard>
@else
    <x-layouts.account :title="__('seasons.owner.title')" :subtitle="$business->t('name')" :breadcrumbs="array_slice($crumbs, 0)">
        @include('partials.special-hours-body')
    </x-layouts.account>
@endif
