<?php

namespace App\Notifications\Identity;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PractitionerInviteNotification extends Notification
{
    public function __construct(
        private readonly string $orgName,
        private readonly string $tempPassword,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = config('app.frontend_url', config('app.url')) . '/login';

        return (new MailMessage)
            ->subject("Votre accès praticien — {$this->orgName}")
            ->greeting('Bonjour,')
            ->line("Vous avez été ajouté(e) comme **praticien** au sein de l'organisation **{$this->orgName}**.")
            ->line('Voici vos identifiants de connexion temporaires :')
            ->line("**Email :** {$notifiable->routeNotificationForMail()}")
            ->line("**Mot de passe temporaire :** `{$this->tempPassword}`")
            ->line('⚠️ Vous devrez changer ce mot de passe dès votre première connexion.')
            ->action('Se connecter', $loginUrl)
            ->salutation("L'équipe {$this->orgName}");
    }
}
