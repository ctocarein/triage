<?php

namespace App\Notifications\Identity;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrgManagerEmailVerificationNotification extends Notification
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Lien vers la page frontend chereh-dash qui appellera ensuite l'API
        $verifyUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/verify-email/' . $this->token;

        return (new MailMessage)
            ->subject('Vérification de votre adresse email — Triage')
            ->greeting('Bonjour,')
            ->line('Vous avez soumis une demande d\'accès en tant que responsable d\'organisation sur la plateforme Triage.')
            ->line('Pour continuer, veuillez vérifier votre adresse email en cliquant sur le bouton ci-dessous.')
            ->action('Vérifier mon email', $verifyUrl)
            ->line('Ce lien est à usage unique. Si vous n\'avez pas effectué cette demande, ignorez cet email.')
            ->salutation('L\'équipe Triage');
    }
}
