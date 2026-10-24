<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BusinessStatus;
use App\Enums\ModerationStatus;
use App\Enums\OfferStatus;
use App\Enums\RequestStatus;
use App\Models\CategoryReview;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sidebar of the administration dashboard, filtered by the signed-in user's
 * permissions, with live pending-work badges (cached briefly).
 */
final class AdminNavigation
{
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $b = self::badges();
        $can = fn (string $permission) => $user->can($permission);

        $sections = [
            ['heading' => null, 'items' => [
                ['label' => __('admin.nav.dashboard'), 'route' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'pattern' => 'admin.dashboard'],
            ]],
            ['heading' => __('admin.nav.directory'), 'items' => array_values(array_filter([
                $can('businesses.view') ? ['label' => __('admin.nav.businesses'), 'route' => 'admin.businesses.index', 'icon' => 'store', 'pattern' => 'admin.businesses.*', 'badge' => $b['businesses']] : null,
                $can('claims.review') ? ['label' => __('admin.nav.claims'), 'route' => 'admin.claims.index', 'icon' => 'key-round', 'pattern' => 'admin.claims.*', 'badge' => $b['claims']] : null,
                $can('businesses.verify') ? ['label' => __('admin.nav.verifications'), 'route' => 'admin.verifications.index', 'icon' => 'badge-check', 'pattern' => 'admin.verifications.*', 'badge' => $b['verifications']] : null,
                $can('imports.manage') ? ['label' => __('imports.nav.imports'), 'route' => 'admin.imports.index', 'icon' => 'file-spreadsheet', 'pattern' => 'admin.imports.*'] : null,
                $can('imports.manage') ? ['label' => __('imports.nav.discovery'), 'route' => 'admin.discovery.index', 'icon' => 'radar', 'pattern' => 'admin.discovery.*'] : null,
                $can('imports.manage') ? ['label' => __('enrichment.nav'), 'route' => 'admin.enrichment.index', 'icon' => 'sparkles', 'pattern' => 'admin.enrichment.*', 'badge' => $b['enrichment']] : null,
                $can('imports.manage') ? ['label' => __('places.nav'), 'route' => 'admin.places.index', 'icon' => 'map-pinned', 'pattern' => 'admin.places.*'] : null,
                $can('categories.manage') ? ['label' => __('admin.nav.categories'), 'route' => 'admin.categories.index', 'icon' => 'folder-tree', 'pattern' => 'admin.categories.*'] : null,
                $can('businesses.update') ? ['label' => __('category_review.nav'), 'route' => 'admin.category-review.index', 'icon' => 'layers', 'pattern' => 'admin.category-review.*', 'badge' => $b['categories']] : null,
                $can('locations.manage') ? ['label' => __('admin.nav.locations'), 'route' => 'admin.municipalities.index', 'icon' => 'map-pin', 'pattern' => ['admin.municipalities.*', 'admin.areas.*']] : null,
                $can('offers.moderate') ? ['label' => __('admin.nav.offers'), 'route' => 'admin.offers.index', 'icon' => 'tag', 'pattern' => 'admin.offers.*', 'badge' => $b['offers']] : null,
            ]))],
            ['heading' => __('admin.nav.community'), 'items' => array_values(array_filter([
                $can('reviews.moderate') ? ['label' => __('admin.nav.reviews'), 'route' => 'admin.reviews.index', 'icon' => 'star', 'pattern' => 'admin.reviews.*', 'badge' => $b['reviews']] : null,
                $can('quotes.manage') ? ['label' => __('quotes.admin.title'), 'route' => 'admin.quotes.index', 'icon' => 'message-square-quote', 'pattern' => 'admin.quotes.*'] : null,
                $can('reports.handle') ? ['label' => __('admin.nav.reports'), 'route' => 'admin.reports.index', 'icon' => 'flag', 'pattern' => 'admin.reports.*', 'badge' => $b['reports']] : null,
                $can('messages.manage') ? ['label' => __('admin.nav.messages'), 'route' => 'admin.messages.index', 'icon' => 'inbox', 'pattern' => 'admin.messages.*', 'badge' => $b['messages']] : null,
                $can('users.view') ? ['label' => __('admin.nav.users'), 'route' => 'admin.users.index', 'icon' => 'users', 'pattern' => 'admin.users.*'] : null,
                $can('roles.manage') ? ['label' => __('admin.nav.roles'), 'route' => 'admin.roles.index', 'icon' => 'shield', 'pattern' => 'admin.roles.*'] : null,
            ]))],
            ['heading' => __('admin.nav.content'), 'items' => array_values(array_filter([
                $can('content.manage') ? ['label' => __('admin.nav.homepage'), 'route' => 'admin.homepage.edit', 'icon' => 'house', 'pattern' => 'admin.homepage.*'] : null,
                $can('content.manage') ? ['label' => __('admin.nav.pages'), 'route' => 'admin.pages.index', 'icon' => 'file-text', 'pattern' => 'admin.pages.*'] : null,
                $can('guides.manage') ? ['label' => __('guides.admin.nav'), 'route' => 'admin.guides.index', 'icon' => 'book-marked', 'pattern' => 'admin.guides.*'] : null,
                $can('content.manage') ? ['label' => __('seasons.admin.nav'), 'route' => 'admin.seasons.index', 'icon' => 'moon', 'pattern' => 'admin.seasons.*'] : null,
                $can('content.manage') ? ['label' => __('admin.nav.faqs'), 'route' => 'admin.faqs.index', 'icon' => 'circle-help', 'pattern' => 'admin.faqs.*'] : null,
                $can('content.manage') ? ['label' => __('admin.nav.banners'), 'route' => 'admin.banners.index', 'icon' => 'megaphone', 'pattern' => 'admin.banners.*'] : null,
            ]))],
            ['heading' => __('admin.nav.system'), 'items' => array_values(array_filter([
                $can('analytics.view') ? ['label' => __('admin.nav.analytics'), 'route' => 'admin.analytics.index', 'icon' => 'chart-line', 'pattern' => 'admin.analytics.*'] : null,
                $can('settings.manage') ? ['label' => __('adsense.nav'), 'route' => 'admin.adsense.index', 'icon' => 'badge-dollar-sign', 'pattern' => 'admin.adsense.*'] : null,
                $can('audit.view') ? ['label' => __('admin.nav.audit'), 'route' => 'admin.audit.index', 'icon' => 'file-clock', 'pattern' => 'admin.audit.*'] : null,
                $can('settings.manage') ? ['label' => __('admin.nav.settings'), 'route' => 'admin.settings.edit', 'icon' => 'settings', 'pattern' => 'admin.settings.*'] : null,
            ]))],
        ];

