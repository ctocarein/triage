<?php

namespace App\Notifications\Identity;

use App\Domains\Organization\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrgManagerApprovedNotification extends Notification
{
    public function __construct(
        private readonly ?Organization $organization,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $orgName = $this->organization?->name ?? '—';
        $loginUrl = config('app.frontend_url', config('app.url')) . '/login';

        return (new MailMessage)
            ->subject('Votre accès a été approuvé — Triage')
            ->greeting('Bonjour,')
            ->line('Bonne nouvelle ! Votre demande d\'accès en tant que responsable d\'organisation a été approuvée.')
            ->line("**Organisation :** {$orgName}")
            ->line('Vous pouvez désormais vous connecter à la plateforme avec votre email et mot de passe.')
            ->action('Se connecter', $loginUrl)
            ->salutation('L\'équipe Triage');
    }
}
