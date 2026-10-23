<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalResetPassword extends Notification
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->email ?? '';
        $url = url(route('portal.password.reset', [
            'token' => $this->token,
            'email' => $email,
        ], false));

        return (new MailMessage)
            ->subject('Reset your Royal Indian Stones staff password')
            ->line('A password reset was requested for this staff account.')
            ->action('Choose a new password', $url)
            ->line('This link expires soon. If you did not ask for it, you can ignore this email.');
    }
}
