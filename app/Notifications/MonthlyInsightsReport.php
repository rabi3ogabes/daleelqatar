<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The monthly e-mail to a business owner: one line per published listing with
 * its real numbers, and the single most useful next step. Sent directly by the
 * daily cron (not queued), so it is delivered even without a queue worker.
 */
class MonthlyInsightsReport extends Notification
{
    /**
     * @param  list<array{name: string, uuid: string, views: int, views_change: ?int, impressions: int, contacts: int, quotes: int, tip: ?string}>  $lines
     */
    public function __construct(public readonly string $month, public readonly array $lines) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('insights.report.subject', ['month' => $this->month]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('insights.report.intro', ['month' => $this->month]));

        foreach ($this->lines as $line) {
            $change = $line['views_change'] === null ? __('insights.report.no_change') : (($line['views_change'] >= 0 ? '+' : '').$line['views_change'].'%');
            $mail->line(__('insights.report.line', [
                'business' => $line['name'],
                'views' => format_number($line['views']),
                'views_change' => $change,
                'impressions' => format_number($line['impressions']),
                'contacts' => format_number($line['contacts']),
                'quotes' => format_number($line['quotes']),
            ]));
        }

        $tip = collect($this->lines)->pluck('tip')->filter()->first();
        if ($tip) {
            $mail->line(__('insights.report.tip', ['tip' => $tip]));
        }

        $first = $this->lines[0] ?? null;
        $mail->action(__('insights.report.cta'), $first
            ? route('account.businesses.insights', [$first['uuid'], 'period' => 'last_month'])
            : route('account.businesses.index'));

        return $mail->line(__('insights.report.footer'))->salutation(__('mail.salutation'));
    }
}
