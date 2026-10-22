<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ModerationStatus;
use App\Enums\QuoteRecipientStatus;
use App\Models\QuoteRecipient;
use App\Models\Review;
use App\Models\User;

/** Sidebar navigation for the business-owner / member dashboard (x-layouts.dashboard area="account"). */
final class AccountNavigation
{
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $ownsBusinesses = $user->businesses()->exists();
        $unansweredReviews = $ownsBusinesses
            ? Review::query()->where('status', ModerationStatus::Approved)->whereNull('owner_reply')
                ->whereIn('business_id', $user->businesses()->select('id'))->count()
            : 0;
        $newLeads = $ownsBusinesses
            ? QuoteRecipient::query()->where('status', QuoteRecipientStatus::Sent->value)
                ->whereIn('business_id', $user->businesses()->select('id'))
                ->whereHas('quoteRequest', fn ($q) => $q->open())->count()
            : 0;

        return [
            ['heading' => null, 'items' => [
                ['label' => __('account.nav.overview'), 'route' => 'account.dashboard', 'icon' => 'layout-dashboard', 'pattern' => 'account.dashboard'],
            ]],
            ['heading' => __('account.nav.my_business'), 'items' => [
                ['label' => __('account.nav.businesses'), 'route' => 'account.businesses.index', 'icon' => 'store', 'pattern' => ['account.businesses.index', 'account.businesses.show', 'account.businesses.edit', 'account.businesses.verification']],
                ['label' => __('account.nav.add_business'), 'route' => 'account.businesses.create', 'icon' => 'plus', 'pattern' => 'account.businesses.create'],
                ...($ownsBusinesses ? [['label' => __('quotes.leads.title'), 'route' => 'account.leads.index', 'icon' => 'message-square-quote', 'pattern' => 'account.leads.*', 'badge' => $newLeads ?: null]] : []),
                ['label' => __('account.nav.offers'), 'route' => 'account.offers.index', 'icon' => 'tag', 'pattern' => 'account.offers.*'],
                ['label' => __('account.nav.reviews'), 'route' => 'account.reviews.index', 'icon' => 'star', 'pattern' => 'account.reviews.*', 'badge' => $unansweredReviews ?: null],
                ['label' => __('account.nav.claims'), 'route' => 'account.claims.index', 'icon' => 'flag', 'pattern' => 'account.claims.*'],
            ]],
            ['heading' => __('account.nav.personal'), 'items' => [
                ['label' => __('quotes.mine.title'), 'route' => 'account.quotes.index', 'icon' => 'send', 'pattern' => 'account.quotes.*'],
                ['label' => __('account.nav.favorites'), 'route' => 'account.favorites', 'icon' => 'heart', 'pattern' => 'account.favorites'],
                ['label' => __('account.nav.my_reviews'), 'route' => 'account.my-reviews', 'icon' => 'message-square', 'pattern' => 'account.my-reviews'],
                ['label' => __('account.nav.notifications'), 'route' => 'account.notifications', 'icon' => 'bell', 'pattern' => 'account.notifications', 'badge' => $user->unreadNotifications()->count() ?: null],
                ['label' => __('account.nav.settings'), 'route' => 'account.settings', 'icon' => 'settings', 'pattern' => 'account.settings'],
            ]],
        ];
    }
}
