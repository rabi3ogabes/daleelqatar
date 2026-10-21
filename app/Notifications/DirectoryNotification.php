<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for in-app + e-mail notifications. Rendered in the recipient's
 * preferred language (User implements HasLocalePreference). Links are stored
 * as route names so they open in whichever language the user is browsing.
 */
abstract class DirectoryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Short machine type, e.g. "listing.approved". */
    abstract protected function type(): string;

    abstract protected function title(): string;

    abstract protected function body(): string;

    /** @return array{0: string, 1: array}|null [routeName, params] */
    protected function link(): ?array
    {
        return null;
    }

    protected function icon(): string
    {
        return 'bell';
    }

    protected function mailEnabled(object $notifiable): bool
    {
        return true;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->mailEnabled($notifiable) && (! method_exists($notifiable, 'wantsNotification') || $notifiable->wantsNotification('email'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        [$route, $params] = $this->link() ?? [null, []];

        return [
            'type' => $this->type(),
            'title' => $this->title(),
            'body' => $this->body(),
            'route' => $route,
            'params' => $params,
            'icon' => $this->icon(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title().' — '.__('brand.name'))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name ?? '']))
            ->line($this->body());

        if ($link = $this->link()) {
            $mail->action(__('mail.view_details'), route($link[0], $link[1]));
        }

        return $mail->salutation(__('mail.salutation'));
    }
}
