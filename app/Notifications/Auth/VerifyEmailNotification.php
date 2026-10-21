<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.verify_subject'))
            ->greeting(__('mail.greeting', ['name' => '']))
            ->line(__('mail.verify_line'))
            ->action(__('mail.verify_action'), $url)
            ->line(__('mail.verify_ignore'))
            ->salutation(__('mail.salutation'));
    }
}
