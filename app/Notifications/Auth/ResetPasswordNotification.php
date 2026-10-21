<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.reset_subject'))
            ->greeting(__('mail.greeting', ['name' => '']))
            ->line(__('mail.reset_line'))
            ->action(__('mail.reset_action'), $url)
            ->line(__('mail.reset_expire', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
            ->line(__('mail.reset_ignore'))
            ->salutation(__('mail.salutation'));
    }
}
