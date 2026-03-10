<?php

namespace App\Notifications\Identity;

use App\Domains\Identity\Infrastructure\Persistence\Models\OrgManagerOnboardingRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrgManagerPendingAdminNotification extends Notification
{
    public function __construct(
        private readonly OrgManagerOnboardingRequest $onboardingRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->onboardingRequest->load(['identity.credentials', 'organization']);
        $credential = $request->identity->credentials->firstWhere('type', 'email');
        $email = $credential?->identifier ?? '—';
        $orgName = $request->organization?->name ?? '—';

        $adminUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/admin/onboarding/' . $request->id;

        return (new MailMessage)
            ->subject('Nouvelle demande d\'accès OrgManager en attente — Triage')
            ->greeting('Bonjour,')
            ->line('Une nouvelle demande d\'accès en tant que responsable d\'organisation est en attente de validation.')
            ->line("**Email :** {$email}")
            ->line("**Organisation :** {$orgName}")
            ->line("**Date de demande :** " . $request->created_at?->format('d/m/Y H:i'))
            ->action('Valider la demande', $adminUrl)
            ->line('Connectez-vous à la console d\'administration pour approuver ou rejeter cette demande.')
            ->salutation('L\'équipe Triage');
    }
}