        return array_values(array_filter($sections, fn ($s) => $s['items'] !== []));
    }

    /** Counts of items waiting for staff action. */
    public static function badges(): array
    {
        return Cache::remember('admin.badges', now()->addSeconds(30), fn () => [
            'businesses' => DB::table('businesses')->whereNull('deleted_at')->where('status', BusinessStatus::Pending->value)->count(),
            'claims' => DB::table('business_claim_requests')->where('status', RequestStatus::Pending->value)->count(),
            'verifications' => DB::table('business_verification_requests')->where('status', RequestStatus::Pending->value)->count(),
            'reviews' => DB::table('reviews')->whereNull('deleted_at')->where('status', ModerationStatus::Pending->value)->count(),
            'offers' => DB::table('offers')->whereNull('deleted_at')->where('status', OfferStatus::Pending->value)->count(),
            'reports' => DB::table('listing_reports')->where('status', 'open')->count() + DB::table('review_reports')->where('status', 'open')->count(),
            'messages' => DB::table('contact_messages')->where('status', 'new')->count(),
            'enrichment' => DB::table('business_suggestions')->where('status', 'pending')->count(),
            'categories' => DB::table('category_reviews')->where('status', 'pending')->whereNotNull('suggested_category_id')->where('confidence', '>=', CategoryReview::HIGH)->count(),
        ]);
    }

    public static function flush(): void
    {
        Cache::forget('admin.badges');
    }
}
