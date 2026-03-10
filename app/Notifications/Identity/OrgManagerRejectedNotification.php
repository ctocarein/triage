<?php

namespace App\Notifications\Identity;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrgManagerRejectedNotification extends Notification
{
    public function __construct(
        private readonly string $reasonCode,
        private readonly ?string $reasonDetail,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reasonLabel = match ($this->reasonCode) {
            'incomplete_info' => 'Informations incomplètes ou incorrectes',
            'unknown_org'     => 'Organisation non reconnue',
            'duplicate'       => 'Demande en doublon',
            default           => 'Autre motif',
        };

        $message = (new MailMessage)
            ->subject('Votre demande d\'accès n\'a pas été approuvée — Triage')
            ->greeting('Bonjour,')
            ->line('Votre demande d\'accès en tant que responsable d\'organisation a été examinée et n\'a pas pu être approuvée.')
            ->line("**Motif :** {$reasonLabel}");

        if ($this->reasonDetail) {
            $message->line("**Détail :** {$this->reasonDetail}");
        }

        $message
            ->line('Si vous pensez qu\'il s\'agit d\'une erreur, veuillez contacter l\'équipe support.')
            ->salutation('L\'équipe Triage');

        return $message;
    }
}
