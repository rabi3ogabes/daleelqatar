@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
{{-- Business-owner / member dashboard shell (wraps the shared dashboard layout with the account navigation). --}}
@php
    $crumbs = array_merge([['label' => __('account.dashboard.title'), 'url' => route('account.dashboard')]], $breadcrumbs);
@endphp
<x-layouts.dashboard :title="$title" :subtitle="$subtitle" area="account" :nav="\App\Support\AccountNavigation::for(auth()->user())" :breadcrumbs="$crumbs">
    @isset($actions)<x-slot:actions>{{ $actions }}</x-slot:actions>@endisset
    {{ $slot }}
</x-layouts.dashboard>
